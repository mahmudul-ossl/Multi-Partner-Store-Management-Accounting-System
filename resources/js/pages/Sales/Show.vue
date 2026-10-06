<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineProps({
    income: { type: Object, required: true },
});
</script>

<template>
    <AppLayout :title="income.reference">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ income.reference }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ income.date }} · {{ income.source }}</p>
            </div>
            <StatusBadge :label="income.status.label" :tone="income.status.tone" />
        </div>

        <section class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Amount</p><p class="mt-2 font-semibold">{{ income.amount }}</p></div>
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Source</p><p class="mt-2 font-semibold">{{ income.source }}</p></div>
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Account</p><p class="mt-2 font-semibold">{{ income.account }}</p></div>
        </section>
        <p v-if="income.note" class="mt-4 text-sm text-slate-600">{{ income.note }}</p>
        <p v-if="income.approval" class="mt-2 text-sm text-slate-600">
            Approvals {{ income.approval.completed }}/{{ income.approval.required }}.
            <Link :href="`/approvals/${income.approval.id}`" class="text-teal-800 hover:underline">Open the request</Link>
        </p>
        <p v-if="income.journal_entry_id" class="mt-2 text-sm">
            <Link :href="`/accounting/entries/${income.journal_entry_id}`" class="text-teal-800 hover:underline">View journal</Link>
        </p>
    </AppLayout>
</template>
