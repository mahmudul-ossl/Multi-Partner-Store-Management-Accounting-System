<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useCan } from '../composables/useCan';

const props = defineProps({
    title: { type: String, required: true },
});

const page = usePage();
const { can } = useCan();
const sidebarOpen = ref(false);
const toasts = ref([]);

const navigation = computed(() => [
    { label: 'Dashboard', href: '/dashboard', show: true },
    { label: 'Reports', href: '/reports', show: can('report.view') },
    { label: 'Approvals', href: '/approvals', show: can('approval.view') || can('partner.investment.approve') || can('partner.withdrawal.approve') || can('partner.transfer.approve') },
    { label: 'Investments', href: '/investments', show: can('partner.investment.view') },
    { label: 'Withdrawals', href: '/withdrawals', show: can('partner.withdrawal.view') },
    { label: 'Transfers', href: '/transfers', show: can('partner.transfer.view') },
    { label: 'My partnership', href: page.props.auth.user?.partner_id ? `/partners/${page.props.auth.user.partner_id}/dashboard` : '/partners', show: Boolean(page.props.auth.user?.partner_id) },
    { label: 'My profile', href: page.props.auth.user?.partner_id ? `/partners/${page.props.auth.user.partner_id}/profile` : '/partners', show: Boolean(page.props.auth.user?.partner_id) },
    { label: 'Products', href: '/inventory/products', show: can('product.view') },
    { label: 'Catalog', href: '/inventory/catalog', show: can('product.view') },
    { label: 'Suppliers', href: '/inventory/suppliers', show: can('supplier.view') },
    { label: 'Purchases', href: '/inventory/purchases', show: can('purchase.view') },
    { label: 'Purchase returns', href: '/inventory/returns', show: can('purchase.view') },
    { label: 'Stock', href: '/inventory/stock', show: can('stock.view') },
    { label: 'Stock movements', href: '/inventory/stock/movements', show: can('stock.view') },
    { label: 'Low stock', href: '/inventory/stock/low', show: can('stock.view') },
    { label: 'Stock adjustments', href: '/inventory/adjustments', show: can('stock.view') },
    { label: 'Customers', href: '/sales/customers', show: can('customer.view') },
    { label: 'Sales', href: '/sales/orders', show: can('sale.view') },
    { label: 'Sales returns', href: '/sales/returns', show: can('sale.view') },
    { label: 'Promotions', href: '/promotions', show: can('promotion.view') },
    { label: 'Expenses', href: '/expenses', show: can('expense.view') },
    { label: 'Partners', href: '/partners', show: can('partner.view') },
    { label: 'Users', href: '/users', show: can('user.manage') },
    { label: 'Roles', href: '/roles', show: can('role.manage') },
    { label: 'Chart of accounts', href: '/accounting/chart', show: can('accounting.view') },
    { label: 'Cash & bank', href: '/accounting/accounts', show: can('accounting.view') },
    { label: 'Manual journals', href: '/accounting/manual-journals', show: can('accounting.view') },
    { label: 'Account transfers', href: '/accounting/transfers', show: can('accounting.view') },
    { label: 'Journal', href: '/accounting/entries', show: can('accounting.view') },
    { label: 'General ledger', href: '/accounting/reports/general-ledger', show: can('accounting.view') },
    { label: 'Cash report', href: '/accounting/reports/cash', show: can('accounting.view') },
    { label: 'Bank report', href: '/accounting/reports/bank', show: can('accounting.view') },
    { label: 'Profit & loss', href: '/accounting/reports/profit-loss', show: can('profit_loss.view') },
    { label: 'Balance sheet', href: '/accounting/reports/balance-sheet', show: can('balance_sheet.view') },
    { label: 'Trial balance', href: '/accounting/reports/trial-balance', show: can('trial_balance.view') },
    { label: 'Profit allocations', href: '/accounting/allocations', show: can('accounting.view') },
    { label: 'Period close', href: '/accounting/periods', show: can('accounting.view') },
    { label: 'Approval settings', href: '/settings/approvals', show: can('settings.manage') },
    { label: 'Audit log', href: '/audit-logs', show: can('audit_log.view') },
].filter((item) => item.show));

const later = [];
const bellOpen = ref(false);
const notifications = computed(() => page.props.notifications ?? { unread: 0, operational: [], approvals: [] });

