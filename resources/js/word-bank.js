export function createWordBank(words = [], blankCount = 1, onChange = () => {}) {
    const wordBank = Array.from(words, String);
    const selectedIndices = Array(Math.max(1, blankCount)).fill(null);

    return {
        words: wordBank,
        wordBank,
        selectedIndices,
        selectedWords: selectedIndices,
        selectWord(wordIndex) {
            if (this.isUsed(wordIndex)) return;
            const blankIndex = this.selectedIndices.findIndex((value) => value === null);
            if (blankIndex === -1) return;
            this.selectedIndices[blankIndex] = wordIndex;
            onChange(this.answers());
        },
        removeWord(blankIndex) {
            if (blankIndex < 0 || blankIndex >= this.selectedIndices.length) return;
            this.selectedIndices[blankIndex] = null;
            onChange(this.answers());
        },
        isUsed(wordIndex) {
            return this.selectedIndices.includes(wordIndex);
        },
        answers() {
            return this.selectedIndices.map((index) => index === null ? '' : this.words[index]);
        },
        complete() {
            return this.selectedIndices.every((index) => index !== null);
        },
    };
}
