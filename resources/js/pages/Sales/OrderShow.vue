<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    sale: { type: Object, required: true },
    accounts: { type: Array, required: true },
    methods: { type: Array, required: true },
    can: { type: Object, required: true },
});

const returning = useForm({
    sale_id: props.sale.id,
    transaction_date: '',
    note: '',
    items: props.sale.items.map((item) => ({
        sale_item_id: item.id,
        quantity: '0',
        product: item.product,
        available: item.quantity,
    })),
});
const payment = useForm({
    sale_id: props.sale.id,
    financial_account_id: props.accounts[0]?.id || '',
    payment_method: 'cash',
    amount: '',
    payment_date: '',
    note: '',
});
const refund = useForm({
    sale_id: props.sale.id,
    financial_account_id: props.accounts[0]?.id || '',
    payment_method: 'cash',
    amount: '',
    transaction_date: '',
    note: '',
});
const cancellation = useForm({
    sale_id: props.sale.id,
    transaction_date: '',
    reason: '',
});

const credit = String(props.sale.due_amount).startsWith('-');
const owes = Number(props.sale.due_amount) > 0;

function submitReturn() {
    returning.transform((data) => ({
        sale_id: data.sale_id,
        transaction_date: data.transaction_date,
        note: data.note,
        items: data.items.filter((item) => Number(item.quantity) > 0).map((item) => ({
            sale_item_id: item.sale_item_id,
            quantity: item.quantity,
        })),
    })).post('/sales/returns');
}
</script>

