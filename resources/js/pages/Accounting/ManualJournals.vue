<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useCan } from '../../composables/useCan';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    journals: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    charts: { type: Array, required: true },
    financialAccounts: { type: Array, required: true },
    partners: { type: Array, required: true },
});

const { can } = useCan();
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const showForm = ref(false);

function blankLine() {
    return { account_code: '', partner_id: '', financial_account_id: '', debit: '', credit: '', description: '' };
}

const form = useForm({
    entry_date: '',
    description: '',
    lines: [blankLine(), blankLine()],
});

function accountsFor(code) {
    return props.financialAccounts.filter((account) => account.chart_code === code);
}

function applyFilters() {
    router.get('/accounting/manual-journals', { search: search.value, status: status.value }, { preserveState: true, replace: true });
}

function submit() {
    form.post('/accounting/manual-journals', { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Manual journals">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Manual journals</h1>
                <p class="mt-1 text-sm text-slate-500">Nothing hits the ledger until the last required approval.</p>
            </div>
            <button v-if="can('accounting.manage')" class="btn btn-primary" type="button" @click="showForm = true">New journal</button>
        </div>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
            <div class="md:col-span-2">
                <label class="label" for="mj-search">Search</label>
                <input id="mj-search" v-model="search" class="field" placeholder="Reference or description">
            </div>
            <div>
                <label class="label" for="mj-status">Status</label>
                <select id="mj-status" v-model="status" class="field">
                    <option value="">All statuses</option>
                    <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
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
                            <th class="px-4 py-3 font-semibold">Reference</th>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Description</th>
                            <th class="px-4 py-3 font-semibold">Amount</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Approvals</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in journals.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3 font-medium">
                                <Link :href="`/accounting/manual-journals/${row.id}`" class="text-teal-800 hover:underline">{{ row.reference }}</Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ row.entry_date_formatted }}</td>
                            <td class="px-4 py-3">{{ row.description }}</td>
                            <td class="px-4 py-3 font-medium">{{ row.amount.formatted }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                            <td class="px-4 py-3 text-slate-600">{{ row.approval ? `${row.approval.completed_approvals}/${row.approval.required_approvals}` : '—' }}</td>
                        </tr>
                        <tr v-if="!journals.data.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">No manual journals match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="journals" :filters="{ search, status }" url="/accounting/manual-journals" />
        </section>

        <Modal :open="showForm" title="New manual journal" @close="showForm = false">
            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="mj-date">Date</label>
                        <input id="mj-date" v-model="form.entry_date" class="field" type="date" required>
                    </div>
                    <div>
                        <label class="label" for="mj-description">Description</label>
                        <input id="mj-description" v-model="form.description" class="field" required>
                        <p v-if="form.errors.description" class="error">{{ form.errors.description }}</p>
                    </div>
                </div>
                <div v-for="(line, index) in form.lines" :key="index" class="grid gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label">Account</label>
                        <select v-model="line.account_code" class="field" required>
                            <option value="">Select account</option>
                            <option v-for="chart in charts" :key="chart.code" :value="chart.code">{{ chart.name }}</option>
                        </select>
                    </div>
                    <div v-if="accountsFor(line.account_code).length">
                        <label class="label">Financial account</label>
                        <select v-model="line.financial_account_id" class="field" required>
                            <option value="">Select</option>
                            <option v-for="account in accountsFor(line.account_code)" :key="account.id" :value="account.id">{{ account.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Partner</label>
                        <select v-model="line.partner_id" class="field">
                            <option value="">None</option>
                            <option v-for="partner in partners" :key="partner.id" :value="partner.id">{{ partner.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Debit</label>
                        <input v-model="line.debit" class="field" inputmode="decimal" placeholder="0.00">
                    </div>
                    <div>
                        <label class="label">Credit</label>
                        <input v-model="line.credit" class="field" inputmode="decimal" placeholder="0.00">
                    </div>
                </div>
                <p v-if="form.errors.lines" class="error">{{ form.errors.lines }}</p>
                <div class="flex justify-between">
                    <button class="btn btn-secondary" type="button" @click="form.lines.push(blankLine())">Add line</button>
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">Submit for approval</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
