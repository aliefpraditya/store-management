<script setup>
/**
 * Permissions as checkboxes grouped by screen area, shared by the role form and the extra permissions of a user.
 * `modelValue` is the list of checked permission names.
 */
const props = defineProps({
    groups: { type: Array, required: true },
    modelValue: { type: Array, required: true },
});

const emit = defineEmits(['update:modelValue']);

/**
 * @param {string} name
 * @param {boolean} isChecked
 */
function toggle(name, isChecked) {
    const withoutName = props.modelValue.filter((checkedName) => checkedName !== name);

    emit('update:modelValue', isChecked ? [...withoutName, name] : withoutName);
}

/**
 * Checks or unchecks a whole group at once.
 *
 * @param {{permissions: {name: string}[]}} group
 * @param {boolean} isChecked
 */
function toggleGroup(group, isChecked) {
    const groupNames = group.permissions.map((permission) => permission.name);
    const otherNames = props.modelValue.filter((checkedName) => !groupNames.includes(checkedName));

    emit('update:modelValue', isChecked ? [...otherNames, ...groupNames] : otherNames);
}

/**
 * @param {{permissions: {name: string}[]}} group
 * @returns {boolean|null} true when all are checked, null when only some are
 */
function groupState(group) {
    const checkedCount = group.permissions.filter((permission) => props.modelValue.includes(permission.name)).length;

    if (checkedCount === 0) {
        return false;
    }

    return checkedCount === group.permissions.length ? true : null;
}
</script>

<template>
    <div class="permissions">
        <section v-for="group in groups" :key="group.group" class="permissions__group">
            <q-checkbox
                :model-value="groupState(group)"
                :label="group.group"
                class="permissions__title"
                dense
                @update:model-value="toggleGroup(group, $event === true)"
            />
            <q-checkbox
                v-for="permission in group.permissions"
                :key="permission.name"
                :model-value="modelValue.includes(permission.name)"
                :label="permission.label"
                class="permissions__item"
                dense
                @update:model-value="toggle(permission.name, $event)"
            />
        </section>
    </div>
</template>

<style scoped>
.permissions {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.permissions__group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.permissions__title {
    font-weight: 700;
}

.permissions__item {
    padding-left: 28px;
}
</style>
