<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('admin.gestion.configuracion.manage') ?? false;
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
            'min_duration_minutes' => ['required', 'integer', 'min:1', 'max:720'],
            'max_duration_minutes' => ['required', 'integer', 'min:1', 'max:720'],

            'lead_time_min_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'lead_time_max_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_active_reservations_per_user' => ['required', 'integer', 'min:0', 'max:50'],
            'weekly_quota_per_school_base' => ['required', 'integer', 'min:0', 'max:50'],
            'pending_expiration_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'cancel_cutoff_hours' => ['required', 'integer', 'min:0', 'max:168'],

            'email_notifications_enabled' => ['required', 'boolean'],
            'notify_student_on_approval' => ['required', 'boolean'],
            'pdf_template' => ['required', 'string', 'max:64'],

            'opening_hours' => ['required', 'array'],

            'notify_admin_emails' => ['required', 'array'],
            'notify_admin_emails.to' => ['present', 'array'],
            'notify_admin_emails.to.*' => ['email'],
            'notify_admin_emails.cc' => ['present', 'array'],
            'notify_admin_emails.cc.*' => ['email'],
            'notify_admin_emails.bcc' => ['present', 'array'],
            'notify_admin_emails.bcc.*' => ['email'],
        ];

        foreach ($days as $day) {
            $rules["opening_hours.{$day}"] = ['required', 'array'];
            $rules["opening_hours.{$day}.open"] = ['required', 'date_format:H:i'];
            $rules["opening_hours.{$day}.close"] = ['required', 'date_format:H:i'];
        }

        return $rules;
    }
}
