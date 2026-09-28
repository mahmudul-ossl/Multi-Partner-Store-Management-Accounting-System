<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, required: true },
    roles: { type: Array, required: true },
});

const search = ref(props.filters.search);
const role = ref(props.filters.role);
const isActive = ref(props.filters.is_active);
const sort = ref(props.filters.sort);
const direction = ref(props.filters.direction);
const showForm = ref(false);
const editing = ref(null);
const pendingDelete = ref(null);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    role: 'Viewer',
    is_active: true,
});

function applyFilters() {
    router.get('/users', {
        search: search.value,
        role: role.value,
        is_active: isActive.value,
        sort: sort.value,
        direction: direction.value,
    }, { preserveState: true, replace: true });
}

function toggleSort(column) {
    if (sort.value === column) {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';
    } else {
        sort.value = column;
        direction.value = 'asc';
    }
    applyFilters();
}

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'Viewer';
    form.is_active = true;
    showForm.value = true;
}

function openEdit(user) {
    editing.value = user;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.phone = user.phone || '';
    form.password = '';
    form.password_confirmation = '';
    form.role = user.role || 'Viewer';
    form.is_active = user.is_active;
    showForm.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    };

    if (editing.value) {
        form.put(`/users/${editing.value.id}`, options);
        return;
    }

    form.post('/users', options);
}

function confirmDelete() {
    router.delete(`/users/${pendingDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            pendingDelete.value = null;
        },
    });
}
</script>

<template>
    <AppLayout title="Users">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Users</h1>
                <p class="mt-1 text-sm text-slate-500">System accounts and the role each person holds.</p>
            </div>
            <button class="btn btn-primary" type="button" @click="openCreate">New user</button>
        </div>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
            <div class="md:col-span-2">
                <label class="label" for="search">Search</label>
                <input id="search" v-model="search" class="field" placeholder="Name, email, or phone">
            </div>
            <div>
                <label class="label" for="role">Role</label>
                <select id="role" v-model="role" class="field">
                    <option value="">All roles</option>
                    <option v-for="item in roles" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
            <div>
                <label class="label" for="status">Status</label>
                <select id="status" v-model="isActive" class="field">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="md:col-span-4">
                <button class="btn btn-secondary" type="submit">Apply filters</button>
            </div>
        </form>

        <section class="card mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">
                                <button type="button" class="font-semibold" @click="toggleSort('name')">Name</button>
                            </th>
                            <th class="px-4 py-3">
                                <button type="button" class="font-semibold" @click="toggleSort('email')">Email</button>
                            </th>
                            <th class="px-4 py-3 font-semibold">Phone</th>
                            <th class="px-4 py-3 font-semibold">Role</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Created</th>
                            <th class="px-4 py-3 font-semibold" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users.data" :key="user.id" class="border-t border-slate-100">
                            <td class="px-4 py-3 font-medium">{{ user.name }}</td>
                            <td class="px-4 py-3">{{ user.email }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ user.phone || '—' }}</td>
                            <td class="px-4 py-3">{{ user.role || '—' }}</td>
                            <td class="px-4 py-3">
                                <StatusBadge :label="user.status.label" :tone="user.status.tone" />
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ user.created_at }}</td>
                            <td class="px-4 py-3 text-right">
                                <button class="text-sm font-semibold text-teal-700" type="button" @click="openEdit(user)">Edit</button>
                                <button class="ml-3 text-sm font-semibold text-red-600" type="button" @click="pendingDelete = user">Remove</button>
                            </td>
                        </tr>
                        <tr v-if="!users.data.length">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">No users match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="users" :filters="{ search, role, is_active: isActive, sort, direction }" url="/users" />
        </section>

        <Modal :open="showForm" :title="editing ? 'Edit user' : 'New user'" @close="showForm = false">
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <div>
                    <label class="label" for="name">Name</label>
                    <input id="name" v-model="form.name" class="field" required>
                    <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="label" for="user-email">Email</label>
                    <input id="user-email" v-model="form.email" class="field" type="email" required>
                    <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="label" for="phone">Phone</label>
                    <input id="phone" v-model="form.phone" class="field">
                    <p v-if="form.errors.phone" class="error">{{ form.errors.phone }}</p>
                </div>
                <div>
                    <label class="label" for="user-role">Role</label>
                    <select id="user-role" v-model="form.role" class="field">
                        <option v-for="item in roles" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </select>
                    <p v-if="form.errors.role" class="error">{{ form.errors.role }}</p>
                </div>
                <div>
                    <label class="label" for="user-password">Password</label>
                    <input id="user-password" v-model="form.password" class="field" type="password" :required="!editing" autocomplete="new-password">
                    <p v-if="form.errors.password" class="error">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="label" for="user-password-confirmation">Confirm password</label>
                    <input id="user-password-confirmation" v-model="form.password_confirmation" class="field" type="password" :required="!editing" autocomplete="new-password">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-teal-700">
                    Account is active
                </label>
                <div class="flex justify-end gap-3 sm:col-span-2">
                    <button class="btn btn-secondary" type="button" @click="showForm = false">Cancel</button>
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">Save user</button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog
            :open="Boolean(pendingDelete)"
            title="Remove user"
            :message="pendingDelete ? `Archive ${pendingDelete.name}? Their audit history stays.` : ''"
            confirm-label="Remove user"
            @close="pendingDelete = null"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
