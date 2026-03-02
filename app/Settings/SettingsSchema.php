<?php

namespace App\Settings;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingsSchema
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'opening_hours' => [
                'mon' => ['open' => '08:00', 'close' => '22:00'],
                'tue' => ['open' => '08:00', 'close' => '22:00'],
                'wed' => ['open' => '08:00', 'close' => '22:00'],
                'thu' => ['open' => '08:00', 'close' => '22:00'],
                'fri' => ['open' => '08:00', 'close' => '22:00'],
                'sat' => ['open' => '08:00', 'close' => '22:00'],
                'sun' => ['open' => '08:00', 'close' => '22:00'],
            ],
            'min_duration_minutes' => 60,
            'max_duration_minutes' => 120,
            'lead_time_min_hours' => 2,
            'lead_time_max_days' => 30,
            'max_active_reservations_per_user' => 1,
            'weekly_quota_per_school_base' => 2,
            'pending_expiration_hours' => 24,
            'cancel_cutoff_hours' => 2,
            'notify_admin_emails' => [
                'to' => ['kevin.quispe5@unmsm.edu.pe'],
                'cc' => [],
                'bcc' => [],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::defaults());
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalize(array $values): array
    {
        if (array_key_exists('notify_admin_emails', $values)) {
            $notify = $values['notify_admin_emails'];

            if (is_array($notify) && Arr::isList($notify)) {
                $values['notify_admin_emails'] = ['to' => $notify, 'cc' => [], 'bcc' => []];
            }
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public static function validate(array $values): array
    {
        $normalized = self::normalize($values);

        $validator = Validator::make($normalized, self::rules(), self::messages());

        try {
            return $validator->validate();
        } catch (ValidationException $exception) {
            throw $exception;
        }
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $messages = [];

        foreach (self::DAYS as $day) {
            $messages["opening_hours.{$day}.close.after"] =
                "La hora de cierre del {$day} debe ser posterior a la hora de apertura.";
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $rules = [
            'min_duration_minutes' => ['required', 'integer', 'min:1', 'max:720'],
            'max_duration_minutes' => ['required', 'integer', 'min:1', 'max:720'],
            'lead_time_min_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'lead_time_max_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_active_reservations_per_user' => ['required', 'integer', 'min:0', 'max:50'],
            'weekly_quota_per_school_base' => ['required', 'integer', 'min:0', 'max:50'],
            'pending_expiration_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'cancel_cutoff_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'opening_hours' => ['required', 'array'],
            'notify_admin_emails' => ['required', 'array'],
            'notify_admin_emails.to' => ['present', 'array'],
            'notify_admin_emails.to.*' => ['email'],
            'notify_admin_emails.cc' => ['present', 'array'],
            'notify_admin_emails.cc.*' => ['email'],
            'notify_admin_emails.bcc' => ['present', 'array'],
            'notify_admin_emails.bcc.*' => ['email'],
        ];

        foreach (self::DAYS as $day) {
            $rules["opening_hours.{$day}"] = ['required', 'array'];
            $rules["opening_hours.{$day}.open"] = ['required', 'date_format:H:i'];
            $rules["opening_hours.{$day}.close"] = ['required', 'date_format:H:i', "after:opening_hours.{$day}.open"];
        }

        return $rules;
    }
}
