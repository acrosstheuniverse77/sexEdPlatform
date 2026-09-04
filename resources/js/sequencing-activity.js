export function moveItem(order, index, delta) {
    const target = index + delta;
    if (index < 0 || index >= order.length || target < 0 || target >= order.length) return [...order];
    const next = [...order];
    [next[index], next[target]] = [next[target], next[index]];
    return next;
}

async function readResponse(response) {
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Unable to save the sequence.');
    return data;
}

export function createSequencingActivity(config = {}, request = globalThis.fetch?.bind(globalThis)) {
    const initialOrder = Array.isArray(config.initialOrder) ? [...config.initialOrder] : [];
    const activity = {
        order: initialOrder,
        initialOrder,
        items: Array.isArray(config.items) ? [...config.items] : [],
        status: config.initialStatus || 'in_progress',
        revision: config.revision ?? 1,
        error: '',
        feedback: '',
        submitting: false,
        saveTimer: null,
        pendingSave: null,
        dragIndex: null,
        dragOverIndex: null,

        isLocked() {
            return this.status === 'completed' || this.submitting;
        },

        positionLabel(index) {
            return `${index + 1} of ${this.order.length}`;
        },

        itemFor(id) {
            return this.items.find((item) => item.id === id) ?? { id, value: id };
        },

        move(index, delta) {
            if (!this.isLocked()) this.order = moveItem(this.order, index, delta);
            this.scheduleSave();
            return this;
        },

        keyboardMove(index, delta, event = null) {
            if (event?.key && !['ArrowUp', 'ArrowDown'].includes(event.key)) return this;
            if (event?.preventDefault) event.preventDefault();
            return this.move(index, delta);
        },

        startItemDrag(index, event = null) {
            if (this.isLocked()) return this;
            this.dragIndex = index;
            this.dragOverIndex = index;
            if (event?.currentTarget?.hasPointerCapture?.(event.pointerId)) {
                event.currentTarget.releasePointerCapture(event.pointerId);
            }
            return this;
        },

        dropItem(index) {
            if (!this.isLocked() && this.dragIndex !== null && Number.isInteger(index) && this.dragIndex !== index) {
                const next = [...this.order];
                const [item] = next.splice(this.dragIndex, 1);
                next.splice(index, 0, item);
                this.order = next;
                this.scheduleSave();
            }
            this.dragIndex = null;
            this.dragOverIndex = null;
            return this;
        },

        cancelItemDrag() {
            this.dragIndex = null;
            this.dragOverIndex = null;
            return this;
        },

        scheduleSave() {
            if (config.preview || !config.stateUrl || typeof request !== 'function' || this.status === 'completed') return this;
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.persistState(), config.saveDebounceMs ?? 300);
            return this;
        },

        async persistState(force = false) {
            if (config.preview || (!force && this.submitting) || typeof request !== 'function' || !config.stateUrl) return null;
            const body = { revision: this.revision, state: { item_order: [...this.order] } };
            this.pendingSave = request(config.stateUrl, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json' },
                body: JSON.stringify(body),
            }).then(readResponse).catch((error) => {
                this.error = error.message || 'Unable to save the sequence.';
                return null;
            });
            const result = await this.pendingSave;
            this.pendingSave = null;
            return result;
        },

        async checkAnswer() {
            if (this.isLocked()) return null;
            clearTimeout(this.saveTimer);
            this.submitting = true;
            this.feedback = '';
            this.error = '';
            try {
                if (config.preview) {
                    const correct = JSON.stringify(this.order) === JSON.stringify(config.answerKey ?? []);
                    const data = { is_correct: correct, status: correct ? 'completed' : this.status };
                    this.status = data.status;
                    if (!correct) this.feedback = 'Not quite—try again';
                    this.$dispatch?.('interactive-activity-state', { status: this.status, data });
                    return data;
                }
                if (typeof request !== 'function' || !config.checkUrl) return null;
                if (this.pendingSave) await this.pendingSave;
                else if (config.stateUrl) await this.persistState(true);
                const response = await request(config.checkUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json' },
                    body: JSON.stringify({ revision: this.revision, item_order: [...this.order] }),
                });
                const data = await readResponse(response);
                this.status = data.status ?? this.status;
                if (!data.is_correct) this.feedback = 'Not quite—try again';
                this.$dispatch?.('interactive-activity-state', { status: this.status, data });
                return data;
            } catch (error) {
                this.error = error.message || 'Unable to check the sequence.';
                return null;
            } finally {
                this.submitting = false;
            }
        },

        resetPractice() {
            this.order = [...this.initialOrder];
            this.status = 'practice';
            this.feedback = '';
            this.error = '';
            return this;
        },
    };

    return activity;
}
