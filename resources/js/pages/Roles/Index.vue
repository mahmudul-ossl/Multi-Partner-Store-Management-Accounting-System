<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    roles: { type: Array, required: true },
    permissions: { type: Array, required: true },
});

function granted(role, permission) {
    return role.permissions.includes(permission);
}
</script>

<template>
    <AppLayout title="Roles">
        <h1 class="text-2xl font-semibold tracking-tight">Roles and permissions</h1>
        <p class="mt-1 max-w-3xl text-sm text-slate-500">
            The full permission set is seeded now so later phases can authorize investments, withdrawals, stock, and reports without a new access model. Assign a role from the user form.
        </p>

        <section class="card mt-6 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="sticky left-0 bg-slate-50 px-4 py-3 font-semibold">Permission</th>
                        <th v-for="role in roles" :key="role.name" class="px-4 py-3 font-semibold">{{ role.name }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="permission in permissions" :key="permission" class="border-t border-slate-100">
                        <td class="sticky left-0 bg-white px-4 py-2 font-mono text-xs text-slate-700">{{ permission }}</td>
                        <td v-for="role in roles" :key="role.name + permission" class="px-4 py-2 text-center">
                            <span v-if="granted(role, permission)" class="text-teal-700" aria-label="Granted">Yes</span>
                            <span v-else class="text-slate-300">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
