import { ref, type Ref } from 'vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: Ref<BreadcrumbItem[]> = ref([]);

export function useBreadcrumbs(items?: BreadcrumbItem[]): Ref<BreadcrumbItem[]> {
    if (items !== undefined) {
        breadcrumbs.value = items;
    }

    return breadcrumbs;
}
