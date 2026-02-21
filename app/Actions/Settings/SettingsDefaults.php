<?php

namespace App\Actions\Settings;

class SettingsDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        return [
            'timezone' => 'America/Lima',
            'opening_hours' => [
                'mon' => ['open' => '08:00', 'close' => '22:00'],
                'tue' => ['open' => '08:00', 'close' => '22:00'],
                'wed' => ['open' => '08:00', 'close' => '22:00'],
                'thu' => ['open' => '08:00', 'close' => '22:00'],
                'fri' => ['open' => '08:00', 'close' => '22:00'],
                'sat' => ['open' => '08:00', 'close' => '22:00'],
                'sun' => ['open' => '08:00', 'close' => '22:00'],
            ],
            'booking_mode' => 'fixed_duration',
            'slot_duration_minutes' => 60,
            'slot_step_minutes' => 30,
            'min_duration_minutes' => 60,
            'max_duration_minutes' => 120,
            'lead_time_min_hours' => 2,
            'lead_time_max_days' => 30,
            'max_active_reservations_per_user' => 1,
            'weekly_quota_per_school_base' => 2,
            'pending_expiration_hours' => 24,
            'cancel_cutoff_hours' => 2,
            'notify_admin_emails' => [
                'to' => [],
                'cc' => [],
                'bcc' => [],
            ],
            'notify_student_on_approval' => true,
            'pdf_template' => 'default',
            'predefined_blocks' => [
                'mon' => [],
                'tue' => [],
                'wed' => [],
                'thu' => [],
                'fri' => [],
                'sat' => [],
                'sun' => [],
            ],
        ];
    }
}

