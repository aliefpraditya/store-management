<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';
import DamagedGoodsDialog from '../../../Components/DamagedGoodsDialog.vue';
import ProductSearchSelect from '../../../Components/ProductSearchSelect.vue';
import { formatQuantity, formatStock } from '../../../Composables/useQuantityFormat';
import StoreLayout from '../../../Layouts/StoreLayout.vue';

const props = defineProps({
    opname: { type: Object, required: true },
    summary: { type: Object, required: true },
    items: { type: Object, required: true },
    filters: { type: Object, required: true },
    /** The reasons a variance can be given; `needs_damage_decision` marks the one that asks where damaged goods go. */
    reasons: { type: Array, required: true },
    /** What the damaged goods decisions add up to, for the posting confirmation; null outside the review of a routine opname. */
    damage_summary: { type: Object, default: null },
    sealed_pack_drops: { type: Array, default: () => [] },
});

const page = usePage();
const $q = useQuasar();

const steps = [
    { status: 'counting', label: 'Menghitung' },
    { status: 'review', label: 'Review' },
    { status: 'posted', label: 'Diposting' },
];

const currentStepIndex = computed(() => steps.findIndex((step) => step.status === props.opname.status));

const dateFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' });
const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
const moneyFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

const scopeSummary = computed(() =>
    props.opname.scope === 'category'
        ? [props.opname.category && `Kategori ${props.opname.category}`, props.opname.brand && `Merek ${props.opname.brand}`].filter(Boolean).join(' · ')
        : props.opname.scope_label,
);

const search = ref(props.filters.search);
const filter = ref(props.filters.filter);

const filterOptions = computed(() => [
    { label: 'Semua barang', value: '' },
    { label: 'Belum dihitung', value: 'uncounted' },
    { label: 'Ada selisih', value: 'variance' },
    ...(props.opname.requires_review ? [{ label: 'Selisih belum direview', value: 'unreviewed' }] : []),
]);

/**
 * What the admin is typing, per item id: one quantity per unit counted (5 DUS and 3 PAIL are two parts), the reason of the
 * variance from the store's list, and a note.
 * @type {import('vue').Ref<Record<number, {parts: {unit_code: string, quantity: string}[], reason_id: number|null, note: string}>>}
 */
const typedCounts = ref({});

/** The typed counts as they were last loaded from the server, to tell which ones were edited. */
const loadedCounts = ref({});

/**
 * A saved count comes back as one base-unit quantity, so it is shown as a single part in the base unit. A product with a
 * sealed pack unit (a roll of wire) is split into the whole packs counted and the loose rest, so that saving it again does
 * not lose which of the metres were whole rolls.
 */
function savedPartsOf(item) {
    if (item.counted_quantity === null) {
        return [{ unit_code: item.base_unit, quantity: '' }];
    }

    const sealedPackUnit = item.units.find((unit) => unit.is_sealed_pack);

    if (!sealedPackUnit || item.counted_packs === null) {
        return [{ unit_code: item.base_unit, quantity: String(Number(item.counted_quantity)) }];
    }

    const looseQuantity = Math.round((Number(item.counted_quantity) - item.counted_packs * Number(sealedPackUnit.conversion_factor)) * 1000) / 1000;

    return [
        { unit_code: sealedPackUnit.code, quantity: String(item.counted_packs) },
        { unit_code: item.base_unit, quantity: String(looseQuantity) },
    ];
}

function typedCountOf(item) {
    return {
        parts: savedPartsOf(item),
        reason_id: item.reason_id,
        note: item.note ?? '',
    };
}

/**
 * What the admin decides while reviewing the variances, per item id: the reason, a note, whether the item is reviewed, and
 * for damaged goods where the units go (dead stock, DG product with its price per base unit, full price).
 * The `...Packs` say how many whole sealed packs (rolls) each of the three parts holds, '' when they were not said.
 * @type {import('vue').Ref<Record<number, {reason_id: number|null, note: string, is_reviewed: boolean, dead: string, dg: string, full: string, price: string, deadPacks: string, dgPacks: string, fullPacks: string}>>}
 */
const reviews = ref({});

/** The reviews as they were last loaded from the server, to tell which ones were edited. */
const loadedReviews = ref({});

const textOf = (value) => (value === null || value === undefined ? '' : String(Number(value)));

function reviewOf(item) {
    return {
        reason_id: item.reason_id,
        note: item.note ?? '',
        is_reviewed: item.is_reviewed,
        dead: textOf(item.damage?.dead_stock_quantity),
        dg: textOf(item.damage?.damaged_goods_quantity),
        full: textOf(item.damage?.full_price_quantity),
        price: textOf(item.damage?.damaged_goods_price ?? item.damage?.existing?.price),
        deadPacks: textOf(item.damage?.dead_stock_packs),
        dgPacks: textOf(item.damage?.damaged_goods_packs),
        fullPacks: textOf(item.damage?.full_price_packs),
    };
}

watch(
    () => props.items.data,
    (items) => {
        typedCounts.value = Object.fromEntries(items.map((item) => [item.id, typedCountOf(item)]));
        loadedCounts.value = Object.fromEntries(items.map((item) => [item.id, JSON.stringify(typedCountOf(item))]));
        reviews.value = Object.fromEntries(items.map((item) => [item.id, reviewOf(item)]));
        loadedReviews.value = Object.fromEntries(items.map((item) => [item.id, JSON.stringify(reviewOf(item))]));
    },
    { immediate: true },
);

const dirtyItems = computed(() => props.items.data.filter((item) => JSON.stringify(typedCounts.value[item.id]) !== loadedCounts.value[item.id]));

const isDirty = computed(() => dirtyItems.value.length > 0);

const dirtyReviews = computed(() => props.items.data.filter((item) => JSON.stringify(reviews.value[item.id]) !== loadedReviews.value[item.id]));

const isReviewDirty = computed(() => dirtyReviews.value.length > 0);

/** Whether the variances are explained and reviewed on this screen: only in the review step of a routine opname. */
const isReviewing = computed(() => props.opname.can_review);

/** The row an input of the item edits: the count while counting, the review while reviewing. */
const editableRow = (item) => (props.opname.can_count ? typedCounts.value[item.id] : reviews.value[item.id]);

