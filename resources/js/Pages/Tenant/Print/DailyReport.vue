<script setup>
import { Head } from '@inertiajs/vue3';
import DailyReportSheet from '../../../Components/DailyReportSheet.vue';
import PrintPage from '../../../Components/PrintPage.vue';

defineProps({
    report: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Laporan Harian ${report.business_date}`" />

    <PrintPage paper-width="210mm">
        <DailyReportSheet :report="report" />
    </PrintPage>
</template>
