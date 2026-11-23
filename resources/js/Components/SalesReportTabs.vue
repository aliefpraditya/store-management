<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ReportTabs from './ReportTabs.vue';

defineProps({
    /** Which of the sales report's tabs the page is: `summary` or `receipts`. */
    current: { type: String, required: true },
});

const page = usePage();

/** A tab opens on the period the page is showing, so moving between tabs does not ask for the period again. */
function withPeriod(href) {
    const period = page.props.period;

    if (!period) {
        return href;
    }

    const url = new URL(href, window.location.origin);

    url.searchParams.set('from', period.from);
    url.searchParams.set('to', period.to);

    return `${url.pathname}${url.search}`;
}

const tabs = computed(() => [
    { key: 'summary', label: 'Ringkasan', icon: 'pie_chart', href: withPeriod(page.props.store.reports_url) },
    { key: 'receipts', label: 'Daftar nota', icon: 'receipt_long', href: withPeriod(page.props.store.sales_receipts_url) },
]);
</script>

<template>
    <ReportTabs :tabs="tabs" :current="current" label="Laporan penjualan" />
</template>
