<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Code39Barcode from '../../../Components/Code39Barcode.vue';
import PrintPage from '../../../Components/PrintPage.vue';

const props = defineProps({
    /** The size is that of the whole stock (one row of stickers), which `columns` stickers share side by side. @type {{labels: {name: string, sku: string, barcode: string, copies: number}[], width_mm: number, height_mm: number, columns: number}} */
    sheet: { type: Object, required: true },
});

/** A label this tall or taller has room for a larger type and a name on two lines. */
const TALL_LABEL_MM = 25;

const stickers = computed(() => props.sheet.labels.flatMap((label) => Array.from({ length: label.copies }, () => label)));

/** One row per stock length: each row prints on a page of its own, so a row of labels is what the printer feeds at once. */
const rows = computed(() => {
    const filled = [];

    for (let index = 0; index < stickers.value.length; index += props.sheet.columns) {
        filled.push(stickers.value.slice(index, index + props.sheet.columns));
    }

    return filled;
});

const pageRule = computed(() => `@page { size: ${props.sheet.width_mm}mm ${props.sheet.height_mm}mm; margin: 0; }`);

const isTall = computed(() => props.sheet.height_mm >= TALL_LABEL_MM);

/** Half a millimetre short of the stock: a row exactly as tall as the page tips a blank page out of the printer. */
const rowStyle = computed(() => ({
    height: `${props.sheet.height_mm - 0.5}mm`,
    gridTemplateColumns: `repeat(${props.sheet.columns}, 1fr)`,
    '--name-lines': isTall.value ? 2 : 1,
    '--name-size': isTall.value ? '8pt' : '6.5pt',
    '--sku-size': isTall.value ? '7.5pt' : '6.5pt',
}));
</script>

<template>
    <Head title="Cetak Barcode" />
    <component :is="'style'">{{ pageRule }}</component>

    <PrintPage :paper-width="`${sheet.width_mm}mm`">
        <div v-for="(row, rowIndex) in rows" :key="rowIndex" class="sticker-row" :style="rowStyle">
            <div v-for="(sticker, index) in row" :key="index" class="sticker">
                <div class="sticker__name">{{ sticker.name }}</div>
                <div class="sticker__bars"><Code39Barcode :value="sticker.barcode" class="sticker__svg" /></div>
                <div class="sticker__sku">{{ sticker.sku }}</div>
            </div>
        </div>
    </PrintPage>
</template>

<style scoped>
.sticker-row {
    display: grid;
    overflow: hidden;
    break-after: page;
}

.sticker-row:last-child {
    break-after: auto;
}

.sticker {
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

.sticker__name {
    display: -webkit-box;
    overflow: hidden;
    font-size: var(--name-size);
    font-weight: 700;
    line-height: 1.15;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: var(--name-lines);
}

.sticker__bars {
    flex: 1;
    min-height: 0;
    margin: 0.4mm 0;
}

.sticker__svg {
    height: 100%;
}

.sticker__sku {
    overflow: hidden;
    font-size: var(--sku-size);
    font-weight: 700;
    line-height: 1.15;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media print {
    .sticker {
        outline: none;
    }
}
</style>
