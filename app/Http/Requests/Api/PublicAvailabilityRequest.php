<?php

namespace App\Http\Requests\Api;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublicAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $timezone = (string) config('app.timezone', 'America/Lima');
                $maxDays = (int) config('rate-limiting.public_availability_max_days', 60);

                try {
                    $startLocal = CarbonImmutable::parse((string) $this->input('start'), $timezone);
                    $endLocal = CarbonImmutable::parse((string) $this->input('end'), $timezone);
                } catch (\Throwable) {
                    return;
                }

                if ($endLocal->greaterThan($startLocal->addDays($maxDays))) {
                    $validator->errors()->add('end', "El rango de fechas no puede exceder {$maxDays} días.");
                }
            },
        ];
    }
}
