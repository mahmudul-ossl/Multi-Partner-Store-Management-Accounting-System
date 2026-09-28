<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    customers: { type: Array, required: true },
    can: { type: Object, required: true },
});

const form = useForm({ name: '', phone: '', email: '', address: '', notes: '' });
</script>

<template>
    <AppLayout title="Customers">
        <h1 class="text-2xl font-semibold tracking-tight">Customers</h1>
        <p class="mt-1 text-sm text-slate-500">Dues are the remaining balance on completed sales, after returns, payments, and refunds.</p>

        <form v-if="can.manage" class="card mt-6 grid gap-3 p-4 md:grid-cols-3" @submit.prevent="form.post('/sales/customers')">
            <input v-model="form.name" class="field" placeholder="Customer name" required>
            <input v-model="form.phone" class="field" placeholder="Phone">
            <input v-model="form.email" class="field" placeholder="Email">
            <input v-model="form.address" class="field md:col-span-2" placeholder="Address">
            <button class="btn btn-primary" type="submit">Add customer</button>
        </form>

        <section class="card mt-4 overflow-hidden">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Name</th>
                        <th class="px-4 py-3 font-semibold">Phone</th>
                        <th class="px-4 py-3 font-semibold">Due</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in customers" :key="row.id" class="border-t border-slate-100">
                        <td class="px-4 py-3">
                            <Link :href="`/sales/customers/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.name }}</Link>
                        </td>
                        <td class="px-4 py-3">{{ row.phone || '—' }}</td>
                        <td class="px-4 py-3 font-medium">{{ row.due }}</td>
                    </tr>
                    <tr v-if="customers.length === 0">
                        <td class="px-4 py-8 text-center text-slate-500" colspan="3">No customers yet.</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
