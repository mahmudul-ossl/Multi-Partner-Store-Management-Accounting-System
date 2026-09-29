<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    entries: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
});

const search = ref(props.filters.search);
const status = ref(props.filters.status);
const from = ref(props.filters.from);
const to = ref(props.filters.to);

function applyFilters() {
    router.get('/accounting/entries', {
        search: search.value,
        status: status.value,
        from: from.value,
        to: to.value,
    }, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout title="Journal">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Journal</h1>
            <p class="mt-1 text-sm text-slate-500">Posted entries stay in the ledger. Corrections are reversals.</p>
        </div>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-5" @submit.prevent="applyFilters">
            <div class="md:col-span-2">
                <label class="label" for="je-search">Search</label>
                <input id="je-search" v-model="search" class="field" placeholder="Reference or description">
            </div>
            <div>
                <label class="label" for="je-from">From</label>
                <input id="je-from" v-model="from" class="field" type="date">
            </div>
            <div>
                <label class="label" for="je-to">To</label>
                <input id="je-to" v-model="to" class="field" type="date">
            </div>
            <div>
                <label class="label" for="je-status">Status</label>
                <select id="je-status" v-model="status" class="field">
                    <option value="">All statuses</option>
                    <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
            <div class="md:col-span-5">
                <button class="btn btn-secondary" type="submit">Apply filters</button>
            </div>
        </form>

        <section class="card mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Reference</th>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Description</th>
                            <th class="px-4 py-3 font-semibold">Source</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in entries.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3 font-medium">
                                <Link :href="`/accounting/entries/${row.id}`" class="text-teal-800 hover:underline">{{ row.reference }}</Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ row.entry_date_formatted }}</td>
                            <td class="px-4 py-3">{{ row.description }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.source_label }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                        </tr>
                        <tr v-if="!entries.data.length">
                            <td colspan="5" class="px-4 py-10 text-center text-slate-500">No journal entries match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="entries" :filters="{ search, status, from, to }" url="/accounting/entries" />
        </section>
    </AppLayout>
</template>
