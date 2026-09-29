<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    entry: { type: Object, required: true },
    can: { type: Object, required: true },
});

const form = useForm({ reason: '' });

function reverse() {
    form.post(`/accounting/entries/${props.entry.id}/reverse`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="entry.reference">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm text-slate-500">{{ entry.entry_date_formatted }} · {{ entry.source_label }}</p>
                <h1 class="text-2xl font-semibold tracking-tight">{{ entry.reference }}</h1>
                <p class="mt-1 text-slate-600">{{ entry.description }}</p>
            </div>
            <StatusBadge :label="entry.status.label" :tone="entry.status.tone" />
        </div>

        <section class="card mt-6 overflow-hidden">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Account</th>
                        <th class="px-4 py-3 font-semibold">Partner / wallet</th>
                        <th class="px-4 py-3 font-semibold">Debit</th>
                        <th class="px-4 py-3 font-semibold">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="line in entry.lines" :key="line.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ line.account }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ line.financial_account || line.partner || '—' }}</td>
                        <td class="px-4 py-3">{{ line.debit.formatted }}</td>
                        <td class="px-4 py-3">{{ line.credit.formatted }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <form v-if="can.reverse" class="card mt-6 grid gap-3 p-4 sm:grid-cols-[1fr_auto]" @submit.prevent="reverse">
            <div>
                <label class="label" for="reverse-reason">Reversal reason</label>
                <input id="reverse-reason" v-model="form.reason" class="field" required minlength="3" placeholder="Why this entry is being reversed">
                <p v-if="form.errors.reason" class="error">{{ form.errors.reason }}</p>
            </div>
            <div class="flex items-end">
                <button class="btn btn-primary" type="submit" :disabled="form.processing">Reverse entry</button>
            </div>
        </form>
    </AppLayout>
</template>
