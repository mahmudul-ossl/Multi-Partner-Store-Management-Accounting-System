<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useCan } from '../../composables/useCan';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Pagination.vue';

const props = defineProps({
    products: { type: Object, required: true },
    filters: { type: Object, required: true },
    options: { type: Object, required: true },
    statuses: { type: Array, required: true },
});

const { can } = useCan();
const search = ref(props.filters.search);
const showForm = ref(false);
const form = useForm({
    sku: '',
    barcode: '',
    name: '',
    category_id: props.options.categories[0]?.id || '',
    brand_id: '',
    supplier_id: '',
    unit_id: props.options.units[0]?.id || '',
    purchase_price: '0.00',
    selling_price: '0.00',
    wholesale_price: '0.00',
    minimum_stock: '0',
    reorder_level: '0',
    status: 'active',
    description: '',
    image: null,
});

function applyFilters() {
    router.get('/inventory/products', { search: search.value }, { preserveState: true, replace: true });
}

function submit() {
    form.post('/inventory/products', { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Products">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Products</h1>
                <p class="mt-1 text-sm text-slate-500">SKU and barcode are unique. Stock changes only through purchases, returns, and approved adjustments.</p>
            </div>
            <button v-if="can('product.manage')" class="btn btn-primary" type="button" @click="showForm = true">Add product</button>
        </div>

        <form class="card mt-6 flex gap-3 p-4" @submit.prevent="applyFilters">
            <input v-model="search" class="field" placeholder="Name, SKU, or barcode">
            <button class="btn btn-secondary" type="submit">Search</button>
        </form>

        <section class="card mt-4 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Product</th>
                            <th class="px-4 py-3 font-semibold">SKU</th>
                            <th class="px-4 py-3 font-semibold">Selling</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in products.data" :key="row.id" class="border-t border-slate-100">
                            <td class="px-4 py-3">
                                <Link :href="`/inventory/products/${row.id}`" class="font-medium text-teal-800 hover:underline">{{ row.name }}</Link>
                                <p class="text-xs text-slate-500">{{ row.category }} · {{ row.barcode }}</p>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ row.sku }}</td>
                            <td class="px-4 py-3">{{ row.selling_price_formatted }}</td>
                            <td class="px-4 py-3">{{ row.status }}</td>
                        </tr>
                        <tr v-if="products.data.length === 0">
                            <td class="px-4 py-8 text-center text-slate-500" colspan="4">No products yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :meta="products" :filters="{ search }" url="/inventory/products" />
        </section>

        <Modal :open="showForm" title="Add product" @close="showForm = false">
            <form class="grid max-h-[70vh] gap-3 overflow-y-auto md:grid-cols-2" @submit.prevent="submit">
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
                    <select v-model="form.category_id" class="field" required>
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
                    <label class="label">Preferred supplier</label>
                    <select v-model="form.supplier_id" class="field">
                        <option value="">None</option>
                        <option v-for="row in options.suppliers" :key="row.id" :value="row.id">{{ row.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Unit</label>
                    <select v-model="form.unit_id" class="field" required>
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
                    <input v-model="form.purchase_price" class="field" required>
                </div>
                <div>
                    <label class="label">Selling price</label>
                    <input v-model="form.selling_price" class="field" required>
                </div>
                <div>
                    <label class="label">Wholesale price</label>
                    <input v-model="form.wholesale_price" class="field" required>
                </div>
                <div>
                    <label class="label">Minimum stock</label>
                    <input v-model="form.minimum_stock" class="field" required>
                </div>
                <div>
                    <label class="label">Reorder level</label>
                    <input v-model="form.reorder_level" class="field" required>
                </div>
                <div class="md:col-span-2">
                    <label class="label">Description</label>
                    <textarea v-model="form.description" class="field" rows="2" />
                </div>
                <div class="md:col-span-2">
                    <label class="label">Image</label>
                    <input class="field" type="file" accept="image/*" @input="form.image = $event.target.files[0]">
                </div>
                <p v-if="form.errors.sku || form.errors.barcode" class="text-sm text-rose-700 md:col-span-2">{{ form.errors.sku || form.errors.barcode }}</p>
                <div class="md:col-span-2 flex justify-end">
                    <button class="btn btn-primary" :disabled="form.processing" type="submit">Save product</button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
