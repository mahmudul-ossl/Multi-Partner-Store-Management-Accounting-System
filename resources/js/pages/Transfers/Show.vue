<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    transfer: { type: Object, required: true },
    partners: { type: Array, required: true },
    can: { type: Object, required: true },
});

const editing = ref(false);
const reversing = ref(false);
const form = useForm({
    from_partner_id: props.transfer.from_partner_id,
    to_partner_id: props.transfer.to_partner_id,
    amount: props.transfer.amount,
    transaction_date: props.transfer.transaction_date,
    note: props.transfer.note || '',
});
const reverseForm = useForm({ reason: '' });

function save() {
    form.put(`/transfers/${props.transfer.id}`);
}
</script>

<template>
    <AppLayout title="Transfer">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-teal-700">{{ transfer.reference }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ transfer.amount_formatted }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ transfer.from_partner?.name }} → {{ transfer.to_partner?.name }}</p>
                <div class="mt-3"><StatusBadge :label="transfer.status.label" :tone="transfer.status.tone" /></div>
            </div>
            <div class="flex flex-wrap gap-3">
                <Link href="/transfers" class="btn btn-secondary">Back</Link>
                <button v-if="can.update" class="btn btn-secondary" type="button" @click="editing = true">Edit</button>
                <button v-if="can.cancel" class="btn btn-danger" type="button" @click="router.post(`/transfers/${transfer.id}/cancel`)">Cancel</button>
                <button v-if="can.reverse" class="btn btn-secondary" type="button" @click="reversing = true">Reverse</button>
            </div>
        </div>
        <section class="card mt-6 p-5">
            <p class="text-slate-500">Approvals</p>
            <p class="font-medium">{{ transfer.approval ? `${transfer.approval.completed_approvals} of ${transfer.approval.required_approvals}` : '—' }}</p>
            <p class="mt-4 text-slate-500">Note</p>
            <p class="font-medium">{{ transfer.note || '—' }}</p>
        </section>
        <Modal :open="editing" title="Edit transfer" @close="editing = false">
            <form class="grid gap-4" @submit.prevent="save">
                <select v-model="form.from_partner_id" class="field"><option v-for="partner in partners" :key="partner.id" :value="partner.id">{{ partner.name }}</option></select>
                <select v-model="form.to_partner_id" class="field"><option v-for="partner in partners" :key="partner.id" :value="partner.id">{{ partner.name }}</option></select>
                <input v-model="form.amount" class="field" required>
                <input v-model="form.transaction_date" class="field" type="date" required>
                <textarea v-model="form.note" class="field" rows="3" />
                <button class="btn btn-primary" type="submit" :disabled="form.processing">Save</button>
            </form>
        </Modal>
        <Modal :open="reversing" title="Reverse transfer" @close="reversing = false">
            <form class="grid gap-4" @submit.prevent="reverseForm.post(`/transfers/${transfer.id}/reverse`)">
                <textarea v-model="reverseForm.reason" class="field" rows="3" required />
                <button class="btn btn-primary" type="submit" :disabled="reverseForm.processing">Post reversal</button>
            </form>
        </Modal>
    </AppLayout>
</template>
