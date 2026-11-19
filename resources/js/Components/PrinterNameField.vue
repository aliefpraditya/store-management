<script setup>
import { useQuasar } from 'quasar';
import { printTestPage } from '../Composables/qzTray';

const props = defineProps({
    label: { type: String, required: true },
    hint: { type: String, default: null },
    /** The printers of this computer as QZ Tray listed them, to pick the name from; null before QZ Tray was asked. */
    detectedPrinters: { type: Array, default: null },
    /** Whether the store prints through QZ Tray, which is when the name must be the one the computer knows the printer by. */
    isQzEnabled: { type: Boolean, default: false },
    /** The page the printer is loaded with, for its test page. @type {{width_mm: number, height_mm: number|null, label: string}} */
    page: { type: Object, required: true },
    error: { type: String, default: null },
});

/** The name of the printer, as the operating system knows it; empty while none is named. */
const printerName = defineModel({ type: String, required: true });

const $q = useQuasar();

/** Prints a test page on the printer as it is typed, to see that the name is right and the paper fits. */
async function testPrint() {
    const name = printerName.value.trim();

    try {
        await printTestPage({ name, width_mm: props.page.width_mm, height_mm: props.page.height_mm }, props.page.label);
        $q.notify({ type: 'positive', position: 'top', message: `Tes cetak dikirim ke ${name}.` });
    } catch (error) {
        $q.notify({ type: 'negative', position: 'top', message: `Tes cetak gagal: ${error?.message ?? error}` });
    }
}
</script>

<template>
    <div class="printer-field">
        <q-input v-model="printerName" outlined dense maxlength="255" :label="label" :hint="hint" :error="!!error" :error-message="error" class="printer-field__name">
            <template v-if="detectedPrinters !== null && detectedPrinters.length > 0" #append>
                <q-btn flat round dense icon="arrow_drop_down" aria-label="Pilih dari printer komputer ini">
                    <q-menu>
                        <q-list dense>
                            <q-item v-for="detectedName in detectedPrinters" :key="detectedName" v-close-popup clickable @click="printerName = detectedName">
                                <q-item-section>{{ detectedName }}</q-item-section>
                            </q-item>
                        </q-list>
                    </q-menu>
                </q-btn>
            </template>
        </q-input>
        <q-btn v-if="isQzEnabled" outline no-caps color="primary" icon="print" label="Tes" :disable="printerName.trim() === ''" :aria-label="`Tes cetak ${label}`" @click="testPrint" />
    </div>
</template>

<style scoped>
.printer-field {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.printer-field__name {
    flex: 1;
}
</style>
