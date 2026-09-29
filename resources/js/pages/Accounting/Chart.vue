<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useCan } from '../../composables/useCan';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';

const props = defineProps({
    accounts: { type: Array, required: true },
    types: { type: Array, required: true },
    normalBalances: { type: Array, required: true },
    parents: { type: Array, required: true },
});

const { can } = useCan();
const canManage = computed(() => can('settings.manage'));
const showForm = ref(false);
const editing = ref(null);

const form = useForm({
    code: '',
    name: '',
    type: 'expense',
    normal_balance: 'debit',
    parent_id: '',
    is_active: true,
    description: '',
});

const parentChoices = computed(() => props.parents.filter((parent) => !editing.value || parent.id !== editing.value.id));

function openCreate() {
    editing.value = null;
    form.reset();
    form.type = 'expense';
    form.normal_balance = 'debit';
    form.is_active = true;
    form.clearErrors();
    showForm.value = true;
}

function openEdit(account) {
    editing.value = account;
    form.code = account.code;
    form.name = account.name;
    form.type = account.type.value;
    form.normal_balance = account.normal_balance.value;
    form.parent_id = account.parent_id || '';
    form.is_active = account.is_active;
    form.description = account.description || '';
    form.clearErrors();
    showForm.value = true;
}

function submit() {
    const payload = { ...form.data(), parent_id: form.parent_id || null };
    if (editing.value) {
        form.transform(() => payload).put(`/accounting/chart/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false; },
        });
        return;
    }
    form.transform(() => payload).post('/accounting/chart', {
        preserveScroll: true,
        onSuccess: () => { showForm.value = false; form.reset(); },
    });
}

function remove(account) {
    if (!window.confirm(`Remove ${account.code} ${account.name}?`)) {
        return;
    }
    router.delete(`/accounting/chart/${account.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Chart of accounts">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Chart of accounts</h1>
                <p class="mt-1 text-sm text-slate-500">System accounts keep their code, type, and place in the hierarchy. Admins can add accounts beside them.</p>
            </div>
            <button v-if="canManage" class="btn btn-primary" type="button" @click="openCreate">Add account</button>
        </div>

        <section class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Code</th>
                            <th class="px-4 py-3 font-semibold">Name</th>
                            <th class="px-4 py-3 font-semibold">Type</th>
                            <th class="px-4 py-3 font-semibold">Normal balance</th>
                            <th class="px-4 py-3 font-semibold">Parent</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="account in accounts" :key="account.id" class="border-t border-slate-100">
                            <td class="px-4 py-3 font-medium" :style="{ paddingLeft: `${1 + account.depth}rem` }">{{ account.code }}</td>
                            <td class="px-4 py-3">
                                {{ account.name }}
                                <span v-if="account.is_system" class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">System</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ account.type.label }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ account.normal_balance.label }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ account.parent_name || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ account.is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button v-if="canManage" class="text-sm font-medium text-teal-800 hover:underline" type="button" @click="openEdit(account)">Edit</button>
                                <button v-if="canManage && !account.is_system" class="ml-3 text-sm font-medium text-red-700 hover:underline" type="button" @click="remove(account)">Remove</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Modal :open="showForm" :title="editing ? 'Edit account' : 'Add account'" @close="showForm = false">
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <div>
                    <label class="label" for="coa-code">Code</label>
                    <input id="coa-code" v-model="form.code" class="field" required :disabled="editing?.is_system">
                    <p v-if="form.errors.code" class="error">{{ form.errors.code }}</p>
                </div>
                <div>
                    <label class="label" for="coa-name">Name</label>
                    <input id="coa-name" v-model="form.name" class="field" required>
                    <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="label" for="coa-type">Type</label>
                    <select id="coa-type" v-model="form.type" class="field" :disabled="editing?.is_system">
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="coa-normal">Normal balance</label>
                    <select id="coa-normal" v-model="form.normal_balance" class="field" :disabled="editing?.is_system">
                        <option v-for="normal in normalBalances" :key="normal.value" :value="normal.value">{{ normal.label }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="coa-parent">Parent</label>
                    <select id="coa-parent" v-model="form.parent_id" class="field" :disabled="editing?.is_system">
                        <option value="">No parent</option>
                        <option v-for="parent in parentChoices" :key="parent.id" :value="parent.id">{{ parent.label }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="coa-description">Description</label>
                    <textarea id="coa-description" v-model="form.description" class="field" rows="3" />
                </div>
                <label v-if="!editing?.is_system" class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox"> Active
                </label>
                <div class="sm:col-span-2 flex justify-end">
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">Save account</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
