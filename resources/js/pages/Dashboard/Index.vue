<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import SummaryCard from '../../Components/SummaryCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    summary: { type: Object, required: true },
});
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                <p class="mt-1 text-sm text-slate-500">Cards and charts follow your permissions. Money figures come from the ledger.</p>
            </div>
        </div>

        <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <SummaryCard label="Partners" :value="summary.partners_total" />
            <SummaryCard label="Active partners" :value="summary.partners_active" />
            <SummaryCard label="Inactive partners" :value="summary.partners_inactive" />
            <SummaryCard label="Suspended partners" :value="summary.partners_suspended" />
            <SummaryCard v-if="summary.show_user_counts" label="Users" :value="summary.users_total" />
            <SummaryCard v-if="summary.show_user_counts" label="Active users" :value="summary.users_active" />
            <SummaryCard v-for="card in summary.cards" :key="card.key" :label="card.label" :value="card.value" />
        </section>

        <section v-if="summary.charts.length" class="mt-10 grid gap-4 lg:grid-cols-2">
            <article v-for="chart in summary.charts" :key="chart.key" class="card p-5">
                <h2 class="font-semibold">{{ chart.label }}</h2>
                <div class="mt-4 space-y-2">
                    <div v-for="point in chart.points" :key="point.label" class="grid grid-cols-[5.5rem_1fr_auto] items-center gap-3 text-sm">
                        <span class="text-slate-500">{{ point.label }}</span>
                        <span class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <span class="block h-2 rounded-full bg-teal-600" :style="{ width: point.width }" />
                        </span>
                        <span class="font-medium text-slate-800">{{ point.formatted }}</span>
                    </div>
                </div>
            </article>
        </section>

        <section class="card mt-10 overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold">Recent partners</h2>
                <Link href="/partners" class="text-sm font-semibold text-teal-700 hover:text-teal-800">View all</Link>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Code</th>
                            <th class="px-5 py-3 font-semibold">Name</th>
                            <th class="px-5 py-3 font-semibold">Joined</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="partner in summary.recent_partners" :key="partner.id" class="border-t border-slate-100">
                            <td class="px-5 py-3 font-medium">{{ partner.partner_code }}</td>
                            <td class="px-5 py-3">
                                <Link :href="`/partners/${partner.id}`" class="font-medium text-teal-800 hover:underline">{{ partner.name }}</Link>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ partner.joining_date }}</td>
                            <td class="px-5 py-3">
                                <StatusBadge :label="partner.status.label" :tone="partner.status.tone" />
                            </td>
                        </tr>
                        <tr v-if="!summary.recent_partners.length">
                            <td colspan="4" class="px-5 py-8 text-center text-slate-500">No partners yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