const crumbs = computed(() => {
    const path = page.url.split('?')[0];
    const items = [{ label: 'Dashboard', href: '/dashboard' }];

    if (path.startsWith('/partners')) {
        items.push({ label: 'Partners', href: '/partners' });
        if (path.includes('/profile')) {
            items.push({ label: 'Profile' });
        } else if (path !== '/partners') {
            items.push({ label: 'Details' });
        }
    } else if (path.startsWith('/users')) {
        items.push({ label: 'Users' });
    } else if (path.startsWith('/roles')) {
        items.push({ label: 'Roles' });
    } else if (path.startsWith('/notifications')) {
        items.push({ label: 'Notifications' });
    } else if (path.startsWith('/audit-logs')) {
        items.push({ label: 'Audit log' });
    } else if (path.startsWith('/approvals')) {
        items.push({ label: 'Approvals', href: '/approvals' });
    } else if (path.startsWith('/investments')) {
        items.push({ label: 'Investments', href: '/investments' });
    } else if (path.startsWith('/withdrawals')) {
        items.push({ label: 'Withdrawals', href: '/withdrawals' });
    } else if (path.startsWith('/transfers')) {
        items.push({ label: 'Transfers', href: '/transfers' });
    } else if (path.startsWith('/accounting/chart')) {
        items.push({ label: 'Chart of accounts' });
    } else if (path.startsWith('/accounting/accounts')) {
        items.push({ label: 'Cash & bank', href: '/accounting/accounts' });
    } else if (path.startsWith('/accounting/manual-journals')) {
        items.push({ label: 'Manual journals', href: '/accounting/manual-journals' });
    } else if (path.startsWith('/accounting/transfers')) {
        items.push({ label: 'Account transfers', href: '/accounting/transfers' });
    } else if (path.startsWith('/accounting/entries')) {
        items.push({ label: 'Journal', href: '/accounting/entries' });
    } else if (path.startsWith('/accounting/reports/general-ledger')) {
        items.push({ label: 'General ledger' });
    } else if (path.startsWith('/accounting/reports/cash')) {
        items.push({ label: 'Cash report' });
    } else if (path.startsWith('/accounting/reports/bank')) {
        items.push({ label: 'Bank report' });
    } else if (path.startsWith('/accounting/reports/profit-loss')) {
        items.push({ label: 'Profit & loss' });
    } else if (path.startsWith('/accounting/reports/balance-sheet')) {
        items.push({ label: 'Balance sheet' });
    } else if (path.startsWith('/accounting/reports/trial-balance')) {
        items.push({ label: 'Trial balance' });
    } else if (path.startsWith('/accounting/allocations')) {
        items.push({ label: 'Profit allocations', href: '/accounting/allocations' });
    } else if (path.startsWith('/accounting/periods')) {
        items.push({ label: 'Period close' });
    } else if (path.startsWith('/inventory/products')) {
        items.push({ label: 'Products', href: '/inventory/products' });
    } else if (path.startsWith('/inventory/catalog')) {
        items.push({ label: 'Catalog' });
    } else if (path.startsWith('/inventory/suppliers')) {
        items.push({ label: 'Suppliers', href: '/inventory/suppliers' });
    } else if (path.startsWith('/inventory/purchases')) {
        items.push({ label: 'Purchases', href: '/inventory/purchases' });
    } else if (path.startsWith('/inventory/returns')) {
        items.push({ label: 'Purchase returns', href: '/inventory/returns' });
    } else if (path.startsWith('/inventory/stock/low')) {
        items.push({ label: 'Low stock' });
    } else if (path.startsWith('/inventory/stock/movements')) {
        items.push({ label: 'Stock movements' });
    } else if (path.startsWith('/inventory/stock')) {
        items.push({ label: 'Stock' });
    } else if (path.startsWith('/inventory/adjustments')) {
        items.push({ label: 'Stock adjustments' });
    } else if (path.startsWith('/sales/customers')) {
        items.push({ label: 'Customers', href: '/sales/customers' });
    } else if (path.startsWith('/sales/orders')) {
        items.push({ label: 'Sales', href: '/sales/orders' });
    } else if (path.startsWith('/sales/returns')) {
        items.push({ label: 'Sales returns', href: '/sales/returns' });
    } else if (path.startsWith('/promotions')) {
        items.push({ label: 'Promotions', href: '/promotions' });
    } else if (path.startsWith('/expenses')) {
        items.push({ label: 'Expenses', href: '/expenses' });
    } else if (path.startsWith('/settings')) {
        items.push({ label: 'Approval settings' });
    } else if (path.startsWith('/reports')) {
        items.push({ label: 'Reports', href: '/reports' });
    }

    return items;
});

const user = computed(() => page.props.auth.user);

watch(() => page.props.flash, (flash) => {
    if (flash?.success) {
        pushToast(flash.success, 'success');
    }
    if (flash?.error) {
        pushToast(flash.error, 'error');
    }
}, { deep: true, immediate: true });

function pushToast(message, tone) {
    const id = `${Date.now()}-${Math.random()}`;
    toasts.value.push({ id, message, tone });
    setTimeout(() => {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }, 4000);
}

function logout() {
    router.post('/logout');
}

