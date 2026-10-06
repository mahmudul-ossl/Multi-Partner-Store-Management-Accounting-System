<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    purchase: { type: Object, required: true },
    suppliers: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    accounts: { type: Array, default: () => [] },
    methods: { type: Array, default: () => [] },
    can: { type: Object, required: true },
});

const showEdit = ref(false);

const returnForm = useForm({
    purchase_id: props.purchase.id,
    transaction_date: '',
    note: '',
    items: props.purchase.items.map((item) => ({
        purchase_item_id: item.id,
        quantity: '0',
        product: item.product,
        available: item.quantity,
    })),
});

const editForm = useForm({
    supplier_id: props.purchase.supplier_id,
    transaction_date: props.purchase.transaction_date,
    paid_amount: props.purchase.paid_amount,
    payment_method: props.purchase.payment_method || '',
    financial_account_id: props.purchase.financial_account_id || '',
    note: props.purchase.note || '',
    items: props.purchase.items.map((item) => ({
        product_id: item.product_id,
        quantity: item.quantity,
        unit_cost: item.unit_cost,
    })),
});

function addLine() {
    editForm.items.push({
        product_id: props.products[0]?.id || '',
        quantity: '1',
        unit_cost: '0.0000',
    });
}

function submitReturn() {
    returnForm.transform((data) => ({
        purchase_id: data.purchase_id,
        transaction_date: data.transaction_date,
        note: data.note,
        items: data.items
            .filter((item) => Number(item.quantity) > 0)
            .map((item) => ({ purchase_item_id: item.purchase_item_id, quantity: item.quantity })),
    })).post('/inventory/returns');
}

function submitEdit() {
    editForm.put(`/inventory/purchases/${props.purchase.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showEdit.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :title="purchase.reference">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ purchase.reference }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ purchase.date }} · {{ purchase.supplier }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button v-if="can.update" class="btn btn-secondary" type="button" @click="showEdit = true">Edit purchase</button>
                <StatusBadge :label="purchase.status.label" :tone="purchase.status.tone" />
            </div>
        </div>

        <section class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Total</p><p class="mt-2 text-xl font-semibold">{{ purchase.total }}</p></div>
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Paid</p><p class="mt-2 text-xl font-semibold">{{ purchase.paid }}</p></div>
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Due</p><p class="mt-2 text-xl font-semibold">{{ purchase.due }}</p></div>
        </section>

        <p v-if="purchase.account" class="mt-4 text-sm text-slate-600">Paid from {{ purchase.account }}.</p>
        <p v-if="purchase.note" class="mt-2 text-sm text-slate-600">{{ purchase.note }}</p>
        <p v-if="purchase.approval" class="mt-2 text-sm text-slate-600">
            Approvals {{ purchase.approval.completed }}/{{ purchase.approval.required }}.
            <Link :href="`/approvals/${purchase.approval.id}`" class="text-teal-800 hover:underline">Open the request</Link>
        </p>
        <p v-if="purchase.journal_entry_id" class="mt-2 text-sm">
            <Link :href="`/accounting/entries/${purchase.journal_entry_id}`" class="text-teal-800 hover:underline">View journal</Link>
        </p>

        <section class="card mt-6 overflow-hidden">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Product</th>
                        <th class="px-4 py-3 font-semibold">Quantity</th>
                        <th class="px-4 py-3 font-semibold">Unit cost</th>
                        <th class="px-4 py-3 font-semibold">Line</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in purchase.items" :key="item.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ item.product }}</td>
                        <td class="px-4 py-3">{{ item.quantity }}</td>
                        <td class="px-4 py-3">{{ item.unit_cost }}</td>
                        <td class="px-4 py-3">{{ item.line_total }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <form v-if="can.return && purchase.approved" class="card mt-6 grid gap-3 p-5" @submit.prevent="submitReturn">
            <h2 class="font-semibold">Return goods</h2>
            <p class="text-sm text-slate-500">The return posts only after approval. It reverses purchase expense and the amount due to the supplier.</p>
            <div>
                <label class="label">Date</label>
                <input v-model="returnForm.transaction_date" class="field max-w-xs" type="date" required>
                <p v-if="returnForm.errors.transaction_date" class="error">{{ returnForm.errors.transaction_date }}</p>
            </div>
            <div v-for="line in returnForm.items" :key="line.purchase_item_id" class="grid items-end gap-2 md:grid-cols-3">
                <p class="text-sm">{{ line.product }} <span class="text-slate-500">(bought {{ line.available }})</span></p>
                <input v-model="line.quantity" class="field" placeholder="Quantity to return">
            </div>
            <div>
                <label class="label">Note</label>
                <input v-model="returnForm.note" class="field" placeholder="Optional">
            </div>
            <p v-if="Object.entries(returnForm.errors).some(([key]) => key !== 'transaction_date')" class="text-sm text-rose-700">{{ Object.entries(returnForm.errors).find(([key]) => key !== 'transaction_date')?.[1] }}</p>
            <div class="flex justify-end">
                <button class="btn btn-primary" :disabled="returnForm.processing" type="submit">Submit return</button>
            </div>
        </form>

        <Modal :open="showEdit" title="Edit purchase" @close="showEdit = false">
            <form class="grid max-h-[70vh] gap-3 overflow-y-auto" @submit.prevent="submitEdit">
                <p class="text-sm text-slate-500">Super Admin edits reverse and re-post the purchase journal.</p>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="label">Supplier</label>
                        <select v-model="editForm.supplier_id" class="field" required>
                            <option v-for="row in suppliers" :key="row.id" :value="row.id">{{ row.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Date</label>
                        <input v-model="editForm.transaction_date" class="field" type="date" required>
                        <p v-if="editForm.errors.transaction_date" class="error">{{ editForm.errors.transaction_date }}</p>
                    </div>
                    <div>
                        <label class="label">Paid now</label>
                        <input v-model="editForm.paid_amount" class="field">
                    </div>
                    <div>
                        <label class="label">Method</label>
                        <select v-model="editForm.payment_method" class="field">
                            <option value="">None (fully due)</option>
                            <option v-for="row in methods" :key="row.value" :value="row.value">{{ row.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Pay from</label>
                        <select v-model="editForm.financial_account_id" class="field">
                            <option value="">None</option>
                            <option v-for="row in accounts" :key="row.id" :value="row.id">{{ row.name }}</option>
                        </select>
                    </div>
                </div>
                <div v-for="(line, index) in editForm.items" :key="index" class="grid gap-2 md:grid-cols-3">
                    <select v-model="line.product_id" class="field">
                        <option v-for="row in products" :key="row.id" :value="row.id">{{ row.sku }} · {{ row.name }}</option>
                    </select>
                    <input v-model="line.quantity" class="field" placeholder="Quantity">
                    <input v-model="line.unit_cost" class="field" placeholder="Unit cost">
                </div>
                <button class="btn btn-secondary" type="button" @click="addLine">Add line</button>
                <div>
                    <label class="label">Note</label>
                    <input v-model="editForm.note" class="field" placeholder="Optional">
                </div>
                <p v-if="Object.entries(editForm.errors).some(([key]) => key !== 'transaction_date')" class="text-sm text-rose-700">{{ Object.entries(editForm.errors).find(([key]) => key !== 'transaction_date')?.[1] }}</p>
                <div class="flex justify-end gap-2">
                    <button class="btn btn-secondary" type="button" @click="showEdit = false">Cancel</button>
                    <button class="btn btn-primary" :disabled="editForm.processing" type="submit">Save changes</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
