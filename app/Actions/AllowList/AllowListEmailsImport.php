<?php

namespace App\Actions\AllowList;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class AllowListEmailsImport implements ToCollection
{
    /**
     * @var array<int, array{row: int, email: string|null, school_code: string|null, base: string|null}>
     */
    public array $rows = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            $email = null;
            $schoolCode = null;
            $base = null;

            if (is_array($row)) {
                $email = $row['email'] ?? $row['correo'] ?? $row[0] ?? null;
                $schoolCode = $row['school_code'] ?? $row['codigo_escuela'] ?? $row['escuela_codigo'] ?? $row['code'] ?? $row['codigo'] ?? $row[1] ?? null;
                $base = $row['base_year'] ?? $row['base'] ?? $row['base_code'] ?? $row[2] ?? null;
            } elseif ($row instanceof Collection) {
                $email = $row->get('email') ?? $row->get('correo') ?? $row->get(0);
                $schoolCode = $row->get('school_code') ?? $row->get('codigo_escuela') ?? $row->get('escuela_codigo') ?? $row->get('code') ?? $row->get('codigo') ?? $row->get(1);
                $base = $row->get('base_year') ?? $row->get('base') ?? $row->get('base_code') ?? $row->get(2);
            }

            $email = is_string($email) ? trim($email) : null;
            $schoolCode = is_string($schoolCode) ? trim($schoolCode) : null;
            $base = is_string($base) ? trim($base) : null;

            if (($email === null || $email === '') && ($schoolCode === null || $schoolCode === '') && ($base === null || $base === '')) {
                continue;
            }

            $this->rows[] = [
                'row' => $rowNumber,
                'email' => $email,
                'school_code' => $schoolCode,
                'base' => $base,
            ];
        }
    }
}