const hasVariance = (item) => varianceOf(item) !== null && varianceOf(item) !== 0;

/** Counting lets every item be given a reason; reviewing only the ones that have a variance. */
const isReasonEditable = (item) => props.opname.can_count || (isReviewing.value && (props.opname.is_returns_check || hasVariance(item)));

/** An inspection of returns is always that, so its items are given no reason; only a note and where the goods go. */
const isReasonPickable = (item) => !props.opname.is_returns_check && isReasonEditable(item);

const reasonOptionsOf = (item) =>
    props.reasons.filter((reason) => reason.is_active || reason.id === editableRow(item)?.reason_id).map((reason) => ({ label: reason.name, value: reason.id }));

const reasonOf = (reasonId) => props.reasons.find((reason) => reason.id === reasonId) ?? null;

/**
 * Whether the item has to be reviewed: one with a variance, or, when inspecting returns, every counted one, since each unit
 * still has to be sent on to the shelf, dead stock or a DG product.
 */
const needsReview = (item) => (props.opname.is_returns_check ? item.counted_quantity !== null : hasVariance(item));

/** A damaged reason asks where the damaged units go; an inspection of returns asks it for everything counted. */
const isDamagedReason = (item) =>
    props.opname.is_returns_check ? Number(item.counted_quantity ?? 0) > 0 : (reasonOf(reviews.value[item.id]?.reason_id)?.needs_damage_decision ?? false);

const damageNumber = (value) => (value === '' ? 0 : Number(value));

/** What the decision has to split, in the base unit: the shortage of a damaged reason, or everything counted when inspecting returns. */
const shortageOf = (item) => (props.opname.is_returns_check ? Number(item.counted_quantity ?? 0) : Math.abs(Math.min(varianceOf(item) ?? 0, 0)));

/**
 * @returns {boolean} whether the three parts of the damaged decision are filled and add up to the shortage
 */
function isDamageDecided(item) {
    const row = reviews.value[item.id];

    if ([row.dead, row.dg, row.full].some((part) => part === '')) {
        return false;
    }

    const sum = Math.round((damageNumber(row.dead) + damageNumber(row.dg) + damageNumber(row.full)) * 1000) / 1000;

    return sum === shortageOf(item) && (damageNumber(row.dg) === 0 || (row.price !== '' && item.damage?.damaged_goods_problem === null));
}

/**
 * An item can be marked reviewed once a variance has a reason and, for damaged goods (or any returned unit), a complete
 * decision. A returned item without a variance needs no reason.
 */
function isReviewable(item) {
    const row = reviews.value[item.id];

    if (!needsReview(item) || (!props.opname.is_returns_check && row.reason_id === null)) {
        return false;
    }

    if (props.opname.is_returns_check) {
        return !isDamagedReason(item) || isDamageDecided(item);
    }

    return !isDamagedReason(item) || (varianceOf(item) < 0 && isDamageDecided(item));
}

/**
 * A new reason is a new thing to review, and a reason that is not damaged goods has no decision to keep. Picking the reason
 * is the admin's action, so the item is marked reviewed on its own once nothing else is missing (a damaged reason still
 * waits for its decision); the admin can untick it again.
 */
function changeReason(item) {
    const row = reviews.value[item.id];

    if (!isDamagedReason(item)) {
        Object.assign(row, { dead: '', dg: '', full: '', price: '', deadPacks: '', dgPacks: '', fullPacks: '' });
    }

    row.is_reviewed = isReviewable(item);
}

function markAllReviewed() {
    props.items.data.filter(isReviewable).forEach((item) => {
        reviews.value[item.id].is_reviewed = true;
    });
}

/**
 * How many whole sealed packs (rolls) the decision of the item can send on: the rolls counted in a returns warehouse, or the
 * rolls a routine count found missing from the shelf. The same rule the server checks when posting.
 * @returns {number|null} null for a product without a sealed pack unit
 */
function packsToDivideOf(item) {
    const sealedPackUnit = item.units.find((unit) => unit.is_sealed_pack);

    if (!sealedPackUnit) {
        return null;
    }

    const factor = Number(sealedPackUnit.conversion_factor);
    const systemPacks = item.system_intact_packs ?? 0;
    const countedPacks = Math.min(item.counted_packs ?? systemPacks, Math.floor(Number(item.counted_quantity ?? 0) / factor + 1e-9));

    return props.opname.is_returns_check ? countedPacks : Math.max(0, systemPacks - countedPacks);
}

const damageDialog = ref({ isOpen: false, itemId: null });

const damageDialogItem = computed(() => {
    const item = props.items.data.find((candidate) => candidate.id === damageDialog.value.itemId);

    if (item === undefined) {
        return null;
    }

    return {
        productName: item.product.name,
        baseUnit: item.base_unit,
        units: item.units,
        shortage: shortageOf(item),
        countedPacks: props.opname.is_returns_check ? item.counted_packs : null,
        maxPacks: packsToDivideOf(item),
        isReturn: props.opname.is_returns_check,
        damagedGoodsSku: item.damage?.damaged_goods_sku ?? '',
        damagedGoodsProblem: item.damage?.damaged_goods_problem ?? null,
        existingName: item.damage?.existing?.name ?? null,
        existingSku: item.damage?.existing?.sku ?? null,
        existingPrice: item.damage?.existing?.price ?? null,
        originalCost: item.damage?.original_cost_price ?? null,
        originalPrice: item.damage?.original_selling_price ?? null,
    };
});

function openDamageDialog(item) {
    damageDialog.value = { isOpen: true, itemId: item.id };
}

/** Deciding where the damaged units go is the last thing a damaged item waits for, so a complete decision marks it reviewed. */
function saveDamageDecision(decision) {
    const item = props.items.data.find((candidate) => candidate.id === damageDialog.value.itemId);
    const row = reviews.value[item.id];

    Object.assign(row, decision);
    row.is_reviewed = isReviewable(item);
}

/**
 * A decided quantity (in the base unit) in the product's units, e.g. 130 as "1 ROL + 30 MTR"; `packs` is how many of it are
 * whole sealed packs, when the admin said.
 */
const decidedText = (item, quantity, packs = null) =>
    formatStock(Number(quantity), item.units, item.base_unit, packs === '' || packs === null || packs === undefined ? null : Number(packs));

