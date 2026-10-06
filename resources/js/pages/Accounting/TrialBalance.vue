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
    router.get('/accounting/reports/trial-balance', { as_of: asOf.value }, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout title="Trial balance">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Trial balance</h1>
            <p class="mt-1 text-sm text-slate-500">Each account’s net debit or credit through the selected date. Income and expense accounts stay on this list.</p>
        </div>

        <form class="card mt-6 flex flex-wrap items-end gap-3 p-4" @submit.prevent="applyFilters">
            <div>
                <label class="label" for="tb-date">As of</label>
                <input id="tb-date" v-model="asOf" class="field" type="date" required>
            </div>
            <button class="btn btn-secondary" type="submit">Show trial balance</button>
        </form>

        <p class="mt-4 text-sm" :class="report.balanced ? 'text-teal-800' : 'text-red-700'">
            {{ report.balanced ? 'Debits equal credits.' : 'Debits and credits do not match.' }}
            As of {{ report.as_of_formatted }}.
        </p>

        <section class="card mt-4 overflow-hidden">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Account</th>
                        <th class="px-4 py-3 text-right font-semibold">Debit</th>
                        <th class="px-4 py-3 text-right font-semibold">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in report.rows" :key="row.code" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ row.code }} {{ row.name }}</td>
                        <td class="px-4 py-3 text-right">{{ row.debit.formatted }}</td>
                        <td class="px-4 py-3 text-right">{{ row.credit.formatted }}</td>
                    </tr>
                    <tr v-if="!report.rows.length">
                        <td class="px-4 py-8 text-center text-slate-500" colspan="3">No ledger activity yet.</td>
                    </tr>
                    <tr class="border-t border-slate-200 font-semibold">
                        <td class="px-4 py-3">Totals</td>
                        <td class="px-4 py-3 text-right">{{ report.debit_total.formatted }}</td>
                        <td class="px-4 py-3 text-right">{{ report.credit_total.formatted }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
