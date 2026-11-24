<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { urlForRange } from '../Composables/reportRange';
import ReportTabs from './ReportTabs.vue';

defineProps({
    /** Which of the sales report's tabs the page is: `summary`, `receipts` or `products`. */
    current: { type: String, required: true },
});

const page = usePage();

/** A tab opens on the period the page is showing, so moving between tabs does not ask for the period again. */
const withPeriod = (href) => (page.props.period ? urlForRange(href, page.props.period.from, page.props.period.to) : href);

const tabs = computed(() => [
    { key: 'summary', label: 'Ringkasan', icon: 'pie_chart', href: withPeriod(page.props.store.reports_url) },
    { key: 'receipts', label: 'Daftar nota', icon: 'receipt_long', href: withPeriod(page.props.store.sales_receipts_url) },
    { key: 'products', label: 'Barang & kategori', icon: 'category', href: withPeriod(page.props.store.sales_products_url) },
]);
</script>

<template>
    <ReportTabs :tabs="tabs" :current="current" label="Laporan penjualan" />
</template>
