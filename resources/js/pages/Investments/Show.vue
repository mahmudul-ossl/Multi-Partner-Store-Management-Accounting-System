<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    investment: { type: Object, required: true },
    paymentMethods: { type: Array, required: true },
    partners: { type: Array, required: true },
    accounts: { type: Array, required: true },
    can: { type: Object, required: true },
});

const editing = ref(false);
const reversing = ref(false);
const form = useForm({
    partner_id: props.investment.partner_id,
    amount: props.investment.amount,
    transaction_date: props.investment.transaction_date,
    payment_method: props.investment.payment_method,
    financial_account_id: props.investment.financial_account_id,
    note: props.investment.note || '',
});
const reverseForm = useForm({ reason: '' });

function save() {
    form.put(`/investments/${props.investment.id}`);
}

function cancelDocument() {
    router.post(`/investments/${props.investment.id}/cancel`);
}

function reverseDocument() {
    reverseForm.post(`/investments/${props.investment.id}/reverse`);
}
</script>

<template>
    <AppLayout title="Investment">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-teal-700">{{ investment.reference }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ investment.amount_formatted }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ investment.partner?.name }} · {{ investment.transaction_date_formatted }}</p>
                <div class="mt-3"><StatusBadge :label="investment.status.label" :tone="investment.status.tone" /></div>
            </div>
            <div class="flex flex-wrap gap-3">
                <Link href="/investments" class="btn btn-secondary">Back</Link>
                <button v-if="can.update" class="btn btn-secondary" type="button" @click="editing = true">Edit</button>
                <button v-if="can.cancel" class="btn btn-danger" type="button" @click="cancelDocument">Cancel</button>
                <button v-if="can.reverse" class="btn btn-secondary" type="button" @click="reversing = true">Reverse</button>
            </div>
        </div>

        <section class="card mt-6 grid gap-4 p-5 sm:grid-cols-2">
            <p><span class="text-slate-500">Account</span><br><span class="font-medium">{{ investment.financial_account }}</span></p>
            <p><span class="text-slate-500">Method</span><br><span class="font-medium">{{ investment.payment_method }}</span></p>
            <p><span class="text-slate-500">Approvals</span><br><span class="font-medium">{{ investment.approval ? `${investment.approval.completed_approvals} of ${investment.approval.required_approvals}` : '—' }}</span></p>
            <p><span class="text-slate-500">Recorded by</span><br><span class="font-medium">{{ investment.created_by }}</span></p>
            <p class="sm:col-span-2"><span class="text-slate-500">Note</span><br><span class="font-medium">{{ investment.note || '—' }}</span></p>
        </section>

        <Modal :open="editing" title="Edit investment" @close="editing = false">
            <form class="grid gap-4" @submit.prevent="save">
                <select v-model="form.partner_id" class="field">
                    <option v-for="partner in partners" :key="partner.id" :value="partner.id">{{ partner.name }}</option>
                </select>
                <input v-model="form.amount" class="field" required>
                <input v-model="form.transaction_date" class="field" type="date" required>
                <p v-if="form.errors.transaction_date" class="error">{{ form.errors.transaction_date }}</p>
                <select v-model="form.payment_method" class="field">
                    <option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                </select>
                <select v-model="form.financial_account_id" class="field">
                    <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.name }}</option>
                </select>
                <textarea v-model="form.note" class="field" rows="3" />
                <p v-if="form.errors.amount" class="error">{{ form.errors.amount }}</p>
                <button class="btn btn-primary" type="submit" :disabled="form.processing">Save</button>
            </form>
        </Modal>

        <Modal :open="reversing" title="Reverse investment" @close="reversing = false">
            <form class="grid gap-4" @submit.prevent="reverseDocument">
                <label class="label" for="reverse-reason">Reason</label>
                <textarea id="reverse-reason" v-model="reverseForm.reason" class="field" rows="3" required />
                <p v-if="reverseForm.errors.reason" class="error">{{ reverseForm.errors.reason }}</p>
                <button class="btn btn-primary" type="submit" :disabled="reverseForm.processing">Post reversal</button>
            </form>
        </Modal>
    </AppLayout>
</template>
