<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    approval: { type: Object, required: true },
});

const mode = ref('');
const form = useForm({ comment: '' });

function submit() {
    const url = mode.value === 'reject'
        ? `/approvals/${props.approval.id}/reject`
        : `/approvals/${props.approval.id}/approve`;
    form.post(url, { onSuccess: () => { mode.value = ''; form.reset(); } });
}
</script>

<template>
    <AppLayout title="Approval">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-teal-700">{{ approval.type.label }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ approval.amount_formatted }}</h1>
                <p class="mt-1 text-sm text-slate-500">{{ approval.partner_label }} · requested by {{ approval.requester }}</p>
                <div class="mt-3"><StatusBadge :label="approval.status.label" :tone="approval.status.tone" /></div>
            </div>
            <div class="flex flex-wrap gap-3">
                <Link href="/approvals" class="btn btn-secondary">Back</Link>
                <button v-if="approval.can_decide" class="btn btn-primary" type="button" @click="mode = 'approve'">Approve</button>
                <button v-if="approval.can_decide" class="btn btn-danger" type="button" @click="mode = 'reject'">Reject</button>
            </div>
        </div>

        <section class="card mt-6 grid gap-4 p-5 sm:grid-cols-3">
            <p><span class="text-slate-500">Progress</span><br><span class="font-medium">{{ approval.completed_approvals }} of {{ approval.required_approvals }}</span></p>
            <p><span class="text-slate-500">Requested</span><br><span class="font-medium">{{ approval.requested_at }}</span></p>
            <p><span class="text-slate-500">Reference</span><br><span class="font-medium">{{ approval.document?.reference || '—' }}</span></p>
            <p class="sm:col-span-3"><span class="text-slate-500">Note</span><br><span class="font-medium">{{ approval.note || '—' }}</span></p>
        </section>

        <section class="card mt-6 overflow-hidden">
            <h2 class="border-b border-slate-200 px-5 py-4 font-semibold">History</h2>
            <ul>
                <li v-for="action in approval.actions" :key="action.id" class="border-t border-slate-100 px-5 py-3 text-sm">
                    <p class="font-medium">{{ action.action }} · {{ action.user }}</p>
                    <p class="text-slate-500">{{ action.created_at }}</p>
                    <p v-if="action.comment" class="mt-1">{{ action.comment }}</p>
                </li>
            </ul>
        </section>

        <Modal :open="mode !== ''" :title="mode === 'reject' ? 'Reject request' : 'Approve request'" @close="mode = ''">
            <form class="grid gap-4" @submit.prevent="submit">
                <label class="label" for="decision-note">{{ mode === 'reject' ? 'Note (required)' : 'Note' }}</label>
                <textarea id="decision-note" v-model="form.comment" class="field" rows="4" :required="mode === 'reject'" />
                <p v-if="form.errors.comment" class="error">{{ form.errors.comment }}</p>
                <button class="btn" :class="mode === 'reject' ? 'btn-danger' : 'btn-primary'" type="submit" :disabled="form.processing">
                    {{ mode === 'reject' ? 'Reject' : 'Approve' }}
                </button>
            </form>
        </Modal>
    </AppLayout>
</template>
