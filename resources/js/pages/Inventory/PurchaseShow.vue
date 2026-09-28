<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    purchase: { type: Object, required: true },
    can: { type: Object, required: true },
});

const form = useForm({
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

function submit() {
    form.transform((data) => ({
        purchase_id: data.purchase_id,
        transaction_date: data.transaction_date,
        note: data.note,
        items: data.items
            .filter((item) => Number(item.quantity) > 0)
            .map((item) => ({ purchase_item_id: item.purchase_item_id, quantity: item.quantity })),
    })).post('/inventory/returns');
}
</script>

<template>
    <AppLayout :title="purchase.reference">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ purchase.reference }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ purchase.date }} · {{ purchase.supplier }} · {{ purchase.warehouse }}</p>
            </div>
            <StatusBadge :label="purchase.status.label" :tone="purchase.status.tone" />
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

        <form v-if="can.return && purchase.approved" class="card mt-6 grid gap-3 p-5" @submit.prevent="submit">
            <h2 class="font-semibold">Return goods</h2>
            <p class="text-sm text-slate-500">The return posts only after approval. Stock leaves at the original purchase cost.</p>
            <div>
                <label class="label">Date</label>
                <input v-model="form.transaction_date" class="field max-w-xs" type="date" required>
            </div>
            <div v-for="line in form.items" :key="line.purchase_item_id" class="grid items-end gap-2 md:grid-cols-3">
                <p class="text-sm">{{ line.product }} <span class="text-slate-500">(bought {{ line.available }})</span></p>
                <input v-model="line.quantity" class="field" placeholder="Quantity to return">
            </div>
            <div>
                <label class="label">Note</label>
                <input v-model="form.note" class="field" placeholder="Optional">
            </div>
            <p v-if="Object.keys(form.errors).length" class="text-sm text-rose-700">{{ Object.values(form.errors)[0] }}</p>
            <div class="flex justify-end">
                <button class="btn btn-primary" :disabled="form.processing" type="submit">Submit return</button>
            </div>
        </form>
    </AppLayout>
</template>
