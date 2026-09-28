<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

defineProps({
    customer: { type: Object, required: true },
    sales: { type: Array, required: true },
});
</script>

<template>
    <AppLayout :title="customer.name">
        <h1 class="text-2xl font-semibold tracking-tight">{{ customer.name }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ customer.phone }} · {{ customer.address }}</p>
        <p v-if="customer.notes" class="mt-2 text-sm text-slate-600">{{ customer.notes }}</p>

        <section class="card mt-6 overflow-hidden">
            <h2 class="px-4 py-3 font-semibold">Sales</h2>
            <table class="min-w-full text-left text-sm">
                <tbody>
                    <tr v-for="row in sales" :key="row.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">
                            <Link :href="`/sales/orders/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.reference }}</Link>
                        </td>
                        <td class="px-4 py-3">{{ row.date }}</td>
                        <td class="px-4 py-3">{{ row.total }}</td>
                        <td class="px-4 py-3">Due {{ row.due }}</td>
                        <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                    </tr>
                    <tr v-if="sales.length === 0">
                        <td class="px-4 py-6 text-slate-500">No sales yet.</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
