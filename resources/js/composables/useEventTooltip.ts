import type { EventHoveringArg } from '@fullcalendar/core';
import { reactive } from 'vue';
import { APP_LOCALE, APP_TIMEZONE } from '@/lib/formatters';

const typeLabels: Record<string, string> = {
    reservation: 'Ocupado',
    pending: 'Solicitado',
    blackout: 'Bloqueado',
};

const timeFmt = new Intl.DateTimeFormat(APP_LOCALE, {
    timeZone: 'UTC',
    hour: '2-digit',
    minute: '2-digit',
});

export function useEventTooltip() {
    const tooltip = reactive({
        visible: false,
        label: '',
        time: '',
        top: 0,
        left: 0,
    });

    function showTooltip(arg: EventHoveringArg): void {
        const { event, el } = arg;
        const type = (event.extendedProps?.type as string) ?? 'reservation';
        const start = event.start;
        const end = event.end;

        tooltip.label = typeLabels[type] ?? type;
        tooltip.time =
            start && end
                ? `${timeFmt.format(start)} – ${timeFmt.format(end)}`
                : '';

        const rect = el.getBoundingClientRect();
        tooltip.top = rect.top + window.scrollY - 4;
        tooltip.left = rect.left + rect.width / 2 + window.scrollX;
        tooltip.visible = true;
    }

    function hideTooltip(): void {
        tooltip.visible = false;
    }

    return { tooltip, showTooltip, hideTooltip };
}
