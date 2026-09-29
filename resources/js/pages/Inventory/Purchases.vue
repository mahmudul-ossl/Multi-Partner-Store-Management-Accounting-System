<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    purchases: { type: Object, required: true },
    suppliers: { type: Array, required: true },
    warehouses: { type: Array, required: true },
    products: { type: Array, required: true },
    accounts: { type: Array, required: true },
    methods: { type: Array, required: true },
    can: { type: Object, required: true },
});

const showForm = ref(false);
const form = useForm({
    supplier_id: props.suppliers[0]?.id || '',
    warehouse_id: props.warehouses[0]?.id || '',
    transaction_date: '',
    paid_amount: '0.00',
    payment_method: '',
    financial_account_id: '',
    note: '',
    items: [{ product_id: props.products[0]?.id || '', quantity: '1', unit_cost: '0.0000' }],
});

function addLine() {
    form.items.push({ product_id: props.products[0]?.id || '', quantity: '1', unit_cost: '0.0000' });
}

function submit() {
    form.post('/inventory/purchases', { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Purchases">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Purchases</h1>
                <p class="mt-1 text-sm text-slate-500">Stock and the inventory journal post only after the purchase is fully approved.</p>
            </div>
            <button v-if="can.create" class="btn btn-primary" type="button" @click="showForm = true">New purchase</button>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Reference</th>
                            <th class="px-4 py-3 font-semibold">Supplier</th>
                            <th class="px-4 py-3 font-semibold">Total</th>
                            <th class="px-4 py-3 font-semibold">Paid</th>
                            <th class="px-4 py-3 font-semibold">Due</th>
                            <th class="px-4 py-3 font-semibold">Approvals</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in purchases.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3">
                                <Link :href="`/inventory/purchases/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.reference }}</Link>
                                <p class="text-xs text-slate-500">{{ row.date }}</p>
                            </td>
                            <td class="px-4 py-3">{{ row.supplier }}</td>
                            <td class="px-4 py-3">{{ row.total }}</td>
                            <td class="px-4 py-3">{{ row.paid }}</td>
                            <td class="px-4 py-3">{{ row.due }}</td>
                            <td class="px-4 py-3">{{ row.approval || '—' }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                        </tr>
                        <tr v-if="purchases.data.length === 0">
                            <td class="px-4 py-8 text-center text-slate-500" colspan="7">No purchases yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="purchases" url="/inventory/purchases" />
        </section>

        <Modal :open="showForm" title="New purchase" @close="showForm = false">
            <form class="grid max-h-[70vh] gap-3 overflow-y-auto" @submit.prevent="submit">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="label">Supplier</label>
                        <select v-model="form.supplier_id" class="field" required>
                            <option v-for="row in suppliers" :key="row.id" :value="row.id">{{ row.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Warehouse</label>
                        <select v-model="form.warehouse_id" class="field" required>
                            <option v-for="row in warehouses" :key="row.id" :value="row.id">{{ row.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Date</label>
                        <input v-model="form.transaction_date" class="field" type="date" required>
                        <p v-if="form.errors.transaction_date" class="error">{{ form.errors.transaction_date }}</p>
                    </div>
                    <div>
                        <label class="label">Paid now</label>
                        <input v-model="form.paid_amount" class="field">
                    </div>
                    <div>
                        <label class="label">Method</label>
                        <select v-model="form.payment_method" class="field">
                            <option value="">None (fully due)</option>
                            <option v-for="row in methods" :key="row.value" :value="row.value">{{ row.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Pay from</label>
                        <select v-model="form.financial_account_id" class="field">
                            <option value="">None</option>
                            <option v-for="row in accounts" :key="row.id" :value="row.id">{{ row.name }}</option>
                        </select>
                    </div>
                </div>
                <div v-for="(line, index) in form.items" :key="index" class="grid gap-2 md:grid-cols-3">
                    <select v-model="line.product_id" class="field">
                        <option v-for="row in products" :key="row.id" :value="row.id">{{ row.sku }} · {{ row.name }}</option>
                    </select>
                    <input v-model="line.quantity" class="field" placeholder="Quantity">
                    <input v-model="line.unit_cost" class="field" placeholder="Unit cost">
                </div>
                <button class="btn btn-secondary" type="button" @click="addLine">Add line</button>
                <p v-if="Object.entries(form.errors).some(([key]) => key !== 'transaction_date')" class="text-sm text-rose-700">{{ Object.entries(form.errors).find(([key]) => key !== 'transaction_date')?.[1] }}</p>
                <div class="flex justify-end">
                    <button class="btn btn-primary" :disabled="form.processing" type="submit">Submit for approval</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
