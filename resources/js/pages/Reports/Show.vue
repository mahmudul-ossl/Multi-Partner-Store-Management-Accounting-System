<script setup>
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Pagination from '../../Components/Pagination.vue';

const props = defineProps({
    report: { type: Object, required: true },
    columns: { type: Array, required: true },
    rows: { type: Object, required: true },
    totals: { type: Array, required: true },
    filters: { type: Object, required: true },
    partners: { type: Array, required: true },
    accounts: { type: Array, required: true },
});

const form = reactive({
    from: props.filters.from || '',
    to: props.filters.to || '',
    search: props.filters.search || '',
    sort: props.filters.sort || props.columns[0]?.key || '',
    direction: props.filters.direction || 'asc',
    partner: props.filters.partner || '',
    account: props.filters.account || '',
});

const exportQuery = computed(() => {
    const params = new URLSearchParams();
    Object.entries(form).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            params.set(key, String(value));
        }
    });
    const query = params.toString();

    return query ? `?${query}` : '';
});

function apply() {
    router.get(`/reports/${props.report.key}`, { ...form }, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout :title="report.label">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ report.label }}</h1>
                <p class="mt-1 text-sm text-slate-500">Date filter, search, and sort apply before paging. Excel and PDF use the same rows and totals.</p>
            </div>
            <div class="flex gap-2">
                <a class="btn btn-secondary" :href="`/reports/${report.key}/excel${exportQuery}`">Excel</a>
                <a class="btn btn-secondary" :href="`/reports/${report.key}/pdf${exportQuery}`">PDF</a>
            </div>
        </div>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="apply">
            <div>
                <label class="label">From</label>
                <input v-model="form.from" class="field" type="date">
            </div>
            <div>
                <label class="label">To</label>
                <input v-model="form.to" class="field" type="date">
            </div>
            <div>
                <label class="label">Search</label>
                <input v-model="form.search" class="field" type="search">
            </div>
            <div>
                <label class="label">Sort</label>
                <select v-model="form.sort" class="field">
                    <option v-for="column in columns" :key="column.key" :value="column.key">{{ column.label }}</option>
                </select>
            </div>
            <div>
                <label class="label">Direction</label>
                <select v-model="form.direction" class="field">
                    <option value="asc">Ascending</option>
                    <option value="desc">Descending</option>
                </select>
            </div>
            <div v-if="report.key === 'partner-statement'">
                <label class="label">Partner</label>
                <select v-model="form.partner" class="field">
                    <option value="">Select a partner</option>
                    <option v-for="partner in partners" :key="partner.id" :value="String(partner.id)">{{ partner.partner_code }} · {{ partner.name }}</option>
                </select>
            </div>
            <div v-if="report.key === 'general-ledger'">
                <label class="label">Account</label>
                <select v-model="form.account" class="field">
                    <option value="">Select an account</option>
                    <option v-for="account in accounts" :key="account.id" :value="String(account.id)">{{ account.label }}</option>
                </select>
            </div>
            <div class="flex items-end">
                <button class="btn btn-primary" type="submit">Apply</button>
            </div>
        </form>

        <section class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th v-for="column in columns" :key="column.key" class="px-4 py-3 font-semibold">{{ column.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows.data" :key="index" class="border-t border-slate-100">
                            <td v-for="column in columns" :key="column.key" class="px-4 py-3">{{ row[column.key] }}</td>
                        </tr>
                        <tr v-if="rows.data.length === 0">
                            <td class="px-4 py-8 text-center text-slate-500" :colspan="columns.length || 1">No rows for this filter.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="totals.length" class="border-t border-slate-200 px-4 py-3 text-sm">
                <p v-for="total in totals" :key="total.label" class="font-medium text-slate-800">
                    {{ total.label }}: {{ total.formatted }}
                </p>
            </div>
            <Pagination :meta="rows" :filters="filters" :url="`/reports/${report.key}`" />
        </section>
    </AppLayout>
</template>
