<script setup>
import { computed } from 'vue';
import { dailyReportSectionsOf, dayText } from '../Composables/dailyReportRows';
import { formatReceiptDateTime } from '../Composables/receiptDateTime';
import { formatDeduction, formatRupiah } from '../Composables/useMoneyFormat';
import ThermalPageSize from './ThermalPageSize.vue';

const props = defineProps({
    /** The roll form of the daily report: the figures, the shifts and the voids, without the receipts of the day. @type {{business_date: string, closed_by_name: string|null, closed_at: string, notes: string|null, figures: Record<string, string|number>, store: {name: string, address: string|null, phone: string|null}, shifts: object[], voids: object[], paper_width: number}} */
    report: { type: Object, required: true },
});

const sections = computed(() => dailyReportSectionsOf(props.report));

/** @param {import('../Composables/dailyReportRows').DailyReportRow} row */
function valueOf(row) {
    if (row.kind === 'deduction') {
        return formatDeduction(row.value);
    }

    return row.kind === 'money' ? formatRupiah(row.value) : String(row.value);
}
</script>

<template>
    <div class="daily-roll">
        <ThermalPageSize :width="report.paper_width" />
        <header>
            <b>{{ report.store.name }}</b>
            <span v-if="report.store.address">{{ report.store.address }}</span>
            <span v-if="report.store.phone">{{ report.store.phone }}</span>
        </header>

        <p class="daily-roll__title">LAPORAN HARIAN</p>
        <p class="daily-roll__date">{{ dayText(report.business_date) }}</p>

        <section v-for="section in sections" :key="section.title">
            <p class="daily-roll__section">{{ section.title }}</p>
            <template v-for="(row, index) in section.rows" :key="index">
                <p v-if="row.kind === 'heading'" class="daily-roll__heading">{{ row.label }}</p>
                <p v-else-if="row.kind === 'note'" class="daily-roll__text">{{ row.label }}</p>
                <div v-else class="daily-roll__row"><span>{{ row.label }}</span><span>{{ valueOf(row) }}</span></div>
            </template>
        </section>

        <p v-if="report.notes" class="daily-roll__text">Catatan penutupan: {{ report.notes }}</p>
        <p class="daily-roll__text">Ditutup oleh {{ report.closed_by_name ?? '-' }}<br />{{ formatReceiptDateTime(report.closed_at) }}</p>

        <div class="daily-roll__signs">
            <div><span>Admin</span></div>
            <div><span>Pemilik</span></div>
        </div>
    </div>
</template>

<style>
.daily-roll {
    box-sizing: border-box;
    padding: 4mm var(--thermal-padding-right, 4mm) 4mm var(--thermal-padding-left, 4mm);
    font-family: 'Courier New', monospace;
    font-size: 11px;
    line-height: 1.35;
    color: #000;
}

.daily-roll header {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.daily-roll header b {
    font-size: 15px;
}

.daily-roll p {
    margin: 0;
}

.daily-roll__title {
    margin-top: 8px !important;
    font-size: 14px;
    font-weight: 700;
    text-align: center;
    letter-spacing: 0.1em;
}

.daily-roll__date {
    text-align: center;
}

.daily-roll__section {
    margin-top: 8px !important;
    font-weight: 700;
}

.daily-roll__heading {
    font-weight: 700;
}

.daily-roll__text {
    margin-top: 6px !important;
}

.daily-roll__row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.daily-roll__signs {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
}

.daily-roll__signs div {
    width: 45%;
    padding-top: 2px;
    border-top: 1px solid #000;
    text-align: center;
}
</style>
