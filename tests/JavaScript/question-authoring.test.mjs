import test from 'node:test';
import assert from 'node:assert/strict';
import {
    createQuestionAuthoring,
    stripQuestionHtml,
} from '../../resources/js/question-authoring.js';

test('multiple choice adds unlimited options and never removes below two', () => {
    const state = createQuestionAuthoring({
        type: 'multiple_choice',
        options: [
            { text: 'A', isCorrect: true },
            { text: 'B', isCorrect: false },
        ],
    });

    for (let index = 0; index < 23; index += 1) state.addOption();
    assert.equal(state.options.length, 25);

    state.removeOption(0);
    assert.equal(state.options.some((option) => option.isCorrect), false);
    while (state.options.length > 2) state.removeOption(0);
    state.removeOption(0);
    assert.equal(state.options.length, 2);
});

test('true false always owns two fixed rows with radio semantics', () => {
    const state = createQuestionAuthoring({ type: 'true_false' });

    assert.deepEqual(state.options.map(({ text, readonly }) => ({ text, readonly })), [
        { text: 'True', readonly: true },
        { text: 'False', readonly: true },
    ]);
    state.setOnlyCorrect(1);
    assert.deepEqual(state.correctIndices(), [1]);
    assert.equal(state.canAddOptions(), false);
    assert.equal(state.canRemoveOptions(), false);
});

test('multiple select removes deleted answers from the correct set', () => {
    const state = createQuestionAuthoring({
        type: 'multiple_select',
        options: [
            { text: 'A', isCorrect: true },
            { text: 'B', isCorrect: false },
            { text: 'C', isCorrect: true },
        ],
    });

    state.removeOption(2);
    assert.deepEqual(state.correctIndices(), [0]);
});

test('blank helpers insert markers and count ordered answer groups', () => {
    const state = createQuestionAuthoring({
        type: 'fill_blank_text',
        questionText: 'The _____ is _____.',
        answers: ['color|colour', 'blue'],
    });

    assert.equal(state.blankCount(), 2);
    assert.deepEqual(state.validationErrors(), {});
    state.questionText += ' _____';
    state.syncAnswersToBlanks();
    assert.equal(state.answers.length, 3);
    assert.match(state.validationErrors().acceptable_answers, /one answer for each blank/i);
});

test('word bank validation caps ten entries and requires membership', () => {
    const state = createQuestionAuthoring({
        type: 'fill_blank_select',
        questionText: '_____ follows _____.',
        wordBank: 'alpha, beta',
        answers: ['alpha', 'missing'],
    });

    assert.match(state.validationErrors().acceptable_answers, /Word Bank/i);
    state.answers[1] = 'beta';
    assert.deepEqual(state.validationErrors(), {});
    state.wordBank = Array.from({ length: 11 }, (_, index) => `word${index}`).join(',');
    assert.match(state.validationErrors().word_bank, /10 words/i);
});

test('the full switch sequence resets type state and retains common fields', () => {
    const state = createQuestionAuthoring({
        type: 'multiple_choice',
        questionText: '<p>Keep this question</p>',
        explanation: 'Keep this explanation',
        points: 4,
        options: [
            { text: 'A', isCorrect: true },
            { text: 'B', isCorrect: false },
        ],
    });

    const sequence = [
        'true_false',
        'identification',
        'fill_blank_text',
        'fill_blank_select',
        'multiple_select',
        'multiple_choice',
    ];

    for (const type of sequence) {
        state.switchType(type);
        assert.equal(state.questionType, type);
        assert.equal(state.explanation, 'Keep this explanation');
        assert.equal(state.points, 4);
        assert.deepEqual(state.answers, ['']);
        assert.equal(state.wordBank, '');
        assert.equal(state.caseSensitive, false);

        if (type === 'true_false') {
            assert.deepEqual(state.options.map((option) => option.text), ['True', 'False']);
        } else if (['multiple_choice', 'multiple_select'].includes(type)) {
            assert.equal(state.options.length, 2);
            assert.equal(state.options.every((option) => option.text === ''), true);
        } else {
            assert.deepEqual(state.options, []);
        }
    }

    assert.equal(state.questionText, 'Keep this question');
});

test('rich to plain conversion removes markup and decodes visible text', () => {
    assert.equal(stripQuestionHtml('<p>Consent&nbsp;<strong>matters</strong></p>'), 'Consent matters');
});
