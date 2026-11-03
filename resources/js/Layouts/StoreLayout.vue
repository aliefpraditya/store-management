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

const brand = computed(() => ({ initial: store.value.receipt_prefix, name: store.value.name, sub: 'Toko' }));

/**
 * Sidebar of a store. Entries without a `href` are planned R1 screens that are not built yet.
 */
const menuGroups = computed(() => {
    const groups = [
        {
            label: null,
            items: [{ label: 'Dashboard', icon: 'space_dashboard', href: store.value.dashboard_url, isExact: true }],
        },
        { label: 'Barang', items: [{ label: 'Daftar Barang', icon: 'inventory_2', href: store.value.products_url }] },
        {
            label: 'Stok',
            items: [
                { label: 'Stok Opname', icon: 'fact_check', href: store.value.stock_opnames_url },
                { label: 'Histori Stok', icon: 'layers', href: store.value.stock_history_url },
            ],
        },
        {
            label: 'Penjualan',
            items: [
                { label: 'Kasir', icon: 'shopping_cart', href: store.value.cashier_url },
                { label: 'Riwayat Nota', icon: 'receipt_long', href: store.value.sales_url },
                { label: 'Surat Jalan', icon: 'local_shipping', href: store.value.delivery_notes_url },
                { label: 'Retur', icon: 'undo', href: store.value.returns_url },
            ],
        },
        {
            label: 'Kas & Harian',
            items: [
                { label: 'Rekap Shift', icon: 'schedule', href: store.value.shifts_url },
                { label: 'Pengeluaran', icon: 'remove_circle_outline', href: store.value.expenses_url },
                { label: 'Tutup Hari', icon: 'lock', href: store.value.daily_closings_url },
                { label: 'Laporan', icon: 'bar_chart', href: store.value.reports_url },
            ],
        },
        {
            label: 'Pengaturan',
            items: [
                { label: 'Pengaturan Toko', icon: 'settings', href: store.value.settings_url },
            ],
        },
    ];

    if (user.value.is_super_admin) {
        groups.unshift({
            label: 'Pusat',
            items: [{ label: 'Kembali ke Pusat', icon: 'arrow_back', href: page.props.app_urls.central_dashboard }],
        });
    }

    return groups;
});
</script>

<template>
    <AppShell :title="title" :menu-groups="menuGroups" :brand="brand" :role-label="user.is_super_admin ? 'Super Admin' : 'Admin Toko'">
        <template v-if="$slots.back" #back>
            <slot name="back" />
        </template>

        <template #actions>
            <slot name="actions" />
        </template>

        <slot />
    </AppShell>
</template>
