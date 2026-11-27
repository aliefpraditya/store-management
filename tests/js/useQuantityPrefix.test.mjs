import assert from 'node:assert/strict';
import { test } from 'node:test';
import { splitQuantityPrefix } from '../../resources/js/Composables/useQuantityPrefix.js';

test('a number and an asterisk in front of the term is the quantity, the rest is looked up', () => {
    assert.deepEqual(splitQuantityPrefix('12*kabel'), { quantity: 12, lookupTerm: 'kabel', isPrefixed: true });
    assert.deepEqual(splitQuantityPrefix('12 * 8991234567890'), { quantity: 12, lookupTerm: '8991234567890', isPrefixed: true });
});

test('the prefix alone leaves nothing to look up yet', () => {
    assert.deepEqual(splitQuantityPrefix('12*'), { quantity: 12, lookupTerm: '', isPrefixed: true });
});

test('a decimal quantity can use a dot or a comma', () => {
    assert.equal(splitQuantityPrefix('0.5*lem').quantity, 0.5);
    assert.equal(splitQuantityPrefix('0,5*lem').quantity, 0.5);
});

test('a term without a prefix is looked up whole, as one', () => {
    assert.deepEqual(splitQuantityPrefix('kabel 2x1.5'), { quantity: 1, lookupTerm: 'kabel 2x1.5', isPrefixed: false });
    assert.deepEqual(splitQuantityPrefix('5x20'), { quantity: 1, lookupTerm: '5x20', isPrefixed: false });
    assert.deepEqual(splitQuantityPrefix('8991234567890'), { quantity: 1, lookupTerm: '8991234567890', isPrefixed: false });
});

test('a zero quantity is no prefix', () => {
    assert.deepEqual(splitQuantityPrefix('0*kabel'), { quantity: 1, lookupTerm: '0*kabel', isPrefixed: false });
});

test('an asterisk after the first character of a word is not a prefix', () => {
    assert.equal(splitQuantityPrefix('kabel*12').isPrefixed, false);
});
