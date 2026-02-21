<?php

namespace App\Http\Requests\Student;

use App\Actions\Settings\SettingsService;
use App\Models\Enums\BookingMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $settings = app(SettingsService::class);
        $mode = BookingMode::from($settings->getString('booking_mode'));

        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => [
                $mode === BookingMode::FixedDuration ? 'nullable' : 'required',
                'date',
                'after:starts_at',
            ],
        ];
    }
}
