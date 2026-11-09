<script setup>
import { computed, ref, watch } from 'vue';
import { useQuasar } from 'quasar';
import { postJson } from '../../Composables/postJson';
import { newUuid } from '../../Composables/useCart';
import { formatRupiah } from '../../Composables/useMoneyFormat';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    /** Where the categories and this shift's expenses are fetched. */
    listUrl: { type: String, required: true },
    /** Where a new expense is posted. */
    storeUrl: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const $q = useQuasar();

const isLoading = ref(false);
const isSaving = ref(false);

/** @type {import('vue').Ref<{categories: {id: number, name: string}[], expenses: object[]}>} */
const expenseDialogContext = ref({ categories: [], expenses: [] });

const form = ref({ expense_category_id: null, amount: '', description: '', paid_to: '' });

const timeFormat = new Intl.DateTimeFormat('id-ID', { timeStyle: 'short' });

const recordedExpenses = computed(() => expenseDialogContext.value.expenses.filter((expense) => expense.status === 'recorded'));

const takenTotal = computed(() => recordedExpenses.value.reduce((sum, expense) => sum + Number(expense.amount), 0));

const canSave = computed(() => form.value.expense_category_id !== null && Number(form.value.amount) > 0 && form.value.description.trim() !== '' && !isSaving.value);

/**
 * Sent with every save of one expense. A retry of the same attempt (a lost answer, a second tap) carries the same
 * marker, so the server gives back the first expense; it is renewed once an expense is saved.
 */
let expenseUuid = newUuid();

function fail(error) {
    $q.notify({ type: 'negative', message: error.message, position: 'top' });
}

async function load() {
    isLoading.value = true;

    try {
        const response = await fetch(props.listUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error((await response.json().catch(() => ({}))).message ?? 'Pengeluaran tidak bisa dimuat.');
        }

        expenseDialogContext.value = (await response.json()).data;
    } catch (error) {
        fail(error);
    } finally {
        isLoading.value = false;
    }
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        form.value = { expense_category_id: null, amount: '', description: '', paid_to: '' };
        expenseDialogContext.value = { categories: [], expenses: [] };
        expenseUuid = newUuid();
        load();
    },
);

/** Takes the money out of this shift's drawer; the close-shift figures are fetched again by whoever listens. */
async function save() {
    isSaving.value = true;

    try {
        const expense = await postJson(props.storeUrl, { client_uuid: expenseUuid, ...form.value, amount: Number(form.value.amount) });

        expenseUuid = newUuid();
        form.value = { expense_category_id: null, amount: '', description: '', paid_to: '' };
        $q.notify({ type: 'positive', position: 'top', message: `Pengeluaran ${expense.number} dicatat.` });
        emit('saved');
        await load();
    } catch (error) {
        fail(error);
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card class="expense">
            <q-card-section class="expense__head">
                <div class="expense__title">Pengeluaran dari laci</div>
                <small>Uang yang diambil dari laci shift ini mengurangi tunai yang seharusnya ada saat tutup shift.</small>
            </q-card-section>

            <q-card-section class="expense__body">
                <q-inner-loading :showing="isLoading" />

                <q-select
                    v-model="form.expense_category_id"
                    :options="expenseDialogContext.categories"
                    option-value="id"
                    option-label="name"
                    emit-value
                    map-options
                    behavior="menu"
                    outlined
                    dense
                    label="Kategori"
                />
                <q-input v-model="form.amount" type="number" min="0" step="any" outlined dense prefix="Rp" label="Nominal" />
                <q-input v-model="form.description" outlined dense maxlength="255" label="Keterangan (mis. beli galon)" />
                <q-input v-model="form.paid_to" outlined dense maxlength="255" label="Dibayar ke (opsional)" />

                <template v-if="expenseDialogContext.expenses.length > 0">
                    <div class="expense__label">Sudah diambil di shift ini</div>
                    <ul class="expense__list">
                        <li v-for="expense in expenseDialogContext.expenses" :key="expense.id" :class="{ 'expense__item--voided': expense.status === 'voided' }">
                            <span>
                                {{ expense.description }}
                                <small>{{ expense.category_name }} · {{ timeFormat.format(new Date(expense.created_at)) }}<template v-if="expense.status === 'voided'"> · dibatalkan</template></small>
                            </span>
                            <b>{{ formatRupiah(expense.amount) }}</b>
                        </li>
                    </ul>
                    <div class="expense__sum"><span>Total diambil</span><b>{{ formatRupiah(takenTotal) }}</b></div>
                </template>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn flat no-caps label="Tutup" v-close-popup />
                <q-btn color="primary" unelevated no-caps label="Catat Pengeluaran" :disable="!canSave" :loading="isSaving" @click="save" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.expense {
    display: flex;
    flex-direction: column;
    width: 460px;
    max-width: 94vw;
    max-height: 90vh;
}

.expense__title {
    font-size: 17px;
    font-weight: 700;
}

.expense__head small {
    color: var(--app-mute);
}

.expense__body {
    position: relative;
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 10px;
    min-height: 120px;
    overflow-y: auto;
}

.expense__label {
    margin-top: 4px;
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.expense__list {
    margin: 0;
    padding: 0;
    list-style: none;
    font-size: 13px;
}

.expense__list li {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 6px 0;
    border-bottom: 1px solid var(--app-line);
}

.expense__list small {
    display: block;
    color: var(--app-mute);
}

.expense__item--voided {
    color: var(--app-mute);
    text-decoration: line-through;
}

.expense__sum {
    display: flex;
    justify-content: space-between;
    font-weight: 600;
}
</style>
