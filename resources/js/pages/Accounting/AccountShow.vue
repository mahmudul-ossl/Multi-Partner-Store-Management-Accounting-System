<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    account: { type: Object, required: true },
    lines: { type: Array, required: true },
    can: { type: Object, required: true },
});

const form = useForm({
    name: props.account.name,
    is_active: props.account.is_active,
});

function save() {
    form.put(`/accounting/accounts/${props.account.id}`, { preserveScroll: true });
}

function reconcile() {
    router.post(`/accounting/accounts/${props.account.id}/reconcile`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="account.name">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm text-slate-500">{{ account.type.label }} · {{ account.chart?.code }} {{ account.chart?.name }}</p>
                <h1 class="text-2xl font-semibold tracking-tight">{{ account.name }}</h1>
            </div>
            <Link href="/accounting/accounts" class="text-sm font-medium text-teal-800 hover:underline">All accounts</Link>
        </div>

        <section class="mt-6 grid gap-4 sm:grid-cols-3">
            <article class="card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Opening balance</p>
                <p class="mt-2 text-xl font-semibold">{{ account.opening_balance.formatted }}</p>
            </article>
            <article class="card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Cached balance</p>
                <p class="mt-2 text-xl font-semibold">{{ account.current_balance.formatted }}</p>
            </article>
            <article class="card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Ledger balance</p>
                <p class="mt-2 text-xl font-semibold">{{ account.ledger_balance.formatted }}</p>
                <p class="mt-1 text-sm" :class="account.reconciled ? 'text-teal-700' : 'text-amber-700'">{{ account.reconciled ? 'Matches the ledger' : 'Differs from the ledger' }}</p>
            </article>
        </section>

        <section v-if="can.update" class="card mt-6 p-4">
            <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="save">
                <div class="sm:col-span-2">
                    <label class="label" for="account-name">Name</label>
                    <input id="account-name" v-model="form.name" class="field" required>
                </div>
                <label v-if="!account.is_system" class="flex items-end gap-2 pb-2 text-sm">
                    <input v-model="form.is_active" type="checkbox"> Active
                </label>
                <div class="sm:col-span-3 flex gap-3">
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">Save</button>
                    <button v-if="can.reconcile" class="btn btn-secondary" type="button" @click="reconcile">Reconcile from ledger</button>
                </div>
            </form>
        </section>

        <section class="card mt-6 overflow-hidden">
            <h2 class="border-b border-slate-100 px-4 py-3 text-sm font-semibold">Recent ledger lines</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Reference</th>
                            <th class="px-4 py-3 font-semibold">Description</th>
                            <th class="px-4 py-3 font-semibold">Debit</th>
                            <th class="px-4 py-3 font-semibold">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in lines" :key="line.id" class="border-t border-slate-100">
                            <td class="px-4 py-3 text-slate-600">{{ line.date }}</td>
                            <td class="px-4 py-3">
                                <Link :href="`/accounting/entries/${line.journal_id}`" class="text-teal-800 hover:underline">{{ line.reference }}</Link>
                            </td>
                            <td class="px-4 py-3">{{ line.description }}</td>
                            <td class="px-4 py-3">{{ line.debit.formatted }}</td>
                            <td class="px-4 py-3">{{ line.credit.formatted }}</td>
                        </tr>
                        <tr v-if="!lines.length">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500">No ledger lines yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
