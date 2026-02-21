<?php

namespace App\Http\Requests\Admin;

use App\Models\Enums\BookingMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        $rules = [
            'timezone' => ['required', 'string', 'max:64'],
            'booking_mode' => ['required', Rule::in(array_map(fn (BookingMode $m) => $m->value, BookingMode::cases()))],

            'slot_duration_minutes' => ['required', 'integer', 'min:1', 'max:720'],
            'slot_step_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'min_duration_minutes' => ['required', 'integer', 'min:1', 'max:720'],
            'max_duration_minutes' => ['required', 'integer', 'min:1', 'max:720'],

            'lead_time_min_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'lead_time_max_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_active_reservations_per_user' => ['required', 'integer', 'min:0', 'max:50'],
            'weekly_quota_per_school_base' => ['required', 'integer', 'min:0', 'max:50'],
            'pending_expiration_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'cancel_cutoff_hours' => ['required', 'integer', 'min:0', 'max:168'],

            'notify_student_on_approval' => ['required', 'boolean'],
            'pdf_template' => ['required', 'string', 'max:64'],

            'opening_hours' => ['required', 'array'],
            'predefined_blocks' => ['required', 'array'],

            'notify_admin_emails' => ['required', 'array'],
            'notify_admin_emails.to' => ['required', 'array'],
            'notify_admin_emails.to.*' => ['email'],
            'notify_admin_emails.cc' => ['nullable', 'array'],
            'notify_admin_emails.cc.*' => ['email'],
            'notify_admin_emails.bcc' => ['nullable', 'array'],
            'notify_admin_emails.bcc.*' => ['email'],
        ];

        foreach ($days as $day) {
            $rules["opening_hours.{$day}"] = ['required', 'array'];
            $rules["opening_hours.{$day}.open"] = ['required', 'date_format:H:i'];
            $rules["opening_hours.{$day}.close"] = ['required', 'date_format:H:i'];

            $rules["predefined_blocks.{$day}"] = ['required', 'array'];
            $rules["predefined_blocks.{$day}.*.start"] = ['required', 'date_format:H:i'];
            $rules["predefined_blocks.{$day}.*.end"] = ['required', 'date_format:H:i'];
        }

        return $rules;
    }
}
