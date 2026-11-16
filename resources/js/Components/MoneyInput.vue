<script setup>
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    /** The amount as plain digits ("10000", or "10000.5" with decimals), a number, or empty. */
    modelValue: { type: [String, Number], default: '' },
    /** Whether a decimal comma may be typed (rupiah amounts normally have none). */
    allowDecimal: { type: Boolean, default: false },
    /** How much the up and down arrow keys move the amount by; 0 leaves the arrows alone. */
    step: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue']);

const DECIMAL_DIGITS = 2;

const field = ref(null);

/** What this field last handed to its parent, so a value that came from elsewhere (a server amount like "500000.00") can be told apart from what is being typed. */
let lastEmitted = null;

/**
 * The amount as it reads in Indonesia: a dot between thousands and a comma before the decimals.
 *
 * @param {string|number} rawAmount plain digits, e.g. "10000" or "10000.5"
 * @returns {string} e.g. "10.000" or "10.000,5"
 */
function formatAmount(rawAmount) {
    const [whole, decimals] = String(rawAmount ?? '').split('.');
    const groupedWhole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return decimals === undefined ? groupedWhole : `${groupedWhole},${decimals}`;
}

/**
 * What was typed, cleaned to plain digits: letters and thousands dots are dropped, the comma becomes the decimal point
 * (only when decimals are allowed), and leading zeros go.
 *
 * @param {string} typedText e.g. "10.000,5"
 * @returns {string} e.g. "10000.5"
 */
function readAmount(typedText) {
    const withDecimalPoint = props.allowDecimal ? typedText.replace(/\./g, '').replace(',', '.') : typedText.replace(/[.,]/g, '');
    const [whole = '', decimals] = withDecimalPoint.replace(/[^\d.]/g, '').split('.');
    const wholeWithoutZeros = whole.replace(/^0+(?=\d)/, '');

    return decimals === undefined ? wholeWithoutZeros : `${wholeWithoutZeros || '0'}.${decimals.slice(0, DECIMAL_DIGITS)}`;
}

/** An amount that was typed is shown as typed ("10,0" while someone is still typing); one that came from outside is cleaned of trailing zeros. */
const displayedAmount = computed(() => {
    const amount = String(props.modelValue ?? '');
    const isTyped = amount === lastEmitted || amount === '' || Number.isNaN(Number(amount));

    return formatAmount(isTyped ? amount : String(Number(amount)));
});

/**
 * Formats while typing and keeps the caret next to the digit it was after, so typing in the middle of a number does not
 * throw the caret to the end.
 *
 * @param {string|number|null} typedText
 */
function onInput(typedText) {
    const native = field.value?.getNativeElement();
    const text = String(typedText ?? '');
    const significant = props.allowDecimal ? /[\d,]/ : /\d/;
    const significantBeforeCaret = native ? [...text.slice(0, native.selectionStart ?? text.length)].filter((character) => significant.test(character)).length : 0;
    const rawAmount = readAmount(text);
    const formatted = formatAmount(rawAmount);

    lastEmitted = rawAmount;
    emit('update:modelValue', rawAmount);

    nextTick(() => {
        if (!native) {
            return;
        }

        native.value = formatted;

        let seen = 0;
        let caret = formatted.length;

        for (let position = 0; position < formatted.length && seen < significantBeforeCaret; position++) {
            if (significant.test(formatted[position])) {
                seen++;
            }

            caret = position + 1;
        }

        if (significantBeforeCaret === 0) {
            caret = 0;
        }

        native.setSelectionRange(caret, caret);
    });
}

/** The arrows step the amount like a number field does, never below zero. */
function onKeydown(event) {
    if (props.step <= 0 || !['ArrowUp', 'ArrowDown'].includes(event.key)) {
        return;
    }

    event.preventDefault();

    const current = Number(props.modelValue) || 0;
    const moved = Math.max(current + (event.key === 'ArrowUp' ? props.step : -props.step), 0);

    lastEmitted = String(moved);
    emit('update:modelValue', lastEmitted);
}

defineExpose({ focus: () => field.value?.focus(), select: () => field.value?.select() });
</script>

<template>
    <q-input
        ref="field"
        :model-value="displayedAmount"
        type="text"
        :inputmode="allowDecimal ? 'decimal' : 'numeric'"
        autocomplete="off"
        @update:model-value="onInput"
        @keydown="onKeydown"
    >
        <template v-for="(_, slotName) in $slots" #[slotName]="slotData">
            <slot :name="slotName" v-bind="slotData ?? {}" />
        </template>
    </q-input>
</template>
