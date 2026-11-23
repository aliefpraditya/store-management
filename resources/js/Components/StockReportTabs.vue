<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ReportTabs from './ReportTabs.vue';

defineProps({
    /** Which of the stock reports the page is: `positions`, `restock`, `idle` or `movements`. */
    current: { type: String, required: true },
});

const store = computed(() => usePage().props.store);

const tabs = computed(() => [
    { key: 'positions', label: 'Posisi stok', icon: 'inventory_2', href: store.value.stock_report_url },
    { key: 'restock', label: 'Perlu restok', icon: 'add_shopping_cart', href: store.value.restock_url },
    { key: 'idle', label: 'Tidak terjual', icon: 'hourglass_empty', href: store.value.idle_stock_url },
    { key: 'movements', label: 'Pergerakan', icon: 'swap_vert', href: store.value.stock_movements_url },
]);
</script>

<template>
    <ReportTabs :tabs="tabs" :current="current" label="Laporan stok" />
</template>
