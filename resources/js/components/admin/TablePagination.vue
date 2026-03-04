<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { TableCell, TableFooter, TableRow } from '@/components/ui/table';
import type { PaginationLink } from '@/types/admin';

defineProps<{
    links: PaginationLink[];
    lastPage: number;
    colspan: number;
}>();
</script>

<template>
    <TableFooter v-if="lastPage > 1">
        <TableRow>
            <TableCell :colspan="colspan">
                <div class="flex items-center justify-center gap-1">
                    <template v-for="link in links" :key="link.label">
                        <Button
                            v-if="link.url"
                            variant="outline"
                            size="sm"
                            :class="{ 'font-bold': link.active }"
                            @click="router.get(link.url!)"
                        >
                            <span v-html="link.label" />
                        </Button>
                        <span
                            v-else
                            class="px-2 text-sm text-muted-foreground"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </TableCell>
        </TableRow>
    </TableFooter>
</template>
