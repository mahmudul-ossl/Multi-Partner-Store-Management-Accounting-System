<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineProps({
    saleReturn: { type: Object, required: true },
});
</script>

<template>
    <AppLayout :title="saleReturn.reference">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ saleReturn.reference }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ saleReturn.date }} · {{ saleReturn.customer }} ·
                    <Link :href="`/sales/orders/${saleReturn.sale_id}`" class="text-teal-800 hover:underline">{{ saleReturn.sale }}</Link>
                </p>
            </div>
            <StatusBadge :label="saleReturn.status.label" :tone="saleReturn.status.tone" />
        </div>
        <p class="mt-4 text-lg font-semibold">Revenue reversed {{ saleReturn.total }} · COGS reversed {{ saleReturn.cogs }}</p>
        <p v-if="saleReturn.journal_entry_id" class="mt-2 text-sm">
            <Link :href="`/accounting/entries/${saleReturn.journal_entry_id}`" class="text-teal-800 hover:underline">View journal</Link>
        </p>
        <section class="card mt-6 overflow-hidden">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Product</th>
                        <th class="px-4 py-3 font-semibold">Quantity</th>
                        <th class="px-4 py-3 font-semibold">Unit cost</th>
                        <th class="px-4 py-3 font-semibold">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in saleReturn.items" :key="index" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ item.product }}</td>
                        <td class="px-4 py-3">{{ item.quantity }}</td>
                        <td class="px-4 py-3">{{ item.unit_cost }}</td>
                        <td class="px-4 py-3">{{ item.line_total }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
