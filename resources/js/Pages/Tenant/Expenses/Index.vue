<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import DateInput from '../../../Components/DateInput.vue';
import ExpenseCategoriesDialog from '../../../Components/ExpenseCategoriesDialog.vue';
import ExpenseFormDialog from '../../../Components/ExpenseFormDialog.vue';
import { expenseStatusChipClass } from '../../../Composables/useExpenseStatus';
import { formatRupiah } from '../../../Composables/useMoneyFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    expenses: { type: Object, required: true },
    filters: { type: Object, required: true },
    totals: { type: Object, required: true },
    categories: { type: Array, required: true },
    sources: { type: Array, required: true },
    statuses: { type: Array, required: true },
    open_shifts: { type: Array, required: true },
    urls: { type: Object, required: true },
});

const page = usePage();
const $q = useQuasar();

const search = ref(props.filters.search ?? '');
const categoryFilter = ref(props.filters.category ?? '');
const sourceFilter = ref(props.filters.source ?? '');
const statusFilter = ref(props.filters.status ?? '');
const fromDate = ref(props.filters.from ?? '');
const toDate = ref(props.filters.to ?? '');

const isFormOpen = ref(false);
const isCategoriesOpen = ref(false);

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });

const activeCategories = computed(() => props.categories.filter((expenseCategory) => expenseCategory.is_active));

