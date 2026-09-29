<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    expenses: { type: Object, required: true },
    categories: { type: Array, required: true },
    partners: { type: Array, required: true },
    accounts: { type: Array, required: true },
    methods: { type: Array, required: true },
    can: { type: Object, required: true },
});

const showForm = ref(false);
const form = useForm({
    category: props.categories[0]?.value || 'rent',
    amount: '',
    transaction_date: '',
    description: '',
    partner_id: '',
    payment_method: 'cash',
    financial_account_id: props.accounts[0]?.id || '',
});

const otherError = computed(() => {
    const errors = { ...form.errors };
    delete errors.payment_method;
    delete errors.financial_account_id;

    return Object.values(errors)[0] || '';
});

function submit() {
    form.transform((data) => ({
        ...data,
        payment_method: data.partner_id ? null : data.payment_method,
        financial_account_id: data.partner_id ? null : data.financial_account_id,
    })).post('/expenses', { preserveScroll: true, onSuccess: () => { showForm.value = false; } });
}
</script>

<template>
    <AppLayout title="Expenses">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Expenses</h1>
                <p class="mt-1 text-sm text-slate-500">Leave the partner blank when the business pays from cash or bank. Choose a partner when they paid personally — that credits their capital. Approval is required either way.</p>
            </div>
            <button v-if="can.create" class="btn btn-primary" type="button" @click="showForm = true">New expense</button>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Reference</th>
                            <th class="px-4 py-3 font-semibold">Category</th>
                            <th class="px-4 py-3 font-semibold">Paid by</th>
                            <th class="px-4 py-3 font-semibold">Amount</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in expenses.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3">
                                <Link :href="`/expenses/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.reference }}</Link>
                                <p class="text-xs text-slate-500">{{ row.date }}</p>
                            </td>
                            <td class="px-4 py-3">{{ row.category }}</td>
                            <td class="px-4 py-3">{{ row.partner }}<p v-if="row.account" class="text-xs text-slate-500">{{ row.account }}</p></td>
                            <td class="px-4 py-3">{{ row.amount }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                        </tr>
                        <tr v-if="expenses.data.length === 0">
                            <td class="px-4 py-8 text-center text-slate-500" colspan="5">No expenses yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="expenses" url="/expenses" />
        </section>

        <Modal :open="showForm" title="New expense" @close="showForm = false">
            <form class="grid gap-3" @submit.prevent="submit">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="label">Category</label>
                        <select v-model="form.category" class="field">
                            <option v-for="row in categories" :key="row.value" :value="row.value">{{ row.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Amount</label>
                        <input v-model="form.amount" class="field" required>
                    </div>
                    <div>
                        <label class="label">Date</label>
                        <input v-model="form.transaction_date" class="field" type="date" required>
                    </div>
                    <div>
                        <label class="label">Partner (if they paid)</label>
                        <select v-model="form.partner_id" class="field">
                            <option value="">Business paid</option>
                            <option v-for="row in partners" :key="row.id" :value="row.id">{{ row.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Method</label>
                        <select v-model="form.payment_method" class="field">
                            <option value="">None</option>
                            <option v-for="row in methods" :key="row.value" :value="row.value">{{ row.label }}</option>
                        </select>
                        <p v-if="form.errors.payment_method" class="error">{{ form.errors.payment_method }}</p>
                    </div>
                    <div>
                        <label class="label">Pay from</label>
                        <select v-model="form.financial_account_id" class="field">
                            <option value="">None</option>
                            <option v-for="row in accounts" :key="row.id" :value="row.id">{{ row.name }}</option>
                        </select>
                        <p v-if="form.errors.financial_account_id" class="error">{{ form.errors.financial_account_id }}</p>
                    </div>
                </div>
                <div>
                    <label class="label">Description</label>
                    <textarea v-model="form.description" class="field" rows="2" required></textarea>
                </div>
                <p v-if="otherError" class="text-sm text-rose-700">{{ otherError }}</p>
                <div class="flex justify-end">
                    <button class="btn btn-primary" :disabled="form.processing" type="submit">Submit for approval</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