const damageSummary = (item) => {
    const row = reviews.value[item.id];

    const normalLabel = props.opname.is_returns_check ? 'Normal' : 'Penuh';

    return isDamageDecided(item)
        ? `Dead stock ${decidedText(item, row.dead, row.deadPacks)} · DG ${decidedText(item, row.dg, row.dgPacks)} · ${normalLabel} ${decidedText(item, row.full, row.fullPacks)}`
        : 'Tentukan keputusan';
};

const uncountedCount = computed(() => props.summary.items_count - props.summary.counted_count);

const unreviewedCount = computed(() => props.summary.review_count - props.summary.reviewed_count);

/**
 * @returns {number|null} the typed count in the base unit, or null while nothing is typed
 */
function countedBase(item) {
    const filledParts = typedCounts.value[item.id].parts.filter((part) => part.quantity !== '' && part.quantity !== null);

    if (filledParts.length === 0) {
        return null;
    }

    const factors = Object.fromEntries(item.units.map((unit) => [unit.code, Number(unit.conversion_factor)]));
    const total = filledParts.reduce((sum, part) => sum + Number(part.quantity) * (factors[part.unit_code] ?? 0), 0);

    return Math.round(total * 1000) / 1000;
}

/**
 * While counting the variance follows what is typed; afterwards it is the one the server worked out. When the
 * opname counts arrivals, what is typed is the variance itself.
 * @returns {number|null}
 */
function varianceOf(item) {
    if (!props.opname.can_count) {
        return item.variance === null ? null : Number(item.variance);
    }

    const counted = countedBase(item);

    if (counted === null) {
        return null;
    }

    return props.opname.is_counting_arrivals ? counted : Math.round((counted - Number(item.system_quantity)) * 1000) / 1000;
}

/**
 * @returns {number|null} how many of the typed parts are whole sealed packs, or null for a product without a sealed pack unit
 *   or while nothing is typed
 */
function countedPacksOf(item) {
    const sealedPackUnit = item.units.find((unit) => unit.is_sealed_pack);

    if (!sealedPackUnit || countedBase(item) === null) {
        return null;
    }

    return typedCounts.value[item.id].parts
        .filter((part) => part.unit_code === sealedPackUnit.code && part.quantity !== '' && part.quantity !== null)
        .reduce((sum, part) => sum + Number(part.quantity), 0);
}

/**
 * The variance of a product with a sealed pack unit, split the way it is counted: the whole packs and the loose rest
 * (3 whole rolls + 100 m counted as 200 m and no roll is -3 ROL and +100 MTR), so a lost roll is not hidden in the metres.
 * @returns {{packChange: number, looseChange: number, packCode: string}|null} null for a product without a sealed pack unit,
 *   or while nothing is typed
 */
function splitVarianceOf(item) {
    const sealedPackUnit = item.units.find((unit) => unit.is_sealed_pack);
    const variance = varianceOf(item);
    const countedPacks = props.opname.can_count ? countedPacksOf(item) : item.counted_packs;

    if (!sealedPackUnit || variance === null || countedPacks === null) {
        return null;
    }

    const packChange = countedPacks - (props.opname.is_counting_arrivals ? 0 : item.system_intact_packs);
    const looseChange = Math.round((variance - packChange * Number(sealedPackUnit.conversion_factor)) * 1000) / 1000;

    return { packChange, looseChange, packCode: sealedPackUnit.code };
}

/**
 * @returns {string} the variance in words: "−3 ROL +100 MTR" for a product with a sealed pack unit, otherwise "−200 MTR"
 */
function varianceTextOf(item) {
    const split = splitVarianceOf(item);

    if (split === null || (split.packChange === 0 && split.looseChange === 0)) {
        return formatVariance(varianceOf(item), item.base_unit);
    }

    return [
        split.packChange !== 0 ? formatVariance(split.packChange, split.packCode) : null,
        split.looseChange !== 0 ? formatVariance(split.looseChange, item.base_unit) : null,
    ]
        .filter(Boolean)
        .join(' ');
}

/** A lost whole pack is a loss even when the metres add up, so it colours the chip like a shortage. */
function varianceChipClassOf(item) {
    const packChange = splitVarianceOf(item)?.packChange ?? 0;

    return packChange < 0 ? 'chip--bad' : varianceClass(varianceOf(item));
}

/**
 * @returns {string|null} the stock once the typed count (or arrival) is applied, or null while nothing is typed
 */
function stockAfter(item) {
    const variance = varianceOf(item);

    if (variance === null) {
        return null;
    }

    const countedPacks = countedPacksOf(item);
    const intactPacksAfter = countedPacks === null ? null : props.opname.is_counting_arrivals ? item.system_intact_packs + countedPacks : countedPacks;

    return formatStock(Number(item.system_quantity) + variance, item.units, item.base_unit, intactPacksAfter);
}

function varianceClass(variance) {
    if (variance === 0 || (props.opname.is_counting_arrivals && variance > 0)) {
        return 'chip--ok';
    }

    return variance > 0 ? 'chip--warn' : 'chip--bad';
}

function formatVariance(variance, baseUnit) {
    return `${variance > 0 ? '+' : variance < 0 ? '−' : ''}${formatQuantity(Math.abs(variance))} ${baseUnit}`;
}

function formatMoney(value) {
    const amount = Number(value);

    return `${amount > 0 ? '+' : amount < 0 ? '−' : ''}Rp ${moneyFormat.format(Math.abs(amount))}`;
}

function addPart(item) {
    const row = typedCounts.value[item.id];
    const unusedUnit = item.units.find((unit) => !row.parts.some((part) => part.unit_code === unit.code));

    row.parts.push({ unit_code: (unusedUnit ?? item.units[0]).code, quantity: '' });
}

function removePart(item, partIndex) {
    typedCounts.value[item.id].parts.splice(partIndex, 1);
}

/**
 * @param {() => void} [afterSaved] runs once the counts are stored
 */