function applyFilters(extra = {}) {
    router.get(
        page.url.split('?')[0],
        {
            search: search.value || undefined,
            category: categoryFilter.value || undefined,
            source: sourceFilter.value || undefined,
            status: statusFilter.value || undefined,
            from: fromDate.value || undefined,
            to: toDate.value || undefined,
            ...extra,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});

watch([categoryFilter, sourceFilter, statusFilter, fromDate, toDate], () => applyFilters());

/** A search typed just before leaving the page must not pull the admin back to it. */
onBeforeUnmount(() => clearTimeout(searchTimer));

/**
 * Asks why the expense is cancelled, then cancels it. It stays on the list as cancelled, with the reason.
 *
 * @param {{description: string, amount: string, void_url: string}} expense
 */
function confirmVoid(expense) {
    $q.dialog({
        title: 'Batalkan pengeluaran?',
        message: `${expense.description} (${formatRupiah(expense.amount)}) tidak dihitung lagi. Isi alasannya.`,
        prompt: { model: '', type: 'text', label: 'Alasan pembatalan', outlined: true, dense: true, isValid: (reason) => reason.trim() !== '' },
        cancel: { label: 'Kembali', flat: true, noCaps: true },
        ok: { label: 'Batalkan Pengeluaran', color: 'negative', unelevated: true, noCaps: true },
        persistent: true,
    }).onOk((reason) => router.post(expense.void_url, { reason }, { preserveScroll: true }));
}
</script>

<template>
    <StoreLayout title="Pengeluaran">
        <template #actions>
            <q-btn flat no-caps icon="category" label="Kategori" @click="isCategoriesOpen = true" />
            <q-btn color="primary" unelevated no-caps icon="add" label="Pengeluaran" class="q-ml-sm" @click="isFormOpen = true" />
        </template>

        <div class="totals">
            <div class="total"><small>Dari laci</small><b>{{ formatRupiah(totals.drawer) }}</b></div>
            <div class="total"><small>Sumber lain</small><b>{{ formatRupiah(totals.other) }}</b></div>
            <div class="total total--sum"><small>Total</small><b>{{ formatRupiah(totals.total) }}</b></div>
        </div>

        <div class="filters">
            <q-input v-model="search" outlined dense clearable placeholder="Cari nomor, keterangan, atau penerima" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select
                v-model="categoryFilter"
                :options="[{ id: '', name: 'Semua kategori' }, ...categories]"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                behavior="menu"
                outlined
                dense
                class="filters__select"
            />
            <q-select v-model="sourceFilter" :options="[{ value: '', label: 'Semua sumber' }, ...sources]" option-value="value" option-label="label" emit-value map-options behavior="menu" outlined dense class="filters__select" />
            <q-select v-model="statusFilter" :options="[{ value: '', label: 'Semua status' }, ...statuses]" option-value="value" option-label="label" emit-value map-options behavior="menu" outlined dense class="filters__select" />
            <DateInput v-model="fromDate" label="Dari" outlined dense clearable class="filters__date" />
            <DateInput v-model="toDate" label="Sampai" outlined dense clearable class="filters__date" />
        </div>

        <div class="expenses">
            <table class="expenses__table">
                <thead>
                    <tr>
                        <th>Keterangan</th>
                        <th>Kategori</th>
                        <th>Sumber</th>
                        <th class="expenses__right">Nominal</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="expenses.data.length === 0">
                        <td colspan="6" class="expenses__empty">Belum ada pengeluaran. Catat uang keluar untuk bensin, galon, listrik, upah harian, dan sejenisnya.</td>
                    </tr>
                    <tr v-for="expense in expenses.data" :key="expense.id" :class="{ 'expenses__row--voided': expense.status === 'voided' }">
                        <td>
                            <b>{{ expense.description }}</b>
                            <small>
                                {{ expense.number }} · {{ dateFormat.format(new Date(expense.business_date)) }}
                                <template v-if="expense.paid_to"> · {{ expense.paid_to }}</template>
                            </small>
                            <small v-if="expense.status === 'voided'" class="expenses__void">
                                Dibatalkan oleh {{ expense.voided_by_name }}: {{ expense.void_reason }}
                            </small>
                        </td>
                        <td>{{ expense.category_name }}</td>
                        <td>
                            {{ expense.source_label }}
                            <small v-if="expense.drawer_label">{{ expense.drawer_label }}</small>
                        </td>
                        <td class="expenses__right">{{ formatRupiah(expense.amount) }}</td>
                        <td><span class="chip" :class="expenseStatusChipClass[expense.status]">{{ expense.status_label }}</span></td>
                        <td class="expenses__actions">
                            <q-btn v-if="expense.can_be_voided" flat dense no-caps color="negative" label="Batalkan" @click="confirmVoid(expense)" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="expenses.total > 0" class="pager">
            <span class="expenses__muted">{{ expenses.from }}–{{ expenses.to }} dari {{ expenses.total }} pengeluaran</span>
            <q-pagination
                v-if="expenses.last_page > 1"
                :model-value="expenses.current_page"
                :max="expenses.last_page"
                :max-pages="7"
                direction-links
                boundary-links
                color="primary"
                @update:model-value="applyFilters({ page: $event })"
            />
        </div>

        <ExpenseFormDialog v-model="isFormOpen" :categories="activeCategories" :sources="sources" :open-shifts="open_shifts" :store-url="urls.store" />
        <ExpenseCategoriesDialog v-model="isCategoriesOpen" :categories="categories" :create-url="urls.category_store" />
    </StoreLayout>
</template>

<style scoped>
.totals {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 14px;
}

.total {
    padding: 14px 16px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.total small,
.total b {
    display: block;
}

.total small {
    color: var(--app-mute);
    font-size: 12px;
}

.total b {
    font-size: 20px;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 14px;
}

.filters__search {
    flex: 2 1 260px;
}

.filters__select {
    flex: 1 1 160px;
}

.filters__date {
    flex: 1 1 150px;
}

.expenses {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.expenses__table {
    width: 100%;
    border-collapse: collapse;
}

.expenses__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.expenses__table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.expenses__table td small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.expenses__table tr:last-child td {
    border-bottom: 0;
}

.expenses__table .expenses__right {
    text-align: right;
    white-space: nowrap;
}

.expenses__row--voided td {
    color: var(--app-mute);
}

.expenses__row--voided .expenses__right {
    text-decoration: line-through;
}

.expenses__table .expenses__void {
    color: #be123c;
}

.expenses__actions {
    text-align: right;
    white-space: nowrap;
}

.expenses__muted {
    color: var(--app-mute);
    font-size: 12.5px;
}

.expenses__empty {
    padding: 28px 16px !important;
    color: var(--app-mute);
    text-align: center;
}

.pager {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 14px;
}
</style>
