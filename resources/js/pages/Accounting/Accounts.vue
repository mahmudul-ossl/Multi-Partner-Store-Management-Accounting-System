<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useCan } from '../../composables/useCan';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';

const props = defineProps({
    accounts: { type: Object, required: true },
    filters: { type: Object, required: true },
    types: { type: Array, required: true },
    charts: { type: Array, required: true },
});

const { can } = useCan();
const search = ref(props.filters.search);
const type = ref(props.filters.type);
const showForm = ref(false);
const form = useForm({
    name: '',
    type: 'cash',
    chart_of_account_id: props.charts[0]?.id || '',
    opening_balance: '0.00',
    opening_date: '',
});

function applyFilters() {
    router.get('/accounting/accounts', { search: search.value, type: type.value }, { preserveState: true, replace: true });
}

function submit() {
    form.post('/accounting/accounts', { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Cash and bank">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Cash, bank, and wallets</h1>
                <p class="mt-1 text-sm text-slate-500">Opening balances post through the ledger. The cached balance is reconciled back to those lines.</p>
            </div>
            <button v-if="can('accounting.manage')" class="btn btn-primary" type="button" @click="showForm = true">Open account</button>
        </div>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
            <div class="md:col-span-2">
                <label class="label" for="fa-search">Search</label>
                <input id="fa-search" v-model="search" class="field" placeholder="Account name">
            </div>
            <div>
                <label class="label" for="fa-type">Type</label>
                <select id="fa-type" v-model="type" class="field">
                    <option value="">All types</option>
                    <option v-for="item in types" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
            <div class="flex items-end">
                <button class="btn btn-secondary" type="submit">Apply filters</button>
            </div>
        </form>

        <section class="card mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Name</th>
                            <th class="px-4 py-3 font-semibold">Type</th>
                            <th class="px-4 py-3 font-semibold">Ledger account</th>
                            <th class="px-4 py-3 font-semibold">Cached</th>
                            <th class="px-4 py-3 font-semibold">Ledger</th>
                            <th class="px-4 py-3 font-semibold">Match</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in accounts.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3 font-medium">
                                <Link :href="`/accounting/accounts/${row.id}`" class="text-teal-800 hover:underline">{{ row.name }}</Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ row.type.label }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.chart ? `${row.chart.code} ${row.chart.name}` : '—' }}</td>
                            <td class="px-4 py-3">{{ row.current_balance.formatted }}</td>
                            <td class="px-4 py-3">{{ row.ledger_balance.formatted }}</td>
                            <td class="px-4 py-3">{{ row.reconciled ? 'Yes' : 'Differs' }}</td>
                        </tr>
                        <tr v-if="!accounts.data.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">No financial accounts match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="accounts" :filters="{ search, type }" url="/accounting/accounts" />
        </section>

        <Modal :open="showForm" title="Open financial account" @close="showForm = false">
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <div class="sm:col-span-2">
                    <label class="label" for="fa-name">Name</label>
                    <input id="fa-name" v-model="form.name" class="field" required>
                    <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="label" for="fa-new-type">Type</label>
                    <select id="fa-new-type" v-model="form.type" class="field">
                        <option v-for="item in types" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="fa-chart">Ledger account</label>
                    <select id="fa-chart" v-model="form.chart_of_account_id" class="field" required>
                        <option v-for="chart in charts" :key="chart.id" :value="chart.id">{{ chart.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="fa-opening">Opening balance</label>
                    <input id="fa-opening" v-model="form.opening_balance" class="field" inputmode="decimal" required>
                    <p v-if="form.errors.opening_balance" class="error">{{ form.errors.opening_balance }}</p>
                </div>
                <div>
                    <label class="label" for="fa-date">Opening date</label>
                    <input id="fa-date" v-model="form.opening_date" class="field" type="date">
                    <p v-if="form.errors.opening_date" class="error">{{ form.errors.opening_date }}</p>
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">Post opening balance</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
