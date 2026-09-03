import test from 'node:test';
import assert from 'node:assert/strict';
import { createInteractiveActivityAuthoring, createInteractiveActivityPreview } from '../../resources/js/interactive-activity-authoring.js';
import { createInteractiveActivity } from '../../resources/js/interactive-activity.js';
import { createMatchingActivity } from '../../resources/js/matching-activity.js';
import { createSequencingActivity } from '../../resources/js/sequencing-activity.js';

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

test('preview submits FormData, initializes injected Alpine HTML, supports viewports, and restores focus', async () => {
    const originalFormData = globalThis.FormData;
    const calls = [];
    const initialized = [];
    const focused = [];
    globalThis.FormData = class {
        constructor(form) { this.form = form; }
        delete(key) { this.deleted = [...(this.deleted ?? []), key]; }
    };

    try {
        const preview = createInteractiveActivityPreview({ url: '/preview', csrf: 'token' }, async (url, options) => {
            calls.push({ url, options });
            return { ok: true, async json() { return { html: '<section x-data="interactiveActivity"></section>' }; } };
        });
        const form = {};
        const trigger = { focus() { focused.push(true); } };
        preview.$refs = { previewMount: {} };
        preview.$nextTick = (callback) => callback();
        globalThis.Alpine = { initTree(node) { initialized.push(node); } };

        await preview.open(form, trigger);

        assert.equal(calls[0].url, '/preview');
        assert.equal(calls[0].options.method, 'POST');
        assert.equal(calls[0].options.body.form, form);
        assert.deepEqual(calls[0].options.body.deleted, ['_method']);
        assert.equal(preview.isOpen, true);
        assert.equal(preview.previewWidth(), 1440);
        preview.selectViewport('mobile');
        assert.equal(preview.previewWidth(), 375);
        assert.equal(initialized.length, 1);
        preview.close();
        assert.deepEqual(focused, [true]);
    } finally {
        globalThis.FormData = originalFormData;
        delete globalThis.Alpine;
    }
});

test('preview exposes 422 errors without opening the modal', async () => {
    const originalFormData = globalThis.FormData;
    globalThis.FormData = class {};
    try {
        const preview = createInteractiveActivityPreview({ url: '/preview' }, async () => ({
            ok: false,
            status: 422,
            async json() { return { message: 'Fix fields', errors: { title: ['Title is required.'] } }; },
        }));

        const result = await preview.open({});

        assert.equal(result, null);
        assert.equal(preview.isOpen, false);
        assert.deepEqual(preview.errors, { title: ['Title is required.'] });
        assert.deepEqual(preview.errorMessages(), ['Title is required.']);
    } finally {
        globalThis.FormData = originalFormData;
    }
});

test('preview local adapters evaluate matching and sequencing without network navigation', async () => {
    const matching = createMatchingActivity({
        preview: true,
        answerKey: { left: 'right' },
        leftItems: [{ id: 'left', value: 'Left' }],
        rightItems: [{ id: 'right', value: 'Right' }],
    }, () => { throw new Error('network disabled'); });
    matching.selectLeft('left').selectRight('right');
    const matchingResult = await matching.submitMatch();
    assert.equal(matchingResult.is_correct, true);
    assert.equal(matching.status, 'completed');

    const sequencing = createSequencingActivity({
        preview: true,
        answerKey: ['one', 'two', 'three'],
        initialOrder: ['three', 'two', 'one'],
        items: [{ id: 'one', value: 'One' }, { id: 'two', value: 'Two' }, { id: 'three', value: 'Three' }],
    }, () => { throw new Error('network disabled'); });
    sequencing.order = ['one', 'two', 'three'];
    const sequencingResult = await sequencing.checkAnswer();
    assert.equal(sequencingResult.is_correct, true);
    assert.equal(sequencing.status, 'completed');
});

test('preview common lifecycle uses local state and never follows real navigation', async () => {
    const activity = createInteractiveActivity({ preview: true, continueUrl: '/real-route' }, () => {
        throw new Error('network disabled');
    });
    const events = [];
    activity.$dispatch = (name, detail) => events.push([name, detail]);

    await activity.skip();
    assert.equal(activity.status, 'skipped');
    await activity.resume();
    assert.equal(activity.status, 'in_progress');
    await activity.practice();
    assert.equal(activity.status, 'practice');
    activity.continueLearning();

    assert.equal(events.at(-1)[0], 'interactive-activity-continued');
    assert.equal(events.at(-1)[1].preview, true);
});
