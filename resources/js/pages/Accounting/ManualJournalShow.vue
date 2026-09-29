<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    journal: { type: Object, required: true },
    charts: { type: Array, required: true },
    financialAccounts: { type: Array, required: true },
    partners: { type: Array, required: true },
    can: { type: Object, required: true },
});

const form = useForm({
    entry_date: props.journal.entry_date,
    description: props.journal.description,
    lines: props.journal.lines.map((line) => ({
        account_code: line.account_code,
        partner_id: line.partner_id || '',
        financial_account_id: line.financial_account_id || '',
        debit: line.debit.amount,
        credit: line.credit.amount,
        description: line.description || '',
    })),
});

function accountsFor(code) {
    return props.financialAccounts.filter((account) => account.chart_code === code);
}

function save() {
    form.put(`/accounting/manual-journals/${props.journal.id}`, { preserveScroll: true });
}

function cancel() {
    router.post(`/accounting/manual-journals/${props.journal.id}/cancel`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="journal.reference">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm text-slate-500">{{ journal.entry_date_formatted }} · {{ journal.author }}</p>
                <h1 class="text-2xl font-semibold tracking-tight">{{ journal.reference }}</h1>
                <p class="mt-1 text-slate-600">{{ journal.description }}</p>
            </div>
            <StatusBadge :label="journal.status.label" :tone="journal.status.tone" />
        </div>

        <p v-if="journal.approval" class="mt-4 text-sm text-slate-600">
            Approvals {{ journal.approval.completed_approvals }}/{{ journal.approval.required_approvals }}.
            <Link :href="`/approvals/${journal.approval.id}`" class="font-medium text-teal-800 hover:underline">Open request</Link>
        </p>
        <p v-if="journal.journal_entry_id" class="mt-2 text-sm">
            <Link :href="`/accounting/entries/${journal.journal_entry_id}`" class="font-medium text-teal-800 hover:underline">Posted journal</Link>
        </p>

        <section class="card mt-6 overflow-hidden">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Account</th>
                        <th class="px-4 py-3 font-semibold">Financial account</th>
                        <th class="px-4 py-3 font-semibold">Debit</th>
                        <th class="px-4 py-3 font-semibold">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="line in journal.lines" :key="line.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ line.account_code }} {{ line.account_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ line.financial_account || line.partner || '—' }}</td>
                        <td class="px-4 py-3">{{ line.debit.formatted }}</td>
                        <td class="px-4 py-3">{{ line.credit.formatted }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <form v-if="can.update" class="card mt-6 grid gap-4 p-4" @submit.prevent="save">
            <h2 class="text-sm font-semibold">Edit while it is still pending</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <input v-model="form.entry_date" class="field" type="date" required>
                <p v-if="form.errors.entry_date" class="error">{{ form.errors.entry_date }}</p>
                <input v-model="form.description" class="field" required>
            </div>
            <div v-for="(line, index) in form.lines" :key="index" class="grid gap-3 sm:grid-cols-2">
                <select v-model="line.account_code" class="field" required>
                    <option v-for="chart in charts" :key="chart.code" :value="chart.code">{{ chart.name }}</option>
                </select>
                <select v-if="accountsFor(line.account_code).length" v-model="line.financial_account_id" class="field">
                    <option value="">Financial account</option>
                    <option v-for="account in accountsFor(line.account_code)" :key="account.id" :value="account.id">{{ account.name }}</option>
                </select>
                <input v-model="line.debit" class="field" placeholder="Debit">
                <input v-model="line.credit" class="field" placeholder="Credit">
            </div>
            <div class="flex gap-3">
                <button class="btn btn-primary" type="submit" :disabled="form.processing">Save changes</button>
                <button v-if="can.cancel" class="btn btn-secondary" type="button" @click="cancel">Cancel journal</button>
            </div>
        </form>
        <button v-else-if="can.cancel" class="btn btn-secondary mt-6" type="button" @click="cancel">Cancel journal</button>
    </AppLayout>
</template>