function markNotification(id) {
    router.post(`/notifications/${id}/read`, {}, { preserveScroll: true, onSuccess: () => { bellOpen.value = false; } });
}

function markAllNotifications() {
    router.post('/notifications/read-all', {}, { preserveScroll: true, onSuccess: () => { bellOpen.value = false; } });
}

function isActive(href) {
    const path = page.url.split('?')[0];
    return path === href || (href !== '/dashboard' && path.startsWith(href));
}
</script>

<template>
    <div class="min-h-screen">
        <Head :title="props.title" />

        <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden" @click="sidebarOpen = false" />

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-slate-950 text-slate-200 transition-transform lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-500 text-sm font-bold text-white">৳</div>
                <div>
                    <p class="text-sm font-semibold text-white">{{ page.props.app.name }}</p>
                    <p class="text-xs text-slate-400">Partner operations</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    class="block rounded-lg px-3 py-2 text-sm font-medium"
                    :class="isActive(item.href) ? 'bg-teal-600 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white'"
                    @click="sidebarOpen = false"
                >
                    {{ item.label }}
                </Link>

                <template v-if="later.length">
                    <p class="px-3 pt-6 text-xs font-semibold uppercase tracking-wider text-slate-500">Coming later</p>
                    <p v-for="item in later" :key="item" class="px-3 py-1.5 text-sm text-slate-500">
                        {{ item }}
                    </p>
                </template>
            </nav>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6">
                <div class="flex items-center gap-3">
                    <button class="btn btn-secondary px-2.5 lg:hidden" type="button" @click="sidebarOpen = true">Menu</button>
                    <nav class="flex flex-wrap items-center gap-2 text-sm text-slate-500" aria-label="Breadcrumb">
                        <template v-for="(crumb, index) in crumbs" :key="crumb.label">
                            <span v-if="index > 0">/</span>
                            <Link v-if="crumb.href && index < crumbs.length - 1" :href="crumb.href" class="hover:text-teal-700">{{ crumb.label }}</Link>
                            <span v-else class="font-medium text-slate-800">{{ crumb.label }}</span>
                        </template>
                    </nav>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <button class="btn btn-secondary px-2.5" type="button" aria-label="Notifications" @click="bellOpen = !bellOpen">
                            Bell
                            <span v-if="notifications.unread" class="ml-1 rounded-full bg-teal-600 px-1.5 text-xs text-white">{{ notifications.unread }}</span>
                        </button>
                        <div v-if="bellOpen" class="absolute right-0 z-30 mt-2 w-96 rounded-xl border border-slate-200 bg-white p-3 shadow-lg">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-sm font-semibold text-slate-900">Notifications</p>
                                <button v-if="notifications.unread" class="text-xs font-semibold text-teal-700" type="button" @click="markAllNotifications">Mark all read</button>
                            </div>
                            <p class="px-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Payment and stock</p>
                            <p v-if="!notifications.operational?.length" class="px-2 py-2 text-sm text-slate-500">No operational alerts.</p>
                            <button
                                v-for="item in notifications.operational"
                                :key="item.id"
                                class="block w-full rounded-lg px-2 py-2 text-left hover:bg-slate-50"
                                type="button"
                                @click="markNotification(item.id)"
                            >
                                <p class="text-sm font-medium text-slate-900">{{ item.title }}</p>
                                <p class="text-xs text-slate-500">{{ item.message }}</p>
                            </button>
                            <p class="mt-2 px-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Approvals</p>
                            <p v-if="!notifications.approvals?.length" class="px-2 py-2 text-sm text-slate-500">No approval alerts.</p>
                            <button
                                v-for="item in notifications.approvals"
                                :key="item.id"
                                class="block w-full rounded-lg px-2 py-2 text-left hover:bg-slate-50"
                                type="button"
                                @click="markNotification(item.id)"
                            >
                                <p class="text-sm font-medium text-slate-900">{{ item.title }}</p>
                                <p class="text-xs text-slate-500">{{ item.message }}</p>
                            </button>
                            <Link href="/notifications" class="mt-2 block px-2 text-sm font-semibold text-teal-700" @click="bellOpen = false">View all</Link>
                        </div>
                    </div>
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold text-slate-900">{{ user?.name }}</p>
                        <p class="text-xs text-slate-500">{{ user?.roles?.[0] }}</p>
                    </div>
                    <button class="btn btn-secondary" type="button" @click="logout">Log out</button>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <slot />
            </main>
        </div>

        <div class="pointer-events-none fixed bottom-4 right-4 z-50 flex w-full max-w-sm flex-col gap-2 px-4">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto rounded-xl px-4 py-3 text-sm font-medium text-white shadow-lg"
                :class="toast.tone === 'error' ? 'bg-red-600' : 'bg-slate-900'"
            >
                {{ toast.message }}
            </div>
        </div>
    </div>
</template>
