<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    promotions: { type: Object, required: true },
    platforms: { type: Array, required: true },
    statuses: { type: Array, required: true },
    can: { type: Object, required: true },
});

const showForm = ref(false);
const form = useForm({
    name: '',
    platform: props.platforms[0]?.value || 'facebook',
    starts_on: '',
    ends_on: '',
    budget: '',
    status: 'active',
    description: '',
});

function submit() {
    form.post('/promotions', { preserveScroll: true, onSuccess: () => { showForm.value = false; form.reset(); } });
}
</script>

<template>
    <AppLayout title="Promotions">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Promotions</h1>
                <p class="mt-1 text-sm text-slate-500">Budget is the plan. Actual spend is the sum of approved contributions. Stock and cash do not move until a contribution is approved.</p>
            </div>
            <button v-if="can.create" class="btn btn-primary" type="button" @click="showForm = true">New promotion</button>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Name</th>
                            <th class="px-4 py-3 font-semibold">Platform</th>
                            <th class="px-4 py-3 font-semibold">Budget</th>
                            <th class="px-4 py-3 font-semibold">Actual</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in promotions.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3">
                                <Link :href="`/promotions/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.name }}</Link>
                                <p class="text-xs text-slate-500">{{ row.starts }} – {{ row.ends }}</p>
                            </td>
                            <td class="px-4 py-3">{{ row.platform }}</td>
                            <td class="px-4 py-3">{{ row.budget }}</td>
                            <td class="px-4 py-3">{{ row.actual }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                        </tr>
                        <tr v-if="promotions.data.length === 0">
                            <td class="px-4 py-8 text-center text-slate-500" colspan="5">No promotions yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="promotions" url="/promotions" />
        </section>

        <Modal :open="showForm" title="New promotion" @close="showForm = false">
            <form class="grid gap-3" @submit.prevent="submit">
                <div>
                    <label class="label">Name</label>
                    <input v-model="form.name" class="field" required>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="label">Platform</label>
                        <select v-model="form.platform" class="field">
                            <option v-for="row in platforms" :key="row.value" :value="row.value">{{ row.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Status</label>
                        <select v-model="form.status" class="field">
                            <option v-for="row in statuses" :key="row.value" :value="row.value">{{ row.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Starts</label>
                        <input v-model="form.starts_on" class="field" type="date" required>
                    </div>
                    <div>
                        <label class="label">Ends</label>
                        <input v-model="form.ends_on" class="field" type="date">
                    </div>
                    <div>
                        <label class="label">Budget</label>
                        <input v-model="form.budget" class="field" required>
                    </div>
                </div>
                <div>
                    <label class="label">Description</label>
                    <textarea v-model="form.description" class="field" rows="2"></textarea>
                </div>
                <p v-if="Object.keys(form.errors).length" class="text-sm text-rose-700">{{ Object.values(form.errors)[0] }}</p>
                <div class="flex justify-end">
                    <button class="btn btn-primary" :disabled="form.processing" type="submit">Save promotion</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
