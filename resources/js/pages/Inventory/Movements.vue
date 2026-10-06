<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Pagination from '../../Components/Pagination.vue';

const props = defineProps({
    movements: { type: Object, required: true },
    filters: { type: Object, required: true },
    products: { type: Array, required: true },
    types: { type: Array, required: true },
});

const productId = ref(props.filters.product_id);
const type = ref(props.filters.type);
const from = ref(props.filters.from);
const to = ref(props.filters.to);

function applyFilters() {
    router.get('/inventory/stock/movements', {
        product_id: productId.value,
        type: type.value,
        from: from.value,
        to: to.value,
    }, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout title="Stock movements">
        <h1 class="text-2xl font-semibold tracking-tight">Stock movement report</h1>
        <p class="mt-1 text-sm text-slate-500">Every quantity change is a row. Nothing else writes stock.</p>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-5" @submit.prevent="applyFilters">
            <div>
                <label class="label">Product</label>
                <select v-model="productId" class="field">
                    <option value="">All</option>
                    <option v-for="row in products" :key="row.id" :value="row.id">{{ row.name }}</option>
                </select>
            </div>
            <div>
                <label class="label">Type</label>
                <select v-model="type" class="field">
                    <option value="">All</option>
                    <option v-for="row in types" :key="row.value" :value="row.value">{{ row.label }}</option>
                </select>
            </div>
            <div>
                <label class="label">From</label>
                <input v-model="from" class="field" type="date">
            </div>
            <div>
                <label class="label">To</label>
                <input v-model="to" class="field" type="date">
            </div>
            <div class="flex items-end">
                <button class="btn btn-secondary" type="submit">Apply</button>
            </div>
        </form>

        <section class="card mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Product</th>
                            <th class="px-4 py-3 font-semibold">Warehouse</th>
                            <th class="px-4 py-3 font-semibold">Type</th>
                            <th class="px-4 py-3 font-semibold">Quantity</th>
                            <th class="px-4 py-3 font-semibold">Unit cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in movements.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3">{{ row.date }}</td>
                            <td class="px-4 py-3">{{ row.product }} <span class="text-xs text-slate-500">{{ row.sku }}</span></td>
                            <td class="px-4 py-3">{{ row.warehouse }}</td>
                            <td class="px-4 py-3">{{ row.type }}</td>
                            <td class="px-4 py-3 font-mono">{{ row.quantity }}</td>
                            <td class="px-4 py-3 font-mono">{{ row.unit_cost }}</td>
                        </tr>
                        <tr v-if="movements.data.length === 0">
                            <td class="px-4 py-8 text-center text-slate-500" colspan="6">No movements yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="movements" :filters="filters" url="/inventory/stock/movements" />
        </section>
    </AppLayout>
</template>
