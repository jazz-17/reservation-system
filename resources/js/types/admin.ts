export type ReservationStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';

export type ArtifactKind = 'pdf' | 'email_admin' | 'email_student';

export type ArtifactStatus = 'pending' | 'sent' | 'failed';

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    first_name?: string | null;
    last_name?: string | null;
    phone?: string | null;
    base_year?: number | null;
    professional_school?: {
        id: number;
        name: string;
        faculty?: { id: number; name: string } | null;
    } | null;
};

export type AdminReservation = {
    id: number;
    starts_at: string;
    ends_at: string;
    status: ReservationStatus;
    user: AdminUser;
};

export type Artifact = {
    id: number;
    kind: ArtifactKind;
    status: ArtifactStatus;
    attempts: number;
    last_error?: string | null;
    last_attempt_at?: string | null;
    reservation: {
        id: number;
        starts_at: string;
        ends_at: string;
        user?: { name: string; email: string } | null;
    };
};

export type AuditActor = {
    id: number;
    name: string;
};

export type AuditEvent = {
    id: number;
    event_type: string;
    actor_id: number | null;
    actor?: AuditActor | null;
    subject_type: string | null;
    subject_id: number | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
};

export type Blackout = {
    id: number;
    starts_at: string;
    ends_at: string;
    reason?: string | null;
};

export type Faculty = {
    id: number;
    name: string;
    active: boolean;
};

export type ProfessionalSchool = {
    id: number;
    faculty_id: number;
    code: string | null;
    name: string;
    base_year_min: number;
    base_year_max: number;
    active: boolean;
    faculty?: { id: number; name: string } | null;
};

export type DayKey = 'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun';

export type OpeningHours = Record<DayKey, { open: string; close: string }>;

export type AdminSettings = {
    timezone: string;
    opening_hours: OpeningHours;
    min_duration_minutes: number;
    max_duration_minutes: number;
    lead_time_min_hours: number;
    lead_time_max_days: number;
    max_active_reservations_per_user: number;
    weekly_quota_per_school_base: number;
    pending_expiration_hours: number;
    cancel_cutoff_hours: number;
    email_notifications_enabled: boolean;
    notify_admin_emails: { to: string[]; cc: string[]; bcc: string[] };
    notify_student_on_approval: boolean;
    pdf_template: string;
};

export type ImportReport = {
    imported: number;
    duplicates: number;
    invalid: number;
    invalid_rows: Array<{ row: number; value: string | null }>;
    batch_id: string;
};
