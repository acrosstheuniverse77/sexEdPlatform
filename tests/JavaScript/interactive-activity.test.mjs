import test from 'node:test';
import assert from 'node:assert/strict';
import { createInteractiveActivity } from '../../resources/js/interactive-activity.js';

const response = (data, ok = true) => ({ ok, json: async () => data });

test('common activity state sends revisioned skip and exposes lifecycle controls', async () => {
    const calls = [];
    const activity = createInteractiveActivity({
        revision: 4,
        initialStatus: 'in_progress',
        skipUrl: '/skip',
        csrf: 'token',
    }, async (url, options) => {
        calls.push({ url, options });
        return response({ status: 'skipped', payload: { items: [] } });
    });

    assert.equal(activity.showSkip(), true);
    await activity.skip();
    assert.equal(activity.status, 'skipped');
    assert.equal(activity.showResume(), true);
    assert.equal(activity.showContinue(), true);
    assert.deepEqual(JSON.parse(calls[0].options.body), { revision: 4 });
    assert.equal(calls[0].options.headers['X-CSRF-TOKEN'], 'token');
});

test('common activity retains state and reports request errors', async () => {
    const activity = createInteractiveActivity({ skipUrl: '/skip' }, async () => response({ message: 'Offline' }, false));

    await activity.skip();

    assert.equal(activity.error, 'Offline');
    assert.equal(activity.status, 'in_progress');
    assert.equal(activity.submitting, false);
});
