import test from 'node:test';
import assert from 'node:assert/strict';
import { createSequencingActivity, moveItem } from '../../resources/js/sequencing-activity.js';

const response = (data, ok = true) => ({ ok, json: async () => data });

test('moveItem obeys bounds and preserves one shared order array', () => {
    assert.deepEqual(moveItem(['one', 'two', 'three'], 1, -1), ['two', 'one', 'three']);
    assert.deepEqual(moveItem(['one', 'two', 'three'], 0, -1), ['one', 'two', 'three']);
    assert.deepEqual(moveItem(['one', 'two', 'three'], 2, 1), ['one', 'two', 'three']);
});

test('buttons and keyboard use the same order primitive', () => {
    const activity = createSequencingActivity({ initialOrder: ['one', 'two', 'three'] });
    activity.move(2, -1);
    activity.keyboardMove(1, -1, { key: 'ArrowUp', preventDefault() {} });
    assert.deepEqual(activity.order, ['three', 'one', 'two']);
    assert.equal(activity.positionLabel(1), '2 of 3');
});

test('pointer reorder uses the shared order and a debounced full state save', async () => {
    const calls = [];
    const activity = createSequencingActivity({
        initialOrder: ['one', 'two', 'three'],
        stateUrl: '/state',
        saveDebounceMs: 1,
    }, async (url, options) => {
        calls.push({ url, options });
        return response({ status: 'in_progress' });
    });

    activity.startItemDrag(0).dropItem(2);
    await new Promise((resolve) => setTimeout(resolve, 10));

    assert.deepEqual(activity.order, ['two', 'three', 'one']);
    assert.equal(calls.length, 1);
    assert.deepEqual(JSON.parse(calls[0].options.body), {
        revision: 1,
        state: { item_order: ['two', 'three', 'one'] },
    });
});

test('check flushes state, locks requests, and preserves order after a failed check', async () => {
    const calls = [];
    const activity = createSequencingActivity({
        initialOrder: ['one', 'two', 'three'],
        stateUrl: '/state',
        checkUrl: '/check',
    }, async (url, options) => {
        calls.push({ url, options });
        return response({ message: 'Offline' }, false);
    });

    activity.move(0, 1);
    await activity.checkAnswer();

    assert.deepEqual(activity.order, ['two', 'one', 'three']);
    assert.deepEqual(calls.map(({ url }) => url), ['/state', '/check']);
    assert.equal(activity.error, 'Offline');
    assert.equal(activity.submitting, false);
});

test('correct state locks controls and practice resets local status', async () => {
    const activity = createSequencingActivity({ initialOrder: ['one', 'two', 'three'], checkUrl: '/check' }, async () => response({
        status: 'completed',
        is_correct: true,
        is_complete: true,
    }));

    await activity.checkAnswer();
    assert.equal(activity.isLocked(), true);
    activity.resetPractice();
    assert.equal(activity.status, 'practice');
    assert.deepEqual(activity.order, ['one', 'two', 'three']);
});
