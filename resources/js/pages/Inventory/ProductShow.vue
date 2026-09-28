<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    product: { type: Object, required: true },
    options: { type: Object, required: true },
    statuses: { type: Array, required: true },
    can: { type: Object, required: true },
});

const form = useForm({
    sku: props.product.sku,
    barcode: props.product.barcode,
    name: props.product.name,
    category_id: props.product.category_id,
    brand_id: props.product.brand_id || '',
    supplier_id: props.product.supplier_id || '',
    unit_id: props.product.unit_id,
    purchase_price: props.product.purchase_price,
    selling_price: props.product.selling_price,
    wholesale_price: props.product.wholesale_price,
    minimum_stock: props.product.minimum_stock,
    reorder_level: props.product.reorder_level,
    status: props.product.status_value,
    description: props.product.description || '',
    image: null,
});

function submit() {
    form.put(`/inventory/products/${props.product.id}`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :title="product.name">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ product.name }}</h1>
                <p class="mt-1 font-mono text-xs text-slate-500">{{ product.sku }} · {{ product.barcode }}</p>
            </div>
            <span v-if="product.low" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">Low stock</span>
        </div>

        <section class="mt-6 grid gap-4 sm:grid-cols-4">
            <div class="card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">On hand</p>
                <p class="mt-2 text-xl font-semibold">{{ product.on_hand }}</p>
            </div>
            <div class="card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Average cost</p>
                <p class="mt-2 text-xl font-semibold">{{ product.average_cost }}</p>
            </div>
            <div class="card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Reorder at</p>
                <p class="mt-2 text-xl font-semibold">{{ product.reorder_level }}</p>
            </div>
            <div class="card p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Selling price</p>
                <p class="mt-2 text-xl font-semibold">{{ product.selling_price_formatted }}</p>
            </div>
        </section>

        <img v-if="product.image_url" :src="product.image_url" alt="" class="mt-6 h-40 rounded-xl object-cover">
        <p v-if="product.description" class="mt-4 max-w-2xl text-sm text-slate-600">{{ product.description }}</p>

        <form v-if="can.update" class="card mt-6 grid gap-3 p-5 md:grid-cols-2" @submit.prevent="submit">
            <div>
                <label class="label">Name</label>
                <input v-model="form.name" class="field" required>
            </div>
            <div>
                <label class="label">SKU</label>
                <input v-model="form.sku" class="field" required>
            </div>
            <div>
                <label class="label">Barcode</label>
                <input v-model="form.barcode" class="field" required>
            </div>
            <div>
                <label class="label">Category</label>
                <select v-model="form.category_id" class="field">
                    <option v-for="row in options.categories" :key="row.id" :value="row.id">{{ row.name }}</option>
                </select>
            </div>
            <div>
                <label class="label">Brand</label>
                <select v-model="form.brand_id" class="field">
                    <option value="">None</option>
                    <option v-for="row in options.brands" :key="row.id" :value="row.id">{{ row.name }}</option>
                </select>
            </div>
            <div>
                <label class="label">Supplier</label>
                <select v-model="form.supplier_id" class="field">
                    <option value="">None</option>
                    <option v-for="row in options.suppliers" :key="row.id" :value="row.id">{{ row.name }}</option>
                </select>
            </div>
            <div>
                <label class="label">Unit</label>
                <select v-model="form.unit_id" class="field">
                    <option v-for="row in options.units" :key="row.id" :value="row.id">{{ row.name }}</option>
                </select>
            </div>
            <div>
                <label class="label">Status</label>
                <select v-model="form.status" class="field">
                    <option v-for="row in statuses" :key="row.value" :value="row.value">{{ row.label }}</option>
                </select>
            </div>
            <div>
                <label class="label">Purchase price</label>
                <input v-model="form.purchase_price" class="field">
            </div>
            <div>
                <label class="label">Selling price</label>
                <input v-model="form.selling_price" class="field">
            </div>
            <div>
                <label class="label">Wholesale price</label>
                <input v-model="form.wholesale_price" class="field">
            </div>
            <div>
                <label class="label">Minimum stock</label>
                <input v-model="form.minimum_stock" class="field">
            </div>
            <div>
                <label class="label">Reorder level</label>
                <input v-model="form.reorder_level" class="field">
            </div>
            <div class="md:col-span-2">
                <label class="label">Description</label>
                <textarea v-model="form.description" class="field" rows="3" />
            </div>
            <div class="md:col-span-2">
                <label class="label">Replace image</label>
                <input class="field" type="file" accept="image/*" @input="form.image = $event.target.files[0]">
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button class="btn btn-primary" :disabled="form.processing" type="submit">Save changes</button>
            </div>
        </form>
    </AppLayout>
</template>
