<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';

const props = defineProps({
    thresholds: { type: Array, required: true },
    types: { type: Array, required: true },
});

const open = ref(false);
const editingId = ref(null);
const form = useForm({
    request_type: props.types[0]?.value || 'withdrawal',
    min_amount: '0.00',
    max_amount: '',
    required_approvals: 1,
});

function edit(row) {
    editingId.value = row.id;
    form.request_type = row.request_type;
    form.min_amount = row.min_amount;
    form.max_amount = row.max_amount || '';
    form.required_approvals = row.required_approvals;
    open.value = true;
}

function create() {
    editingId.value = null;
    form.reset();
    form.request_type = props.types[0]?.value || 'withdrawal';
    form.required_approvals = 1;
    open.value = true;
}

function save() {
    if (editingId.value) {
        form.put(`/settings/approvals/${editingId.value}`, { onSuccess: () => { open.value = false; } });
        return;
    }
    form.post('/settings/approvals', { onSuccess: () => { open.value = false; } });
}

function remove(row) {
    router.delete(`/settings/approvals/${row.id}`);
}
</script>

<template>
    <AppLayout title="Approval settings">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Approval thresholds</h1>
                <p class="mt-1 text-sm text-slate-500">Example: ৳0–10,000 needs 1 approver, ৳10,001–100,000 needs 2, and above ৳100,000 needs 3.</p>
            </div>
            <button class="btn btn-primary" type="button" @click="create">Add band</button>
        </div>
        <section class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Type</th>
                            <th class="px-4 py-3 font-semibold">From</th>
                            <th class="px-4 py-3 font-semibold">To</th>
                            <th class="px-4 py-3 font-semibold">Approvers</th>
                            <th class="px-4 py-3 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in thresholds" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3">{{ row.request_type_label }}</td>
                            <td class="px-4 py-3">{{ row.min_formatted }}</td>
                            <td class="px-4 py-3">{{ row.max_formatted }}</td>
                            <td class="px-4 py-3 font-medium">{{ row.required_approvals }}</td>
                            <td class="px-4 py-3 text-right">
                                <button class="font-semibold text-teal-800" type="button" @click="edit(row)">Edit</button>
                                <button class="ml-3 font-semibold text-red-700" type="button" @click="remove(row)">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <Modal :open="open" :title="editingId ? 'Edit band' : 'Add band'" @close="open = false">
            <form class="grid gap-4" @submit.prevent="save">
                <div>
                    <label class="label" for="band-type">Request type</label>
                    <select id="band-type" v-model="form.request_type" class="field">
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="band-min">Minimum amount</label>
                    <input id="band-min" v-model="form.min_amount" class="field" required>
                    <p v-if="form.errors.min_amount" class="error">{{ form.errors.min_amount }}</p>
                </div>
                <div>
                    <label class="label" for="band-max">Maximum amount</label>
                    <input id="band-max" v-model="form.max_amount" class="field" placeholder="Leave empty for no maximum">
                    <p v-if="form.errors.max_amount" class="error">{{ form.errors.max_amount }}</p>
                </div>
                <div>
                    <label class="label" for="band-count">Required approvals</label>
                    <input id="band-count" v-model="form.required_approvals" class="field" type="number" min="1" max="10" required>
                </div>
                <button class="btn btn-primary" type="submit" :disabled="form.processing">Save band</button>
            </form>
        </Modal>
    </AppLayout>
</template>
