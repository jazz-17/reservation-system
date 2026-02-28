import type {
    ArtifactKind,
    AuditEvent,
    ReservationStatus,
} from '@/types/admin';

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

    return new Intl.DateTimeFormat('es-PE', {
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
    pdf: 'PDF',
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
