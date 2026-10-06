<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    incomes: { type: Object, required: true },
    sources: { type: Array, required: true },
    accounts: { type: Array, required: true },
    methods: { type: Array, required: true },
    can: { type: Object, required: true },
});

const showForm = ref(false);
const form = useForm({
    source: props.sources[0]?.value || 'website',
    amount: '',
    transaction_date: '',
    payment_method: 'cash',
    financial_account_id: props.accounts[0]?.id || '',
    note: '',
});

const otherError = computed(() => {
    const errors = { ...form.errors };
    delete errors.transaction_date;

    return Object.values(errors)[0] || '';
});

function submit() {
    form.post('/sales', { preserveScroll: true, onSuccess: () => { showForm.value = false; } });
}
</script>

<template>
    <AppLayout title="Sales">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Sales</h1>
                <p class="mt-1 text-sm text-slate-500">Record daily or weekly takings from the website or shop. This is cash in, not product invoices.</p>
            </div>
            <button v-if="can.create" class="btn btn-primary" type="button" @click="showForm = true">New sale</button>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Reference</th>
                            <th class="px-4 py-3 font-semibold">Source</th>
                            <th class="px-4 py-3 font-semibold">Amount</th>
                            <th class="px-4 py-3 font-semibold">Account</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in incomes.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3">
                                <Link :href="`/sales/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.reference }}</Link>
                                <p class="text-xs text-slate-500">{{ row.date }}</p>
                            </td>
                            <td class="px-4 py-3">{{ row.source }}</td>
                            <td class="px-4 py-3">{{ row.amount }}</td>
                            <td class="px-4 py-3">{{ row.account }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                        </tr>
                        <tr v-if="incomes.data.length === 0">
                            <td class="px-4 py-8 text-center text-slate-500" colspan="5">No sales recorded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="incomes" url="/sales" />
        </section>

        <Modal :open="showForm" title="Record sale" @close="showForm = false">
            <form class="grid gap-3" @submit.prevent="submit">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="label">Source</label>
                        <select v-model="form.source" class="field" required>
                            <option v-for="row in sources" :key="row.value" :value="row.value">{{ row.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Amount</label>
                        <input v-model="form.amount" class="field" required>
                    </div>
                    <div>
                        <label class="label">Date</label>
                        <input v-model="form.transaction_date" class="field" type="date" required>
                        <p v-if="form.errors.transaction_date" class="error">{{ form.errors.transaction_date }}</p>
                    </div>
                    <div>
                        <label class="label">Method</label>
                        <select v-model="form.payment_method" class="field" required>
                            <option v-for="row in methods" :key="row.value" :value="row.value">{{ row.label }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="label">Received in</label>
                        <select v-model="form.financial_account_id" class="field" required>
                            <option v-for="row in accounts" :key="row.id" :value="row.id">{{ row.name }}</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label">Note</label>
                    <input v-model="form.note" class="field" placeholder="e.g. Sale from website 30000">
                </div>
                <p v-if="otherError" class="text-sm text-rose-700">{{ otherError }}</p>
                <div class="flex justify-end">
                    <button class="btn btn-primary" :disabled="form.processing" type="submit">Save</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
