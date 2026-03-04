export type ReservationStatus =
    | 'pending'
    | 'approved'
    | 'rejected'
    | 'cancelled';

export type NotifyEmailEventKey =
    | 'pending'
    | 'approved'
    | 'rejected'
    | 'cancelled'
    | 'expired';

export type NotifyEmailEvents = {
    admin: Record<NotifyEmailEventKey, boolean>;
    student: Record<NotifyEmailEventKey, boolean>;
};

export type ArtifactKind = 'email_admin' | 'email_student';

export type ArtifactStatus = 'pending' | 'sent' | 'failed' | 'skipped';

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
    created_at: string;
    decided_at: string | null;
    cancelled_at: string | null;
    user: AdminUser;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedResponse<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
};

export type SimplePaginatedResponse<T> = {
    current_page: number;
    data: T[];
    first_page_url: string;
    from: number | null;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
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

export type RecurringBlackout = {
    id: number;
    weekday: number;
    starts_time: string;
    ends_time: string;
    starts_on?: string | null;
    ends_on?: string | null;
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

export type AllowListEntry = {
    id: number;
    email: string;
    student_code: string | null;
    base_year: number | null;
    professional_school: { id: number; name: string } | null;
};

export type DayKey = 'mon' | 'tue' | 'wed' | 'thu' | 'fri' | 'sat' | 'sun';

export type OpeningHours = Record<DayKey, { open: string; close: string }>;

export type AdminSettings = {
    opening_hours: OpeningHours;
    min_duration_minutes: number;
    max_duration_minutes: number;
    lead_time_min_hours: number;
    lead_time_max_days: number;
    max_active_reservations_per_user: number;
    weekly_quota_per_school_base: number;
    pending_expiration_hours: number;
    cancel_cutoff_hours: number;
    notify_admin_emails: { to: string[]; cc: string[]; bcc: string[] };
    notify_email_events: NotifyEmailEvents;
};

export type ImportReport = {
    imported: number;
    duplicates: number;
    invalid: number;
    invalid_rows: Array<{ row: number; value: string | null }>;
    batch_id: string;
};

export type UserAuditActivity = {
    event_type: string;
    actor_name: string | null;
    created_at: string | null;
};

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    email_verified_at: string | null;
    disabled_at: string | null;
    is_protected: boolean;
    created_at: string | null;
    recent_activity: UserAuditActivity[];
};
