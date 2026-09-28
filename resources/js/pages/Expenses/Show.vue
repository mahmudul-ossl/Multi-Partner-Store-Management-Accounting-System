<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineProps({
    expense: { type: Object, required: true },
});
</script>

<template>
    <AppLayout :title="expense.reference">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ expense.reference }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ expense.date }} · {{ expense.category }}</p>
            </div>
            <StatusBadge :label="expense.status.label" :tone="expense.status.tone" />
        </div>

        <section class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Amount</p><p class="mt-2 font-semibold">{{ expense.amount }}</p></div>
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Paid by</p><p class="mt-2 font-semibold">{{ expense.partner || 'Business' }}</p></div>
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Account</p><p class="mt-2 font-semibold">{{ expense.account || 'Partner capital' }}</p></div>
        </section>
        <p class="mt-4 text-sm text-slate-600">{{ expense.description }}</p>
        <p v-if="expense.approval" class="mt-2 text-sm text-slate-600">
            Approvals {{ expense.approval.completed }}/{{ expense.approval.required }}.
            <Link :href="`/approvals/${expense.approval.id}`" class="text-teal-800 hover:underline">Open the request</Link>
        </p>
        <p v-if="expense.journal_entry_id" class="mt-2 text-sm">
            <Link :href="`/accounting/entries/${expense.journal_entry_id}`" class="text-teal-800 hover:underline">View journal</Link>
        </p>
    </AppLayout>
</template>
