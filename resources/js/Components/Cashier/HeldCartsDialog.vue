<script setup>
import { nextTick, ref, watch } from 'vue';
import { formatQuantity } from '../../Composables/useQuantityFormat';
import { formatRupiah } from '../../Composables/useMoneyFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** @type {{id: string, heldAt: string, lines: {product: {name: string, units: object[]}, unitId: number, quantity: number|string}[]}[]} */
    heldCarts: { type: Array, required: true },
});

const emit = defineEmits(['update:modelValue', 'resume', 'discard']);

const timeFormat = new Intl.DateTimeFormat('id-ID', { timeStyle: 'short' });

const list = ref(null);

const unitOf = (line) => line.product.units.find((unit) => unit.id === line.unitId);

const totalOf = (heldCart) => heldCart.lines.reduce((sum, line) => sum + (Number(line.quantity) || 0) * Number(unitOf(line)?.price ?? 0), 0);

const summaryOf = (heldCart) => heldCart.lines.map((line) => `${formatQuantity(Number(line.quantity) || 0)} ${unitOf(line)?.code ?? ''} ${line.product.name}`).join(', ');

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            nextTick(() => list.value?.querySelector('[data-held]')?.focus());
        }
    },
);

/** Arrow keys move between the held carts, Enter brings one back, Delete throws it away. */
function onKeydown(event) {
    const items = [...list.value.querySelectorAll('[data-held]')];
    const index = items.indexOf(document.activeElement);

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        items[Math.min(index + 1, items.length - 1)]?.focus();
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        items[Math.max(index - 1, 0)]?.focus();
    } else if (event.key === 'Delete' && index >= 0) {
        event.preventDefault();
        emit('discard', items[index].dataset.held);
        nextTick(() => list.value?.querySelector('[data-held]')?.focus());
    }
}
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card class="held">
            <q-card-section>
                <div class="held__title">Keranjang ditahan</div>
                <small>Panah untuk memilih, Enter untuk memanggil, Delete untuk membuang. Keranjang yang sedang berjalan ikut ditahan.</small>
            </q-card-section>

            <q-card-section>
                <div ref="list" class="held__list" @keydown="onKeydown">
                    <p v-if="heldCarts.length === 0" class="held__empty">Tidak ada keranjang yang ditahan.</p>

                    <button v-for="heldCart in heldCarts" :key="heldCart.id" type="button" class="held__item" :data-held="heldCart.id" @click="emit('resume', heldCart.id)">
                        <span class="held__main">
                            <b>{{ timeFormat.format(new Date(heldCart.heldAt)) }} · {{ heldCart.lines.length }} baris</b>
                            <small>{{ summaryOf(heldCart) }}</small>
                        </span>
                        <b>{{ formatRupiah(totalOf(heldCart)) }}</b>
                    </button>
                </div>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn flat no-caps label="Tutup" v-close-popup />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.held {
    width: 520px;
    max-width: 94vw;
}

.held__title {
    font-size: 17px;
    font-weight: 700;
}

.held small {
    color: var(--app-mute);
}

.held__list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 60vh;
    overflow-y: auto;
}

.held__empty {
    margin: 0;
    padding: 20px 0;
    color: var(--app-mute);
    text-align: center;
}

.held__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    border: 1px solid var(--app-line);
    border-radius: 10px;
    background: #fff;
    text-align: left;
    cursor: pointer;
}

.held__item:hover,
.held__item:focus {
    border-color: var(--q-primary);
    outline: 2px solid var(--q-primary);
    outline-offset: -1px;
}

.held__main {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.held__main small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
