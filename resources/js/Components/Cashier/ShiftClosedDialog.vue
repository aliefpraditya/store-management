<script setup>
import { formatRupiah } from '../../Composables/useMoneyFormat';

defineProps({
    modelValue: { type: Boolean, required: true },
    /** @type {{counted_cash: string, deposit_amount: string, drawer_left_cash: string}|null} */
    shiftTotals: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card v-if="shiftTotals" class="closed">
            <q-card-section>
                <div class="closed__title">Shift ditutup</div>

                <dl class="closed__rows">
                    <div><dt>Tunai dihitung</dt><dd>{{ formatRupiah(shiftTotals.counted_cash) }}</dd></div>
                    <div class="closed__deposit"><dt>Setoran</dt><dd>{{ formatRupiah(shiftTotals.deposit_amount) }}</dd></div>
                    <div><dt>Ditinggal di laci</dt><dd>{{ formatRupiah(shiftTotals.drawer_left_cash) }}</dd></div>
                </dl>
                <p class="closed__note">Kas shift ini akan diperiksa Admin. Serahkan setoran sesuai angka di atas.</p>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn color="primary" unelevated no-caps label="Selesai" autofocus v-close-popup />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.closed {
    width: 380px;
    max-width: 94vw;
}

.closed__title {
    margin-bottom: 12px;
    font-size: 17px;
    font-weight: 700;
}

.closed__rows {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 0;
}

.closed__rows div {
    display: flex;
    justify-content: space-between;
}

.closed__rows dd {
    margin: 0;
    font-weight: 600;
}

.closed__rows .closed__deposit {
    margin-top: 6px;
    padding-top: 8px;
    border-top: 1px solid var(--app-line);
    font-size: 17px;
    font-weight: 700;
}

.closed__note {
    margin: 10px 0 0;
    color: var(--app-mute);
    font-size: 13px;
}
</style>
