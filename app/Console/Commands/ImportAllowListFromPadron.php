<?php

namespace App\Console\Commands;

use App\Actions\AllowList\StudentCodeParser;
use App\Actions\Audit\Audit;
use App\Models\AllowListEntry;
use App\Models\ProfessionalSchool;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportAllowListFromPadron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'allow-list:import-padron
        {path=temp/PADRON20.csv : Path to PADRON CSV file}
        {--mode=replace : replace|merge}
        {--admin-email= : Email of the admin performing the import}
        {--dry-run : Parse and report only; do not write to DB}
        {--database= : Database connection name (e.g. production)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import allow-list entries from a PADRON CSV file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $database = $this->option('database');
        $previousConnection = null;

        if (is_string($database) && $database !== '') {
            $previousConnection = DB::getDefaultConnection();

            if (config("database.connections.{$database}") === null) {
                $this->error("Database connection '{$database}' is not configured.");

                return self::FAILURE;
            }

            DB::setDefaultConnection($database);

            try {
                DB::connection($database)->getPdo();
            } catch (\Throwable $e) {
                $this->error("Cannot connect to '{$database}': {$e->getMessage()}");
                DB::setDefaultConnection($previousConnection);

                return self::FAILURE;
            }

            $this->warn("Using database connection: {$database}");
        }

        try {
            return $this->executeImport();
        } finally {
            if ($previousConnection !== null) {
                DB::setDefaultConnection($previousConnection);
            }
        }
    }

    private function executeImport(): int
    {
        $path = (string) $this->argument('path');
        $mode = Str::lower(trim((string) $this->option('mode')));
        $dryRun = (bool) $this->option('dry-run');

        if (! in_array($mode, ['replace', 'merge'], true)) {
            $this->error("Invalid --mode: {$mode} (expected replace|merge)");

            return self::FAILURE;
        }

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $adminEmail = $this->option('admin-email');
        $admin = null;
        if (is_string($adminEmail) && trim($adminEmail) !== '') {
            $admin = User::query()->where('email', Str::lower(trim($adminEmail)))->first();
        }

        $batchId = (string) Str::uuid();

        $schools = ProfessionalSchool::query()
            ->with(['faculty:id,active'])
            ->whereNotNull('code')
            ->get(['id', 'code', 'active', 'faculty_id', 'base_year_min', 'base_year_max']);

        $schoolsByCode = [];
        foreach ($schools as $school) {
            if (! is_string($school->code) || $school->code === '') {
                continue;
            }

            $schoolsByCode[Str::lower($school->code)] = $school;
        }

        $programToSchoolCode = [
            Str::lower('E.P. de Ingeniería de Sistemas') => 'ep_sistemas',
            Str::lower('E.P. de Ingeniería de Software') => 'ep_software',
        ];

        $duplicates = 0;
        $invalidRows = [];

        /** @var array<string, array<string, mixed>> $byEmail */
        $byEmail = [];

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Unable to open file: {$path}");

            return self::FAILURE;
        }

        $header = null;
        $rowNumber = 0;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($rowNumber === 1) {
                    $header = array_map(fn ($h) => Str::lower(trim((string) $h)), $row);

                    continue;
                }

                if ($header === null) {
                    continue;
                }

                $data = [];
                foreach ($header as $index => $key) {
                    $data[$key] = $row[$index] ?? null;
                }

                $emailRaw = is_string($data['correo'] ?? null) ? $data['correo'] : null;
                $studentCodeRaw = is_string($data['codigo'] ?? null) ? $data['codigo'] : null;
                $baseRaw = is_string($data['base'] ?? null) ? $data['base'] : null;
                $programRaw = is_string($data['programa'] ?? null) ? $data['programa'] : null;

                $email = $emailRaw !== null ? Str::lower(trim($emailRaw)) : '';
                $studentCode = StudentCodeParser::normalize($studentCodeRaw);
                $derivedBaseYear = StudentCodeParser::baseYear($studentCodeRaw);
                $programKey = $programRaw !== null ? Str::lower(trim($programRaw)) : '';

                if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $invalidRows[] = ['row' => $rowNumber, 'value' => $emailRaw];

                    continue;
                }

                if (! Str::endsWith($email, '@unmsm.edu.pe')) {
                    $invalidRows[] = ['row' => $rowNumber, 'value' => $emailRaw];

                    continue;
                }

                if ($studentCode === null || $derivedBaseYear === null) {
                    $invalidRows[] = ['row' => $rowNumber, 'value' => "{$emailRaw} ({$studentCodeRaw})"];

                    continue;
                }

                $baseColumnYear = null;
                if ($baseRaw !== null && trim($baseRaw) !== '') {
                    $baseColumnYear = $this->parseBaseYear($baseRaw);
                    if ($baseColumnYear !== null && $baseColumnYear !== $derivedBaseYear) {
                        $invalidRows[] = ['row' => $rowNumber, 'value' => "{$emailRaw} (base/código no coincide)"];

                        continue;
                    }
                }

                $schoolCode = $programToSchoolCode[$programKey] ?? null;
                if (! is_string($schoolCode) || $schoolCode === '') {
                    $invalidRows[] = ['row' => $rowNumber, 'value' => "{$emailRaw} ({$programRaw})"];

                    continue;
                }

                $school = $schoolsByCode[Str::lower($schoolCode)] ?? null;
                if (! $school instanceof ProfessionalSchool || ! $school->active || ! $school->faculty?->active) {
                    $invalidRows[] = ['row' => $rowNumber, 'value' => "{$emailRaw} ({$schoolCode})"];

                    continue;
                }

                if ($derivedBaseYear < (int) $school->base_year_min || $derivedBaseYear > (int) $school->base_year_max) {
                    $invalidRows[] = ['row' => $rowNumber, 'value' => "{$emailRaw} ({$derivedBaseYear})"];

                    continue;
                }

                if (array_key_exists($email, $byEmail)) {
                    $duplicates++;
                }

                $byEmail[$email] = [
                    'email' => $email,
                    'student_code' => $studentCode,
                    'professional_school_id' => $school->id,
                    'base_year' => $derivedBaseYear,
                ];
            }
        } finally {
            fclose($handle);
        }

        $unique = array_values($byEmail);

        $this->line("File: {$path}");
        $this->line("Mode: {$mode}");
        $this->line('---');
        $this->info('Imported (unique): '.count($unique));
        $this->line('Duplicates: '.max(0, $duplicates));
        $this->line('Invalid: '.count($invalidRows));

        if (count($invalidRows) > 0) {
            $this->line('---');
            $this->line('Invalid rows (first 20):');

            foreach (array_slice($invalidRows, 0, 20) as $row) {
                $value = $row['value'] ?? null;
                $this->line("  Row {$row['row']}: {$value}");
            }
        }

        if ($dryRun) {
            $this->warn('Dry-run: no changes were written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($mode, $unique, $admin, $batchId, $path): void {
            if ($mode === 'replace') {
                AllowListEntry::query()->delete();
            }

            $now = now();

            $rows = array_map(fn (array $row) => [
                'email' => $row['email'],
                'student_code' => $row['student_code'],
                'professional_school_id' => $row['professional_school_id'],
                'base_year' => $row['base_year'],
                'import_batch_id' => $batchId,
                'imported_by' => $admin?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $unique);

            AllowListEntry::query()->upsert(
                $rows,
                ['email'],
                ['student_code', 'professional_school_id', 'base_year', 'import_batch_id', 'imported_by', 'updated_at'],
            );

            Audit::record('allow_list.imported', actor: $admin, subject: null, metadata: [
                'mode' => $mode,
                'batch_id' => $batchId,
                'emails' => count($rows),
                'source' => 'padron',
                'path' => $path,
            ]);
        });

        $this->info("Batch ID: {$batchId}");

        return self::SUCCESS;
    }

    private function parseBaseYear(string $value): ?int
    {
        $raw = Str::upper(trim($value));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^B(\d{2})$/', $raw, $matches) === 1) {
            return 2000 + (int) $matches[1];
        }

        if (preg_match('/^\d{2}$/', $raw) === 1) {
            return 2000 + (int) $raw;
        }

        if (preg_match('/^\d{4}$/', $raw) === 1) {
            $year = (int) $raw;

            return $year >= 2000 && $year <= 2100 ? $year : null;
        }

        return null;
    }
}
