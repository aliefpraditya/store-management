<script setup>
import { Head } from '@inertiajs/vue3';
import PrintPage from '../../../Components/PrintPage.vue';
import SalesDetailReportSheet from '../../../Components/SalesDetailReportSheet.vue';

defineProps({
    report: { type: Object, required: true },
    printed_at: { type: String, required: true },
});
</script>

<template>
    <Head :title="`Laporan Penjualan Rinci ${report.from} sampai ${report.to}`" />

    <PrintPage paper-width="210mm">
        <SalesDetailReportSheet :report="report" :printed-at="printed_at" />
    </PrintPage>
</template>
