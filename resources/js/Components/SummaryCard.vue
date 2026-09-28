<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    muted: { type: Boolean, default: false },
});
</script>

<template>
    <article class="card p-5" :class="muted ? 'bg-slate-50' : ''">
        <p class="text-sm font-medium text-slate-500">{{ label }}</p>
        <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ value }}</p>
        <p v-if="hint" class="mt-2 text-xs font-medium uppercase tracking-wide text-amber-700">{{ hint }}</p>
    </article>
</template>