function saveCounts(afterSaved) {
    const counts = dirtyItems.value.map((item) => ({
        item_id: item.id,
        parts: typedCounts.value[item.id].parts.map((part) => ({ unit_code: part.unit_code, quantity: part.quantity === '' ? null : part.quantity })),
        reason_id: typedCounts.value[item.id].reason_id,
        note: typedCounts.value[item.id].note || null,
    }));

    router.put(
        props.opname.urls.counts,
        { counts },
        {
            preserveScroll: true,
            onSuccess: () => afterSaved?.(),
            onError: (errors) => $q.notify({ type: 'negative', position: 'top', message: Object.values(errors)[0] ?? 'Hitungan gagal disimpan.' }),
        },
    );
}

/**
 * @param {() => void} [afterSaved] runs once the review is stored
 */
function saveReview(afterSaved) {
    const rows = dirtyReviews.value.map((item) => {
        const row = reviews.value[item.id];
        const isDamaged = isDamagedReason(item);

        return {
            item_id: item.id,
            reason_id: row.reason_id,
            note: row.note || null,
            is_reviewed: row.is_reviewed,
            dead_stock_quantity: isDamaged && row.dead !== '' ? row.dead : null,
            damaged_goods_quantity: isDamaged && row.dg !== '' ? row.dg : null,
            full_price_quantity: isDamaged && row.full !== '' ? row.full : null,
            damaged_goods_price: isDamaged && damageNumber(row.dg) > 0 && row.price !== '' ? row.price : null,
            dead_stock_packs: isDamaged && row.deadPacks !== '' ? row.deadPacks : null,
            damaged_goods_packs: isDamaged && row.dgPacks !== '' ? row.dgPacks : null,
            full_price_packs: isDamaged && row.fullPacks !== '' ? row.fullPacks : null,
        };
    });

    router.put(
        props.opname.urls.review,
        { rows },
        {
            preserveScroll: true,
            onSuccess: () => afterSaved?.(),
            onError: (errors) => $q.notify({ type: 'negative', position: 'top', message: Object.values(errors)[0] ?? 'Review gagal disimpan.' }),
        },
    );
}

/**
 * Runs the next step only after unsaved counts and reviews are stored, so moving on never throws typed work away.
 * @param {() => void} runNext
 */
function afterSavingCounts(runNext) {
    if (isDirty.value) {
        saveCounts(runNext);
    } else if (isReviewDirty.value) {
        saveReview(runNext);
    } else {
        runNext();
    }
}

function postAction(url, payload = {}) {
    router.post(url, payload, { preserveScroll: true });
}

function confirmDialog({ title, message, okLabel, okColor = 'primary' }, onOk) {
    $q.dialog({
        title,
        message,
        cancel: { label: 'Batal', flat: true, noCaps: true },
        ok: { label: okLabel, color: okColor, unelevated: true, noCaps: true },
        persistent: true,
    }).onOk(onOk);
}

function finishCounting() {
    afterSavingCounts(() => {
        if (uncountedCount.value === 0) {
            postAction(props.opname.urls.finish);

            return;
        }

        confirmDialog(
            {
                title: 'Selesai menghitung?',
                message: `${uncountedCount.value} barang belum dihitung. Stoknya tidak berubah saat diposting.`,
                okLabel: 'Selesai hitung',
            },
            () => postAction(props.opname.urls.finish),
        );
    });
}

function postOpname() {
    afterSavingCounts(confirmPosting);
}

const isPostOpen = ref(false);

/** An inspection of returns moves every counted item out of the returns warehouse, so what it changes is not "the variance". */
const postMessage = computed(() => {
    if (props.opname.is_returns_check) {
        return `${props.summary.counted_count} barang akan dilepas dari gudang retur dan dibagi ke stok jual, dead stock, atau barang DG sesuai keputusan. Selisih dengan catatan stok dicatat lebih dulu. Setelah diposting, opname tidak bisa diubah.`;
    }

    return props.opname.is_counting_arrivals
        ? `Stok sekitar ${props.summary.variance_count} barang akan bertambah, dengan nilai sekitar ${formatMoney(props.summary.variance_value)}. Setelah diposting, opname tidak bisa diubah.`
        : `Stok sekitar ${props.summary.variance_count} barang akan diperbarui, dengan nilai selisih sekitar ${formatMoney(props.summary.variance_value)}. Setelah diposting, opname tidak bisa diubah.`;
});

/** How many damaged goods rows the posting confirmation lists before saying how many more there are. */
const DAMAGE_ROWS_SHOWN = 6;

const damageRows = computed(() => props.damage_summary?.items.slice(0, DAMAGE_ROWS_SHOWN) ?? []);

const sealedPackDropRows = computed(() => props.sealed_pack_drops.slice(0, DAMAGE_ROWS_SHOWN));

const hasDamageSummary = computed(() => (props.damage_summary?.items.length ?? 0) > 0);

const rupiah = (value) => `Rp ${moneyFormat.format(Number(value))}`;

function confirmPosting() {
    isPostOpen.value = true;
}

function post() {
    isPostOpen.value = false;
    postAction(props.opname.urls.post);
}

/** An opname still being counted is erased for good; one in review is cancelled and hidden from the list but kept. */
function cancelOpname() {
    confirmDialog(
        props.opname.is_erased_on_cancel
            ? { title: 'Hapus opname?', message: 'Opname ini dihapus beserta hitungannya dan tidak disimpan. Stok tidak berubah.', okLabel: 'Hapus opname', okColor: 'negative' }
            : { title: 'Batalkan opname?', message: 'Stok tidak berubah. Opname disembunyikan dari daftar dan hanya bisa dilihat lewat filter status Dibatalkan.', okLabel: 'Batalkan opname', okColor: 'negative' },
        () => postAction(props.opname.urls.cancel),
    );
}

const isAddDialogOpen = ref(false);
const productToAdd = ref(null);
/**
 * The count typed for the picked product, one part per unit (1 ROL and 200 MTR are two parts), like a row of the table.
 * @type {import('vue').Ref<{unit_code: string, quantity: string}[]>}
 */
const addedParts = ref([]);
const isAdding = ref(false);
const productSearchField = ref(null);
const firstAddedQuantityField = ref(null);

/** Only the first quantity box is kept, to move the cursor there once a product is picked. */
function trackFirstAddedQuantityField(field, partIndex) {
    if (partIndex === 0) {
        firstAddedQuantityField.value = field;
    }
}

