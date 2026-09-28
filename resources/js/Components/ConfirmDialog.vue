<script setup>
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Confirm' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <div v-if="props.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <button class="absolute inset-0 bg-slate-900/50" type="button" aria-label="Dismiss confirmation" @click="emit('close')" />
        <div class="relative z-10 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" role="alertdialog" aria-modal="true">
            <h2 class="text-lg font-semibold text-slate-900">{{ title }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ message }}</p>
            <div class="mt-6 flex justify-end gap-3">
                <button class="btn btn-secondary" type="button" @click="emit('close')">Cancel</button>
                <button class="btn btn-danger" type="button" @click="emit('confirm')">{{ confirmLabel }}</button>
            </div>
        </div>
    </div>
</template>
