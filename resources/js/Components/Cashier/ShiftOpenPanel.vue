<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { formatRupiah } from '../../Composables/useMoneyFormat';
import MoneyInput from '../MoneyInput.vue';

const props = defineProps({
    /** @type {{id: number, code: string, name: string, carried_over_cash: string|null, busy_with: string|null, busy_note: string|null}[]} */
    registers: { type: Array, required: true },
    openUrl: { type: String, required: true },
    storeName: { type: String, required: true },
});

const firstFreeRegister = props.registers.find((register) => register.busy_with === null) ?? props.registers[0] ?? null;

const registerId = ref(firstFreeRegister?.id ?? null);
const openingCash = ref('');
const openingNote = ref('');
const isOpening = ref(false);

const register = computed(() => props.registers.find((candidate) => candidate.id === registerId.value) ?? null);

/** The drawer's cash is whatever the last shift on the register left in it; a register that never had a shift has nothing to carry over. */
const hasCarriedOverCash = computed(() => register.value?.carried_over_cash !== null && register.value?.carried_over_cash !== undefined);

const isOpeningEntered = computed(() => openingCash.value !== '' && openingCash.value !== null);

const isOpeningChanged = computed(() => hasCarriedOverCash.value && isOpeningEntered.value && Number(openingCash.value) !== Number(register.value.carried_over_cash));

const canOpen = computed(
    () => register.value !== null
        && register.value.busy_with === null
        && isOpeningEntered.value
        && (!isOpeningChanged.value || openingNote.value.trim() !== '')
        && !isOpening.value,
);

watch(
    register,
    (selected) => {
        openingCash.value = selected?.carried_over_cash ?? '';
        openingNote.value = '';
    },
    { immediate: true },
);

function openShift() {
    router.post(
        props.openUrl,
        { register_id: registerId.value, opening_cash: Number(openingCash.value), opening_cash_note: isOpeningChanged.value ? openingNote.value.trim() : null },
        { onStart: () => (isOpening.value = true), onFinish: () => (isOpening.value = false) },
    );
}
</script>

<template>
    <div class="open">
        <q-card class="open__card">
            <q-card-section>
                <div class="open__store">{{ storeName }}</div>
                <h1>Buka shift</h1>
            </q-card-section>

            <q-card-section v-if="registers.length === 0" class="open__empty">Belum ada register yang aktif. Minta Admin menyiapkannya.</q-card-section>

            <q-card-section v-else class="open__form">
                <q-select
                    v-if="registers.length > 1"
                    v-model="registerId"
                    :options="registers.map((candidate) => ({ label: `${candidate.code} · ${candidate.name}${candidate.busy_note ? ` (${candidate.busy_note})` : ''}`, value: candidate.id }))"
                    emit-value
                    map-options
                    outlined
                    dense
                    label="Register"
                />
                <div v-else class="open__register">{{ register.code }} · {{ register.name }}</div>

                <MoneyInput v-model="openingCash" allow-decimal outlined dense prefix="Rp" label="Modal kas awal" autofocus>
                    <template #hint>
                        <template v-if="hasCarriedOverCash">Sisa laci shift sebelumnya: {{ formatRupiah(register.carried_over_cash) }}</template>
                        <template v-else>Belum ada shift sebelumnya di register ini. Hitung uang di laci lalu isi.</template>
                    </template>
                </MoneyInput>
                <q-input v-if="isOpeningChanged" v-model="openingNote" outlined dense maxlength="255" label="Alasan modal berbeda dari sisa laci (wajib)" />
                <p v-if="register?.busy_with" class="open__busy">{{ register.name }} sedang dipakai {{ register.busy_with }}.</p>
            </q-card-section>

            <q-card-actions v-if="registers.length > 0" align="right">
                <q-btn color="primary" unelevated no-caps label="Buka Shift" :loading="isOpening" :disable="!canOpen" @click="openShift" />
            </q-card-actions>
        </q-card>
    </div>
</template>

<style scoped>
.open {
    display: grid;
    flex: 1;
    place-items: center;
    padding: 24px;
}

.open__card {
    width: 400px;
    max-width: 100%;
}

.open__store {
    color: var(--app-mute);
    font-size: 13px;
}

.open h1 {
    margin: 2px 0 0;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: normal;
    line-height: 1.3;
}

.open__form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.open__register {
    font-weight: 600;
}

.open__busy {
    margin: 0;
    color: #be123c;
    font-size: 13px;
}

.open__empty {
    color: var(--app-mute);
}
</style>