/**
 * A picked product waits for its quantity, so adding and counting it is one step.
 * @param {{id: number, base_unit: string}|null} product
 */
function pickProductToAdd(product) {
    if (product === null) {
        return;
    }

    addedParts.value = [{ unit_code: product.base_unit, quantity: '' }];
    nextTick(() => firstAddedQuantityField.value?.focus());
}

function addAddedPart() {
    const unusedUnitCode = productToAdd.value.units.find((unitCode) => !addedParts.value.some((part) => part.unit_code === unitCode));

    addedParts.value.push({ unit_code: unusedUnitCode ?? productToAdd.value.units[0], quantity: '' });
}

/**
 * Adds the picked product together with its count, then keeps the dialog open and back on the search box, so a shelf can
 * be counted item after item without touching the mouse. A unit left empty is not part of the count.
 */
function addProduct() {
    if (productToAdd.value === null || isAdding.value) {
        return;
    }

    const filledParts = addedParts.value.filter((part) => part.quantity !== '' && part.quantity !== null);

    if (filledParts.length === 0) {
        firstAddedQuantityField.value?.focus();

        return;
    }

    isAdding.value = true;

    const payload = { product_id: productToAdd.value.id, parts: filledParts };

    afterSavingCounts(() =>
        router.post(props.opname.urls.add_item, payload, {
            preserveScroll: true,
            onSuccess: () => {
                productToAdd.value = null;
                nextTick(() => productSearchField.value?.focus());
            },
            onError: (errors) => $q.notify({ type: 'negative', position: 'top', message: Object.values(errors)[0] ?? 'Barang gagal ditambahkan.' }),
            onFinish: () => (isAdding.value = false),
        }),
    );
}

function removeProduct(item) {
    afterSavingCounts(() => router.delete(item.remove_url, { preserveScroll: true }));
}

function applyFilters(extra = {}) {
    afterSavingCounts(() =>
        router.get(page.url.split('?')[0], { search: search.value || undefined, filter: filter.value || undefined, ...extra }, { preserveState: true, replace: true, preserveScroll: true }),
    );
}

let searchTimer;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 300);
});

watch(filter, () => applyFilters());

/** A search typed just before leaving the page must not pull the admin back to it. */
onBeforeUnmount(() => clearTimeout(searchTimer));
</script>

