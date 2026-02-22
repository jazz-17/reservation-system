<?php

namespace App\Actions\AllowList;

use App\Actions\Audit\Audit;
use App\Models\AllowListEntry;
use App\Models\ProfessionalSchool;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class AllowListImportService
{
    /**
     * @return array{imported: int, duplicates: int, invalid: int, invalid_rows: array<int, array{row: int, value: string|null}>, batch_id: string}
     */
    public function import(User $admin, UploadedFile $file, string $mode): array
    {
        $import = new AllowListEmailsImport;
        Excel::import($import, $file);

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

        $duplicates = 0;
        $invalidRows = [];

        /** @var array<string, array<string, mixed>> $byEmail */
        $byEmail = [];

        foreach ($import->rows as $row) {
            $emailRaw = is_string($row['email'] ?? null) ? $row['email'] : null;
            $schoolCodeRaw = is_string($row['school_code'] ?? null) ? $row['school_code'] : null;
            $baseRaw = is_string($row['base'] ?? null) ? $row['base'] : null;

            $email = $emailRaw !== null ? Str::lower(trim($emailRaw)) : '';
            $schoolCode = $schoolCodeRaw !== null ? Str::lower(trim($schoolCodeRaw)) : '';
            $baseYear = $this->parseBaseYear($baseRaw);

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalidRows[] = ['row' => $row['row'], 'value' => $emailRaw];

                continue;
            }

            if (! Str::endsWith($email, '@unmsm.edu.pe')) {
                $invalidRows[] = ['row' => $row['row'], 'value' => $emailRaw];

                continue;
            }

            if ($schoolCode === '') {
                $invalidRows[] = ['row' => $row['row'], 'value' => $emailRaw];

                continue;
            }

            $school = $schoolsByCode[$schoolCode] ?? null;
            if (! $school instanceof ProfessionalSchool || ! $school->active || ! $school->faculty?->active) {
                $invalidRows[] = ['row' => $row['row'], 'value' => "{$emailRaw} ({$schoolCodeRaw})"];

                continue;
            }

            if ($baseYear === null) {
                $invalidRows[] = ['row' => $row['row'], 'value' => "{$emailRaw} ({$baseRaw})"];

                continue;
            }

            if ($baseYear < (int) $school->base_year_min || $baseYear > (int) $school->base_year_max) {
                $invalidRows[] = ['row' => $row['row'], 'value' => "{$emailRaw} ({$baseRaw})"];

                continue;
            }

            if (array_key_exists($email, $byEmail)) {
                $duplicates++;
            }

            $byEmail[$email] = [
                'email' => $email,
                'professional_school_id' => $school->id,
                'base_year' => $baseYear,
            ];
        }

        $unique = array_values($byEmail);

        DB::transaction(function () use ($mode, $unique, $admin, $batchId): void {
            if ($mode === 'replace') {
                AllowListEntry::query()->delete();
            }

            $now = now();
            $rows = array_map(fn (array $row) => [
                'email' => $row['email'],
                'professional_school_id' => $row['professional_school_id'],
                'base_year' => $row['base_year'],
                'import_batch_id' => $batchId,
                'imported_by' => $admin->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $unique);

            AllowListEntry::query()->upsert(
                $rows,
                ['email'],
                ['professional_school_id', 'base_year', 'import_batch_id', 'imported_by', 'updated_at'],
            );

            Audit::record('allow_list.imported', actor: $admin, subject: null, metadata: [
                'mode' => $mode,
                'batch_id' => $batchId,
                'emails' => count($rows),
            ]);
        });

        return [
            'imported' => count($unique),
            'duplicates' => max(0, $duplicates),
            'invalid' => count($invalidRows),
            'invalid_rows' => $invalidRows,
            'batch_id' => $batchId,
        ];
    }

    private function parseBaseYear(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = Str::upper(trim($value));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^B(\d{2})$/', $raw, $matches) === 1) {
            return 2000 + (int) $matches[1];
        }

        if (preg_match('/^\d{4}$/', $raw) === 1) {
            $year = (int) $raw;

            return $year >= 2000 && $year <= 2100 ? $year : null;
        }

        return null;
    }
}
