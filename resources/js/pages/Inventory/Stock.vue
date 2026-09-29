<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    rows: { type: Array, required: true },
    inventory_value: { type: String, required: true },
    filters: { type: Object, required: true },
});
</script>

<template>
    <AppLayout title="Stock">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Stock report</h1>
                <p class="mt-1 text-sm text-slate-500">On-hand quantity is the sum of stock movements. Value uses the weighted-average cost.</p>
            </div>
            <p class="text-lg font-semibold">Inventory value {{ inventory_value }}</p>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Product</th>
                            <th class="px-4 py-3 font-semibold">On hand</th>
                            <th class="px-4 py-3 font-semibold">Average cost</th>
                            <th class="px-4 py-3 font-semibold">Value</th>
                            <th class="px-4 py-3 font-semibold">Reorder</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id" class="border-t border-slate-100" :class="row.low ? 'bg-amber-50' : ''">
                            <td class="px-4 py-3">
                                <Link :href="`/inventory/products/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.name }}</Link>
                                <p class="text-xs text-slate-500">{{ row.sku }} · {{ row.category }}</p>
                            </td>
                            <td class="px-4 py-3">{{ row.on_hand }}</td>
                            <td class="px-4 py-3">{{ row.average_cost }}</td>
                            <td class="px-4 py-3">{{ row.value }}</td>
                            <td class="px-4 py-3">{{ row.reorder_level }} <span v-if="row.low" class="text-xs font-semibold text-amber-800">low</span></td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td class="px-4 py-8 text-center text-slate-500" colspan="5">No products yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
