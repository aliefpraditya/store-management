<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { formatRupiah } from '../../Composables/useMoneyFormat';

const props = defineProps({
    /** @type {{id: number, code: string, name: string, fixed_opening_cash: string, busy_with: string|null}[]} */
    registers: { type: Array, required: true },
    openUrl: { type: String, required: true },
    storeName: { type: String, required: true },
});

const firstFreeRegister = props.registers.find((register) => register.busy_with === null) ?? props.registers[0] ?? null;

const registerId = ref(firstFreeRegister?.id ?? null);
const isOpening = ref(false);

const register = computed(() => props.registers.find((candidate) => candidate.id === registerId.value) ?? null);

const canOpen = computed(() => register.value !== null && register.value.busy_with === null && !isOpening.value);

function openShift() {
    router.post(props.openUrl, { register_id: registerId.value }, { onStart: () => (isOpening.value = true), onFinish: () => (isOpening.value = false) });
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
                    :options="registers.map((candidate) => ({ label: `${candidate.code} · ${candidate.name}${candidate.busy_with ? ` (dipakai ${candidate.busy_with})` : ''}`, value: candidate.id }))"
                    emit-value
                    map-options
                    outlined
                    dense
                    label="Register"
                />
                <div v-else class="open__register">{{ register.code }} · {{ register.name }}</div>

                <dl v-if="register" class="open__cash">
                    <dt>Modal kas awal</dt>
                    <dd>{{ formatRupiah(register.fixed_opening_cash) }}</dd>
                </dl>
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

.open__cash {
    display: flex;
    justify-content: space-between;
    margin: 0;
    padding: 10px 14px;
    border-radius: 10px;
    background: #f6f6f7;
}

.open__cash dd {
    margin: 0;
    font-weight: 700;
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
