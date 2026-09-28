<script setup>
const props = defineProps({
    label: { type: String, required: true },
    tone: { type: String, default: 'slate' },
});

const tones = {
    green: 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
    amber: 'bg-amber-50 text-amber-800 ring-amber-600/20',
    red: 'bg-red-50 text-red-700 ring-red-600/20',
    blue: 'bg-sky-50 text-sky-800 ring-sky-600/20',
    slate: 'bg-slate-100 text-slate-700 ring-slate-500/20',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
        :class="tones[props.tone] || tones.slate"
    >
        {{ label }}
    </span>
</template>
