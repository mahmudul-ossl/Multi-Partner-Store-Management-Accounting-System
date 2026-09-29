<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useCan } from '../../composables/useCan';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    transfers: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    partners: { type: Array, required: true },
});

const { can } = useCan();
const search = ref(props.filters.search);
const status = ref(props.filters.status);
const sort = ref(props.filters.sort);
const direction = ref(props.filters.direction);
const showForm = ref(false);
const form = useForm({
    from_partner_id: props.partners[0]?.id || '',
    to_partner_id: props.partners[1]?.id || '',
    amount: '',
    transaction_date: '',
    note: '',
});

function applyFilters() {
    router.get('/transfers', { search: search.value, status: status.value, sort: sort.value, direction: direction.value }, { preserveState: true, replace: true });
}

function submit() {
    form.post('/transfers');
}
</script>

<template>
    <AppLayout title="Transfers">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Partner transfers</h1>
                <p class="mt-1 text-sm text-slate-500">Capital moves between partners. Business cash stays the same.</p>
            </div>
            <button v-if="can('partner.transfer.create')" class="btn btn-primary" type="button" @click="showForm = true">New transfer</button>
        </div>
        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
            <div class="md:col-span-2">
                <label class="label" for="transfer-search">Search</label>
                <input id="transfer-search" v-model="search" class="field" placeholder="Reference or partner">
            </div>
            <div>
                <label class="label" for="transfer-status">Status</label>
                <select id="transfer-status" v-model="status" class="field">
                    <option value="">All statuses</option>
                    <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                </select>
            </div>
            <div class="flex items-end"><button class="btn btn-secondary" type="submit">Apply filters</button></div>
        </form>
        <section class="card mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Reference</th>
                            <th class="px-4 py-3 font-semibold">From</th>
                            <th class="px-4 py-3 font-semibold">To</th>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Amount</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in transfers.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3"><Link :href="`/transfers/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.reference }}</Link></td>
                            <td class="px-4 py-3">{{ row.from_partner?.name }}</td>
                            <td class="px-4 py-3">{{ row.to_partner?.name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ row.transaction_date_formatted }}</td>
                            <td class="px-4 py-3 font-medium">{{ row.amount_formatted }}</td>
                            <td class="px-4 py-3"><StatusBadge :label="row.status.label" :tone="row.status.tone" /></td>
                        </tr>
                        <tr v-if="!transfers.data.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">No transfers match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="transfers" :filters="{ search, status, sort, direction }" url="/transfers" />
        </section>
        <Modal :open="showForm" title="New transfer" @close="showForm = false">
            <form class="grid gap-4" @submit.prevent="submit">
                <div>
                    <label class="label" for="tr-from">From partner</label>
                    <select id="tr-from" v-model="form.from_partner_id" class="field" required>
                        <option v-for="partner in partners" :key="partner.id" :value="partner.id">{{ partner.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="tr-to">To partner</label>
                    <select id="tr-to" v-model="form.to_partner_id" class="field" required>
                        <option v-for="partner in partners" :key="partner.id" :value="partner.id">{{ partner.name }}</option>
                    </select>
                    <p v-if="form.errors.to_partner_id" class="error">{{ form.errors.to_partner_id }}</p>
                </div>
                <input v-model="form.amount" class="field" required placeholder="3000.00">
                <input v-model="form.transaction_date" class="field" type="date" required>
                <p v-if="form.errors.transaction_date" class="error">{{ form.errors.transaction_date }}</p>
                <textarea v-model="form.note" class="field" rows="3" />
                <button class="btn btn-primary" type="submit" :disabled="form.processing">Submit for approval</button>
            </form>
        </Modal>
    </AppLayout>
</template>
