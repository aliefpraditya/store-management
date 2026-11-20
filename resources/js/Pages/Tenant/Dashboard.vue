<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import StoreLayout from '../../Layouts/StoreLayout.vue';

const props = defineProps({
    summary: { type: Object, required: true },
});

const page = usePage();

const store = computed(() => page.props.store);

const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 2 });

/**
 * @param {string|number} amount decimal string from the server, e.g. "4850000.00"
 * @returns {string} e.g. "Rp 4.850.000"
 */
const formatRupiah = (amount) => rupiah.format(Number(amount)).replace(/ /g, ' ');

/**
 * Greets by the time of day on the viewer's clock.
 * @returns {string}
 */
function greeting() {
    const hour = new Date().getHours();

    if (hour < 11) {
        return 'Selamat Pagi';
    }
    if (hour < 15) {
        return 'Selamat Siang';
    }
    if (hour < 18) {
        return 'Selamat Sore';
    }

    return 'Selamat Malam';
}

const title = `${greeting()}, ${page.props.auth.user.name}`;

/**
 * What waits for a decision, apart by what it is, so each group says plainly what is waited for. Both lists link to the
 * screen where they are decided, already narrowed to what is waiting.
 */
const returnApprovals = computed(() => props.summary.approvals.filter((approval) => approval.kind === 'return'));
const cashDifferenceApprovals = computed(() => props.summary.approvals.filter((approval) => approval.kind === 'cash_difference'));
const codSettlementApprovals = computed(() => props.summary.approvals.filter((approval) => approval.kind === 'cod_settlement'));
const pendingReturnsUrl = computed(() => `${store.value.returns_url}?status=pending`);
const pendingCashDifferencesUrl = computed(() => `${store.value.shifts_url}?difference=pending_approval`);

const formatTime = (isoDate) =>
    new Date(isoDate).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }).replace('.', ':');
</script>

<template>
    <StoreLayout :title="title">
        <div class="kpis">
            <Link :href="store.sales_url" class="card card--link">
                <small>Penjualan bersih hari ini</small>
                <b>{{ formatRupiah(summary.sales.net_total) }}</b>
                <em>
                    {{ summary.sales.receipt_count }} nota ·
                    {{ formatRupiah(summary.sales.gross_total) }} − retur {{ formatRupiah(summary.sales.returns_total) }}
                </em>
            </Link>

            <Link :href="store.stock_opnames_url" class="card card--link">
                <small>Stok di bawah minimum</small>
                <b>{{ summary.low_stock_count }} barang</b>
                <em>{{ summary.low_stock_count > 0 ? 'Perlu restock' : 'Semua stok aman' }}</em>
            </Link>
        </div>

        <div class="lists">
            <section class="card">
                <h2 class="attention__title">
                    Perlu tindakan
                    <span v-if="summary.pending.total > 0" class="attention__count">{{ summary.pending.total }}</span>
                </h2>

                <p v-if="summary.pending.total === 0" class="empty">Semua beres.</p>

                <template v-else>
                    <div v-if="cashDifferenceApprovals.length > 0" class="group">
                        <h3 class="group__title">
                            Selisih kas menunggu review
                            <span>{{ summary.pending.cash_differences }}</span>
                        </h3>
                        <ul class="list">
                            <li v-for="approval in cashDifferenceApprovals" :key="approval.reference">
                                <Link :href="pendingCashDifferencesUrl" class="list__text list__link">
                                    {{ approval.reference }}
                                    <small>{{ formatRupiah(approval.amount) }}</small>
                                </Link>
                                <span class="chip chip--bad">Selisih kas</span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="returnApprovals.length > 0" class="group">
                        <h3 class="group__title">
                            Retur menunggu persetujuan
                            <span>{{ summary.pending.returns }}</span>
                        </h3>
                        <ul class="list">
                            <li v-for="approval in returnApprovals" :key="approval.reference">
                                <Link :href="pendingReturnsUrl" class="list__text list__link">
                                    {{ approval.reference }}
                                    <small>{{ formatRupiah(approval.amount) }}</small>
                                </Link>
                                <span class="chip chip--warn">Retur</span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="codSettlementApprovals.length > 0" class="group">
                        <h3 class="group__title">
                            Uang COD menunggu persetujuan
                            <span>{{ summary.pending.cod_approvals }}</span>
                        </h3>
                        <ul class="list">
                            <li v-for="approval in codSettlementApprovals" :key="approval.reference">
                                <Link :href="approval.show_url" class="list__text list__link">
                                    {{ approval.reference }}
                                    <small>{{ formatRupiah(approval.amount) }}</small>
                                </Link>
                                <span class="chip chip--warn">Selisih COD</span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="summary.unfinished_delivery_notes.length > 0" class="group">
                        <h3 class="group__title">
                            Surat jalan belum selesai
                            <span>{{ summary.pending.delivery_notes }}</span>
                        </h3>
                        <ul class="list">
                            <li v-for="note in summary.unfinished_delivery_notes" :key="note.reference">
                                <Link :href="note.show_url" class="list__text list__link">
                                    {{ note.reference }}
                                    <small>{{ note.recipient_name }}</small>
                                </Link>
                                <span class="chip chip--off">{{ note.status_label }}</span>
                            </li>
                        </ul>
                        <Link v-if="summary.pending.delivery_notes > summary.unfinished_delivery_notes.length" :href="store.delivery_notes_url" class="more">
                            Lihat semua {{ summary.pending.delivery_notes }} surat jalan
                        </Link>
                    </div>
                </template>
            </section>

            <section class="card">
                <h2>Shift berjalan</h2>

                <p v-if="summary.open_shifts.length === 0" class="empty">Belum ada shift berjalan.</p>

                <ul v-else class="list">
                    <li v-for="shift in summary.open_shifts" :key="shift.id">
                        <span class="avatar">{{ shift.cashier_name.charAt(0).toUpperCase() }}</span>
                        <span class="list__text">
                            {{ shift.cashier_name }} · {{ shift.register_name }}
                            <small>sejak {{ formatTime(shift.opened_at) }}</small>
                        </span>
                        <span class="chip chip--ok"><i></i>Buka</span>
                    </li>
                </ul>
            </section>
        </div>
    </StoreLayout>
