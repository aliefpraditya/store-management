<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { newUuid } from '../Composables/useCart';
import DateInput from './DateInput.vue';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** The categories that can still be chosen. @type {{id: number, name: string}[]} */
    categories: { type: Array, required: true },
    /** The ways to pay, as the server names them. @type {{value: string, label: string}[]} */
    sources: { type: Array, required: true },
    /** The drawers of the shifts that are running. @type {{id: number, label: string}[]} */
    openShifts: { type: Array, required: true },
    storeUrl: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const DRAWER = 'drawer';
const OTHER = 'other';

const form = useForm({
    client_uuid: newUuid(),
    expense_category_id: null,
    source: OTHER,
    cash_shift_id: null,
    business_date: '',
    description: '',
    paid_to: '',
    amount: '',
});

const isDrawer = computed(() => form.source === DRAWER);

const hasOpenDrawer = computed(() => props.openShifts.length > 0);

/** The drawer can only be chosen while a shift is running. */
const sourceOptions = computed(() => props.sources.map((source) => ({ ...source, disable: source.value === DRAWER && !hasOpenDrawer.value })));

/** @returns {string} today as the date field wants it, in the browser's own time zone */
function today() {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

/** Each time the window opens it starts empty, on the drawer when a shift is running and on today otherwise. */
watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        form.reset();
        form.clearErrors();
        form.client_uuid = newUuid();
        form.source = hasOpenDrawer.value ? DRAWER : OTHER;
        form.cash_shift_id = props.openShifts[0]?.id ?? null;
        form.business_date = today();
    },
);

/**
 * Sent with every attempt at one expense. A retry of the same attempt carries the same marker, so the server gives
 * back the first expense instead of recording it twice; the next expense gets a new one.
 */
function submit() {
    form
        .transform((submittedForm) => ({
            ...submittedForm,
            cash_shift_id: submittedForm.source === DRAWER ? submittedForm.cash_shift_id : null,
            business_date: submittedForm.source === OTHER ? submittedForm.business_date : null,
        }))
        .post(props.storeUrl, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => emit('update:modelValue', false),
        });
}
</script>

<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="emit('update:modelValue', $event)">
        <q-card class="expense-dialog">
            <q-form @submit.prevent="submit">
                <q-card-section>
                    <div class="expense-dialog__title">Pengeluaran baru</div>
                </q-card-section>

                <q-card-section class="expense-dialog__fields">
                    <div>
                        <q-btn-toggle v-model="form.source" :options="sourceOptions" no-caps unelevated toggle-color="primary" color="grey-3" text-color="grey-8" />
                        <p class="expense-dialog__hint">
                            <template v-if="isDrawer">Uang diambil dari laci kasir dan mengurangi tunai yang seharusnya ada di laci shift itu.</template>
                            <template v-else>Dibayar dari luar laci kasir, tidak memengaruhi kas shift.</template>
                        </p>
                    </div>

                    <q-select
                        v-if="isDrawer"
                        v-model="form.cash_shift_id"
                        :options="openShifts"
                        option-value="id"
                        option-label="label"
                        emit-value
                        map-options
                        behavior="menu"
                        label="Laci shift"
                        outlined
                        :error="!!form.errors.cash_shift_id"
                        :error-message="form.errors.cash_shift_id"
                    />
                    <DateInput v-else v-model="form.business_date" label="Tanggal" outlined :error="!!form.errors.business_date" :error-message="form.errors.business_date" />

                    <q-select
                        v-model="form.expense_category_id"
                        :options="categories"
                        option-value="id"
                        option-label="name"
                        emit-value
                        map-options
                        behavior="menu"
                        label="Kategori"
                        outlined
                        :error="!!form.errors.expense_category_id"
                        :error-message="form.errors.expense_category_id"
                    />
                    <q-input v-model="form.amount" type="number" min="0" step="any" prefix="Rp" label="Nominal" outlined :error="!!form.errors.amount" :error-message="form.errors.amount" />
                    <q-input v-model="form.description" label="Keterangan" outlined maxlength="255" :error="!!form.errors.description" :error-message="form.errors.description" />
                    <q-input v-model="form.paid_to" label="Dibayar ke (opsional)" outlined maxlength="255" :error="!!form.errors.paid_to" :error-message="form.errors.paid_to" />
                </q-card-section>

                <q-card-actions align="right" class="q-pa-md">
                    <q-btn flat no-caps label="Batal" @click="emit('update:modelValue', false)" />
                    <q-btn type="submit" color="primary" unelevated no-caps label="Simpan" :loading="form.processing" />
                </q-card-actions>
            </q-form>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.expense-dialog {
    width: 460px;
    max-width: 94vw;
    border-radius: var(--app-radius);
}

.expense-dialog__title {
    font-size: 17px;
    font-weight: 700;
}

.expense-dialog__fields {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.expense-dialog__hint {
    margin: 8px 0 0;
    color: var(--app-mute);
    font-size: 12.5px;
}
</style>
