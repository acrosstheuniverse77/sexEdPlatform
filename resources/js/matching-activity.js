async function readResponse(response) {
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Unable to check the match.');
    return data;
}

export function calculateConnectorLines(leftRects, rightRects, containerRect) {
    return leftRects.flatMap((left, index) => {
        const right = rightRects[index];
        if (!left || !right) return [];

        return [{
            x1: left.left + left.width / 2 - containerRect.left,
            y1: left.top + left.height / 2 - containerRect.top,
            x2: right.left + right.width / 2 - containerRect.left,
            y2: right.top + right.height / 2 - containerRect.top,
        }];
    });
}

export function createMatchingActivity(config = {}, request = globalThis.fetch?.bind(globalThis)) {
    const activity = {
        leftId: null,
        rightId: null,
        matchedPairs: Array.isArray(config.initialMatchedPairs)
            ? JSON.parse(JSON.stringify(config.initialMatchedPairs))
            : [],
        status: config.initialStatus || 'in_progress',
        feedback: '',
        error: '',
        submitting: false,
        revision: config.revision ?? 1,
        leftItems: config.leftItems ?? [],
        rightItems: config.rightItems ?? [],
        connectorLines: [],
        connectorContainer: null,
        connectorObserver: null,

        isLeftMatched(id) {
            return this.matchedPairs.some((pair) => pair.left_id === id);
        },

        isRightMatched(id) {
            return this.matchedPairs.some((pair) => pair.right_id === id);
        },

        selectLeft(id) {
            if (!this.submitting && this.status !== 'completed' && !this.isLeftMatched(id)) this.leftId = id;
            return this;
        },

        selectRight(id) {
            if (!this.submitting && this.status !== 'completed' && !this.isRightMatched(id)) this.rightId = id;
            return this;
        },

        ariaPressed(side, id) {
            return String(this[side === 'left' ? 'leftId' : 'rightId'] === id);
        },

        labelFor(items, id) {
            return items.find((item) => item.id === id)?.value ?? id;
        },

        setupConnectors(container) {
            this.connectorContainer = container;
            const refresh = () => this.refreshConnectors();
            refresh();
            if (typeof ResizeObserver === 'function') {
                this.connectorObserver = new ResizeObserver(refresh);
                this.connectorObserver.observe(container);
            }
            window.addEventListener('resize', refresh);
            window.addEventListener('scroll', refresh, true);
            return this;
        },

        refreshConnectors() {
            if (!this.connectorContainer) return this;
            const containerRect = this.connectorContainer.getBoundingClientRect();
            const leftRects = this.matchedPairs.map((pair) => this.connectorContainer.querySelector(`[data-match-left="${pair.left_id}"]`)?.getBoundingClientRect());
            const rightRects = this.matchedPairs.map((pair) => this.connectorContainer.querySelector(`[data-match-right="${pair.right_id}"]`)?.getBoundingClientRect());
            this.connectorLines = calculateConnectorLines(leftRects, rightRects, containerRect);
            return this;
        },

        async submitMatch() {
            if (this.submitting || this.status === 'completed' || this.leftId === null || this.rightId === null || typeof request !== 'function') return null;

            const proposal = { left_id: this.leftId, right_id: this.rightId };
            this.submitting = true;
            this.feedback = '';
            this.error = '';
            try {
                const response = await request(config.matchUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf, Accept: 'application/json' },
                    body: JSON.stringify({
                        revision: this.revision,
                        ...proposal,
                        practice: config.practice === true,
                        working_state: { matched: this.matchedPairs },
                    }),
                });
                const data = await readResponse(response);
                if (data.is_correct && !this.matchedPairs.some((pair) => pair.left_id === proposal.left_id)) {
                    this.matchedPairs.push(proposal);
                }
                this.status = data.status ?? this.status;
                if (!data.is_correct) this.feedback = 'Not quite—try another match';
                this.leftId = null;
                this.rightId = null;
                this.$dispatch?.('interactive-activity-state', { status: this.status, data });
                queueMicrotask(() => this.refreshConnectors());
                return data;
            } catch (error) {
                this.error = error.message || 'Unable to check the match.';
                return null;
            } finally {
                this.submitting = false;
            }
        },

        resetPractice() {
            this.matchedPairs = [];
            this.leftId = null;
            this.rightId = null;
            this.status = 'practice';
            this.feedback = '';
            this.error = '';
            this.connectorLines = [];
            return this;
        },
    };

    return activity;
}
