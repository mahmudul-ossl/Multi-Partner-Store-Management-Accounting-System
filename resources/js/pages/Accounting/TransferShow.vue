<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    transfer: { type: Object, required: true },
    can: { type: Object, required: true },
});

function cancel() {
    router.post(`/accounting/transfers/${props.transfer.id}/cancel`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="transfer.reference">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm text-slate-500">{{ transfer.transaction_date_formatted }} · {{ transfer.author }}</p>
                <h1 class="text-2xl font-semibold tracking-tight">{{ transfer.reference }}</h1>
            </div>
            <StatusBadge :label="transfer.status.label" :tone="transfer.status.tone" />
        </div>

        <section class="card mt-6 grid gap-4 p-5 sm:grid-cols-3">
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">From</p>
                <p class="mt-1 font-medium">{{ transfer.from_account }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">To</p>
                <p class="mt-1 font-medium">{{ transfer.to_account }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500">Amount</p>
                <p class="mt-1 text-xl font-semibold">{{ transfer.amount.formatted }}</p>
            </div>
        </section>

        <p v-if="transfer.note" class="mt-4 text-sm text-slate-600">{{ transfer.note }}</p>
        <p v-if="transfer.approval" class="mt-4 text-sm text-slate-600">
            Approvals {{ transfer.approval.completed_approvals }}/{{ transfer.approval.required_approvals }}.
            <Link :href="`/approvals/${transfer.approval.id}`" class="font-medium text-teal-800 hover:underline">Open request</Link>
        </p>
        <p v-if="transfer.journal_entry_id" class="mt-2 text-sm">
            <Link :href="`/accounting/entries/${transfer.journal_entry_id}`" class="font-medium text-teal-800 hover:underline">Posted journal</Link>
        </p>
        <button v-if="can.cancel" class="btn btn-secondary mt-6" type="button" @click="cancel">Cancel transfer</button>
    </AppLayout>
</template>
