<script setup>
import { Head } from '@inertiajs/vue3';
import DailyReportRollSheet from '../../../Components/DailyReportRollSheet.vue';
import PrintPage from '../../../Components/PrintPage.vue';
import { dailyReportEscPosOf } from '../../../Composables/dailyReportEscPos';

const props = defineProps({
    report: { type: Object, required: true },
});

/** The report as text, for QZ Tray; null for a roll whose width has no known columns, which prints the page instead. */
const escposText = dailyReportEscPosOf(props.report);
</script>

<template>
    <Head :title="`Laporan Harian ${report.business_date}`" />

    <PrintPage :paper-width="`${report.paper_width}mm`" printer-kind="receipt" :escpos-text="escposText">
        <DailyReportRollSheet :report="report" />
    </PrintPage>
</template>
