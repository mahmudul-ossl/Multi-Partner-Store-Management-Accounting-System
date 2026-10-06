<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    promotion: { type: Object, required: true },
    partners: { type: Array, required: true },
    accounts: { type: Array, required: true },
    methods: { type: Array, required: true },
    funding: { type: Array, required: true },
    can: { type: Object, required: true },
});

const form = useForm({
    partner_id: props.partners[0]?.id || '',
    funded_by: 'partner',
    amount: '',
    transaction_date: '',
    payment_method: '',
    financial_account_id: '',
    note: '',
});

function submit() {
    form.transform((data) => ({
        ...data,
        payment_method: data.funded_by === 'business' ? data.payment_method : null,
        financial_account_id: data.funded_by === 'business' ? data.financial_account_id : null,
    })).post(`/promotions/${props.promotion.id}/contributions`);
}
</script>

<template>
    <AppLayout :title="promotion.name">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ promotion.name }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ promotion.platform }} · {{ promotion.starts }} – {{ promotion.ends }}</p>
            </div>
            <StatusBadge :label="promotion.status.label" :tone="promotion.status.tone" />
        </div>

        <section class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Budget</p><p class="mt-2 text-lg font-semibold">{{ promotion.budget }}</p></div>
            <div class="card p-4"><p class="text-xs uppercase text-slate-500">Actual (approved)</p><p class="mt-2 text-lg font-semibold">{{ promotion.actual }}</p></div>
        </section>
        <p v-if="promotion.description" class="mt-4 text-sm text-slate-600">{{ promotion.description }}</p>

        <section class="card mt-6 overflow-hidden">
            <h2 class="px-4 py-3 font-semibold">Contributions</h2>
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Reference</th>
                        <th class="px-4 py-3 font-semibold">Partner</th>
                        <th class="px-4 py-3 font-semibold">Paid by</th>
                        <th class="px-4 py-3 font-semibold">Amount</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in promotion.contributions" :key="row.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ row.reference }}</p>
                            <p class="text-xs text-slate-500">{{ row.date }}</p>
                        </td>
                        <td class="px-4 py-3">{{ row.partner }}</td>
                        <td class="px-4 py-3">{{ row.funded_by }}<p v-if="row.account" class="text-xs text-slate-500">{{ row.account }}</p></td>
                        <td class="px-4 py-3">{{ row.amount }}</td>
                        <td class="px-4 py-3">
                            <StatusBadge :label="row.status.label" :tone="row.status.tone" />
                            <p v-if="row.approval" class="mt-1 text-xs text-slate-500">
                                Approvals {{ row.approval }}
                                <Link v-if="row.approval_id" :href="`/approvals/${row.approval_id}`" class="text-teal-800 hover:underline">Open</Link>
                            </p>
                            <p v-if="row.journal_entry_id" class="mt-1 text-xs">
                                <Link :href="`/accounting/entries/${row.journal_entry_id}`" class="text-teal-800 hover:underline">Journal</Link>
                            </p>
                        </td>
                    </tr>
                    <tr v-if="promotion.contributions.length === 0">
                        <td class="px-4 py-8 text-center text-slate-500" colspan="5">No contributions yet.</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <form v-if="can.contribute && promotion.open" class="card mt-4 grid gap-3 p-5" @submit.prevent="submit">
            <h2 class="font-semibold">Add a contribution</h2>
            <p class="text-sm text-slate-500">Partner-paid credits that partner’s capital. Business-paid credits cash or bank. Nothing posts until someone else approves it.</p>
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="form.partner_id" class="field" required>
                    <option v-for="row in partners" :key="row.id" :value="row.id">{{ row.name }}</option>
                </select>
                <select v-model="form.funded_by" class="field">
                    <option v-for="row in funding" :key="row.value" :value="row.value">{{ row.label }}</option>
                </select>
                <input v-model="form.amount" class="field" placeholder="Amount" required>
                <input v-model="form.transaction_date" class="field" type="date" required>
                <p v-if="form.errors.transaction_date" class="error">{{ form.errors.transaction_date }}</p>
                <select v-model="form.payment_method" class="field">
                    <option value="">No business account</option>
                    <option v-for="row in methods" :key="row.value" :value="row.value">{{ row.label }}</option>
                </select>
                <select v-model="form.financial_account_id" class="field">
                    <option value="">No business account</option>
                    <option v-for="row in accounts" :key="row.id" :value="row.id">{{ row.name }}</option>
                </select>
            </div>
            <input v-model="form.note" class="field" placeholder="Note">
            <p v-if="Object.entries(form.errors).some(([key]) => key !== 'transaction_date')" class="text-sm text-rose-700">{{ Object.entries(form.errors).find(([key]) => key !== 'transaction_date')?.[1] }}</p>
            <div class="flex justify-end"><button class="btn btn-primary" :disabled="form.processing" type="submit">Submit for approval</button></div>
        </form>
    </AppLayout>
</template>
