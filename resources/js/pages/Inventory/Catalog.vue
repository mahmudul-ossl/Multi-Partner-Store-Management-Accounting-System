<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    categories: { type: Array, required: true },
    brands: { type: Array, required: true },
    units: { type: Array, required: true },
    can: { type: Object, required: true },
});

const category = useForm({ name: '', description: '' });
const brand = useForm({ name: '' });
const unit = useForm({ name: '', abbreviation: '' });
</script>

<template>
    <AppLayout title="Catalog">
        <h1 class="text-2xl font-semibold tracking-tight">Catalog</h1>
        <p class="mt-1 text-sm text-slate-500">Categories, brands, and units used on products in the purchase log.</p>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <section class="card p-5">
                <h2 class="font-semibold">Categories</h2>
                <ul class="mt-3 space-y-1 text-sm">
                    <li v-for="row in categories" :key="row.id">{{ row.name }}</li>
                    <li v-if="categories.length === 0" class="text-slate-500">None yet.</li>
                </ul>
                <form v-if="can.manage" class="mt-4 grid gap-2" @submit.prevent="category.post('/inventory/categories')">
                    <input v-model="category.name" class="field" placeholder="Category name" required>
                    <input v-model="category.description" class="field" placeholder="Description">
                    <button class="btn btn-secondary" type="submit">Add category</button>
                </form>
            </section>
            <section class="card p-5">
                <h2 class="font-semibold">Brands</h2>
                <ul class="mt-3 space-y-1 text-sm">
                    <li v-for="row in brands" :key="row.id">{{ row.name }}</li>
                    <li v-if="brands.length === 0" class="text-slate-500">None yet.</li>
                </ul>
                <form v-if="can.manage" class="mt-4 grid gap-2" @submit.prevent="brand.post('/inventory/brands')">
                    <input v-model="brand.name" class="field" placeholder="Brand name" required>
                    <button class="btn btn-secondary" type="submit">Add brand</button>
                </form>
            </section>
            <section class="card p-5">
                <h2 class="font-semibold">Units</h2>
                <ul class="mt-3 space-y-1 text-sm">
                    <li v-for="row in units" :key="row.id">{{ row.name }} ({{ row.abbreviation }})</li>
                    <li v-if="units.length === 0" class="text-slate-500">None yet.</li>
                </ul>
                <form v-if="can.manage" class="mt-4 grid gap-2" @submit.prevent="unit.post('/inventory/units')">
                    <input v-model="unit.name" class="field" placeholder="Unit name" required>
                    <input v-model="unit.abbreviation" class="field" placeholder="Abbreviation" required>
                    <button class="btn btn-secondary" type="submit">Add unit</button>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
