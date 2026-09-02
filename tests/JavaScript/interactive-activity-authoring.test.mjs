import test from 'node:test';
import assert from 'node:assert/strict';
import { createInteractiveActivityAuthoring } from '../../resources/js/interactive-activity-authoring.js';

test('matching rows stay paired while moving and obey bounds', () => {
    const authoring = createInteractiveActivityAuthoring({
        pairs: [
            { id: 'one', left: { id: 'left-one', value: 'A' }, right: { id: 'right-one', value: '1' } },
            { id: 'two', left: { id: 'left-two', value: 'B' }, right: { id: 'right-two', value: '2' } },
        ],
    });

    authoring.addPair().removePair(2).movePair(1, -1).movePair(0, -1);
    assert.deepEqual(authoring.configuration().pairs.map((pair) => pair.id), ['two', 'one']);
    assert.equal(authoring.configuration().pairs[0].left.value, 'B');
    assert.equal(authoring.configuration().pairs.length, 2);
});

test('sequencing serializes order and preserves stored ids', () => {
    const authoring = createInteractiveActivityAuthoring({
        activityType: 'sequencing',
        items: [
            { id: 'one', value: 'First' },
            { id: 'two', value: 'Second' },
            { id: 'three', value: 'Third' },
        ],
    });

    authoring.moveItem(2, -1);
    assert.deepEqual(authoring.configuration().items.map((item) => item.id), ['one', 'three', 'two']);
    assert.deepEqual(authoring.configuration().items.map((item) => item.correct_position), [1, 2, 3]);
    assert.match(authoring.serializedConfiguration(), /"schema_version":1/);
});

test('sequencing supports pointer reordering alongside buttons', () => {
    const authoring = createInteractiveActivityAuthoring({
        activityType: 'sequencing',
        items: [{ id: 'one', value: 'First' }, { id: 'two', value: 'Second' }, { id: 'three', value: 'Third' }],
    });

    authoring.startItemDrag(0).dropItem(2);
    assert.deepEqual(authoring.items.map((item) => item.id), ['two', 'three', 'one']);
});

test('activity placement is separate from subtype and can be changed', () => {
    const authoring = createInteractiveActivityAuthoring({ activityType: 'matching' });

    authoring.setActivityType('sequencing');
    authoring.placement = 'inside_topic';
    authoring.parentTopicId = 'topic-7';

    assert.equal(authoring.activityType, 'sequencing');
    assert.equal(authoring.placement, 'inside_topic');
    assert.equal(authoring.parentTopicId, 'topic-7');
});

test('initial values with quotes and line breaks serialize safely', () => {
    const authoring = createInteractiveActivityAuthoring({
        pairs: [{ left: { value: 'She said "yes"' }, right: { value: 'Line 1\nLine 2' } }, { left: { value: 'B' }, right: { value: '2' } }],
    });

    assert.equal(authoring.configuration().pairs[0].left.value, 'She said "yes"');
    assert.equal(JSON.parse(authoring.serializedConfiguration()).pairs[0].right.value, 'Line 1\nLine 2');
});

test('authoring exposes field-specific validation messages', () => {
    const authoring = createInteractiveActivityAuthoring({
        validationErrors: { 'configuration.items.0.value': ['Item text is required.'] },
    });

    assert.equal(authoring.errorFor('configuration.items.0.value'), 'Item text is required.');
    assert.equal(authoring.errorFor('configuration.items.1.value'), '');
});
