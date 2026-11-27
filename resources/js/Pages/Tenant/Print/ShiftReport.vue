<script setup>
import { Head } from '@inertiajs/vue3';
import PrintPage from '../../../Components/PrintPage.vue';
import ShiftReportSheet from '../../../Components/ShiftReportSheet.vue';
import { shiftReportEscPosOf } from '../../../Composables/shiftReportEscPos';

const props = defineProps({
    report: { type: Object, required: true },
});

/** The report as text, for QZ Tray; null for a roll whose width has no known columns, which prints the page instead. */
const escposText = shiftReportEscPosOf(props.report);
</script>

<template>
    <Head :title="`Laporan Akhir ${report.label}`" />

    <PrintPage :paper-width="`${report.paper_width}mm`" printer-kind="receipt" :escpos-text="escposText">
        <ShiftReportSheet :report="report" />
    </PrintPage>
</template>
