<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    adjustments: { type: Array, required: true },
    products: { type: Array, required: true },
    warehouses: { type: Array, required: true },
    kinds: { type: Array, required: true },
    can: { type: Object, required: true },
});

const showForm = ref(false);
const form = useForm({
    kind: 'opening',
    product_id: props.products[0]?.id || '',
    warehouse_id: props.warehouses[0]?.id || '',
    quantity: '1',
    direction: 'increase',
    unit_cost: '',
    transaction_date: '',
    reason: '',
});

function submit() {
    form.post('/inventory/adjustments', { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Stock adjustments">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Stock adjustments</h1>
                <p class="mt-1 text-sm text-slate-500">Opening stock, adjustments, and damage change quantity only after final approval.</p>
            </div>
            <button v-if="can.create" class="btn btn-primary" type="button" @click="showForm = true">New document</button>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Reference</th>
                            <th class="px-4 py-3 font-semibold">Kind</th>
                            <th class="px-4 py-3 font-semibold">Product</th>
                            <th class="px-4 py-3 font-semibold">Quantity</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in adjustments" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ row.reference }}</p>
                                <p class="text-xs text-slate-500">{{ row.date }} · {{ row.warehouse }}</p>
                            </td>
                            <td class="px-4 py-3">{{ row.kind }}</td>
                            <td class="px-4 py-3">{{ row.product }}</td>
                            <td class="px-4 py-3 font-mono">{{ row.quantity }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                        </tr>
                        <tr v-if="adjustments.length === 0">
                            <td class="px-4 py-8 text-center text-slate-500" colspan="5">No stock documents yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Modal :open="showForm" title="Stock document" @close="showForm = false">
            <form class="grid gap-3" @submit.prevent="submit">
                <div>
                    <label class="label">Kind</label>
                    <select v-model="form.kind" class="field">
                        <option v-for="row in kinds" :key="row.value" :value="row.value">{{ row.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Product</label>
                    <select v-model="form.product_id" class="field">
                        <option v-for="row in products" :key="row.id" :value="row.id">{{ row.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Warehouse</label>
                    <select v-model="form.warehouse_id" class="field">
                        <option v-for="row in warehouses" :key="row.id" :value="row.id">{{ row.name }}</option>
                    </select>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="label">Quantity</label>
                        <input v-model="form.quantity" class="field" required>
                    </div>
                    <div v-if="form.kind === 'adjustment'">
                        <label class="label">Direction</label>
                        <select v-model="form.direction" class="field">
                            <option value="increase">Increase</option>
                            <option value="decrease">Decrease</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label">Unit cost</label>
                    <input v-model="form.unit_cost" class="field" placeholder="Required when stock increases">
                </div>
                <div>
                    <label class="label">Date</label>
                    <input v-model="form.transaction_date" class="field" type="date" required>
                </div>
                <div>
                    <label class="label">Reason</label>
                    <textarea v-model="form.reason" class="field" rows="2" required />
                </div>
                <p v-if="Object.keys(form.errors).length" class="text-sm text-rose-700">{{ Object.values(form.errors)[0] }}</p>
                <div class="flex justify-end">
                    <button class="btn btn-primary" :disabled="form.processing" type="submit">Submit for approval</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
