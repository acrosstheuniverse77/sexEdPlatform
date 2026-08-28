import test from 'node:test';
import assert from 'node:assert/strict';
import { createWordBank } from '../../resources/js/word-bank.js';

test('fills the first empty blank and returns a removed word', () => {
    const bank = createWordBank(['HTML', 'CSS', 'JavaScript'], 2);

    bank.selectWord(1);
    bank.selectWord(0);
    assert.deepEqual(bank.answers(), ['CSS', 'HTML']);

    bank.removeWord(0);
    assert.deepEqual(bank.answers(), ['', 'HTML']);
    assert.equal(bank.isUsed(1), false);
});

test('tracks duplicate display values by index', () => {
    const bank = createWordBank(['same', 'same'], 2);

    bank.selectWord(0);
    bank.selectWord(1);

    assert.deepEqual(bank.selectedIndices, [0, 1]);
    assert.deepEqual(bank.answers(), ['same', 'same']);
});
