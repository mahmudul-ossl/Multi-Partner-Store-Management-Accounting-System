<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineProps({
    returns: { type: Array, required: true },
});
</script>

<template>
    <AppLayout title="Sales returns">
        <h1 class="text-2xl font-semibold tracking-tight">Sales returns</h1>
        <p class="mt-1 text-sm text-slate-500">A return puts stock back at the original sale cost and reverses revenue and COGS.</p>
        <section class="card mt-6 overflow-hidden">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Reference</th>
                        <th class="px-4 py-3 font-semibold">Sale</th>
                        <th class="px-4 py-3 font-semibold">Customer</th>
                        <th class="px-4 py-3 font-semibold">Total</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in returns" :key="row.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">
                            <Link :href="`/sales/returns/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.reference }}</Link>
                            <p class="text-xs text-slate-500">{{ row.date }}</p>
                        </td>
                        <td class="px-4 py-3">{{ row.sale }}</td>
                        <td class="px-4 py-3">{{ row.customer }}</td>
                        <td class="px-4 py-3">{{ row.total }}</td>
                        <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                    </tr>
                    <tr v-if="returns.length === 0">
                        <td class="px-4 py-8 text-center text-slate-500" colspan="5">No returns yet.</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
