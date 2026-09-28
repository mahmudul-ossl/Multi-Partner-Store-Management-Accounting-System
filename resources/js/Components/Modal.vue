<script setup>
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
});

const emit = defineEmits(['close']);
</script>

<template>
    <div v-if="props.open" class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <button class="absolute inset-0 bg-slate-900/50" type="button" aria-label="Close dialog" @click="emit('close')" />
        <div class="relative z-10 w-full max-w-2xl rounded-2xl bg-white shadow-xl" role="dialog" aria-modal="true">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">{{ title }}</h2>
                <button class="rounded-md px-2 py-1 text-slate-500 hover:bg-slate-100" type="button" @click="emit('close')">
                    Close
                </button>
            </div>
            <div class="px-5 py-4">
                <slot />
            </div>
        </div>
    </div>
</template>
