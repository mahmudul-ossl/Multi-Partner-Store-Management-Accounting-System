<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useCan } from '../../composables/useCan';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    withdrawals: { type: Object, required: true },
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
    reason: '',
    payment_method: 'cash',
    financial_account_id: props.accounts[0]?.id || '',
    note: '',
});

function applyFilters() {
    router.get('/withdrawals', { search: search.value, status: status.value, sort: sort.value, direction: direction.value }, { preserveState: true, replace: true });
}

function toggleSort(column) {
    direction.value = sort.value === column && direction.value === 'asc' ? 'desc' : 'asc';
    sort.value = column;
    applyFilters();
}

function submit() {
    form.post('/withdrawals');
}
</script>

<template>
    <AppLayout title="Withdrawals">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Withdrawals</h1>
                <p class="mt-1 text-sm text-slate-500">Balances change only when the last required approver agrees.</p>
            </div>
            <button v-if="can('partner.withdrawal.create')" class="btn btn-primary" type="button" @click="showForm = true">New withdrawal</button>
        </div>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
            <div class="md:col-span-2">
                <label class="label" for="withdrawal-search">Search</label>
                <input id="withdrawal-search" v-model="search" class="field" placeholder="Reference or partner">
            </div>
            <div>
                <label class="label" for="withdrawal-status">Status</label>
                <select id="withdrawal-status" v-model="status" class="field">
                    <option value="">All statuses</option>
                    <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
            <div class="flex items-end"><button class="btn btn-secondary" type="submit">Apply filters</button></div>
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
                            <th class="px-4 py-3 font-semibold">Reason</th>
                            <th class="px-4 py-3"><button type="button" class="font-semibold" @click="toggleSort('status')">Status</button></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in withdrawals.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3"><Link :href="`/withdrawals/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.reference }}</Link></td>
                            <td class="px-4 py-3">{{ row.partner?.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.transaction_date_formatted }}</td>
                            <td class="px-4 py-3 font-medium">{{ row.amount_formatted }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.reason }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                        </tr>
                        <tr v-if="!withdrawals.data.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">No withdrawals match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="withdrawals" :filters="{ search, status, sort, direction }" url="/withdrawals" />
        </section>

        <Modal :open="showForm" title="New withdrawal" @close="showForm = false">
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <div>
                    <label class="label" for="wd-partner">Partner</label>
                    <select id="wd-partner" v-model="form.partner_id" class="field" required>
                        <option v-for="partner in partners" :key="partner.id" :value="partner.id">{{ partner.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="wd-amount">Amount</label>
                    <input id="wd-amount" v-model="form.amount" class="field" required placeholder="50000.00">
                    <p v-if="form.errors.amount" class="error">{{ form.errors.amount }}</p>
                </div>
                <div>
                    <label class="label" for="wd-date">Date</label>
                    <input id="wd-date" v-model="form.transaction_date" class="field" type="date" required>
                    <p v-if="form.errors.transaction_date" class="error">{{ form.errors.transaction_date }}</p>
                </div>
                <div>
                    <label class="label" for="wd-reason">Reason</label>
                    <input id="wd-reason" v-model="form.reason" class="field" required>
                </div>
                <div>
                    <label class="label" for="wd-method">Payment method</label>
                    <select id="wd-method" v-model="form.payment_method" class="field">
                        <option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="wd-account">Financial account</label>
                    <select id="wd-account" v-model="form.financial_account_id" class="field" required>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                    <p v-if="form.errors.financial_account_id" class="error">{{ form.errors.financial_account_id }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="wd-note">Note</label>
                    <textarea id="wd-note" v-model="form.note" class="field" rows="3" />
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">Submit for approval</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
