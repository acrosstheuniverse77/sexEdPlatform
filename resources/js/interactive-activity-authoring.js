const copy = (value) => JSON.parse(JSON.stringify(value));

const defaultPair = () => ({ left: { value: '' }, right: { value: '' } });
const defaultItem = () => ({ value: '' });

export function createInteractiveActivityAuthoring(options = {}) {
    const initialType = options.activityType === 'sequencing' ? 'sequencing' : 'matching';
    const api = {
        activityType: initialType,
        placement: options.placement === 'inside_topic' ? 'inside_topic' : 'between_topics',
        parentTopicId: options.parentTopicId ?? '',
        insertAfterBlock: Number.isInteger(options.insertAfterBlock) ? options.insertAfterBlock : 0,
        blockOptions: Array.isArray(options.blockOptions) ? copy(options.blockOptions) : [],
        pairs: Array.isArray(options.pairs) && options.pairs.length > 0
            ? copy(options.pairs)
            : [defaultPair(), defaultPair()],
        items: Array.isArray(options.items) && options.items.length > 0
            ? copy(options.items)
            : [defaultItem(), defaultItem(), defaultItem()],
        dragIndex: null,
        dragOverIndex: null,

        setActivityType(type) {
            this.activityType = type === 'sequencing' ? 'sequencing' : 'matching';
            return this;
        },

        addPair() {
            if (this.pairs.length < 12) this.pairs.push(defaultPair());
            return this;
        },

        removePair(index) {
            if (this.pairs.length > 2) this.pairs.splice(index, 1);
            return this;
        },

        movePair(index, offset) {
            const target = index + offset;
            if (target < 0 || target >= this.pairs.length) return this;
            [this.pairs[index], this.pairs[target]] = [this.pairs[target], this.pairs[index]];
            return this;
        },

        addItem() {
            if (this.items.length < 12) this.items.push(defaultItem());
            return this;
        },

        removeItem(index) {
            if (this.items.length > 3) this.items.splice(index, 1);
            return this;
        },

        moveItem(index, offset) {
            const target = index + offset;
            if (target < 0 || target >= this.items.length) return this;
            [this.items[index], this.items[target]] = [this.items[target], this.items[index]];
            return this;
        },

        startItemDrag(index, event = null) {
            this.dragIndex = index;
            this.dragOverIndex = index;
            if (event?.currentTarget?.hasPointerCapture?.(event.pointerId)) {
                event.currentTarget.releasePointerCapture(event.pointerId);
            }
            return this;
        },

        dropItem(index) {
            if (this.dragIndex !== null && Number.isInteger(index) && this.dragIndex !== index) {
                const [item] = this.items.splice(this.dragIndex, 1);
                this.items.splice(index, 0, item);
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

        configuration() {
            if (this.activityType === 'sequencing') {
                return {
                    schema_version: 1,
                    items: this.items.map((item, index) => ({
                        ...(item.id ? { id: item.id } : {}),
                        kind: 'text',
                        value: item.value ?? '',
                        correct_position: index + 1,
                    })),
                };
            }

            return {
                schema_version: 1,
                pairs: this.pairs.map((pair) => ({
                    ...(pair.id ? { id: pair.id } : {}),
                    left: {
                        ...(pair.left?.id ? { id: pair.left.id } : {}),
                        kind: 'text',
                        value: pair.left?.value ?? '',
                    },
                    right: {
                        ...(pair.right?.id ? { id: pair.right.id } : {}),
                        kind: 'text',
                        value: pair.right?.value ?? '',
                    },
                })),
            };
        },

        serializedConfiguration() {
            return JSON.stringify(this.configuration());
        },
    };

    return api;
}