<template>
    <StoreLayout :title="opname.number">
        <template #actions>
            <q-btn v-if="opname.can_cancel" flat no-caps color="negative" :label="opname.is_erased_on_cancel ? 'Hapus Opname' : 'Batalkan'" class="q-mr-sm" @click="cancelOpname" />
            <q-btn v-if="opname.can_count" outline no-caps color="primary" label="Simpan" :disable="!isDirty" class="q-mr-sm" @click="saveCounts()" />
            <q-btn v-if="opname.can_count" color="primary" unelevated no-caps label="Selesai Hitung" @click="finishCounting" />
            <q-btn v-if="opname.can_reopen" flat no-caps label="Hitung Ulang" class="q-mr-sm" @click="postAction(opname.urls.reopen)" />
            <q-btn v-if="isReviewing" outline no-caps color="primary" label="Simpan Review" :disable="!isReviewDirty" class="q-mr-sm" @click="saveReview()" />
            <q-btn v-if="opname.can_post" color="positive" unelevated no-caps label="Posting" @click="postOpname" />
        </template>

        <div v-if="opname.status === 'cancelled'" class="steps"><span class="chip chip--bad">Dibatalkan</span></div>
        <ol v-else class="steps">
            <li v-for="(step, index) in steps" :key="step.status" :class="{ 'steps__item--done': index < currentStepIndex, 'steps__item--current': index === currentStepIndex }">
                <q-icon v-if="index < currentStepIndex" name="check_circle" size="18px" />
                <span v-else class="steps__dot" />
                {{ step.label }}
            </li>
        </ol>

        <section class="meta">
            <div><small>Gudang</small><b>{{ opname.warehouse }}</b></div>
            <div><small>Tanggal</small><b>{{ dateFormat.format(new Date(opname.opname_date)) }}</b></div>
            <div><small>Cakupan</small><b>{{ scopeSummary }}</b></div>
            <div><small>Alasan</small><b>{{ opname.reason_label ?? '—' }}</b></div>
            <div v-if="opname.snapshot_at"><small>Snapshot stok</small><b>{{ dateTimeFormat.format(new Date(opname.snapshot_at)) }}</b></div>
            <div v-if="opname.posted_at"><small>Diposting</small><b>{{ dateTimeFormat.format(new Date(opname.posted_at)) }}</b></div>
        </section>

        <section v-if="summary.items_count > 0" class="kpis">
            <div class="kpi"><small>{{ opname.is_counting_arrivals ? 'Diisi' : 'Dihitung' }}</small><b>{{ summary.counted_count }} / {{ summary.items_count }}</b></div>
            <div v-if="opname.requires_review && opname.status !== 'counting'" class="kpi">
                <small>Selisih sudah direview</small>
                <b :class="{ 'kpi--bad': unreviewedCount > 0 }">{{ summary.reviewed_count }} / {{ summary.review_count }}</b>
            </div>
            <div class="kpi"><small>{{ opname.is_counting_arrivals ? 'Barang bertambah' : 'Barang ada selisih' }}</small><b>{{ summary.variance_count }}</b></div>
            <div class="kpi">
                <small>{{ opname.is_counting_arrivals ? 'Nilai penambahan (estimasi)' : 'Nilai selisih (estimasi)' }}</small>
                <b :class="{ 'kpi--bad': Number(summary.variance_value) < 0 }">{{ formatMoney(summary.variance_value) }}</b>
            </div>
        </section>

        <div class="filters">
            <q-btn v-if="opname.can_manage_products" color="primary" outline no-caps icon="add" label="Tambah Barang" @click="isAddDialogOpen = true" />
            <q-input v-model="search" outlined dense clearable placeholder="Cari di daftar ini (nama atau SKU)" class="filters__search">
                <template #prepend><q-icon name="search" /></template>
            </q-input>
            <q-select v-model="filter" :options="filterOptions" option-value="value" option-label="label" emit-value map-options outlined dense class="filters__select" />
            <q-btn v-if="isReviewing" flat no-caps color="grey-8" icon="done_all" label="Tandai yang lengkap di halaman ini sudah direview" @click="markAllReviewed" />
            <q-btn
                v-if="opname.can_count && !opname.is_counting_arrivals && uncountedCount > 0"
                flat
                no-caps
                color="grey-8"
                label="Tandai yang belum dihitung = 0"
                @click="afterSavingCounts(() => postAction(opname.urls.zero_uncounted))"
            />
        </div>

        <div class="lines">
            <table class="lines__table">
                <thead>
                    <tr>
                        <th>Barang</th>
                        <th class="lines__right">Stok sistem</th>
                        <th>{{ opname.is_counting_arrivals ? 'Jumlah datang' : 'Hitung fisik' }}</th>
                        <th class="lines__right">{{ opname.is_counting_arrivals ? 'Bertambah' : 'Selisih' }}</th>
                        <th v-if="opname.is_counting_arrivals" class="lines__right">Stok setelah</th>
                        <th>{{ opname.is_returns_check ? 'Tujuan barang' : 'Alasan' }}</th>
                        <th>Catatan</th>
                        <th v-if="opname.requires_review && ['review', 'posted'].includes(opname.status)">Direview</th>
                        <th v-if="opname.can_manage_products"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="items.data.length === 0">
                        <td colspan="9" class="lines__empty">
                            <template v-if="opname.can_manage_products && summary.items_count === 0">Tambahkan barang yang akan dihitung.</template>
                            <template v-else>Tidak ada barang yang cocok.</template>
                        </td>
                    </tr>
                    <tr v-for="item in items.data" :key="item.id">
                        <td>
                            <b>{{ item.product.name }}</b>
                            <small>{{ item.product.sku }}</small>
                        </td>
                        <td class="lines__right">{{ formatStock(item.system_quantity, item.units, item.base_unit, item.system_intact_packs) }}</td>
                        <td>
                            <div v-if="opname.can_count" class="parts">
                                <div v-for="(part, partIndex) in typedCounts[item.id].parts" :key="partIndex" class="parts__row">
                                    <q-input
                                        v-model="part.quantity"
                                        type="number"
                                        min="0"
                                        step="any"
                                        outlined
                                        dense
                                        hide-bottom-space
                                        :placeholder="opname.is_counting_arrivals ? 'jumlah datang' : 'isi hitungan'"
                                        class="parts__quantity"
                                        :aria-label="`Jumlah ${item.product.name}`"
                                    />
                                    <q-select
                                        v-model="part.unit_code"
                                        :options="item.units.map((unit) => unit.code)"
                                        outlined
                                        dense
                                        hide-bottom-space
                                        class="parts__unit"
                                        :aria-label="`Satuan ${item.product.name}`"
                                    />
                                    <q-btn
                                        v-if="partIndex > 0"
                                        flat
                                        round
                                        dense
                                        size="sm"
                                        icon="close"
                                        color="grey-7"
                                        aria-label="Hapus satuan"
                                        @click="removePart(item, partIndex)"
                                    />
                                    <q-btn
                                        v-else-if="item.units.length > 1"
                                        flat
                                        round
                                        dense
                                        size="sm"
                                        icon="add"
                                        color="grey-7"
                                        aria-label="Tambah satuan lain"
                                        @click="addPart(item)"
                                    />
                                </div>
                                <small v-if="item.units.some((unit) => unit.is_sealed_pack)" class="lines__muted">
                                    Hitung yang masih utuh sebagai {{ item.units.find((unit) => unit.is_sealed_pack).code }}, dan sisa potongan dalam satuan yang lebih kecil.
                                </small>
                            </div>
                            <template v-else>
                                <span v-if="item.counted_quantity !== null">{{ formatStock(item.counted_quantity, item.units, item.base_unit, item.counted_packs) }}</span>
                                <span v-else class="lines__muted">Belum dihitung</span>
                            </template>
                        </td>
                        <td class="lines__right">
                            <template v-if="varianceOf(item) !== null">
                                <span class="chip" :class="varianceChipClassOf(item)">{{ varianceTextOf(item) }}</span>
                                <small v-if="!opname.can_count && Number(item.variance_value) !== 0" class="lines__muted">{{ formatMoney(item.variance_value) }}</small>
                            </template>
                            <span v-else class="lines__muted">—</span>
                        </td>
                        <td v-if="opname.is_counting_arrivals" class="lines__right">
                            <b v-if="stockAfter(item) !== null">{{ stockAfter(item) }}</b>
                            <span v-else class="lines__muted">—</span>
                        </td>
                        <td>
                            <div class="lines__reason-row">
                            <q-select
                                v-if="isReasonPickable(item)"
                                v-model="editableRow(item).reason_id"
                                :options="reasonOptionsOf(item)"
                                emit-value
                                map-options
                                clearable
                                outlined
                                dense
                                hide-bottom-space
                                placeholder="Pilih alasan"
                                class="lines__reason"
                                :aria-label="`Alasan ${item.product.name}`"
                                @update:model-value="isReviewing && changeReason(item)"
                            />
                            <span v-else>{{ item.reason_name ?? '' }}</span>
                            <q-btn
                                v-if="isReviewing && needsReview(item) && isDamagedReason(item)"
                                :unelevated="!isDamageDecided(item)"
                                :outline="isDamageDecided(item)"
                                no-caps
                                dense
                                :color="isDamageDecided(item) ? 'positive' : 'warning'"
                                :icon="isDamageDecided(item) ? 'check_circle' : 'edit_note'"
                                :label="damageSummary(item)"
                                class="lines__decision q-px-md"
                                @click="openDamageDialog(item)"
                            />
                            </div>
                            <small v-if="!isReviewing && item.needs_damage_decision && item.damage && item.damage.dead_stock_quantity !== null" class="lines__muted">
                                Dead stock {{ decidedText(item, item.damage.dead_stock_quantity, item.damage.dead_stock_packs) }} · DG {{ decidedText(item, item.damage.damaged_goods_quantity, item.damage.damaged_goods_packs) }} · {{ opname.is_returns_check ? 'Normal' : 'Penuh' }} {{ decidedText(item, item.damage.full_price_quantity, item.damage.full_price_packs) }}
                            </small>
                        </td>
                        <td>
                            <q-input
                                v-if="isReasonEditable(item)"
                                v-model="editableRow(item).note"
                                outlined
                                dense
                                hide-bottom-space
                                maxlength="255"
                                placeholder="mis. tumpah"
                                class="lines__note"
                                :aria-label="`Catatan ${item.product.name}`"
                            />
                            <span v-else>{{ item.note ?? '' }}</span>
                        </td>
                        <td v-if="opname.requires_review && ['review', 'posted'].includes(opname.status)">
                            <q-checkbox
                                v-if="isReviewing && needsReview(item)"
                                v-model="reviews[item.id].is_reviewed"
                                dense
                                :disable="!isReviewable(item)"
                                :aria-label="`Sudah direview ${item.product.name}`"
                            >
                                <q-tooltip v-if="!isReviewable(item)">
                                    <template v-if="opname.is_returns_check">Tentukan ke mana barang retur ini pergi dulu.</template>
                                    <template v-else>Pilih alasan{{ isDamagedReason(item) ? ' dan tentukan keputusan barang rusak' : '' }} dulu.</template>
                                </q-tooltip>
                            </q-checkbox>
                            <span v-else-if="item.is_reviewed" class="chip chip--ok">Sudah</span>
                            <span v-else class="lines__muted">—</span>
                        </td>
                        <td v-if="opname.can_manage_products" class="lines__right">
                            <q-btn flat round dense icon="delete_outline" color="negative" :aria-label="`Hapus ${item.product.name}`" @click="removeProduct(item)" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <q-dialog v-model="isAddDialogOpen" @hide="productToAdd = null">
            <q-card class="add-dialog">
                <q-card-section>
                    <div class="add-dialog__title">Tambah dan hitung barang</div>
                    <ProductSearchSelect
                        ref="productSearchField"
                        v-model="productToAdd"
                        :search-url="opname.urls.product_search"
                        label="Cari nama atau SKU"
                        autofocus
                        @update:model-value="pickProductToAdd"
                    />
                    <div v-if="productToAdd" class="add-dialog__count">
                        <div v-for="(part, partIndex) in addedParts" :key="partIndex" class="parts__row">
                            <q-input
                                :ref="(field) => trackFirstAddedQuantityField(field, partIndex)"
                                v-model="part.quantity"
                                type="number"
                                min="0"
                                step="any"
                                outlined
                                dense
                                hide-bottom-space
                                :placeholder="opname.is_counting_arrivals ? 'jumlah datang' : 'isi hitungan'"
                                class="parts__quantity"
                                :aria-label="`Jumlah ${productToAdd.name}`"
                                @keydown.enter.prevent="addProduct"
                            />
                            <q-select
                                v-model="part.unit_code"
                                :options="productToAdd.units"
                                outlined
                                dense
                                hide-bottom-space
                                class="parts__unit"
                                :aria-label="`Satuan ${productToAdd.name}`"
                            />
                            <q-btn
                                v-if="partIndex > 0"
                                flat
                                round
                                dense
                                size="sm"
                                icon="close"
                                color="grey-7"
                                aria-label="Hapus satuan"
                                @click="addedParts.splice(partIndex, 1)"
                            />
                        </div>
                        <q-btn
                            v-if="addedParts.length < productToAdd.units.length"
                            flat
                            dense
                            no-caps
                            icon="add"
                            label="Satuan lain"
                            color="grey-8"
                            class="add-dialog__more-units"
                            @click="addAddedPart"
                        />
                    </div>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn flat no-caps label="Tutup" v-close-popup />
                    <q-btn v-if="productToAdd" unelevated no-caps color="primary" label="Tambah" :loading="isAdding" @click="addProduct" />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <q-dialog v-model="isPostOpen">
            <q-card class="post">
                <q-card-section>
                    <div class="post__title">Posting opname?</div>
                    <p class="post__message">{{ postMessage }}</p>

                    <div v-if="sealedPackDropRows.length > 0" class="post__warning">
                        <b>Rol utuh berkurang</b>
                        <ul>
                            <li v-for="row in sealedPackDropRows" :key="row.product_name">
                                {{ row.product_name }}: {{ row.pack_unit }} utuh turun dari {{ row.system_packs }} jadi {{ row.counted_packs }}
                            </li>
                        </ul>
                        <p v-if="sealed_pack_drops.length > sealedPackDropRows.length" class="post__more">Dan {{ sealed_pack_drops.length - sealedPackDropRows.length }} barang lain.</p>
                        <small>Yang tidak dihitung sebagai kemasan utuh tidak bisa dijual per kemasan lagi. Bila ada kemasan yang sebenarnya masih utuh, kembali ke hitungan dan tulis dalam satuan kemasannya.</small>
                    </div>

                    <template v-if="hasDamageSummary">
                        <div class="post__heading">{{ opname.is_returns_check ? 'Barang retur dibagi ke' : 'Barang rusak' }}</div>
                        <table class="post__table">
                            <thead>
                                <tr>
                                    <th>Barang</th>
                                    <th class="post__right">Dead stock</th>
                                    <th class="post__right">Barang DG</th>
                                    <th class="post__right">{{ opname.is_returns_check ? 'Kembali ke stok jual' : 'Layak jual penuh' }}</th>
                                    <th class="post__right">Rugi</th>
                                    <th class="post__right">Loss profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in damageRows" :key="row.product_name">
                                    <td><b>{{ row.product_name }}</b></td>
                                    <td class="post__right">{{ formatQuantity(row.dead_stock_quantity) }} {{ row.base_unit }}</td>
                                    <td class="post__right">
                                        {{ formatQuantity(row.damaged_goods_quantity) }} {{ row.base_unit }}
                                        <small v-if="Number(row.damaged_goods_quantity) > 0">
                                            dijual {{ rupiah(row.damaged_goods_price) }}, asli {{ rupiah(row.original_price) }}, beli {{ rupiah(row.cost_price) }}
                                            <template v-if="row.existing_price !== null && Number(row.existing_price) !== Number(row.damaged_goods_price)"><br />harga DG sekarang {{ rupiah(row.existing_price) }}, jadi {{ rupiah(row.damaged_goods_price) }}</template>
                                        </small>
                                    </td>
                                    <td class="post__right">{{ formatQuantity(row.full_price_quantity) }} {{ row.base_unit }}</td>
                                    <td class="post__right post__loss">{{ rupiah(row.loss) }}</td>
                                    <td class="post__right">{{ rupiah(row.loss_profit) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="damage_summary.items.length > damageRows.length" class="post__more">Dan {{ damage_summary.items.length - damageRows.length }} barang rusak lain, ikut dihitung di total.</p>

                        <div class="post__total">
                            <b>Rugi: {{ rupiah(damage_summary.total_loss) }}</b>
                            <small>Dead stock {{ rupiah(damage_summary.dead_stock_loss) }} + barang DG dijual di bawah harga beli {{ rupiah(damage_summary.damaged_goods_loss) }}</small>
                            <b>Loss profit: {{ rupiah(damage_summary.loss_profit) }}</b>
                        </div>
                        <small class="post__note">Rugi dihitung dari harga beli: dead stock tidak bisa dijual lagi (rugi sebesar harga beli), barang DG baru rugi bila dijual di bawah harga beli (selisihnya ke harga beli). Selisih harga yang masih di atas harga beli dihitung loss profit, juga pada barang yang rugi (dead stock: harga asli dikurangi harga beli).</small>
                    </template>
                </q-card-section>

                <q-card-actions align="right">
                    <q-btn flat no-caps color="negative" label="Batal" v-close-popup />
                    <q-btn unelevated no-caps color="positive" label="Posting" @click="post" />
                </q-card-actions>
            </q-card>
        </q-dialog>

        <DamagedGoodsDialog
            v-model="damageDialog.isOpen"
            :item="damageDialogItem"
            :decision="damageDialog.itemId === null ? undefined : reviews[damageDialog.itemId]"
            @save="saveDamageDecision"
        />

        <div v-if="items.total > 0" class="pager">
            <span class="lines__muted">{{ items.from }}–{{ items.to }} dari {{ items.total }} barang</span>
            <q-pagination
                v-if="items.last_page > 1"
                :model-value="items.current_page"
                :max="items.last_page"
                :max-pages="7"
                direction-links
                boundary-links
                color="primary"
                @update:model-value="applyFilters({ page: $event })"
            />
        </div>
    </StoreLayout>
</template>

<style scoped>
.steps {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 22px;
    margin: 0 0 14px;
    padding: 0;
    list-style: none;
}

.steps li {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--app-mute);
    font-size: 13px;
    font-weight: 600;
}

.steps__dot {
    width: 10px;
    height: 10px;
    margin: 0 4px;
    border: 2px solid currentColor;
    border-radius: 50%;
}

.steps .steps__item--done {
    color: #059669;
}

.steps .steps__item--current {
    color: var(--app-brand);
}

.steps .steps__item--current .steps__dot {
    background: currentColor;
}

.meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 32px;
    margin-bottom: 14px;
    padding: 14px 18px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.meta small,
.kpi small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 14px;
}

