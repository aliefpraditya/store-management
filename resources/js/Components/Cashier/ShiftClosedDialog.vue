<script setup>
import { formatRupiah } from '../../Composables/useMoneyFormat';

defineProps({
    modelValue: { type: Boolean, required: true },
    /** @type {{expected_cash: string, counted_cash: string, cash_difference: string, deposit_amount: string}|null} */
    result: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card v-if="result" class="closed">
            <q-card-section>
                <div class="closed__title">Shift ditutup</div>

                <dl class="closed__rows">
                    <div><dt>Tunai seharusnya</dt><dd>{{ formatRupiah(result.expected_cash) }}</dd></div>
                    <div><dt>Tunai dihitung</dt><dd>{{ formatRupiah(result.counted_cash) }}</dd></div>
                    <div :class="{ 'closed__bad': Number(result.cash_difference) !== 0 }">
                        <dt>Selisih</dt>
                        <dd>{{ formatRupiah(result.cash_difference) }}</dd>
                    </div>
                    <div class="closed__deposit"><dt>Setoran</dt><dd>{{ formatRupiah(result.deposit_amount) }}</dd></div>
                </dl>
                <p v-if="Number(result.cash_difference) !== 0" class="closed__note">Selisih menunggu persetujuan Admin.</p>
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

.closed__rows .closed__bad {
    color: #be123c;
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
    color: #c2710c;
    font-size: 13px;
}
</style>
