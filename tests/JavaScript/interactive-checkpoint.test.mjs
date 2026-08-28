import test from 'node:test';
import assert from 'node:assert/strict';
import {
    createCheckpointCoordinator,
    createInteractiveCheckpoint,
} from '../../resources/js/interactive-checkpoint.js';

test('incorrect hides explanation and exposes retry or skip', async () => {
    const request = async () => ({
        ok: true,
        json: async () => ({ status: 'incorrect', is_correct: false, explanation: null }),
    });
    const checkpoint = createInteractiveCheckpoint({
        type: 'identification', submitUrl: '/submit', skipUrl: '/skip', csrf: 'token',
    }, request);
    checkpoint.answer = 'Pressure';

    await checkpoint.submit();

    assert.equal(checkpoint.state, 'incorrect');
    assert.equal(checkpoint.explanation, null);
    assert.equal(checkpoint.showSkip(), true);
    assert.equal(checkpoint.showContinue(), false);
});

test('correct removes skip and exposes continuation', async () => {
    const request = async () => ({
        ok: true,
        json: async () => ({ status: 'correct', is_correct: true, explanation: 'Freely given.' }),
    });
    const checkpoint = createInteractiveCheckpoint({ type: 'identification', submitUrl: '/submit', skipUrl: '/skip', csrf: 'token' }, request);
    checkpoint.answer = 'Consent';

    await checkpoint.submit();

    assert.equal(checkpoint.state, 'correct');
    assert.equal(checkpoint.showSkip(), false);
    assert.equal(checkpoint.showContinue(), true);
});

test('request failure retains the answer and exposes an error', async () => {
    const request = async () => ({
        ok: false,
        json: async () => ({ message: 'Unable to save the checkpoint.' }),
    });
    const checkpoint = createInteractiveCheckpoint({ type: 'identification', submitUrl: '/submit', skipUrl: '/skip', csrf: 'token' }, request);
    checkpoint.answer = 'Consent';

    await checkpoint.submit();

    assert.equal(checkpoint.state, 'error');
    assert.equal(checkpoint.answer, 'Consent');
    assert.equal(checkpoint.error, 'Unable to save the checkpoint.');
});

test('retry clears the answer and coordinator releases footer ownership', () => {
    const checkpoint = createInteractiveCheckpoint({ type: 'identification', submitUrl: '/submit', skipUrl: '/skip', csrf: 'token' });
    checkpoint.answer = 'Pressure';
    checkpoint.state = 'incorrect';
    checkpoint.retry();
    assert.equal(checkpoint.state, 'ready');
    assert.equal(checkpoint.answer, '');

    const coordinator = createCheckpointCoordinator();
    coordinator.activate(17);
    assert.equal(coordinator.footerForwardVisible(), false);
    coordinator.release(17);
    assert.equal(coordinator.footerForwardVisible(), true);
});
