<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** Endpoint that finds a receipt by its full number (`?number=`). */
    lookupUrl: { type: String, required: true },
});

/** `found` carries the receipt the return dialog needs: its number and the two return addresses. */
const emit = defineEmits(['update:modelValue', 'found']);

const number = ref('');
const errorMessage = ref('');
const isSearching = ref(false);

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            number.value = '';
            errorMessage.value = '';
        }
    },
);

/**
 * Looks the receipt up by the number the customer shows; a wrong or partial number says so instead of listing
 * receipts, since the till only starts a return and does not browse old receipts.
 */
async function find() {
    if (number.value.trim() === '') {
        return;
    }

    isSearching.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch(`${props.lookupUrl}?number=${encodeURIComponent(number.value.trim())}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            errorMessage.value = body.message ?? 'Nota tidak bisa dicari.';

            return;
        }

        emit('update:modelValue', false);
        emit('found', body.data);
    } finally {
        isSearching.value = false;
    }
}
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card class="lookup">
            <q-card-section>
                <div class="lookup__title">Retur</div>
                <p class="lookup__hint">Ketik nomor nota yang dibawa pelanggan. Retur menunggu persetujuan Admin.</p>
                <q-input
                    v-model="number"
                    outlined
                    dense
                    autofocus
                    label="Nomor nota"
                    placeholder="INV/MU/K1/2610/0001"
                    :error="errorMessage !== ''"
                    :error-message="errorMessage"
                    @update:model-value="errorMessage = ''"
                    @keydown.enter.prevent="find"
                />
            </q-card-section>
            <q-card-actions align="right">
                <q-btn flat no-caps label="Batal" v-close-popup />
                <q-btn color="primary" unelevated no-caps label="Cari Nota" :disable="number.trim() === ''" :loading="isSearching" @click="find" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.lookup {
    width: 420px;
    max-width: 94vw;
}

.lookup__title {
    font-size: 17px;
    font-weight: 700;
}

.lookup__hint {
    margin: 4px 0 14px;
    color: var(--app-mute);
    font-size: 13px;
}
</style>
