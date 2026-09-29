<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    closed_through: { type: String, default: null },
    periods: { type: Array, required: true },
    can: { type: Object, required: true },
});

const form = useForm({
    closed_through: '',
    note: '',
});

function submit() {
    form.post('/accounting/periods', { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <AppLayout title="Period close">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Period close</h1>
            <p class="mt-1 text-sm text-slate-500">
                Journals cannot be posted on or before the close date.
                <span v-if="closed_through">Books are closed through {{ closed_through }}.</span>
                <span v-else>The books are open.</span>
            </p>
        </div>

        <form v-if="can.close" class="card mt-6 grid gap-3 p-4 md:grid-cols-3" @submit.prevent="submit">
            <div>
                <label class="label" for="close-date">Close through</label>
                <input id="close-date" v-model="form.closed_through" class="field" type="date" required>
            </div>
            <div class="md:col-span-2">
                <label class="label" for="close-note">Note</label>
                <input id="close-note" v-model="form.note" class="field">
            </div>
            <div class="md:col-span-3">
                <button class="btn btn-primary" type="submit" :disabled="form.processing">Close the books</button>
            </div>
        </form>

        <section class="card mt-6 overflow-hidden">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Closed through</th>
                        <th class="px-4 py-3 font-semibold">By</th>
                        <th class="px-4 py-3 font-semibold">Note</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in periods" :key="row.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">{{ row.closed_through }}</td>
                        <td class="px-4 py-3">{{ row.closed_by || '—' }}</td>
                        <td class="px-4 py-3">{{ row.note || '—' }}</td>
                    </tr>
                    <tr v-if="!periods.length">
                        <td class="px-4 py-8 text-center text-slate-500" colspan="3">No period has been closed.</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
