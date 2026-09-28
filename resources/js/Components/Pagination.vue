<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    meta: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    url: { type: String, required: true },
});

function go(page) {
    if (page < 1 || page > props.meta.last_page || page === props.meta.current_page) {
        return;
    }

    router.get(props.url, { ...props.filters, page }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-500">
            <template v-if="meta.total">
                Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }}
            </template>
            <template v-else>No records</template>
        </p>
        <div class="flex items-center gap-2">
            <button class="btn btn-secondary" type="button" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">
                Previous
            </button>
            <span class="text-sm text-slate-600">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
            <button class="btn btn-secondary" type="button" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">
                Next
            </button>
        </div>
    </div>
</template>
