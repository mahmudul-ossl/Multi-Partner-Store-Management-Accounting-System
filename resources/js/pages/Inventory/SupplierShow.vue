<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    supplier: { type: Object, required: true },
    purchases: { type: Array, required: true },
    payments: { type: Array, required: true },
    accounts: { type: Array, required: true },
    methods: { type: Array, required: true },
    can: { type: Object, required: true },
});

const contact = useForm({ name: '', phone: '', email: '', role: '' });
const payment = useForm({
    supplier_id: props.supplier.id,
    purchase_id: '',
    financial_account_id: props.accounts[0]?.id || '',
    payment_method: 'cash',
    amount: '',
    payment_date: '',
    note: '',
});
</script>

<template>
    <AppLayout :title="supplier.name">
        <h1 class="text-2xl font-semibold tracking-tight">{{ supplier.name }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ supplier.company_name }} · {{ supplier.phone }} · {{ supplier.address }}</p>
        <p class="mt-1 text-sm capitalize text-slate-500">{{ supplier.status }}</p>

        <section class="card mt-6 p-5">
            <h2 class="font-semibold">Contacts</h2>
            <ul class="mt-3 space-y-1 text-sm">
                <li v-for="row in supplier.contacts" :key="row.id">{{ row.name }} · {{ row.phone }} · {{ row.role }}</li>
                <li v-if="supplier.contacts.length === 0" class="text-slate-500">No contacts yet.</li>
            </ul>
            <form v-if="can.contact" class="mt-4 grid gap-2 md:grid-cols-4" @submit.prevent="contact.post(`/inventory/suppliers/${supplier.id}/contacts`)">
                <input v-model="contact.name" class="field" placeholder="Name" required>
                <input v-model="contact.phone" class="field" placeholder="Phone">
                <input v-model="contact.role" class="field" placeholder="Role">
                <button class="btn btn-secondary" type="submit">Add contact</button>
            </form>
        </section>

        <section class="card mt-4 overflow-hidden">
            <h2 class="px-4 py-3 font-semibold">Purchases</h2>
            <table class="min-w-full text-left text-sm">
                <tbody>
                    <tr v-for="row in purchases" :key="row.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">
                            <Link :href="`/inventory/purchases/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.reference }}</Link>
                        </td>
                        <td class="px-4 py-3">{{ row.date }}</td>
                        <td class="px-4 py-3">{{ row.total }}</td>
                        <td class="px-4 py-3">Due {{ row.due }}</td>
                        <td class="px-4 py-3">{{ row.status }}</td>
                    </tr>
                    <tr v-if="purchases.length === 0">
                        <td class="px-4 py-6 text-slate-500">No purchases yet.</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card mt-4 overflow-hidden">
            <h2 class="px-4 py-3 font-semibold">Payments</h2>
            <table class="min-w-full text-left text-sm">
                <tbody>
                    <tr v-for="row in payments" :key="row.id" class="border-t border-slate-100">
                        <td class="px-4 py-3 font-medium">{{ row.reference }}</td>
                        <td class="px-4 py-3">{{ row.date }}</td>
                        <td class="px-4 py-3">{{ row.amount }}</td>
                        <td class="px-4 py-3">{{ row.status }}</td>
                    </tr>
                    <tr v-if="payments.length === 0">
                        <td class="px-4 py-6 text-slate-500">No payments yet.</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <form v-if="can.pay" class="card mt-4 grid gap-3 p-5 md:grid-cols-3" @submit.prevent="payment.post('/inventory/supplier-payments')">
            <h2 class="font-semibold md:col-span-3">Record a supplier payment</h2>
            <div>
                <label class="label">Purchase</label>
                <select v-model="payment.purchase_id" class="field">
                    <option value="">Unallocated</option>
                    <option v-for="row in purchases.filter((item) => item.approved)" :key="row.id" :value="row.id">{{ row.reference }} · due {{ row.due }}</option>
                </select>
            </div>
            <div>
                <label class="label">Pay from</label>
                <select v-model="payment.financial_account_id" class="field">
                    <option v-for="row in accounts" :key="row.id" :value="row.id">{{ row.name }}</option>
                </select>
            </div>
            <div>
                <label class="label">Method</label>
                <select v-model="payment.payment_method" class="field">
                    <option v-for="row in methods" :key="row.value" :value="row.value">{{ row.label }}</option>
                </select>
            </div>
            <div>
                <label class="label">Amount</label>
                <input v-model="payment.amount" class="field" required>
            </div>
            <div>
                <label class="label">Date</label>
                <input v-model="payment.payment_date" class="field" type="date" required>
            </div>
            <div class="flex items-end">
                <button class="btn btn-primary" type="submit">Submit for approval</button>
            </div>
            <p v-if="Object.keys(payment.errors).length" class="text-sm text-rose-700 md:col-span-3">{{ Object.values(payment.errors)[0] }}</p>
        </form>
    </AppLayout>
</template>
