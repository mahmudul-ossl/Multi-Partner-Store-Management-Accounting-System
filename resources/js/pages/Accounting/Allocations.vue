<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    allocations: { type: Object, required: true },
    methods: { type: Array, required: true },
    partners: { type: Array, required: true },
    closed_through: { type: String, default: null },
    can: { type: Object, required: true },
});

const showForm = ref(false);
const form = useForm({
    amount: '',
    transaction_date: '',
    method: props.methods[0]?.value || 'ownership',
    note: '',
    lines: props.partners.map((partner) => ({ partner_id: partner.id, percentage: '' })),
});

function submit() {
    form.transform((data) => ({
        amount: data.amount,
        transaction_date: data.transaction_date,
        method: data.method,
        note: data.note || null,
        lines: data.method === 'custom'
            ? data.lines.filter((line) => String(line.percentage).trim() !== '')
            : [],
    })).post('/accounting/allocations', {
        preserveScroll: true,
        onSuccess: () => { showForm.value = false; },
    });
}
</script>

<template>
    <AppLayout title="Profit allocations">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Profit allocations</h1>
                <p class="mt-1 text-sm text-slate-500">Debit retained earnings and credit each partner’s capital after approval. Custom percentages must total 100%.</p>
                <p v-if="closed_through" class="mt-1 text-sm text-slate-500">Books are closed through {{ closed_through }}.</p>
            </div>
            <button v-if="can.create" class="btn btn-primary" type="button" @click="showForm = true">New allocation</button>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Reference</th>
                            <th class="px-4 py-3 font-semibold">Method</th>
                            <th class="px-4 py-3 font-semibold">Amount</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in allocations.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3">
                                <Link :href="`/accounting/allocations/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.reference }}</Link>
                                <p class="text-xs text-slate-500">{{ row.date }}</p>
                            </td>
                            <td class="px-4 py-3">{{ row.method }}</td>
                            <td class="px-4 py-3">{{ row.amount }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                        </tr>
                        <tr v-if="allocations.data.length === 0">
                            <td class="px-4 py-8 text-center text-slate-500" colspan="4">No allocations yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="allocations" url="/accounting/allocations" />
        </section>

        <Modal :open="showForm" title="New profit allocation" @close="showForm = false">
            <form class="grid gap-3" @submit.prevent="submit">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="label">Amount</label>
                        <input v-model="form.amount" class="field" required>
                    </div>
                    <div>
                        <label class="label">Date</label>
                        <input v-model="form.transaction_date" class="field" type="date" required>
                        <p v-if="form.errors.transaction_date" class="error">{{ form.errors.transaction_date }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="label">Method</label>
                        <select v-model="form.method" class="field">
                            <option v-for="row in methods" :key="row.value" :value="row.value">{{ row.label }}</option>
                        </select>
                    </div>
                </div>
                <div v-if="form.method === 'custom'" class="grid gap-2">
                    <p class="text-sm text-slate-500">Enter a percentage for each partner who shares in this allocation. The percentages must total 100.</p>
                    <div v-for="(line, index) in form.lines" :key="line.partner_id" class="grid grid-cols-2 gap-2">
                        <p class="self-center text-sm">{{ partners[index]?.name }}</p>
                        <input v-model="form.lines[index].percentage" class="field" placeholder="0.0000">
                    </div>
                </div>
                <div>
                    <label class="label">Note</label>
                    <textarea v-model="form.note" class="field" rows="2"></textarea>
                </div>
                <p v-if="form.errors.amount || form.errors.method || form.errors.lines" class="text-sm text-red-700">{{ form.errors.amount || form.errors.method || form.errors.lines }}</p>
                <button class="btn btn-primary" type="submit" :disabled="form.processing">Submit for approval</button>
            </form>
        </Modal>
    </AppLayout>
</template>
