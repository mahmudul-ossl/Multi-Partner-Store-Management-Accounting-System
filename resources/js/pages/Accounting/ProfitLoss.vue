<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    filters: { type: Object, required: true },
    periods: { type: Array, required: true },
    report: { type: Object, required: true },
});

const period = ref(props.filters.period);
const from = ref(props.filters.from);
const to = ref(props.filters.to);

function applyFilters() {
    router.get('/accounting/reports/profit-loss', {
        period: period.value,
        from: from.value,
        to: to.value,
    }, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout title="Profit and loss">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Profit and loss</h1>
            <p class="mt-1 text-sm text-slate-500">Revenue, cost of goods, and expenses come from the ledger. Weeks start on Monday.</p>
        </div>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
            <div>
                <label class="label" for="pl-period">Period</label>
                <select id="pl-period" v-model="period" class="field">
                    <option v-for="item in periods" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
            <div>
                <label class="label" for="pl-from">From</label>
                <input id="pl-from" v-model="from" class="field" type="date" :required="period === 'custom'">
            </div>
            <div>
                <label class="label" for="pl-to">To</label>
                <input id="pl-to" v-model="to" class="field" type="date" :required="period === 'custom'">
            </div>
            <div class="flex items-end">
                <button class="btn btn-secondary" type="submit">Show statement</button>
            </div>
        </form>

        <p class="mt-4 text-sm text-slate-500">{{ report.from_formatted }} – {{ report.to_formatted }}</p>

        <section class="card mt-4 overflow-hidden">
            <table class="min-w-full text-left text-sm">
                <tbody>
                    <tr class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3 font-semibold" colspan="2">Revenue</th>
                    </tr>
                    <tr v-for="row in report.revenue" :key="row.code" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ row.code }} {{ row.name }}</td>
                        <td class="px-4 py-3 text-right">{{ row.formatted }}</td>
                    </tr>
                    <tr class="border-t border-slate-200 font-medium">
                        <td class="px-4 py-3">Total revenue</td>
                        <td class="px-4 py-3 text-right">{{ report.total_revenue.formatted }}</td>
                    </tr>
                    <tr class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3 font-semibold" colspan="2">Cost of goods sold</th>
                    </tr>
                    <tr v-for="row in report.cogs" :key="row.code" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ row.code }} {{ row.name }}</td>
                        <td class="px-4 py-3 text-right">{{ row.formatted }}</td>
                    </tr>
                    <tr class="border-t border-slate-200 font-medium">
                        <td class="px-4 py-3">Total COGS</td>
                        <td class="px-4 py-3 text-right">{{ report.total_cogs.formatted }}</td>
                    </tr>
                    <tr class="border-t border-slate-200 bg-teal-50 font-semibold">
                        <td class="px-4 py-3">Gross profit</td>
                        <td class="px-4 py-3 text-right">{{ report.gross_profit.formatted }}</td>
                    </tr>
                    <tr class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3 font-semibold" colspan="2">Expenses</th>
                    </tr>
                    <tr v-for="row in report.expenses" :key="row.code" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ row.code }} {{ row.name }}</td>
                        <td class="px-4 py-3 text-right">{{ row.formatted }}</td>
                    </tr>
                    <tr v-if="!report.expenses.length">
                        <td class="px-4 py-3 text-slate-500" colspan="2">No expenses in this period.</td>
                    </tr>
                    <tr class="border-t border-slate-200 font-medium">
                        <td class="px-4 py-3">Total expenses</td>
                        <td class="px-4 py-3 text-right">{{ report.total_expenses.formatted }}</td>
                    </tr>
                    <tr class="border-t border-slate-200 bg-teal-50 font-semibold">
                        <td class="px-4 py-3">Net profit</td>
                        <td class="px-4 py-3 text-right">{{ report.net_profit.formatted }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
