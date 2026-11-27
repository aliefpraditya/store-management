import assert from 'node:assert/strict';
import { test } from 'node:test';
import { lineSubtotal, refundOfLine } from '../../resources/js/Composables/useLineAmounts.js';

test('a decimal quantity is rounded up to the next multiple of the step', () => {
    assert.equal(lineSubtotal(0.37, 24000, 1000), 9000);
    assert.equal(lineSubtotal(0.37, 24000, 500), 9000);
    assert.equal(lineSubtotal(0.31, 24000, 500), 7500);
    assert.equal(lineSubtotal(2.5, 3333, 100), 8400);
});

test('an amount already on the step stays where it is', () => {
    assert.equal(lineSubtotal(0.5, 24000, 1000), 12000);
});

test('a whole quantity is never rounded', () => {
    assert.equal(lineSubtotal(3, 3333, 1000), 9999);
    assert.equal(lineSubtotal('2.000', 24500, 1000), 49000);
});

test('without a step nothing is rounded', () => {
    assert.equal(lineSubtotal(0.37, 24000, 0), 8880);
    assert.equal(lineSubtotal(0.37, 24000, undefined), 8880);
});

test('a price with sen is worked out to the sen before rounding', () => {
    assert.equal(lineSubtotal(1.5, 100.55, 0), 150.83);
    assert.equal(lineSubtotal(1.5, 100.55, 100), 200);
});

test('a line that was not rounded is returned at quantity times price', () => {
    const line = { quantity: '3.000', remaining: '3.000', unit_price: '10000.00', subtotal: '30000.00', refunded: '0.00' };

    assert.equal(refundOfLine(line, 1), 10000);
    assert.equal(refundOfLine(line, 3), 30000);
});

test('returning all of a rounded line gives back what the customer paid', () => {
    const line = { quantity: '0.370', remaining: '0.370', unit_price: '24000.00', subtotal: '9000.00', refunded: '0.00' };

    assert.equal(refundOfLine(line, 0.37), 9000);
});

test('returning part of a rounded line gives back its share of what was paid', () => {
    const line = { quantity: '0.370', remaining: '0.370', unit_price: '24000.00', subtotal: '9000.00', refunded: '0.00' };

    assert.equal(refundOfLine(line, 0.2), 4864.86);
});

test('returning the rest of a rounded line gives back what is left, so the refunds add up to what was paid', () => {
    const firstPart = { quantity: '0.370', remaining: '0.370', unit_price: '24000.00', subtotal: '9000.00', refunded: '0.00' };
    const rest = { ...firstPart, remaining: '0.170', refunded: String(refundOfLine(firstPart, 0.2)) };

    assert.equal(refundOfLine(rest, 0.17), 4135.14);
    assert.equal(refundOfLine(firstPart, 0.2) + refundOfLine(rest, 0.17), 9000);
});

test('nothing returned gives back nothing', () => {
    const line = { quantity: '0.370', remaining: '0.370', unit_price: '24000.00', subtotal: '9000.00', refunded: '0.00' };

    assert.equal(refundOfLine(line, ''), 0);
    assert.equal(refundOfLine(line, undefined), 0);
});
