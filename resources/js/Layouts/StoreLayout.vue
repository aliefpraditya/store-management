<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppShell from './AppShell.vue';

defineProps({
    title: { type: String, required: true },
});

const page = usePage();

const store = computed(() => page.props.store);

const user = computed(() => page.props.auth.user);

const permissions = computed(() => page.props.auth.permissions);

const brand = computed(() => ({ initial: store.value.receipt_prefix, name: store.value.name, sub: 'Toko' }));

/** The till is the main feature, so it is a large button pinned under the store name instead of one more menu entry. */
const cashierAction = computed(() =>
    permissions.value.includes('cashier.use') ? { label: 'Kasir', icon: 'shopping_cart', href: store.value.cashier_url } : null,
);

const centralAction = computed(() =>
    user.value.is_super_admin ? { label: 'Kembali ke Pusat', icon: 'arrow_back', href: page.props.app_urls.central_dashboard } : null,
);

/**
 * Sidebar of a store. Entries without a `href` are planned R1 screens that are not built yet.
 */
const menuGroups = computed(() => {
    const groups = [
        {
            label: null,
            items: [{ label: 'Dashboard', icon: 'space_dashboard', href: store.value.dashboard_url, isExact: true, can: ['dashboard.view'] }],
        },
        { label: 'Barang', items: [{ label: 'Daftar Barang', icon: 'inventory_2', href: store.value.products_url, can: ['products.manage'] }] },
        {
            label: 'Stok',
            items: [
                { label: 'Stok Opname', icon: 'fact_check', href: store.value.stock_opnames_url, can: ['stock_opnames.count', 'stock_opnames.post'] },
                { label: 'Histori Stok', icon: 'layers', href: store.value.stock_history_url, can: ['stock_history.view'] },
            ],
        },
        {
            label: 'Pembelian',
            items: [
                { label: 'Supplier', icon: 'storefront', href: store.value.suppliers_url, can: ['suppliers.manage'] },
                { label: 'Nota Pembelian', icon: 'request_quote', href: store.value.purchases_url, can: ['purchases.manage'] },
            ],
        },
        {
            label: 'Penjualan',
            items: [
                { label: 'Riwayat Nota', icon: 'receipt_long', href: store.value.sales_url, badge: page.props.menu_badges?.sales ?? 0, badgeHint: 'nota yang belum diantar', can: ['sales.view'] },
                { label: 'Surat Jalan', icon: 'local_shipping', href: store.value.delivery_notes_url, badge: page.props.menu_badges?.delivery_notes ?? 0, badgeHint: 'nota COD yang uangnya belum diterima', can: ['delivery_notes.view'] },
                { label: 'Retur', icon: 'undo', href: store.value.returns_url, badge: page.props.menu_badges?.returns ?? 0, badgeHint: 'retur menunggu persetujuan', can: ['returns.manage'] },
            ],
        },
        {
            label: 'Kas & Harian',
            items: [
                { label: 'Rekap Shift', icon: 'schedule', href: store.value.shifts_url, can: ['shifts.review'] },
                { label: 'Pengeluaran', icon: 'remove_circle_outline', href: store.value.expenses_url, can: ['expenses.manage'] },
                { label: 'Tutup Hari', icon: 'lock', href: store.value.daily_closings_url, can: ['daily_closings.manage'] },
            ],
        },
        {
            label: 'Laporan',
            items: [
                { label: 'Laporan Penjualan', icon: 'bar_chart', href: store.value.reports_url, can: ['reports.view'] },
                { label: 'Laporan Stok', icon: 'inventory', href: store.value.stock_report_url, can: ['reports.view'] },
            ],
        },
        {
            label: 'Pengaturan',
            items: [
                { label: 'Pengaturan Toko', icon: 'settings', href: store.value.settings_url, can: ['settings.manage'] },
            ],
        },
    ];

    return groups
        .map((group) => ({ ...group, items: group.items.filter((item) => !item.can || item.can.some((permission) => permissions.value.includes(permission))) }))
        .filter((group) => group.items.length > 0);
});
</script>

<template>
    <AppShell :title="title" :menu-groups="menuGroups" :brand="brand" :primary-action="cashierAction" :back-action="centralAction" :role-label="page.props.auth.role_label">
        <template v-if="$slots.back" #back>
            <slot name="back" />
        </template>

        <template #actions>
            <slot name="actions" />
        </template>

        <slot />
    </AppShell>
</template>
