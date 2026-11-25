<script setup>
/**
 * The people to call at a supplier or a carrier: rows of a name and an optional phone, added and removed with buttons. A
 * row with an `id` is an existing contact; one with `id: null` is new.
 */
const contacts = defineModel({ type: Array, required: true });

defineProps({
    title: { type: String, required: true },
    addLabel: { type: String, required: true },
    /** The form's errors, as the server names them (e.g. `contacts.0.name`). */
    errors: { type: Object, default: () => ({}) },
});

function addContact() {
    contacts.value = [...contacts.value, { id: null, name: '', phone: '' }];
}

function removeContact(index) {
    contacts.value = contacts.value.filter((_, contactIndex) => contactIndex !== index);
}
</script>

<template>
    <div class="contacts">
        <div class="contacts__head">
            <b>{{ title }}</b>
            <span>opsional</span>
        </div>

        <div v-for="(contact, index) in contacts" :key="contact.id ?? `new-${index}`" class="contacts__row">
            <q-input
                v-model="contact.name"
                outlined
                dense
                label="Nama"
                maxlength="255"
                class="contacts__name"
                :error="!!errors[`contacts.${index}.name`]"
                :error-message="errors[`contacts.${index}.name`]"
            />
            <q-input
                v-model="contact.phone"
                outlined
                dense
                label="Telepon"
                maxlength="50"
                class="contacts__phone"
                :error="!!errors[`contacts.${index}.phone`]"
                :error-message="errors[`contacts.${index}.phone`]"
            />
            <q-btn flat round dense icon="close" color="grey-7" :aria-label="`Hapus kontak ${index + 1}`" @click="removeContact(index)" />
        </div>

        <q-btn flat dense no-caps color="primary" icon="add" :label="addLabel" class="self-start" @click="addContact" />
    </div>
</template>

<style scoped>
.contacts {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px;
    border: 1px solid var(--app-line);
    border-radius: var(--app-radius);
}

.contacts__head span {
    margin-left: 6px;
    color: var(--app-mute);
    font-size: 12px;
}

.contacts__row {
    display: flex;
    align-items: flex-start;
    gap: 6px;
}

.contacts__name {
    flex: 3 1 0;
}

.contacts__phone {
    flex: 2 1 0;
}
</style>
