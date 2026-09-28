<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import SummaryCard from '../../Components/SummaryCard.vue';

defineProps({
    partner: { type: Object, required: true },
    position: { type: Object, required: true },
});
</script>

<template>
    <AppLayout title="Partner dashboard">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-teal-700">{{ partner.partner_code }}</p>
                <h1 class="text-2xl font-semibold tracking-tight">{{ partner.name }}</h1>
                <p class="mt-1 text-sm text-slate-500">Figures come from ledger lines, not from investment minus withdrawal.</p>
            </div>
            <Link :href="`/partners/${partner.id}/statement`" class="btn btn-secondary">Statement</Link>
        </div>
        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <SummaryCard label="Total investment" :value="position.investment.formatted" />
            <SummaryCard label="Withdrawals" :value="position.withdrawal.formatted" />
            <SummaryCard label="Promotion contribution" :value="position.promotion_contribution.formatted" />
            <SummaryCard label="Partner expenses" :value="position.expenses.formatted" />
            <SummaryCard label="Profit share" :value="position.profit_share.formatted" />
            <SummaryCard label="Current capital" :value="position.current_capital.formatted" />
            <SummaryCard label="Pending requests" :value="position.requests.pending" />
            <SummaryCard label="Approved requests" :value="position.requests.approved" />
            <SummaryCard label="Rejected requests" :value="position.requests.rejected" />
        </section>
        <section class="card mt-8 overflow-hidden">
            <h2 class="border-b border-slate-200 px-5 py-4 font-semibold">Recent ledger lines</h2>
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Date</th>
                        <th class="px-5 py-3 font-semibold">Description</th>
                        <th class="px-5 py-3 font-semibold">Debit</th>
                        <th class="px-5 py-3 font-semibold">Credit</th>
                        <th class="px-5 py-3 font-semibold">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(line, index) in position.recent" :key="index" class="border-t border-slate-100">
                        <td class="px-5 py-3">{{ line.date }}</td>
                        <td class="px-5 py-3">{{ line.description }}</td>
                        <td class="px-5 py-3">{{ line.debit_formatted }}</td>
                        <td class="px-5 py-3">{{ line.credit_formatted }}</td>
                        <td class="px-5 py-3 font-medium">{{ line.running_balance_formatted }}</td>
                    </tr>
                    <tr v-if="!position.recent.length">
                        <td colspan="5" class="px-5 py-8 text-center text-slate-500">No posted capital movements yet.</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