</template>

<style scoped>
.kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 14px;
}

.lists {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 14px;
    margin-top: 14px;
}

.card {
    padding: 16px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.card--link {
    display: block;
    color: inherit;
    text-decoration: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.card--link:hover {
    border-color: var(--app-brand);
    box-shadow: 0 1px 6px rgb(0 0 0 / 8%);
}

.card small {
    display: block;
    color: var(--app-ink-soft);
}

.card b {
    display: block;
    margin: 2px 0;
    font-size: 26px;
    letter-spacing: -0.02em;
    line-height: 1.2;
}

.card em {
    color: var(--app-mute);
    font-size: 12.5px;
    font-style: normal;
}

.card h2 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 10px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: normal;
    line-height: 1.4;
}

.card .attention__title {
    gap: 10px;
    margin-bottom: 14px;
    font-size: 20px;
}

.attention__count {
    display: inline-grid;
    place-items: center;
    min-width: 30px;
    height: 30px;
    padding: 0 9px;
    border-radius: 99px;
    background: #fdf1e0;
    color: #c2710c;
    font-size: 17px;
    font-weight: 700;
}

.group + .group {
    margin-top: 18px;
    padding-top: 18px;
    border-top: 2px solid var(--app-line);
}

.card .group__title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0 0 4px;
    padding: 7px 12px;
    border-left: 4px solid #c2710c;
    border-radius: 6px;
    background: #f5f6f8;
    font-size: 13.5px;
    font-weight: 700;
}

.group__title span {
    color: var(--app-mute);
    font-weight: 600;
}

.more {
    display: inline-block;
    margin-top: 8px;
    color: var(--app-brand);
    font-size: 13px;
}

.list__link {
    color: inherit;
    text-decoration: none;
}

.list__link:hover {
    color: var(--app-brand);
}

.empty {
    margin: 0;
    color: var(--app-mute);
    font-size: 13px;
}

.list {
    display: flex;
    flex-direction: column;
    margin: 0;
    padding: 0;
    list-style: none;
}

.list li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 0;
    border-bottom: 1px solid var(--app-line);
}

.list li:last-child {
    border-bottom: 0;
}

.list__text {
    flex: 1;
    min-width: 0;
}

.list__text small {
    color: var(--app-mute);
    font-size: 12px;
}

.avatar {
    display: grid;
    flex: none;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--app-brand-soft);
    color: var(--app-brand);
    font-size: 12px;
    font-weight: 700;
}

.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
}

.chip i {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.chip--ok {
    background: #e7f8f1;
    color: #059669;
}

.chip--warn {
    background: #fdf1e0;
    color: #c2710c;
}

.chip--bad {
    background: #fde8ee;
    color: #be123c;
}

.chip--off {
    background: #eef0f3;
    color: #5b6472;
}
</style>
