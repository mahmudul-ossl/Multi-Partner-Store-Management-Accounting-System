<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useCan } from '../../composables/useCan';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    investments: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    paymentMethods: { type: Array, required: true },
    partners: { type: Array, required: true },
    accounts: { type: Array, required: true },
});

const { can } = useCan();
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const sort = ref(props.filters.sort);
const direction = ref(props.filters.direction);
const showForm = ref(false);

const form = useForm({
    partner_id: props.partners[0]?.id || '',
    amount: '',
    transaction_date: '',
    payment_method: 'cash',
    financial_account_id: props.accounts[0]?.id || '',
    note: '',
});

function applyFilters() {
    router.get('/investments', {
        search: search.value,
        status: status.value,
        sort: sort.value,
        direction: direction.value,
    }, { preserveState: true, replace: true });
}

function toggleSort(column) {
    direction.value = sort.value === column && direction.value === 'asc' ? 'desc' : 'asc';
    sort.value = column;
    applyFilters();
}

function submit() {
    form.post('/investments', { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Investments">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Investments</h1>
                <p class="mt-1 text-sm text-slate-500">Cash is debited only after the final approval.</p>
            </div>
            <button v-if="can('partner.investment.create')" class="btn btn-primary" type="button" @click="showForm = true">New investment</button>
        </div>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
            <div class="md:col-span-2">
                <label class="label" for="investment-search">Search</label>
                <input id="investment-search" v-model="search" class="field" placeholder="Reference or partner">
            </div>
            <div>
                <label class="label" for="investment-status">Status</label>
                <select id="investment-status" v-model="status" class="field">
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
                            <th class="px-4 py-3"><button type="button" class="font-semibold" @click="toggleSort('reference')">Reference</button></th>
                            <th class="px-4 py-3 font-semibold">Partner</th>
                            <th class="px-4 py-3"><button type="button" class="font-semibold" @click="toggleSort('transaction_date')">Date</button></th>
                            <th class="px-4 py-3"><button type="button" class="font-semibold" @click="toggleSort('amount')">Amount</button></th>
                            <th class="px-4 py-3 font-semibold">Account</th>
                            <th class="px-4 py-3"><button type="button" class="font-semibold" @click="toggleSort('status')">Status</button></th>
                            <th class="px-4 py-3 font-semibold">Approvals</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in investments.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3 font-medium">
                                <Link :href="`/investments/${row.id}`" class="text-teal-800 hover:underline">{{ row.reference }}</Link>
                            </td>
                            <td class="px-4 py-3">{{ row.partner?.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.transaction_date_formatted }}</td>
                            <td class="px-4 py-3 font-medium">{{ row.amount_formatted }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.financial_account }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                            <td class="px-4 py-3 text-slate-600">{{ row.approval ? `${row.approval.completed_approvals}/${row.approval.required_approvals}` : '—' }}</td>
                        </tr>
                        <tr v-if="!investments.data.length">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">No investments match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="investments" :filters="{ search, status, sort, direction }" url="/investments" />
        </section>

        <Modal :open="showForm" title="New investment" @close="showForm = false">
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <div>
                    <label class="label" for="inv-partner">Partner</label>
                    <select id="inv-partner" v-model="form.partner_id" class="field" required>
                        <option v-for="partner in partners" :key="partner.id" :value="partner.id">{{ partner.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="inv-amount">Amount</label>
                    <input id="inv-amount" v-model="form.amount" class="field" inputmode="decimal" required placeholder="50000.00">
                    <p v-if="form.errors.amount" class="error">{{ form.errors.amount }}</p>
                </div>
                <div>
                    <label class="label" for="inv-date">Date</label>
                    <input id="inv-date" v-model="form.transaction_date" class="field" type="date" required>
                </div>
                <div>
                    <label class="label" for="inv-method">Payment method</label>
                    <select id="inv-method" v-model="form.payment_method" class="field">
                        <option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="inv-account">Financial account</label>
                    <select id="inv-account" v-model="form.financial_account_id" class="field" required>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                    <p v-if="form.errors.financial_account_id" class="error">{{ form.errors.financial_account_id }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="inv-note">Note</label>
                    <textarea id="inv-note" v-model="form.note" class="field" rows="3" />
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">Submit for approval</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
