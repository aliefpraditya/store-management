<script setup>
defineProps({
    /** A row is at the left edge unless it is marked as right-aligned. */
    isRightAligned: { type: Boolean, default: false },
});
</script>

<template>
    <div class="receipt-print__row">
        <span v-if="isRightAligned">&nbsp;</span>
        <span><slot /></span>
        <span v-if="!isRightAligned" class="receipt-print__blank">.</span>
    </div>
</template>

<style>
/*
 * A line of a roll receipt at one edge. The renderer of QZ Tray centres every line that is narrower than the paper, whatever
 * `text-align`, `align`, `float` or `flex-start` says (proven by the printer test page); only a flex row with two items that
 * both have width, pushed to the two edges by `space-between`, stays at the edge. A blank item at the left of the text keeps
 * its width as `&nbsp;`, but one at the right does not (a right-aligned receipt line sits at the edge, a left-aligned one
 * was centred), so the right-hand blank is an invisible character, which is not whitespace and keeps its width.
 */
.receipt-print__row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.receipt-print__blank {
    visibility: hidden;
    color: #fff;
}
</style>
