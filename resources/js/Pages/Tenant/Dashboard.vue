<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import StoreLayout from '../../Layouts/StoreLayout.vue';

const props = defineProps({
    summary: { type: Object, required: true },
});

const page = usePage();

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

const pendingBreakdown = computed(() => {
    const { returns, cash_differences, delivery_notes } = props.summary.pending;

    return [
        returns > 0 ? `${returns} retur` : null,
        cash_differences > 0 ? `${cash_differences} selisih kas` : null,
        delivery_notes > 0 ? `${delivery_notes} surat jalan` : null,
    ]
        .filter(Boolean)
        .join(' · ');
});

const approvalLabels = {
    return: { title: 'Retur', badge: 'Retur', tone: 'warn' },
    cash_difference: { title: 'Selisih kas', badge: 'Selisih kas', tone: 'bad' },
};

const formatTime = (isoDate) =>
    new Date(isoDate).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }).replace('.', ':');
</script>

<template>
    <StoreLayout :title="title">
        <div class="kpis">
            <article class="card">
                <small>Penjualan bersih hari ini</small>
                <b>{{ formatRupiah(summary.sales.net_total) }}</b>
                <em>
                    {{ summary.sales.receipt_count }} nota ·
                    {{ formatRupiah(summary.sales.gross_total) }} − retur {{ formatRupiah(summary.sales.returns_total) }}
                </em>
            </article>

            <article class="card">
                <small>Stok di bawah minimum</small>
                <b>{{ summary.low_stock_count }} barang</b>
                <em>{{ summary.low_stock_count > 0 ? 'Perlu restock' : 'Semua stok aman' }}</em>
            </article>

            <article class="card">
                <small>Perlu tindakan</small>
                <b>{{ summary.pending.total }}</b>
                <em>{{ pendingBreakdown || 'Semua beres' }}</em>
            </article>
        </div>

        <div class="lists">
            <section class="card">
                <h2>Menunggu persetujuan</h2>

                <p v-if="summary.approvals.length === 0" class="empty">Tidak ada yang menunggu.</p>

                <ul v-else class="list">
                    <li v-for="item in summary.approvals" :key="`${item.kind}-${item.reference}`">
                        <span class="list__text">
                            {{ approvalLabels[item.kind].title }} · {{ item.reference }}
                            <small>{{ formatRupiah(item.amount) }}</small>
                        </span>
                        <span class="chip" :class="`chip--${approvalLabels[item.kind].tone}`">{{ approvalLabels[item.kind].badge }}</span>
                    </li>
                </ul>
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
    margin: 0 0 10px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: normal;
    line-height: 1.4;
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
</style>
