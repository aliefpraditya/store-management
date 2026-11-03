<script setup>
import { Head } from '@inertiajs/vue3';
import PrintPage from '../../../Components/PrintPage.vue';
import SalesReportSheet from '../../../Components/SalesReportSheet.vue';

defineProps({
    report: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Laporan Penjualan ${report.from} sampai ${report.to}`" />

    <PrintPage paper-width="210mm">
        <SalesReportSheet :report="report" />
    </PrintPage>
</template>