<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    report: { type: Object, required: true },
});

const asOf = ref(props.filters.as_of);

function applyFilters() {
    router.get('/accounting/reports/balance-sheet', { as_of: asOf.value }, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout title="Balance sheet">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Balance sheet</h1>
            <p class="mt-1 text-sm text-slate-500">Assets, liabilities, and equity as of a date. Current earnings stay in the income and expense accounts and are included here.</p>
        </div>

        <form class="card mt-6 flex flex-wrap items-end gap-3 p-4" @submit.prevent="applyFilters">
            <div>
                <label class="label" for="bs-date">As of</label>
                <input id="bs-date" v-model="asOf" class="field" type="date" required>
            </div>
            <button class="btn btn-secondary" type="submit">Show statement</button>
        </form>

        <p class="mt-4 text-sm" :class="report.balanced ? 'text-teal-800' : 'text-red-700'">
            {{ report.balanced ? 'Assets equal liabilities and equity.' : `Out of balance by ${report.difference.formatted}.` }}
        </p>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <section class="card overflow-hidden">
                <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Assets</h2>
                <table class="min-w-full text-left text-sm">
                    <tbody>
                        <tr v-for="row in report.assets.lines" :key="row.code" class="border-t border-slate-100">
                            <td class="px-4 py-3">{{ row.code }} {{ row.name }}</td>
                            <td class="px-4 py-3 text-right">{{ row.formatted }}</td>
                        </tr>
                        <tr class="border-t border-slate-200 font-semibold">
                            <td class="px-4 py-3">Total assets</td>
                            <td class="px-4 py-3 text-right">{{ report.assets.total.formatted }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="card overflow-hidden">
                <h2 class="border-b border-slate-100 px-4 py-3 font-semibold">Liabilities and equity</h2>
                <table class="min-w-full text-left text-sm">
                    <tbody>
                        <tr v-for="row in report.liabilities.lines" :key="row.code" class="border-t border-slate-100">
                            <td class="px-4 py-3">{{ row.code }} {{ row.name }}</td>
                            <td class="px-4 py-3 text-right">{{ row.formatted }}</td>
                        </tr>
                        <tr class="border-t border-slate-200 font-medium">
                            <td class="px-4 py-3">Total liabilities</td>
                            <td class="px-4 py-3 text-right">{{ report.liabilities.total.formatted }}</td>
                        </tr>
                        <tr v-for="row in report.equity.lines" :key="row.code" class="border-t border-slate-100">
                            <td class="px-4 py-3">{{ row.code }} {{ row.name }}</td>
                            <td class="px-4 py-3 text-right">{{ row.formatted }}</td>
                        </tr>
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-3">Current earnings</td>
                            <td class="px-4 py-3 text-right">{{ report.equity.current_earnings.formatted }}</td>
                        </tr>
                        <tr class="border-t border-slate-200 font-medium">
                            <td class="px-4 py-3">Total equity</td>
                            <td class="px-4 py-3 text-right">{{ report.equity.total.formatted }}</td>
                        </tr>
                        <tr class="border-t border-slate-200 bg-teal-50 font-semibold">
                            <td class="px-4 py-3">Liabilities + equity</td>
                            <td class="px-4 py-3 text-right">{{ report.liabilities_and_equity.formatted }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AppLayout>
</template>
