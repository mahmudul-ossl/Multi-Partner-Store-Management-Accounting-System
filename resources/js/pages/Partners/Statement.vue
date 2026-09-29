<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    partner: { type: Object, required: true },
    lines: { type: Array, required: true },
    current_capital: { type: Object, required: true },
});
</script>

<template>
    <AppLayout title="Partner statement">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-teal-700">{{ partner.partner_code }}</p>
                <h1 class="text-2xl font-semibold tracking-tight">{{ partner.name }}</h1>
                <p class="mt-1 text-sm text-slate-500">Running balance is the capital ledger. Current capital {{ current_capital.formatted }}.</p>
            </div>
            <Link :href="`/partners/${partner.id}/dashboard`" class="btn btn-secondary">Dashboard</Link>
        </div>
        <section class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Description</th>
                            <th class="px-4 py-3 font-semibold">Debit</th>
                            <th class="px-4 py-3 font-semibold">Credit</th>
                            <th class="px-4 py-3 font-semibold">Running balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in lines" :key="`${line.reference}-${index}`" class="border-t border-slate-100">
                            <td class="px-4 py-3">{{ line.date }}</td>
                            <td class="px-4 py-3">{{ line.description }}</td>
                            <td class="px-4 py-3">{{ line.debit === '0.00' ? '—' : line.debit_formatted }}</td>
                            <td class="px-4 py-3">{{ line.credit === '0.00' ? '—' : line.credit_formatted }}</td>
                            <td class="px-4 py-3 font-medium">{{ line.running_balance_formatted }}</td>
                        </tr>
                        <tr v-if="!lines.length">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500">No ledger lines for this partner yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
