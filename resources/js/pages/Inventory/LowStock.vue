<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    rows: { type: Array, required: true },
});
</script>

<template>
    <AppLayout title="Low stock">
        <h1 class="text-2xl font-semibold tracking-tight">Low stock</h1>
        <p class="mt-1 text-sm text-slate-500">Products whose on-hand quantity is at or below the reorder level.</p>

        <section class="card mt-6 overflow-hidden">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Product</th>
                        <th class="px-4 py-3 font-semibold">On hand</th>
                        <th class="px-4 py-3 font-semibold">Reorder level</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id" class="border-t border-slate-100 bg-amber-50">
                        <td class="px-4 py-3">
                            <Link :href="`/inventory/products/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.name }}</Link>
                            <p class="text-xs text-slate-500">{{ row.sku }}</p>
                        </td>
                        <td class="px-4 py-3 font-semibold">{{ row.on_hand }}</td>
                        <td class="px-4 py-3">{{ row.reorder_level }}</td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td class="px-4 py-8 text-center text-slate-500" colspan="3">Nothing is at or below its reorder level.</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
