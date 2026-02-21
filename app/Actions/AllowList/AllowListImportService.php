<?php

namespace App\Actions\AllowList;

use App\Actions\Audit\Audit;
use App\Models\AllowListEntry;
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
        $import = new AllowListEmailsImport();
        Excel::import($import, $file);

        $batchId = (string) Str::uuid();

        $emails = array_values(array_filter(array_map(function (string $email): ?string {
            $normalized = Str::lower(trim($email));

            return filter_var($normalized, FILTER_VALIDATE_EMAIL) ? $normalized : null;
        }, $import->emails)));

        $unique = array_values(array_unique($emails));
        $duplicates = count($emails) - count($unique);

        $invalidRows = [];
        foreach ($import->rows as $row) {
            $raw = $row['value'];
            $normalized = Str::lower(trim($raw));
            if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                $invalidRows[] = ['row' => $row['row'], 'value' => $raw];
            }
        }

        DB::transaction(function () use ($mode, $unique, $admin, $batchId): void {
            if ($mode === 'replace') {
                AllowListEntry::query()->delete();
            }

            $now = now();
            $rows = array_map(fn (string $email) => [
                'email' => $email,
                'import_batch_id' => $batchId,
                'imported_by' => $admin->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $unique);

            AllowListEntry::query()->upsert($rows, ['email'], ['import_batch_id', 'imported_by', 'updated_at']);

            Audit::record('allow_list.imported', actor: $admin, subject: null, metadata: [
                'mode' => $mode,
                'batch_id' => $batchId,
                'emails' => count($unique),
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
}
