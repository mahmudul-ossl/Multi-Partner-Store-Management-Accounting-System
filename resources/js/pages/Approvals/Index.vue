<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    approvals: { type: Object, required: true },
    filters: { type: Object, required: true },
    types: { type: Array, required: true },
});

const search = ref(props.filters.search);
const type = ref(props.filters.type);
const sort = ref(props.filters.sort);
const direction = ref(props.filters.direction);

function applyFilters() {
    router.get('/approvals', { search: search.value, type: type.value, sort: sort.value, direction: direction.value }, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout title="Approvals">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Approvals</h1>
            <p class="mt-1 text-sm text-slate-500">Requests you can approve. Your own requests stay on the partner dashboard.</p>
        </div>
        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
            <div class="md:col-span-2">
                <label class="label" for="approval-search">Search</label>
                <input id="approval-search" v-model="search" class="field" placeholder="Requester or note">
            </div>
            <div>
                <label class="label" for="approval-type">Type</label>
                <select id="approval-type" v-model="type" class="field">
                    <option value="">All types</option>
                    <option v-for="item in types" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
            <div class="flex items-end"><button class="btn btn-secondary" type="submit">Apply filters</button></div>
        </form>
        <section class="card mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Type</th>
                            <th class="px-4 py-3 font-semibold">Partner</th>
                            <th class="px-4 py-3 font-semibold">Amount</th>
                            <th class="px-4 py-3 font-semibold">Progress</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in approvals.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3">{{ row.type.label }}</td>
                            <td class="px-4 py-3">{{ row.partner_label }}</td>
                            <td class="px-4 py-3 font-medium">{{ row.amount_formatted }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.completed_approvals }}/{{ row.required_approvals }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`/approvals/${row.id}`" class="font-semibold text-teal-800 hover:underline">View</Link>
                            </td>
                        </tr>
                        <tr v-if="!approvals.data.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">Nothing is waiting for your approval.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="approvals" :filters="{ search, type, sort, direction }" url="/approvals" />
        </section>
    </AppLayout>
</template>
