<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import DateInput from '../../../Components/DateInput.vue';
import MoneyInput from '../../../Components/MoneyInput.vue';
import ProductSearchSelect from '../../../Components/ProductSearchSelect.vue';
import SearchableSelect from '../../../Components/SearchableSelect.vue';
import { newUuid } from '../../../Composables/useCart';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    /** The draft being changed, or null for a new nota. */
    purchase: { type: Object, default: null },
    suppliers: { type: Array, required: true },
    warehouses: { type: Array, required: true },
    search_url: { type: String, required: true },
    store_url: { type: String, required: true },
    index_url: { type: String, required: true },
    max_attachments: { type: Number, required: true },
});

const isEditing = computed(() => props.purchase !== null);

let nextLineKey = 0;

/**
 * One line of the nota as it is typed: the product picked, the unit it is bought in, how many, and the price of one unit.
 * `key` is only for the list on screen.
 */
function newLine(line = {}) {
    return { key: nextLineKey++, product: null, unit_code: '', quantity: '', unit_cost: '', notes: '', ...line };
}

const form = useForm({
    client_uuid: newUuid(),
    supplier_id: props.purchase?.supplier_id ?? null,
    supplier_document_number: props.purchase?.supplier_document_number ?? '',
    purchase_date: props.purchase?.purchase_date ?? new Date().toLocaleDateString('sv-SE'),
    expected_date: props.purchase?.expected_date ?? '',
    warehouse_id: props.purchase?.warehouse_id ?? props.warehouses[0]?.id ?? null,
    notes: props.purchase?.notes ?? '',
    attachments: [],
});

const lines = ref(
    props.purchase
        ? props.purchase.items.map((item) =>
              newLine({
                  product: item.product,
                  unit_code: item.unit_code,
                  quantity: String(Number(item.quantity)),
                  unit_cost: String(Number(item.unit_cost)),
                  notes: item.notes ?? '',
              }),
          )
        : [newLine()],
);

/** The nota's total as it is typed, for the reader; the server works out the figure that is kept. */
const total = computed(() => lines.value.reduce((sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.unit_cost) || 0), 0));

const subtotalOf = (line) => (Number(line.quantity) || 0) * (Number(line.unit_cost) || 0);

/** A picked product starts in its base unit, which the admin changes to what the supplier's nota says (a dus, a lusin). */
function pickProduct(line, product) {
    line.product = product;
    line.unit_code = product?.base_unit ?? '';
}

const addLine = () => lines.value.push(newLine());

function removeLine(line) {
    lines.value = lines.value.filter((candidate) => candidate.key !== line.key);

    if (lines.value.length === 0) {
        lines.value.push(newLine());
    }
}

/** What the server said about one field of one line, e.g. the quantity of the second line. */
const lineError = (index, field) => form.errors[`items.${index}.${field}`];

const attachmentLimitText = computed(() => `Foto (JPG, PNG, WebP) atau PDF, paling besar 5 MB, maksimal ${props.max_attachments} file.`);

/**
 * Sends the nota. A new one is a POST; a draft being changed is a PUT, which PHP does not read with files in it, so it goes as
 * a POST that says it is a PUT. Lines left empty are dropped.
 */
function submit() {
    const items = lines.value
        .filter((line) => line.product !== null)
        .map((line) => ({ product_id: line.product.id, unit_code: line.unit_code, quantity: line.quantity, unit_cost: line.unit_cost, notes: line.notes || null }));

    form
        .transform((submittedForm) => ({
            ...submittedForm,
            expected_date: submittedForm.expected_date || null,
            attachments: submittedForm.attachments.length > 0 ? submittedForm.attachments : undefined,
            items,
            ...(isEditing.value ? { client_uuid: undefined, _method: 'put' } : {}),
        }))
        .post(isEditing.value ? props.purchase.urls.update : props.store_url, { forceFormData: true });
}
</script>

