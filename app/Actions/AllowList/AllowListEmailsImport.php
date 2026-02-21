<?php

namespace App\Actions\AllowList;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class AllowListEmailsImport implements ToCollection
{
    /**
     * @var array<int, array{row: int, value: string}>
     */
    public array $rows = [];

    /**
     * @var list<string>
     */
    public array $emails = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            $value = null;
            if (is_array($row)) {
                $value = $row['email'] ?? $row['correo'] ?? $row[0] ?? null;
            } elseif ($row instanceof Collection) {
                $value = $row->get('email') ?? $row->get('correo') ?? $row->get(0);
            }

            $value = is_string($value) ? trim($value) : null;

            if ($value === null || $value === '') {
                continue;
            }

            $this->rows[] = [
                'row' => $rowNumber,
                'value' => $value,
            ];
            $this->emails[] = $value;
        }
    }
}
