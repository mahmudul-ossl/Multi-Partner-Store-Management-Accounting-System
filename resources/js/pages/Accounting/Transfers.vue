<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useCan } from '../../composables/useCan';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    transfers: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    accounts: { type: Array, required: true },
});

const { can } = useCan();
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const showForm = ref(false);
const form = useForm({
    from_financial_account_id: props.accounts[0]?.id || '',
    to_financial_account_id: props.accounts[1]?.id || '',
    amount: '',
    transaction_date: '',
    note: '',
});

function applyFilters() {
    router.get('/accounting/transfers', { search: search.value, status: status.value }, { preserveState: true, replace: true });
}

function submit() {
    form.post('/accounting/transfers', { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Account transfers">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Account transfers</h1>
                <p class="mt-1 text-sm text-slate-500">Moving money from a bank to bKash posts a balanced journal after approval.</p>
            </div>
            <button v-if="can('accounting.manage')" class="btn btn-primary" type="button" @click="showForm = true">New transfer</button>
        </div>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
            <div class="md:col-span-2">
                <label class="label" for="at-search">Search</label>
                <input id="at-search" v-model="search" class="field" placeholder="Reference">
            </div>
            <div>
                <label class="label" for="at-status">Status</label>
                <select id="at-status" v-model="status" class="field">
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
                            <th class="px-4 py-3 font-semibold">From</th>
                            <th class="px-4 py-3 font-semibold">To</th>
                            <th class="px-4 py-3 font-semibold">Amount</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in transfers.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3 font-medium">
                                <Link :href="`/accounting/transfers/${row.id}`" class="text-teal-800 hover:underline">{{ row.reference }}</Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ row.transaction_date_formatted }}</td>
                            <td class="px-4 py-3">{{ row.from_account }}</td>
                            <td class="px-4 py-3">{{ row.to_account }}</td>
                            <td class="px-4 py-3 font-medium">{{ row.amount.formatted }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                        </tr>
                        <tr v-if="!transfers.data.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">No transfers match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="transfers" :filters="{ search, status }" url="/accounting/transfers" />
        </section>

        <Modal :open="showForm" title="New account transfer" @close="showForm = false">
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <div>
                    <label class="label" for="at-from">From</label>
                    <select id="at-from" v-model="form.from_financial_account_id" class="field" required>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="at-to">To</label>
                    <select id="at-to" v-model="form.to_financial_account_id" class="field" required>
                        <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                    </select>
                    <p v-if="form.errors.to_financial_account_id" class="error">{{ form.errors.to_financial_account_id }}</p>
                </div>
                <div>
                    <label class="label" for="at-amount">Amount</label>
                    <input id="at-amount" v-model="form.amount" class="field" inputmode="decimal" required>
                    <p v-if="form.errors.amount" class="error">{{ form.errors.amount }}</p>
                </div>
                <div>
                    <label class="label" for="at-date">Date</label>
                    <input id="at-date" v-model="form.transaction_date" class="field" type="date" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="at-note">Note</label>
                    <textarea id="at-note" v-model="form.note" class="field" rows="3" />
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">Submit for approval</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
