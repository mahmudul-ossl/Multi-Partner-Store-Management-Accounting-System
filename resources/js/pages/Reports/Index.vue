<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    reports: { type: Array, required: true },
});
</script>

<template>
    <AppLayout title="Reports">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Reports</h1>
            <p class="mt-1 text-sm text-slate-500">Every total on these reports comes from the ledger or the stock ledger. Filter, search, and export the ones your role can open.</p>
        </div>

        <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="report in reports"
                :key="report.key"
                :href="`/reports/${report.key}`"
                class="card px-5 py-4 font-medium text-teal-800 hover:border-teal-300"
            >
                {{ report.label }}
            </Link>
        </section>
    </AppLayout>
</template>