.kpi {
    padding: 14px 18px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.kpi b {
    font-size: 20px;
}

.kpi .kpi--bad {
    color: #be123c;
}

.add-dialog {
    width: 460px;
    max-width: 92vw;
}

.add-dialog__title {
    margin-bottom: 14px;
    font-size: 15px;
    font-weight: 700;
}

.add-dialog__count {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 12px;
}

.add-dialog__more-units {
    align-self: flex-start;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}

.filters__search {
    flex: 2 1 260px;
}

.filters__select {
    flex: 1 1 170px;
}

.lines {
    overflow-x: auto;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.lines__table {
    width: 100%;
    border-collapse: collapse;
}

.lines__table th {
    padding: 12px 16px;
    border-bottom: 1px solid var(--app-line);
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.lines__table td {
    padding: 10px 16px;
    border-bottom: 1px solid var(--app-line);
    vertical-align: middle;
}

.lines__table tr:last-child td {
    border-bottom: 0;
}

.lines__table td b,
.lines__table td small {
    display: block;
}

.lines__table td small,
.lines__muted {
    color: var(--app-mute);
    font-size: 12px;
}

.lines__table .lines__right {
    text-align: right;
    white-space: nowrap;
}

.lines__empty {
    padding: 28px 16px !important;
    color: var(--app-mute);
    text-align: center;
}

.parts {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.parts__row {
    display: flex;
    align-items: center;
    gap: 6px;
}

.parts__quantity {
    width: 130px;
}

.parts__unit {
    width: 104px;
}

.lines__reason {
    min-width: 170px;
}

.lines__note {
    min-width: 150px;
}

.post {
    width: 720px;
    max-width: 96vw;
}

.post__title {
    font-size: 20px;
    font-weight: 700;
}

.post__message {
    margin: 12px 0 0;
}

.post__heading {
    margin: 16px 0 6px;
    font-weight: 700;
}

.post__table {
    width: 100%;
    border-collapse: collapse;
}

.post__table th,
.post__table td {
    padding: 6px 8px;
    border-bottom: 1px solid var(--app-line);
    text-align: left;
    vertical-align: top;
}

.post__table th {
    color: var(--app-mute);
    font-size: 12px;
    font-weight: 600;
}

.post__table small {
    display: block;
    color: var(--app-mute);
    font-size: 12px;
}

.post__table .post__right {
    text-align: right;
    white-space: nowrap;
}

.post__table .post__loss {
    color: #be123c;
    font-weight: 600;
}

.post__warning {
    margin: 10px 0;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fffbeb;
    border: 1px solid #fcd34d;
    color: #92400e;
}

.post__warning ul {
    margin: 6px 0;
    padding-left: 18px;
}

.post__more {
    margin: 6px 0 0;
    color: var(--app-mute);
    font-size: 12.5px;
}

.post__total {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-top: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #fde8ee;
    color: #be123c;
}

.post__total small {
    color: #be123c;
}

.post__note {
    display: block;
    margin-top: 6px;
    color: var(--app-mute);
}

.lines__reason-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.lines__decision {
    flex: none;
    white-space: nowrap;
}

.pager {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 14px;
}

@media screen and (max-width: 640px) {
    .kpis {
        grid-template-columns: 1fr;
    }
}
</style>
