<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    report: { type: Object, required: true },
});
</script>

<template>
    <AppLayout title="Bank report">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Bank report</h1>
            <p class="mt-1 text-sm text-slate-500">Bank accounts first, then mobile wallets such as bKash and Nagad.</p>
        </div>

        <section v-for="section in [{ title: 'Banks', rows: report.banks }, { title: 'Mobile wallets', rows: report.wallets }]" :key="section.title" class="card mt-6 overflow-hidden">
            <h2 class="border-b border-slate-100 px-4 py-3 text-sm font-semibold">{{ section.title }}</h2>
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Account</th>
                        <th class="px-4 py-3 font-semibold">Cached</th>
                        <th class="px-4 py-3 font-semibold">Ledger</th>
                        <th class="px-4 py-3 font-semibold">Match</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in section.rows" :key="row.id" class="border-t border-slate-100">
                        <td class="px-4 py-3 font-medium">
                            <Link :href="`/accounting/accounts/${row.id}`" class="text-teal-800 hover:underline">{{ row.name }}</Link>
                        </td>
                        <td class="px-4 py-3">{{ row.cached_balance.formatted }}</td>
                        <td class="px-4 py-3">{{ row.ledger_balance.formatted }}</td>
                        <td class="px-4 py-3">{{ row.reconciled ? 'Yes' : 'Differs' }}</td>
                    </tr>
                    <tr v-if="!section.rows.length">
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">None yet.</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
