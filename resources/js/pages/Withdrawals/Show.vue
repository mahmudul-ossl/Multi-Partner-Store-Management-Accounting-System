<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    withdrawal: { type: Object, required: true },
    paymentMethods: { type: Array, required: true },
    partners: { type: Array, required: true },
    accounts: { type: Array, required: true },
    can: { type: Object, required: true },
});

const editing = ref(false);
const reversing = ref(false);
const form = useForm({
    partner_id: props.withdrawal.partner_id,
    amount: props.withdrawal.amount,
    transaction_date: props.withdrawal.transaction_date,
    reason: props.withdrawal.reason,
    payment_method: props.withdrawal.payment_method,
    financial_account_id: props.withdrawal.financial_account_id,
    note: props.withdrawal.note || '',
});
const reverseForm = useForm({ reason: '' });

function save() {
    form.put(`/withdrawals/${props.withdrawal.id}`);
}
</script>

<template>
    <AppLayout title="Withdrawal">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-teal-700">{{ withdrawal.reference }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ withdrawal.amount_formatted }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ withdrawal.partner?.name }} · {{ withdrawal.transaction_date_formatted }}</p>
                <div class="mt-3"><StatusBadge :label="withdrawal.status.label" :tone="withdrawal.status.tone" /></div>
            </div>
            <div class="flex flex-wrap gap-3">
                <Link href="/withdrawals" class="btn btn-secondary">Back</Link>
                <button v-if="can.update" class="btn btn-secondary" type="button" @click="editing = true">Edit</button>
                <button v-if="can.cancel" class="btn btn-danger" type="button" @click="router.post(`/withdrawals/${withdrawal.id}/cancel`)">Cancel</button>
                <button v-if="can.reverse" class="btn btn-secondary" type="button" @click="reversing = true">Reverse</button>
            </div>
        </div>
        <section class="card mt-6 grid gap-4 p-5 sm:grid-cols-2">
            <p><span class="text-slate-500">Reason</span><br><span class="font-medium">{{ withdrawal.reason }}</span></p>
            <p><span class="text-slate-500">Account</span><br><span class="font-medium">{{ withdrawal.financial_account }}</span></p>
            <p><span class="text-slate-500">Approvals</span><br><span class="font-medium">{{ withdrawal.approval ? `${withdrawal.approval.completed_approvals} of ${withdrawal.approval.required_approvals}` : '—' }}</span></p>
            <p class="sm:col-span-2"><span class="text-slate-500">Note</span><br><span class="font-medium">{{ withdrawal.note || '—' }}</span></p>
        </section>
        <Modal :open="editing" title="Edit withdrawal" @close="editing = false">
            <form class="grid gap-4" @submit.prevent="save">
                <select v-model="form.partner_id" class="field"><option v-for="partner in partners" :key="partner.id" :value="partner.id">{{ partner.name }}</option></select>
                <input v-model="form.amount" class="field" required>
                <input v-model="form.transaction_date" class="field" type="date" required>
                <input v-model="form.reason" class="field" required>
                <select v-model="form.payment_method" class="field"><option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option></select>
                <select v-model="form.financial_account_id" class="field"><option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option></select>
                <textarea v-model="form.note" class="field" rows="3" />
                <button class="btn btn-primary" type="submit" :disabled="form.processing">Save</button>
            </form>
        </Modal>
        <Modal :open="reversing" title="Reverse withdrawal" @close="reversing = false">
            <form class="grid gap-4" @submit.prevent="reverseForm.post(`/withdrawals/${withdrawal.id}/reverse`)">
                <textarea v-model="reverseForm.reason" class="field" rows="3" required />
                <button class="btn btn-primary" type="submit" :disabled="reverseForm.processing">Post reversal</button>
            </form>
        </Modal>
    </AppLayout>
</template>
