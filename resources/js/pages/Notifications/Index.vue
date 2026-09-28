<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import Pagination from '../../Components/Pagination.vue';
import { router } from '@inertiajs/vue3';

defineProps({
    notifications: { type: Object, required: true },
});

function markRead(id) {
    router.post(`/notifications/${id}/read`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Notifications">
        <h1 class="text-2xl font-semibold tracking-tight">Notifications</h1>
        <p class="mt-1 text-sm text-slate-500">Payment due and low stock stay separate from approval requests in the bell. This list is every alert for your account.</p>

        <section class="card mt-6 overflow-hidden">
            <div v-if="notifications.data.length === 0" class="px-5 py-8 text-sm text-slate-500">No notifications.</div>
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="item in notifications.data" :key="item.id" class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-900">
                            {{ item.title }}
                            <span v-if="!item.read" class="ml-2 rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-800">Unread</span>
                        </p>
                        <p class="mt-1 text-sm text-slate-600">{{ item.message }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ item.created_at }}</p>
                    </div>
                    <button v-if="!item.read" class="btn btn-secondary mt-2 sm:mt-0" type="button" @click="markRead(item.id)">Mark read</button>
                </li>
            </ul>
            <Pagination :meta="notifications" url="/notifications" />
        </section>
    </AppLayout>
</template>
