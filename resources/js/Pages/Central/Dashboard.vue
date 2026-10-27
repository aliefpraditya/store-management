<script setup>
import { router } from '@inertiajs/vue3';
import CentralLayout from '../../Layouts/CentralLayout.vue';

defineProps({
    stores: { type: Array, required: true },
});
</script>

<template>
    <CentralLayout title="Dashboard">
        <template #actions>
            <q-btn color="primary" unelevated no-caps icon="add" label="Tambah Toko" disable>
                <q-tooltip>Segera hadir</q-tooltip>
            </q-btn>
        </template>

        <div class="stores">
            <article v-for="store in stores" :key="store.id" class="store">
                <header class="store__head">
                    <div class="store__icon"><q-icon name="storefront" size="20px" /></div>
                    <div class="store__name">
                        <b>{{ store.name }}</b>
                        <small>{{ store.receipt_prefix }} · {{ store.code }}</small>
                    </div>
                    <span class="store__status" :class="`store__status--${store.status.value}`">
                        <i></i>{{ store.status.label }}
                    </span>
                </header>

                <q-btn
                    color="primary"
                    unelevated
                    no-caps
                    icon-right="arrow_forward"
                    label="Masuk ke toko"
                    class="full-width"
                    :disable="!store.is_enterable"
                    @click="router.visit(store.url)"
                />
            </article>

            <article class="store store--new">
                <q-icon name="add" size="26px" />
                <span>Toko baru</span>
                <small>Segera hadir</small>
            </article>
        </div>
    </CentralLayout>
</template>

<style scoped>
.stores {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
}

.store {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 16px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
    background: var(--app-surface);
}

.store__head {
    display: flex;
    align-items: center;
    gap: 12px;
}

.store__icon {
    display: grid;
    flex: none;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--app-brand-soft);
    color: var(--app-brand);
}

.store__name {
    flex: 1;
    min-width: 0;
}

.store__name b,
.store__name small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.store__name small {
    color: var(--app-mute);
    font-size: 12px;
}

.store__status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 10px;
    border-radius: 99px;
    background: #f2f3f5;
    color: var(--app-ink-soft);
    font-size: 12px;
    font-weight: 600;
}

.store__status i {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.store__status--active {
    background: #e7f8f1;
    color: #059669;
}

.store__status--provisioning {
    background: #e8f0fe;
    color: #2563eb;
}

.store__status--failed {
    background: #fde8ee;
    color: #be123c;
}

.store--new {
    align-items: center;
    justify-content: center;
    gap: 4px;
    min-height: 130px;
    border-style: dashed;
    background: transparent;
    color: var(--app-mute);
}

.store--new small {
    font-size: 12px;
}
</style>
