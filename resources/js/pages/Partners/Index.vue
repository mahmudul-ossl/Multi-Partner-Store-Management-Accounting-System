<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useCan } from '../../composables/useCan';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    partners: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    linkableUsers: { type: Array, required: true },
});

const { can } = useCan();
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const sort = ref(props.filters.sort);
const direction = ref(props.filters.direction);
const showForm = ref(false);

const form = useForm({
    partner_code: '',
    name: '',
    phone: '',
    email: '',
    address: '',
    joining_date: '',
    ownership_percentage: '',
    investment_percentage: '',
    status: 'active',
    user_id: '',
    notes: '',
});

function applyFilters() {
    router.get('/partners', {
        search: search.value,
        status: status.value,
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

function submit() {
    form.post('/partners', { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Partners">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Partners</h1>
                <p class="mt-1 text-sm text-slate-500">Ownership and investment percentages are stored separately.</p>
            </div>
            <button v-if="can('partner.create')" class="btn btn-primary" type="button" @click="showForm = true">New partner</button>
        </div>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
            <div class="md:col-span-2">
                <label class="label" for="partner-search">Search</label>
                <input id="partner-search" v-model="search" class="field" placeholder="Name, code, phone, or email">
            </div>
            <div>
                <label class="label" for="partner-status">Status</label>
                <select id="partner-status" v-model="status" class="field">
                    <option value="">All statuses</option>
                    <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
            <div class="flex items-end">
                <button class="btn btn-secondary" type="submit">Apply filters</button>
            </div>
        </form>

        <section class="card mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3"><button type="button" class="font-semibold" @click="toggleSort('partner_code')">Code</button></th>
                            <th class="px-4 py-3"><button type="button" class="font-semibold" @click="toggleSort('name')">Name</button></th>
                            <th class="px-4 py-3 font-semibold">Phone</th>
                            <th class="px-4 py-3"><button type="button" class="font-semibold" @click="toggleSort('joining_date')">Joined</button></th>
                            <th class="px-4 py-3"><button type="button" class="font-semibold" @click="toggleSort('ownership_percentage')">Ownership</button></th>
                            <th class="px-4 py-3"><button type="button" class="font-semibold" @click="toggleSort('investment_percentage')">Investment</button></th>
                            <th class="px-4 py-3"><button type="button" class="font-semibold" @click="toggleSort('status')">Status</button></th>
                            <th class="px-4 py-3 font-semibold">User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="partner in partners.data" :key="partner.id" class="border-t border-slate-100">
                            <td class="px-4 py-3 font-medium">{{ partner.partner_code }}</td>
                            <td class="px-4 py-3">
                                <Link :href="`/partners/${partner.id}`" class="font-semibold text-teal-800 hover:underline">{{ partner.name }}</Link>
                                <Link v-if="can('partner_statement.view')" :href="`/partners/${partner.id}/profile`" class="ml-2 text-xs font-semibold text-teal-700 hover:underline">Profile</Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ partner.phone || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ partner.joining_date_formatted }}</td>
                            <td class="px-4 py-3">{{ partner.ownership_percentage_display }}</td>
                            <td class="px-4 py-3">{{ partner.investment_percentage_display }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="partner.status.label" :tone="partner.status.tone" /></td>
                            <td class="px-4 py-3 text-slate-600">{{ partner.user?.name || 'Not linked' }}</td>
                        </tr>
                        <tr v-if="!partners.data.length">
                            <td colspan="8" class="px-4 py-10 text-center text-slate-500">No partners match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="partners" :filters="{ search, status, sort, direction }" url="/partners" />
        </section>

        <Modal :open="showForm" title="New partner" @close="showForm = false">
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <div>
                    <label class="label" for="code">Partner code</label>
                    <input id="code" v-model="form.partner_code" class="field" placeholder="Leave blank to generate">
                    <p v-if="form.errors.partner_code" class="error">{{ form.errors.partner_code }}</p>
                </div>
                <div>
                    <label class="label" for="pname">Name</label>
                    <input id="pname" v-model="form.name" class="field" required>
                    <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="label" for="pphone">Phone</label>
                    <input id="pphone" v-model="form.phone" class="field">
                </div>
                <div>
                    <label class="label" for="pemail">Email</label>
                    <input id="pemail" v-model="form.email" class="field" type="email">
                    <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="address">Address</label>
                    <input id="address" v-model="form.address" class="field">
                </div>
                <div>
                    <label class="label" for="joined">Joining date</label>
                    <input id="joined" v-model="form.joining_date" class="field" type="date" required>
                    <p v-if="form.errors.joining_date" class="error">{{ form.errors.joining_date }}</p>
                </div>
                <div>
                    <label class="label" for="pstatus">Status</label>
                    <select id="pstatus" v-model="form.status" class="field">
                        <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="ownership">Ownership %</label>
                    <input id="ownership" v-model="form.ownership_percentage" class="field" type="number" min="0" max="100" step="0.0001" required>
                    <p v-if="form.errors.ownership_percentage" class="error">{{ form.errors.ownership_percentage }}</p>
                </div>
                <div>
                    <label class="label" for="investment">Investment %</label>
                    <input id="investment" v-model="form.investment_percentage" class="field" type="number" min="0" max="100" step="0.0001" required>
                    <p v-if="form.errors.investment_percentage" class="error">{{ form.errors.investment_percentage }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="user">Linked user</label>
                    <select id="user" v-model="form.user_id" class="field">
                        <option value="">No system account</option>
                        <option v-for="user in linkableUsers" :key="user.id" :value="user.id">{{ user.name }} · {{ user.email }}</option>
                    </select>
                    <p v-if="form.errors.user_id" class="error">{{ form.errors.user_id }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="notes">Notes</label>
                    <textarea id="notes" v-model="form.notes" class="field" rows="3" />
                </div>
                <div class="flex justify-end gap-3 sm:col-span-2">
                    <button class="btn btn-secondary" type="button" @click="showForm = false">Cancel</button>
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">Save partner</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
