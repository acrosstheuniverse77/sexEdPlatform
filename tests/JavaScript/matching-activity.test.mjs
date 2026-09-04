import test from 'node:test';
import assert from 'node:assert/strict';
import { calculateConnectorLines, createMatchingActivity } from '../../resources/js/matching-activity.js';

const response = (data, ok = true) => ({ ok, json: async () => data });

test('matching selections expose aria state and lock completed pairs', () => {
    const activity = createMatchingActivity({
        initialMatchedPairs: [{ left_id: 'left-1', right_id: 'right-1' }],
    });

    assert.equal(activity.isLeftMatched('left-1'), true);
    assert.equal(activity.isRightMatched('right-1'), true);
    assert.equal(activity.ariaPressed('left', 'left-1'), 'false');
    activity.selectLeft('left-2').selectRight('right-2');
    assert.equal(activity.ariaPressed('left', 'left-2'), 'true');
    assert.equal(activity.ariaPressed('right', 'right-2'), 'true');
});

test('incorrect proposals clear only the proposal and announce feedback', async () => {
    const activity = createMatchingActivity({ matchUrl: '/match' }, async () => response({
        status: 'in_progress',
        accepted: true,
        is_correct: false,
        is_complete: false,
    }));

    activity.selectLeft('left-1').selectRight('right-2');
    await activity.submitMatch();

    assert.deepEqual(activity.matchedPairs, []);
    assert.equal(activity.leftId, null);
    assert.equal(activity.rightId, null);
    assert.equal(activity.feedback, 'Not quite—try another match');
});

test('request errors preserve previously completed pairs', async () => {
    const activity = createMatchingActivity({
        matchUrl: '/match',
        initialMatchedPairs: [{ left_id: 'left-1', right_id: 'right-1' }],
    }, async () => response({ message: 'Offline' }, false));

    activity.selectLeft('left-2').selectRight('right-2');
    await activity.submitMatch();

    assert.deepEqual(activity.matchedPairs, [{ left_id: 'left-1', right_id: 'right-1' }]);
    assert.equal(activity.error, 'Offline');
});

test('connector geometry is derived from item centers relative to the container', () => {
    assert.deepEqual(calculateConnectorLines(
        [{ left: 20, top: 30, width: 100, height: 20 }],
        [{ left: 300, top: 50, width: 80, height: 40 }],
        { left: 10, top: 20 },
    ), [{ x1: 60, y1: 20, x2: 330, y2: 50 }]);
});
