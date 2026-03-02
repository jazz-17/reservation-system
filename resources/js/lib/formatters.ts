import type {
    ArtifactKind,
    AuditEvent,
    ReservationStatus,
} from '@/types/admin';

export const APP_LOCALE = 'es-PE' as const;
export const APP_TIMEZONE = 'America/Lima' as const;

type YmdParts = { year: string; month: string; day: string };

const ymdPartsInTimeZone = (date: Date, timeZone: string): YmdParts => {
    const parts = new Intl.DateTimeFormat('en', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(date);

    const year = parts.find((p) => p.type === 'year')?.value;
    const month = parts.find((p) => p.type === 'month')?.value;
    const day = parts.find((p) => p.type === 'day')?.value;

    if (!year || !month || !day) {
        return { year: '0000', month: '00', day: '00' };
    }

    return { year, month, day };
};

export function formatYmdInTimeZone(date: Date, timeZone = APP_TIMEZONE): string {
    const { year, month, day } = ymdPartsInTimeZone(date, timeZone);

    return `${year}-${month}-${day}`;
}

export function formatDate(iso: string): string {
    const d = new Date(iso);

    return new Intl.DateTimeFormat(APP_LOCALE, {
        timeZone: APP_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(d);
}

/**
 * Format an ISO datetime string for display in es-PE locale.
 *
 * By default includes date + time (hour:minute). Pass `{ seconds: true }`
 * to include seconds (used in the Audit log).
 */
export function formatDateTime(
    iso: string,
    options?: { seconds?: boolean },
): string {
    const d = new Date(iso);

    return new Intl.DateTimeFormat(APP_LOCALE, {
        timeZone: APP_TIMEZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        ...(options?.seconds ? { second: '2-digit' } : {}),
    }).format(d);
}

/**
 * Format a base year (e.g. 2024 → "B24"). Returns "—" for null/undefined.
 */
export function formatBaseYear(year?: number | null): string {
    if (!year) {
        return '—';
    }

    const yy = String(year % 100).padStart(2, '0');

    return `B${yy}`;
}

const statusLabels: Record<ReservationStatus, string> = {
    pending: 'Pendiente',
    approved: 'Aprobada',
    rejected: 'Rechazada',
    cancelled: 'Cancelada',
};

/**
 * Human-readable label for a reservation status.
 */
export function formatReservationStatus(status: ReservationStatus): string {
    return statusLabels[status];
}

const artifactKindLabels: Record<ArtifactKind, string> = {
    email_admin: 'Email (Admin)',
    email_student: 'Email (Estudiante)',
};

/**
 * Human-readable label for an artifact kind.
 */
export function formatArtifactKind(kind: ArtifactKind): string {
    return artifactKindLabels[kind];
}

/**
 * Format an audit event subject as "ModelName#id" or "—".
 */
export function formatAuditSubject(event: AuditEvent): string {
    if (!event.subject_type || !event.subject_id) {
        return '—';
    }

    const basename = event.subject_type.split('\\').pop() ?? event.subject_type;

    return `${basename}#${event.subject_id}`;
}
