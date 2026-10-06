<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    rows: { type: Array, required: true },
});
</script>

<template>
    <AppLayout title="Cash report">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Cash report</h1>
            <p class="mt-1 text-sm text-slate-500">Each cash account shows the cached balance beside the ledger total.</p>
        </div>

        <section class="card mt-6 overflow-hidden">
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
                    <tr v-for="row in rows" :key="row.id" class="border-t border-slate-100">
                        <td class="px-4 py-3 font-medium">
                            <Link :href="`/accounting/accounts/${row.id}`" class="text-teal-800 hover:underline">{{ row.name }}</Link>
                        </td>
                        <td class="px-4 py-3">{{ row.cached_balance.formatted }}</td>
                        <td class="px-4 py-3">{{ row.ledger_balance.formatted }}</td>
                        <td class="px-4 py-3">{{ row.reconciled ? 'Yes' : 'Differs' }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="4" class="px-4 py-10 text-center text-slate-500">No cash accounts yet.</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
