import assert from 'node:assert/strict';
import { test } from 'node:test';
import { costFromBase, marginFromPrice, priceFromMargin, roundedPriceFromMargin } from '../../resources/js/Composables/usePriceMargin.js';

test('a typed margin gives the price of the margin, never a markup', () => {
    assert.equal(priceFromMargin(6000, 20), 7500);
    assert.equal(priceFromMargin(72875, 37.5), 116600);
});

test('a margin of 100 or more, or one that is not a number, gives no price', () => {
    assert.equal(priceFromMargin(100, 100), null);
    assert.equal(priceFromMargin(100, 120), null);
    assert.equal(priceFromMargin(100, 'abc'), null);
});

test('the price of a margin is rounded up to the next multiple of the step', () => {
    assert.equal(roundedPriceFromMargin(15952.4, 37, 1000), 26000);
    assert.equal(roundedPriceFromMargin(15952.4, 37, 500), 25500);
});

test('a price already on the step stays where it is', () => {
    assert.equal(roundedPriceFromMargin(72875, 37.5, 1000), 117000);
});

test('a step of 0 leaves the price as the margin gives it', () => {
    assert.equal(roundedPriceFromMargin(15952.4, 37, 0), 25321.27);
    assert.equal(roundedPriceFromMargin(15952.4, 37, undefined), 25321.27);
});

test('a step that rounds to nothing leaves the price alone instead of dividing by zero', () => {
    assert.equal(roundedPriceFromMargin(15952.4, 37, 0.4), 25321.27);
});

test('a refused margin or a free item gives no rounded price or a price of 0', () => {
    assert.equal(roundedPriceFromMargin(100, 100, 1000), null);
    assert.equal(roundedPriceFromMargin(0, 30, 1000), 0);
});

test('the margin read back from a rounded price is a little above the typed one', () => {
    const price = roundedPriceFromMargin(15952.4, 37, 1000);

    assert.equal(marginFromPrice(15952.4, price), 38.64);
});

test('there is no margin without a price or without a modal', () => {
    assert.equal(marginFromPrice(0, 1000), null);
    assert.equal(marginFromPrice(1000, 0), null);
});

test('a unit that follows the base cost costs the base cost times the conversion, to the sen', () => {
    assert.equal(costFromBase(1.15, 1.5), 1.73);
});
