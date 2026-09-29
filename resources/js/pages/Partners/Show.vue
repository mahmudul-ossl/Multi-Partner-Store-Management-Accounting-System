<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useCan } from '../../composables/useCan';
import AppLayout from '../../Layouts/AppLayout.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    partner: { type: Object, required: true },
    statuses: { type: Array, required: true },
    linkableUsers: { type: Array, required: true },
    can: { type: Object, required: true },
});

const { can: allowed } = useCan();
const confirmDelete = ref(false);

const form = useForm({
    partner_code: props.partner.partner_code,
    name: props.partner.name,
    phone: props.partner.phone || '',
    email: props.partner.email || '',
    address: props.partner.address || '',
    joining_date: props.partner.joining_date || '',
    ownership_percentage: props.partner.ownership_percentage,
    investment_percentage: props.partner.investment_percentage,
    status: props.partner.status.value,
    user_id: props.partner.user_id || '',
    notes: props.partner.notes || '',
});

const linkForm = useForm({
    user_id: props.partner.user_id || '',
});

function save() {
    form.put(`/partners/${props.partner.id}`);
}

function linkUser() {
    linkForm.put(`/partners/${props.partner.id}/user`);
}

function unlinkUser() {
    router.delete(`/partners/${props.partner.id}/user`);
}

function remove() {
    router.delete(`/partners/${props.partner.id}`);
}
</script>

<template>
    <AppLayout title="Partner">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-teal-700">{{ partner.partner_code }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ partner.name }}</h1>
                <div class="mt-3">
                    <StatusBadge :label="partner.status.label" :tone="partner.status.tone" />
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <Link v-if="allowed('partner_statement.view')" :href="`/partners/${partner.id}/profile`" class="btn btn-secondary">Profile</Link>
                <Link v-if="allowed('partner_statement.view')" :href="`/partners/${partner.id}/dashboard`" class="btn btn-secondary">Dashboard</Link>
                <Link v-if="allowed('partner_statement.view')" :href="`/partners/${partner.id}/statement`" class="btn btn-secondary">Statement</Link>
                <Link href="/partners" class="btn btn-secondary">Back</Link>
                <button v-if="can.delete" class="btn btn-danger" type="button" @click="confirmDelete = true">Archive</button>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="card p-4">
                <p class="text-sm text-slate-500">Ownership</p>
                <p class="mt-1 text-xl font-semibold">{{ partner.ownership_percentage_display }}</p>
            </article>
            <article class="card p-4">
                <p class="text-sm text-slate-500">Investment share</p>
                <p class="mt-1 text-xl font-semibold">{{ partner.investment_percentage_display }}</p>
            </article>
            <article class="card p-4">
                <p class="text-sm text-slate-500">Joined</p>
                <p class="mt-1 text-xl font-semibold">{{ partner.joining_date_formatted }}</p>
            </article>
            <article class="card p-4">
                <p class="text-sm text-slate-500">System user</p>
                <p class="mt-1 text-xl font-semibold">{{ partner.user?.name || 'Not linked' }}</p>
            </article>
        </div>

        <section v-if="can.update" class="card mt-6 p-5">
            <h2 class="font-semibold">Partner record</h2>
            <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="save">
                <div>
                    <label class="label">Code</label>
                    <input v-model="form.partner_code" class="field" required>
                    <p v-if="form.errors.partner_code" class="error">{{ form.errors.partner_code }}</p>
                </div>
                <div>
                    <label class="label">Name</label>
                    <input v-model="form.name" class="field" required>
                    <p v-if="form.errors.name" class="error">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="label">Phone</label>
                    <input v-model="form.phone" class="field">
                </div>
                <div>
                    <label class="label">Email</label>
                    <input v-model="form.email" class="field" type="email">
                    <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Address</label>
                    <input v-model="form.address" class="field">
                </div>
                <div>
                    <label class="label">Joining date</label>
                    <input v-model="form.joining_date" class="field" type="date" required>
                </div>
                <div>
                    <label class="label">Status</label>
                    <select v-model="form.status" class="field">
                        <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Ownership %</label>
                    <input v-model="form.ownership_percentage" class="field" type="number" min="0" max="100" step="0.0001" required>
                    <p v-if="form.errors.ownership_percentage" class="error">{{ form.errors.ownership_percentage }}</p>
                </div>
                <div>
                    <label class="label">Investment %</label>
                    <input v-model="form.investment_percentage" class="field" type="number" min="0" max="100" step="0.0001" required>
                    <p v-if="form.errors.investment_percentage" class="error">{{ form.errors.investment_percentage }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Linked user</label>
                    <select v-model="form.user_id" class="field">
                        <option value="">No system account</option>
                        <option v-for="user in linkableUsers" :key="user.id" :value="user.id">{{ user.name }} · {{ user.email }}</option>
                    </select>
                    <p v-if="form.errors.user_id" class="error">{{ form.errors.user_id }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Notes</label>
                    <textarea v-model="form.notes" class="field" rows="3" />
                </div>
                <div class="sm:col-span-2">
                    <button class="btn btn-primary" type="submit" :disabled="form.processing">Save changes</button>
                </div>
            </form>
        </section>

        <section v-else class="card mt-6 p-5 text-sm text-slate-600">
            <p>{{ partner.address || 'No address recorded.' }}</p>
            <p class="mt-2">{{ partner.phone || 'No phone' }} · {{ partner.email || 'No email' }}</p>
            <p v-if="partner.notes" class="mt-3">{{ partner.notes }}</p>
        </section>

        <section v-if="can.update" class="card mt-6 p-5">
            <h2 class="font-semibold">User account</h2>
            <p class="mt-1 text-sm text-slate-500">A partner may optionally have one system login. The link is unique.</p>
            <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="linkUser">
                <div class="flex-1">
                    <label class="label">Account</label>
                    <select v-model="linkForm.user_id" class="field" required>
                        <option value="" disabled>Select a user</option>
                        <option v-for="user in linkableUsers" :key="user.id" :value="user.id">{{ user.name }} · {{ user.email }}</option>
                    </select>
                    <p v-if="linkForm.errors.user_id" class="error">{{ linkForm.errors.user_id }}</p>
                </div>
                <button class="btn btn-primary" type="submit" :disabled="linkForm.processing">Link account</button>
                <button v-if="partner.user_id" class="btn btn-secondary" type="button" @click="unlinkUser">Unlink</button>
            </form>
        </section>

        <ConfirmDialog
            :open="confirmDelete"
            title="Archive partner"
            message="The partner is soft-deleted and drops out of active lists. The row stays in the database."
            confirm-label="Archive partner"
            @close="confirmDelete = false"
            @confirm="remove"
        />
    </AppLayout>
</template>