<template>
    <AppLayout :title="sale.reference">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ sale.reference }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ sale.date }} · {{ sale.customer }} · {{ sale.warehouse }}</p>
            </div>
            <StatusBadge :label="sale.status.label" :tone="sale.status.tone" />
        </div>

        <section class="mt-6 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Subtotal</p><p class="mt-2 font-semibold">{{ sale.subtotal }}</p></div>
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Discount</p><p class="mt-2 font-semibold">{{ sale.discount }}</p></div>
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Delivery</p><p class="mt-2 font-semibold">{{ sale.delivery }}</p></div>
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Total</p><p class="mt-2 font-semibold">{{ sale.total }}</p></div>
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Paid</p><p class="mt-2 font-semibold">{{ sale.paid }}</p></div>
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Due</p><p class="mt-2 font-semibold">{{ sale.due }}</p></div>
        </section>

        <p v-if="sale.account" class="mt-4 text-sm text-slate-600">Paid into {{ sale.account }}.</p>
        <p v-if="sale.note" class="mt-2 text-sm text-slate-600">{{ sale.note }}</p>
        <p v-if="sale.approval" class="mt-2 text-sm text-slate-600">
            Approvals {{ sale.approval.completed }}/{{ sale.approval.required }}.
            <Link :href="`/approvals/${sale.approval.id}`" class="text-teal-800 hover:underline">Open the request</Link>
        </p>
        <p v-if="sale.journal_entry_id" class="mt-2 text-sm">
            <Link :href="`/accounting/entries/${sale.journal_entry_id}`" class="text-teal-800 hover:underline">View journal</Link>
        </p>

        <section class="card mt-6 overflow-hidden">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Product</th>
                        <th class="px-4 py-3 font-semibold">Quantity</th>
                        <th class="px-4 py-3 font-semibold">Price</th>
                        <th class="px-4 py-3 font-semibold">Cost</th>
                        <th class="px-4 py-3 font-semibold">Net</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in sale.items" :key="item.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ item.product }}</td>
                        <td class="px-4 py-3">{{ item.quantity }}</td>
                        <td class="px-4 py-3">{{ item.unit_price }}</td>
                        <td class="px-4 py-3">{{ item.unit_cost || '—' }}</td>
                        <td class="px-4 py-3">{{ item.net }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-if="sale.payments.length || sale.returns.length || sale.refunds.length" class="card mt-4 p-4 text-sm">
            <p v-for="row in sale.payments" :key="row.reference">Payment {{ row.reference }} {{ row.amount }} on {{ row.date }}</p>
            <p v-for="row in sale.returns" :key="row.id">
                Return <Link :href="`/sales/returns/${row.id}`" class="text-teal-800 hover:underline">{{ row.reference }}</Link> {{ row.total }}
            </p>
            <p v-for="row in sale.refunds" :key="row.reference">Refund {{ row.reference }} {{ row.amount }} · {{ row.status }}</p>
        </section>

        <form v-if="can.return && sale.completed" class="card mt-4 grid gap-3 p-5" @submit.prevent="submitReturn">
            <h2 class="font-semibold">Return goods</h2>
            <input v-model="returning.transaction_date" class="field max-w-xs" type="date" required>
            <p v-if="returning.errors.transaction_date" class="error">{{ returning.errors.transaction_date }}</p>
            <div v-for="line in returning.items" :key="line.sale_item_id" class="grid gap-2 md:grid-cols-2">
                <p class="text-sm">{{ line.product }}</p>
                <input v-model="line.quantity" class="field" placeholder="Quantity to return">
            </div>
            <div class="flex justify-end"><button class="btn btn-primary" type="submit">Post return</button></div>
        </form>

        <form v-if="can.pay && sale.completed && owes" class="card mt-4 grid gap-3 p-5 md:grid-cols-3" @submit.prevent="payment.post('/sales/payments')">
            <h2 class="font-semibold md:col-span-3">Receive a payment</h2>
            <select v-model="payment.financial_account_id" class="field">
                <option v-for="row in accounts" :key="row.id" :value="row.id">{{ row.name }}</option>
            </select>
            <select v-model="payment.payment_method" class="field">
                <option v-for="row in methods" :key="row.value" :value="row.value">{{ row.label }}</option>
            </select>
            <input v-model="payment.amount" class="field" placeholder="Amount">
            <input v-model="payment.payment_date" class="field" type="date" required>
            <p v-if="payment.errors.payment_date" class="error">{{ payment.errors.payment_date }}</p>
            <button class="btn btn-primary" type="submit">Post payment</button>
        </form>

        <form v-if="can.refund && sale.completed && credit" class="card mt-4 grid gap-3 p-5 md:grid-cols-3" @submit.prevent="refund.post('/sales/refunds')">
            <h2 class="font-semibold md:col-span-3">Refund customer credit</h2>
            <p class="text-sm text-slate-500 md:col-span-3">Cash goes out only after someone else approves. You cannot approve your own refund.</p>
            <select v-model="refund.financial_account_id" class="field">
                <option v-for="row in accounts" :key="row.id" :value="row.id">{{ row.name }}</option>
            </select>
            <input v-model="refund.amount" class="field" placeholder="Amount">
            <input v-model="refund.transaction_date" class="field" type="date" required>
            <p v-if="refund.errors.transaction_date" class="error">{{ refund.errors.transaction_date }}</p>
            <button class="btn btn-secondary" type="submit">Submit refund</button>
        </form>

        <form v-if="can.cancel && sale.completed && sale.returns.length === 0 && sale.payments.length === 0 && sale.refunds.length === 0" class="card mt-4 grid gap-3 p-5" @submit.prevent="cancellation.post('/sales/cancellations')">
            <h2 class="font-semibold">Cancel this sale</h2>
            <p class="text-sm text-slate-500">Cancellation restores stock and reverses the journal only after approval.</p>
            <input v-model="cancellation.transaction_date" class="field max-w-xs" type="date" required>
            <p v-if="cancellation.errors.transaction_date" class="error">{{ cancellation.errors.transaction_date }}</p>
            <input v-model="cancellation.reason" class="field" placeholder="Reason" required>
            <div class="flex justify-end"><button class="btn btn-secondary" type="submit">Submit cancellation</button></div>
        </form>
    </AppLayout>
</template>
