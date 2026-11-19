<script setup>
import { Head } from '@inertiajs/vue3';
import PrintPage from '../../../Components/PrintPage.vue';
import ShiftReportSheet from '../../../Components/ShiftReportSheet.vue';

defineProps({
    report: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Laporan Akhir ${report.label}`" />

    <PrintPage :paper-width="`${report.paper_width}mm`" printer-kind="receipt">
        <ShiftReportSheet :report="report" />
    </PrintPage>
</template>
