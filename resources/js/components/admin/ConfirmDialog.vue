<script setup lang="ts">
import { ref, watch } from 'vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        confirmLabel?: string;
        cancelLabel?: string;
        variant?: 'default' | 'destructive';
        inputLabel?: string;
    }>(),
    {
        confirmLabel: 'Confirmar',
        cancelLabel: 'Cancelar',
        variant: 'default',
    },
);

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
    confirm: [value?: string];
}>();

const inputValue = ref('');

watch(open, (isOpen) => {
    if (isOpen) {
        inputValue.value = '';
    }
});

const handleConfirm = (): void => {
    const value = props.inputLabel
        ? inputValue.value.trim() || undefined
        : undefined;

    emit('confirm', value);
    open.value = false;
};
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogTrigger as-child>
            <slot name="trigger" />
        </AlertDialogTrigger>
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ props.title }}</AlertDialogTitle>
                <AlertDialogDescription v-if="props.description">
                    {{ props.description }}
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div v-if="props.inputLabel" class="grid gap-1">
                <label class="text-sm" for="confirm-dialog-input">
                    {{ props.inputLabel }}
                </label>
                <Input
                    id="confirm-dialog-input"
                    v-model="inputValue"
                    type="text"
                />
            </div>

            <AlertDialogFooter>
                <AlertDialogCancel>{{ props.cancelLabel }}</AlertDialogCancel>
                <AlertDialogAction
                    :class="
                        props.variant === 'destructive'
                            ? buttonVariants({ variant: 'destructive' })
                            : undefined
                    "
                    @click.prevent="handleConfirm"
                >
                    {{ props.confirmLabel }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
