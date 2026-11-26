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
        <span v-if="!isRightAligned">&nbsp;</span>
    </div>
</template>

<style>
/*
 * A line of a roll receipt at one edge. The renderer of QZ Tray centres every line that is narrower than the paper, whatever
 * `text-align`, `align`, `float` or `flex-start` says (proven by the printer test page); only a flex row with two items that
 * both hold text, pushed to the two edges by `space-between`, stays at the edge. The item at the other edge is a blank that
 * is not empty, because an empty item is dropped and the line is centred again.
 */
.receipt-print__row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}
</style>
