<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    accounts: { type: Array, required: true },
    filters: { type: Object, required: true },
    report: { type: Object, default: null },
});

const account = ref(props.filters.account);
const from = ref(props.filters.from);
const to = ref(props.filters.to);

function applyFilters() {
    router.get('/accounting/reports/general-ledger', {
        account: account.value,
        from: from.value,
        to: to.value,
    }, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout title="General ledger">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">General ledger</h1>
            <p class="mt-1 text-sm text-slate-500">Opening balance is the movement before the start date. The running balance follows the account's normal side.</p>
        </div>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
            <div class="md:col-span-2">
                <label class="label" for="gl-account">Account</label>
                <select id="gl-account" v-model="account" class="field" required>
                    <option value="">Select an account</option>
                    <option v-for="item in accounts" :key="item.id" :value="String(item.id)">{{ item.label }}</option>
                </select>
            </div>
            <div>
                <label class="label" for="gl-from">From</label>
                <input id="gl-from" v-model="from" class="field" type="date">
            </div>
            <div>
                <label class="label" for="gl-to">To</label>
                <input id="gl-to" v-model="to" class="field" type="date">
            </div>
            <div class="md:col-span-4">
                <button class="btn btn-secondary" type="submit">Show ledger</button>
            </div>
        </form>

        <section v-if="report" class="card mt-4 overflow-hidden">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <div>
                    <h2 class="font-semibold">{{ report.account.code }} {{ report.account.name }}</h2>
                    <p class="text-sm text-slate-500">{{ report.account.type }} · normal {{ report.account.normal_balance }}</p>
                </div>
                <div class="text-right text-sm">
                    <p>Opening {{ report.opening_balance.formatted }}</p>
                    <p class="font-semibold">Closing {{ report.closing_balance.formatted }}</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Reference</th>
                            <th class="px-4 py-3 font-semibold">Description</th>
                            <th class="px-4 py-3 font-semibold">Debit</th>
                            <th class="px-4 py-3 font-semibold">Credit</th>
                            <th class="px-4 py-3 font-semibold">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t border-slate-100 bg-slate-50/60">
                            <td class="px-4 py-3 text-slate-500" colspan="5">Opening balance</td>
                            <td class="px-4 py-3 font-medium">{{ report.opening_balance.formatted }}</td>
                        </tr>
                        <tr v-for="line in report.lines" :key="line.id" class="border-t border-slate-100">
                            <td class="px-4 py-3 text-slate-600">{{ line.date }}</td>
                            <td class="px-4 py-3">
                                <Link :href="`/accounting/entries/${line.journal_id}`" class="text-teal-800 hover:underline">{{ line.reference }}</Link>
                            </td>
                            <td class="px-4 py-3">{{ line.description }}</td>
                            <td class="px-4 py-3">{{ line.debit.formatted }}</td>
                            <td class="px-4 py-3">{{ line.credit.formatted }}</td>
                            <td class="px-4 py-3 font-medium">{{ line.running_balance.formatted }}</td>
                        </tr>
                        <tr v-if="!report.lines.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">No movement in this range.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