<template>
    <StoreLayout :title="isEditing ? `Ubah ${purchase.number}` : 'Nota Pembelian Baru'">
        <template #actions>
            <q-btn flat no-caps label="Batal" class="q-mr-sm" @click="router.visit(isEditing ? purchase.urls.index : index_url)" />
            <q-btn color="primary" unelevated no-caps label="Simpan Draft" :loading="form.processing" @click="submit" />
        </template>

        <q-form class="purchase-form" @submit.prevent="submit">
            <section class="card">
                <h2>Nota</h2>

                <div class="grid grid--three">
                    <SearchableSelect v-model="form.supplier_id" :options="suppliers" label="Supplier" :error="!!form.errors.supplier_id" :error-message="form.errors.supplier_id" />
                    <q-input v-model="form.supplier_document_number" outlined label="Nomor nota supplier" maxlength="100" :error="!!form.errors.supplier_document_number" :error-message="form.errors.supplier_document_number" />
                    <DateInput v-model="form.purchase_date" label="Tanggal nota" outlined :error="!!form.errors.purchase_date" :error-message="form.errors.purchase_date" />
                </div>

                <div class="grid grid--three">
                    <q-select
                        v-model="form.warehouse_id"
                        :options="warehouses"
                        option-value="id"
                        option-label="name"
                        emit-value
                        map-options
                        label="Gudang tujuan"
                        outlined
                        :error="!!form.errors.warehouse_id"
                        :error-message="form.errors.warehouse_id"
                    />
                    <DateInput v-model="form.expected_date" label="Perkiraan tiba (opsional)" outlined clearable :error="!!form.errors.expected_date" :error-message="form.errors.expected_date" />
                    <q-input v-model="form.notes" outlined label="Catatan (opsional)" maxlength="1000" :error="!!form.errors.notes" :error-message="form.errors.notes" />
                </div>
            </section>

            <section class="card">
                <h2>Barang</h2>

                <div class="lines">
                    <div v-for="(line, index) in lines" :key="line.key" class="line">
                        <div class="line__product">
                            <ProductSearchSelect :model-value="line.product" :search-url="search_url" label="Cari barang" dense :error="!!lineError(index, 'product_id')" @update:model-value="pickProduct(line, $event)" />
                            <small v-if="lineError(index, 'product_id')" class="line__error">{{ lineError(index, 'product_id') }}</small>
                        </div>
                        <div class="line__unit">
                            <q-select v-model="line.unit_code" :options="line.product?.units ?? []" label="Satuan" outlined dense :disable="!line.product" :error="!!lineError(index, 'unit_code')" :error-message="lineError(index, 'unit_code')" />
                        </div>
                        <div class="line__quantity">
                            <MoneyInput v-model="line.quantity" allow-decimal label="Jumlah" outlined dense :error="!!lineError(index, 'quantity')" :error-message="lineError(index, 'quantity')" />
                        </div>
                        <div class="line__cost">
                            <MoneyInput v-model="line.unit_cost" allow-decimal label="Harga satuan" prefix="Rp" outlined dense :error="!!lineError(index, 'unit_cost')" :error-message="lineError(index, 'unit_cost')" />
                        </div>
                        <div class="line__subtotal">{{ formatRupiah(subtotalOf(line)) }}</div>
                        <q-btn flat round dense icon="close" color="grey-7" aria-label="Hapus baris" @click="removeLine(line)" />
                    </div>
                </div>
                <small v-if="form.errors.items" class="line__error">{{ form.errors.items }}</small>

                <div class="lines__foot">
                    <q-btn flat no-caps color="primary" icon="add" label="Tambah barang" @click="addLine" />
                    <div class="lines__total">Total <b>{{ formatRupiah(total) }}</b></div>
                </div>
            </section>

            <section class="card">
                <h2>Foto atau PDF nota</h2>
                <q-file v-model="form.attachments" outlined multiple append use-chips label="Pilih file nota (opsional)" accept=".jpg,.jpeg,.png,.webp,.pdf" :max-files="max_attachments" :error="!!form.errors.attachments" :error-message="form.errors.attachments">
                    <template #prepend><q-icon name="attach_file" /></template>
                </q-file>
                <small class="hint">{{ attachmentLimitText }}</small>
                <small v-for="(message, key) in form.errors" v-show="String(key).startsWith('attachments.')" :key="key" class="line__error">{{ message }}</small>
            </section>

            <p class="note">Nota disimpan sebagai draft dan belum mengubah stok maupun harga beli. Setelah dikonfirmasi, barang dan harganya terkunci.</p>
        </q-form>
    </StoreLayout>
</template>

<style scoped>
.purchase-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.card {
    padding: 16px 18px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.card h2 {
    margin: 0 0 12px;
    font-size: 15px;
}

.grid {
    display: grid;
    gap: 12px;
    margin-bottom: 12px;
}

.grid--three {
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.lines {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.line {
    display: grid;
    grid-template-columns: minmax(200px, 3fr) 110px 120px minmax(140px, 1.2fr) 130px 36px;
    align-items: start;
    gap: 8px;
}

.line__subtotal {
    padding-top: 8px;
    font-weight: 600;
    text-align: right;
    white-space: nowrap;
}

.line__error {
    display: block;
    color: #be123c;
    font-size: 12px;
}

.lines__foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 12px;
}

.lines__total {
    font-size: 15px;
}

.lines__total b {
    margin-left: 8px;
    font-size: 18px;
}

.hint {
    display: block;
    margin-top: 6px;
    color: var(--app-mute);
}

.note {
    margin: 0;
    color: var(--app-mute);
    font-size: 12.5px;
}

@media (max-width: 900px) {
    .line {
        grid-template-columns: 1fr 1fr;
    }

    .line__product {
        grid-column: 1 / -1;
    }
}
</style>
