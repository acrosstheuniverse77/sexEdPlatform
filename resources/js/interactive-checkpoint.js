export function emptyCheckpointAnswer(type, blankCount = 1) {
    if (type === 'multiple_select') return [];
    if (['fill_blank_text', 'fill_blank_select'].includes(type)) {
        return Array(Math.max(1, blankCount)).fill('');
    }
    return '';
}

async function readResponse(response) {
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Unable to save the checkpoint.');
    return data;
}

export function createInteractiveCheckpoint(config = {}, request = globalThis.fetch?.bind(globalThis)) {
    const initialStatus = ['correct', 'incorrect', 'skipped'].includes(config.initialStatus)
        ? config.initialStatus
        : 'ready';

    const checkpoint = {
        answer: emptyCheckpointAnswer(config.type, config.blankCount),
        state: initialStatus,
        isCorrect: initialStatus === 'correct' ? true : null,
        explanation: initialStatus === 'correct' ? config.initialExplanation || null : null,
        error: '',
        showSkip() { return ['ready', 'incorrect', 'error'].includes(this.state); },
        showContinue() { return ['correct', 'skipped'].includes(this.state); },
        retry() {
            this.answer = emptyCheckpointAnswer(config.type, config.blankCount);
            this.state = 'ready';
            this.isCorrect = null;
            this.explanation = null;
            this.error = '';
        },
        async submit() {
            this.state = 'submitting';
            this.error = '';
            try {
                const response = await request(config.submitUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json' },
                    body: JSON.stringify({ answer: this.answer }),
                });
                const data = await readResponse(response);
                this.state = data.status;
                this.isCorrect = data.is_correct;
                this.explanation = data.status === 'correct' ? data.explanation : null;
                if (['correct', 'skipped'].includes(data.status)) this.claimForward();
            } catch (error) {
                this.state = 'error';
                this.error = error.message || 'Unable to save the checkpoint.';
            }
        },
        async skip() {
            this.state = 'submitting';
            this.error = '';
            try {
                const response = await request(config.skipUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json' },
                });
                const data = await readResponse(response);
                this.state = data.status;
                this.isCorrect = data.is_correct;
                this.explanation = data.status === 'correct' ? data.explanation : null;
                if (['correct', 'skipped'].includes(data.status)) this.claimForward();
            } catch (error) {
                this.state = 'error';
                this.error = error.message || 'Unable to skip the checkpoint.';
            }
        },
        continueLearning() {
            if (config.continueUrl) {
                window.location.assign(config.continueUrl);
                return;
            }
            this.$dispatch?.('checkpoint-continued', { questionId: config.questionId });
        },
        claimForward() {
            this.$dispatch?.('checkpoint-active', { questionId: config.questionId });
        },
    };

    if (config.wordBank) {
        checkpoint.wordBank = createWordBank(config.wordBank, config.blankCount, (answers) => {
            checkpoint.answer = answers;
        });
    }

    return checkpoint;
}

export function createCheckpointCoordinator() {
    return {
        activeQuestionId: null,
        activate(questionId) { this.activeQuestionId = Number(questionId); },
        release(questionId) {
            if (this.activeQuestionId === Number(questionId)) this.activeQuestionId = null;
        },
        footerForwardVisible() { return this.activeQuestionId === null; },
    };
}
import { createWordBank } from './word-bank.js';
