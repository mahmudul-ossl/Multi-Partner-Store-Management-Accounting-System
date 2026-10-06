<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineProps({
    allocation: { type: Object, required: true },
});
</script>

<template>
    <AppLayout :title="allocation.reference">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ allocation.reference }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ allocation.date }} · {{ allocation.method }}</p>
            </div>
            <StatusBadge :label="allocation.status.label" :tone="allocation.status.tone" />
        </div>

        <section class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="card p-4">
                <p class="text-xs uppercase text-slate-500">Amount</p>
                <p class="mt-2 font-semibold">{{ allocation.amount }}</p>
            </div>
            <div class="card p-4">
                <p class="text-xs uppercase text-slate-500">Method</p>
                <p class="mt-2 font-semibold">{{ allocation.method }}</p>
            </div>
        </section>
        <p v-if="allocation.note" class="mt-4 text-sm text-slate-600">{{ allocation.note }}</p>
        <p v-if="allocation.approval" class="mt-2 text-sm text-slate-600">
            Approvals {{ allocation.approval.completed }}/{{ allocation.approval.required }}.
            <Link :href="`/approvals/${allocation.approval.id}`" class="text-teal-800 hover:underline">Open the request</Link>
        </p>
        <p v-if="allocation.journal_entry_id" class="mt-2 text-sm">
            <Link :href="`/accounting/entries/${allocation.journal_entry_id}`" class="text-teal-800 hover:underline">View journal</Link>
        </p>

        <section class="card mt-6 overflow-hidden">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Partner</th>
                        <th class="px-4 py-3 font-semibold">Percentage</th>
                        <th class="px-4 py-3 font-semibold">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="line in allocation.lines" :key="line.partner" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ line.partner }}</td>
                        <td class="px-4 py-3">{{ line.percentage }}</td>
                        <td class="px-4 py-3">{{ line.amount }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
