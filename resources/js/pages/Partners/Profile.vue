<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import SummaryCard from '../../Components/SummaryCard.vue';

const props = defineProps({
    partner: { type: Object, required: true },
    summary: { type: Object, required: true },
    history: { type: Object, required: true },
    totals: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const form = reactive({
    from: props.filters.from || '',
    to: props.filters.to || '',
});

const exportQuery = computed(() => {
    const params = new URLSearchParams();
    if (form.from) {
        params.set('from', form.from);
    }
    if (form.to) {
        params.set('to', form.to);
    }
    const query = params.toString();

    return query ? `?${query}` : '';
});

function apply() {
    router.get(`/partners/${props.partner.id}/profile`, { ...form }, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout title="Partner profile">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-teal-700">{{ partner.partner_code }}</p>
                <h1 class="text-2xl font-semibold tracking-tight">{{ partner.name }}</h1>
                <div class="mt-2">
                    <StatusBadge :label="partner.status.label" :tone="partner.status.tone" />
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a class="btn btn-secondary" :href="`/partners/${partner.id}/profile/excel${exportQuery}`">Excel</a>
                <a class="btn btn-secondary" :href="`/partners/${partner.id}/profile/pdf${exportQuery}`">PDF</a>
                <Link :href="`/partners/${partner.id}/statement`" class="btn btn-secondary">Statement</Link>
                <Link :href="`/partners/${partner.id}`" class="btn btn-secondary">Record</Link>
            </div>
        </div>

        <section class="card mt-6 grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4">
            <div>
                <p class="text-sm text-slate-500">Phone</p>
                <p class="mt-1 font-medium">{{ partner.phone || '—' }}</p>
            </div>
            <div>
                <p class="text-sm text-slate-500">Email</p>
                <p class="mt-1 font-medium">{{ partner.email || '—' }}</p>
            </div>
            <div>
                <p class="text-sm text-slate-500">Joined</p>
                <p class="mt-1 font-medium">{{ partner.joining_date_formatted || '—' }}</p>
            </div>
            <div>
                <p class="text-sm text-slate-500">Linked user</p>
                <p class="mt-1 font-medium">{{ partner.user?.name || 'Not linked' }}</p>
            </div>
            <div class="sm:col-span-2 xl:col-span-4">
                <p class="text-sm text-slate-500">Address</p>
                <p class="mt-1 font-medium">{{ partner.address || '—' }}</p>
            </div>
        </section>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <SummaryCard label="Gross investment" :value="summary.gross_investment.formatted" hint="Posted partner investments" />
            <SummaryCard label="Withdrawals" :value="summary.withdrawals.formatted" hint="Posted partner withdrawals" />
            <SummaryCard label="Allocated profit" :value="summary.allocated_profit.formatted" hint="Posted profit allocations" />
            <SummaryCard label="Promotion contribution" :value="summary.promotion_contribution.formatted" />
            <SummaryCard label="Partner expenses" :value="summary.partner_expenses.formatted" />
            <SummaryCard label="Net capital" :value="summary.net_capital.formatted" hint="Ledger balance, not investment minus withdrawal" />
        </section>

        <section class="mt-6 grid gap-4 lg:grid-cols-2">
            <article class="card p-5">
                <p class="text-sm font-medium text-slate-500">Investment percentage</p>
                <p class="mt-2 text-2xl font-semibold tracking-tight">{{ summary.investment_share_display }}</p>
                <p class="mt-2 text-sm text-slate-600">Stored investment percentage {{ summary.stored_investment_percentage_display }}. Basis total {{ summary.investment_basis_total }}%.</p>
                <p class="mt-2 text-sm text-slate-500">{{ summary.investment_basis_label }}</p>
            </article>
            <article class="card p-5">
                <p class="text-sm font-medium text-slate-500">Share of total partner capital</p>
                <p class="mt-2 text-2xl font-semibold tracking-tight">{{ summary.capital_share_display }}</p>
                <p class="mt-2 text-sm text-slate-600">Total partner capital {{ summary.capital_total.formatted }}.</p>
                <p class="mt-2 text-sm text-slate-500">{{ summary.capital_basis_label }}</p>
            </article>
        </section>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="apply">
            <div>
                <label class="label" for="profile-from">From</label>
                <input id="profile-from" v-model="form.from" class="field" type="date">
            </div>
            <div>
                <label class="label" for="profile-to">To</label>
                <input id="profile-to" v-model="form.to" class="field" type="date">
            </div>
            <div class="flex items-end">
                <button class="btn btn-secondary" type="submit">Apply</button>
            </div>
        </form>

        <section class="card mt-4 overflow-hidden">
            <div class="grid gap-3 border-b border-slate-200 px-5 py-4 sm:grid-cols-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Opening</p>
                    <p class="mt-1 font-semibold">{{ totals.opening.formatted }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Debits</p>
                    <p class="mt-1 font-semibold">{{ totals.debit_total.formatted }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Credits</p>
                    <p class="mt-1 font-semibold">{{ totals.credit_total.formatted }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-500">Closing</p>
                    <p class="mt-1 font-semibold">{{ totals.closing.formatted }}</p>
                </div>
            </div>
            <p class="px-5 py-3 text-sm text-slate-500">Opening plus credits minus debits equals the closing balance. With no date filter, closing equals net capital.</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Reference</th>
                            <th class="px-4 py-3 font-semibold">Type</th>
                            <th class="px-4 py-3 font-semibold">Description</th>
                            <th class="px-4 py-3 font-semibold">Debit</th>
                            <th class="px-4 py-3 font-semibold">Credit</th>
                            <th class="px-4 py-3 font-semibold">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in history.data" :key="`${line.reference}-${index}`" class="border-t border-slate-100">
                            <td class="px-4 py-3">{{ line.date }}</td>
                            <td class="px-4 py-3">{{ line.reference }}</td>
                            <td class="px-4 py-3">{{ line.type }}</td>
                            <td class="px-4 py-3">
                                <p>{{ line.description }}</p>
                                <p class="text-xs text-slate-500">{{ line.account }}</p>
                            </td>
                            <td class="px-4 py-3">{{ line.debit === '0.00' ? '—' : line.debit_formatted }}</td>
                            <td class="px-4 py-3">{{ line.credit === '0.00' ? '—' : line.credit_formatted }}</td>
                            <td class="px-4 py-3 font-medium">{{ line.running_balance_formatted }}</td>
                        </tr>
                        <tr v-if="!history.data.length">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">No capital movements in this range.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="history" :filters="filters" :url="`/partners/${partner.id}/profile`" />
        </section>
    </AppLayout>
</template>
