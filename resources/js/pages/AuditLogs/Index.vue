<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Pagination from '../../Components/Pagination.vue';
import StatusBadge from '../../Components/StatusBadge.vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, required: true },
    actions: { type: Array, required: true },
});

const search = ref(props.filters.search);
const action = ref(props.filters.action);
const openId = ref(null);

function applyFilters() {
    router.get('/audit-logs', { search: search.value, action: action.value }, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout title="Audit log">
        <h1 class="text-2xl font-semibold tracking-tight">Audit log</h1>
        <p class="mt-1 text-sm text-slate-500">Sign-in, sign-out, and changes to users and partners. Entries cannot be edited.</p>

        <form class="card mt-6 grid gap-3 p-4 md:grid-cols-4" @submit.prevent="applyFilters">
            <div class="md:col-span-2">
                <label class="label" for="audit-search">Search</label>
                <input id="audit-search" v-model="search" class="field" placeholder="User, action, or IP">
            </div>
            <div>
                <label class="label" for="audit-action">Action</label>
                <select id="audit-action" v-model="action" class="field">
                    <option value="">All actions</option>
                    <option v-for="item in actions" :key="item.value" :value="item.value">{{ item.label }}</option>
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
                            <th class="px-4 py-3 font-semibold">When</th>
                            <th class="px-4 py-3 font-semibold">Who</th>
                            <th class="px-4 py-3 font-semibold">Action</th>
                            <th class="px-4 py-3 font-semibold">Record</th>
                            <th class="px-4 py-3 font-semibold">IP</th>
                            <th class="px-4 py-3 font-semibold" />
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="log in logs.data" :key="log.id">
                            <tr class="border-t border-slate-100">
                                <td class="px-4 py-3 text-slate-600">{{ log.created_at }}</td>
                                <td class="px-4 py-3">{{ log.user?.name || 'System' }}</td>
                                <td class="px-4 py-3"><StatusBadge :label="log.action.label" :tone="log.action.tone" /></td>
                                <td class="px-4 py-3">{{ log.model || '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ log.ip_address || '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <button class="text-sm font-semibold text-teal-700" type="button" @click="openId = openId === log.id ? null : log.id">
                                        {{ openId === log.id ? 'Hide' : 'Values' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="openId === log.id" class="border-t border-slate-100 bg-slate-50">
                                <td colspan="6" class="px-4 py-3">
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <pre class="overflow-x-auto rounded-lg bg-white p-3 text-xs text-slate-700">{{ JSON.stringify(log.old_values, null, 2) }}</pre>
                                        <pre class="overflow-x-auto rounded-lg bg-white p-3 text-xs text-slate-700">{{ JSON.stringify(log.new_values, null, 2) }}</pre>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!logs.data.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">No audit entries yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="logs" :filters="{ search, action }" url="/audit-logs" />
        </section>
    </AppLayout>
</template>
