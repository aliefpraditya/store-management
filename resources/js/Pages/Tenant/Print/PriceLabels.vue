<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import PrintPage from '../../../Components/PrintPage.vue';
import { formatRupiah } from '../../../Composables/useMoneyFormat';

const props = defineProps({
    /** The size is that of the whole stock (one row of labels), which `columns` labels share side by side. @type {{labels: {name: string, unit: string, price: string, copies: number}[], printed_on: string, width_mm: number, height_mm: number, columns: number}} */
    sheet: { type: Object, required: true },
});

/** A label this tall or taller has room for a larger type and a name on two lines. */
const TALL_LABEL_MM = 25;

/** A label this narrow or narrower must keep its price small, or a price like Rp 1.280.000/PCS would not fit across. */
const NARROW_LABEL_MM = 40;

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });

/** The day comes as plain `Y-m-d`; reading it as local midnight keeps it from sliding to another day in some time zones. */
const printedOn = computed(() => dateFormat.format(new Date(`${props.sheet.printed_on}T00:00:00`)));

const labels = computed(() => props.sheet.labels.flatMap((label) => Array.from({ length: label.copies }, () => label)));

/** One row per stock length: each row prints on a page of its own, so a row of labels is what the printer feeds at once. */
const rows = computed(() => {
    const filled = [];

    for (let index = 0; index < labels.value.length; index += props.sheet.columns) {
        filled.push(labels.value.slice(index, index + props.sheet.columns));
    }

    return filled;
});

const pageRule = computed(() => `@page { size: ${props.sheet.width_mm}mm ${props.sheet.height_mm}mm; margin: 0; }`);

const isTall = computed(() => props.sheet.height_mm >= TALL_LABEL_MM);

const isNarrow = computed(() => props.sheet.width_mm / props.sheet.columns <= NARROW_LABEL_MM);

/** The price is the biggest thing on the label, as large as the label's width allows. */
const priceSize = computed(() => {
    if (!isTall.value) {
        return isNarrow.value ? '10pt' : '13pt';
    }

    return isNarrow.value ? '13pt' : '18pt';
});

/** Half a millimetre short of the stock: a row exactly as tall as the page tips a blank page out of the printer. */
const rowStyle = computed(() => ({
    height: `${props.sheet.height_mm - 0.5}mm`,
    gridTemplateColumns: `repeat(${props.sheet.columns}, 1fr)`,
    '--name-lines': isTall.value ? 2 : 1,
    '--name-size': isTall.value ? '8pt' : '6.5pt',
    '--price-size': priceSize.value,
    '--date-size': isTall.value ? '7pt' : '5.5pt',
}));
</script>

<template>
    <Head title="Cetak Label Harga" />
    <component :is="'style'">{{ pageRule }}</component>

    <PrintPage :paper-width="`${sheet.width_mm}mm`">
        <div v-for="(row, rowIndex) in rows" :key="rowIndex" class="tag-row" :style="rowStyle">
            <div v-for="(label, index) in row" :key="index" class="tag">
                <div class="tag__name">{{ label.name }}</div>
                <div class="tag__price">{{ formatRupiah(label.price) }}<span class="tag__unit">/{{ label.unit }}</span></div>
                <div class="tag__date">{{ printedOn }}</div>
            </div>
        </div>
    </PrintPage>
</template>

<style scoped>
.tag-row {
    display: grid;
    overflow: hidden;
    break-after: page;
}

.tag-row:last-child {
    break-after: auto;
}

.tag {
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
    min-width: 0;
    padding: 0.8mm 1.2mm;
    overflow: hidden;
    background: #fff;
    color: #000;
    outline: 1px dashed #b0bec5;
    outline-offset: -1px;
    font-family: Arial, Helvetica, sans-serif;
    text-align: center;
}

.tag__name {
    display: -webkit-box;
    overflow: hidden;
    font-size: var(--name-size);
    font-weight: 700;
    line-height: 1.15;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: var(--name-lines);
}

.tag__price {
    display: flex;
    flex: 1;
    align-items: center;
    justify-content: center;
    min-height: 0;
    overflow: hidden;
    font-size: var(--price-size);
    font-weight: 800;
    line-height: 1;
    white-space: nowrap;
}

.tag__unit {
    font-size: 0.6em;
    font-weight: 700;
}

.tag__date {
    font-size: var(--date-size);
    line-height: 1.15;
    white-space: nowrap;
}

@media print {
    .tag {
        outline: none;
    }
}
</style>
