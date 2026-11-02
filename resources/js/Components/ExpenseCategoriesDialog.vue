<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';

defineProps({
    modelValue: { type: Boolean, required: true },
    /** @type {{id: number, name: string, is_active: boolean, expenses_count: number, update_url: string}[]} */
    categories: { type: Array, required: true },
    createUrl: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const $q = useQuasar();

const newCategory = useForm({ name: '' });

function addCategory(createUrl) {
    newCategory.post(createUrl, { preserveScroll: true, preserveState: true, onSuccess: () => newCategory.reset() });
}

/**
 * Renames a category or switches it on or off. A switched-off category stays on the expenses already filed under
 * it; it just is not offered for new ones.
 *
 * @param {{name: string, is_active: boolean, update_url: string}} category
 * @param {{name?: string, is_active?: boolean}} change
 */
function updateCategory(category, change) {
    router.put(
        category.update_url,
        { name: category.name, is_active: category.is_active, ...change },
        {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => $q.notify({ type: 'negative', message: Object.values(errors)[0], position: 'top' }),
        },
    );
}
</script>

<template>
    <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
        <q-card class="categories">
            <q-card-section>
                <div class="categories__title">Kategori pengeluaran</div>
                <small class="categories__hint">Kategori tidak dihapus, hanya dinonaktifkan, supaya pengeluaran lama tetap punya kategori.</small>
            </q-card-section>

            <q-card-section class="categories__list">
                <div v-for="category in categories" :key="category.id" class="category" :class="{ 'category--off': !category.is_active }">
                    <span class="category__name">
                        {{ category.name }}
                        <q-icon name="edit" size="14px" color="grey-6" />
                        <q-popup-edit
                            v-slot="scope"
                            :model-value="category.name"
                            buttons
                            label-set="Simpan"
                            label-cancel="Batal"
                            @save="(name) => updateCategory(category, { name })"
                        >
                            <q-input v-model="scope.value" dense autofocus maxlength="255" @keyup.enter="scope.set" />
                        </q-popup-edit>
                    </span>
                    <small class="category__count">{{ category.expenses_count }} pengeluaran</small>
                    <q-toggle :model-value="category.is_active" :aria-label="`Aktifkan ${category.name}`" @update:model-value="updateCategory(category, { is_active: $event })" />
                </div>
            </q-card-section>

            <q-card-section>
                <q-form class="categories__add" @submit.prevent="addCategory(createUrl)">
                    <q-input v-model="newCategory.name" outlined dense label="Kategori baru" maxlength="255" :error="!!newCategory.errors.name" :error-message="newCategory.errors.name" class="categories__input" />
                    <q-btn type="submit" color="primary" unelevated no-caps icon="add" label="Tambah" :loading="newCategory.processing" />
                </q-form>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn flat no-caps label="Tutup" v-close-popup />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style scoped>
.categories {
    width: 460px;
    max-width: 94vw;
}

.categories__title {
    font-size: 17px;
    font-weight: 700;
}

.categories__hint {
    color: var(--app-mute);
}

.categories__list {
    display: flex;
    flex-direction: column;
    max-height: 50vh;
    padding-block: 0;
    overflow-y: auto;
}

.category {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 0;
    border-bottom: 1px solid var(--app-line);
}

.category--off .category__name {
    color: var(--app-mute);
    text-decoration: line-through;
}

.category__name {
    flex: 1;
    font-weight: 600;
    cursor: pointer;
}

.category__count {
    color: var(--app-mute);
}

.categories__add {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.categories__input {
    flex: 1;
}
</style>
