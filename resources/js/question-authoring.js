const RICH_TYPES = ['multiple_choice', 'true_false', 'multiple_select', 'identification'];
const CHOICE_TYPES = ['multiple_choice', 'true_false', 'multiple_select'];
const BLANK_TYPES = ['fill_blank_text', 'fill_blank_select'];

export function stripQuestionHtml(html = '') {
    return String(html)
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p>/gi, '\n')
        .replace(/<[^>]*>/g, '')
        .replace(/&nbsp;|&#160;/gi, ' ')
        .replace(/&amp;/gi, '&')
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

function defaultOptions(type, nextKey) {
    if (type === 'true_false') {
        return [
            { key: nextKey(), text: 'True', isCorrect: false, readonly: true },
            { key: nextKey(), text: 'False', isCorrect: false, readonly: true },
        ];
    }

    if (['multiple_choice', 'multiple_select'].includes(type)) {
        return [
            { key: nextKey(), text: '', isCorrect: false, readonly: false },
            { key: nextKey(), text: '', isCorrect: false, readonly: false },
        ];
    }

    return [];
}

export function createQuestionAuthoring(config = {}) {
    let key = 0;
    const nextKey = () => `question-row-${key += 1}`;
    const type = config.type || 'multiple_choice';
    const suppliedOptions = Array.isArray(config.options) ? config.options : [];
    const initialAnswers = Array.isArray(config.answers) && config.answers.length
        ? config.answers.map(String)
        : [''];
    const initialOptions = type === 'true_false'
        ? defaultOptions(type, nextKey).map((option) => ({
            ...option,
            isCorrect: Boolean(suppliedOptions.find(
                (supplied) => String(supplied.text || '').trim().toLowerCase() === option.text.toLowerCase(),
            )?.isCorrect),
        }))
        : (suppliedOptions.length
            ? suppliedOptions.map((option) => ({
                key: nextKey(),
                text: String(option.text || ''),
                isCorrect: Boolean(option.isCorrect),
                readonly: Boolean(option.readonly),
            }))
            : defaultOptions(type, nextKey));

    return {
        questionType: type,
        questionText: config.questionText || '',
        points: Number(config.points || 1),
        explanation: config.explanation || '',
        options: initialOptions,
        answers: initialAnswers,
        answerKeys: initialAnswers.map(() => nextKey()),
        wordBank: config.wordBank || '',
        caseSensitive: Boolean(config.caseSensitive),
        currentImageUrl: config.currentImageUrl || null,
        removeExistingImage: Boolean(config.removeExistingImage),
        typeMeta: config.typeMeta || {},
        errors: {},
        editorUploadUrl: config.editorUploadUrl || null,

        init() {
            this.$root?.closest('form')?.addEventListener('submit', (event) => this.submit(event));
            this.$nextTick?.(() => this.configureEditor());
        },

        isRichType(typeToCheck = this.questionType) {
            return RICH_TYPES.includes(typeToCheck);
        },

        isChoiceType() {
            return CHOICE_TYPES.includes(this.questionType);
        },

        isBlankType() {
            return BLANK_TYPES.includes(this.questionType);
        },

        canAddOptions() {
            return ['multiple_choice', 'multiple_select'].includes(this.questionType);
        },

        canRemoveOptions() {
            return this.canAddOptions() && this.options.length > 2;
        },

        addOption() {
            if (!this.canAddOptions()) return;
            this.options.push({ key: nextKey(), text: '', isCorrect: false, readonly: false });
        },

        removeOption(index) {
            if (!this.canRemoveOptions()) return;
            this.options.splice(index, 1);
        },

        setOnlyCorrect(index) {
            this.options.forEach((option, optionIndex) => {
                option.isCorrect = optionIndex === index;
            });
        },

        correctIndices() {
            return this.options
                .map((option, index) => option.isCorrect ? index : null)
                .filter((index) => index !== null);
        },

        addAnswer() {
            this.answers.push('');
            this.answerKeys.push(nextKey());
        },

        removeAnswer(index) {
            if (this.answers.length > 1) {
                this.answers.splice(index, 1);
                this.answerKeys.splice(index, 1);
            }
        },

        blankCount() {
            return (String(this.questionText).match(/_____/g) || []).length;
        },

        syncAnswersToBlanks() {
            if (!this.isBlankType()) return;
            const target = Math.max(1, this.blankCount());
            while (this.answers.length < target) this.addAnswer();
            if (this.answers.length > target) {
                this.answers.splice(target);
                this.answerKeys.splice(target);
            }
        },

        wordBankEntries() {
            return String(this.wordBank)
                .split(',')
                .map((word) => word.trim())
                .filter(Boolean);
        },

        insertBlank() {
            const textarea = this.$refs?.plainQuestion;
            const start = textarea?.selectionStart ?? this.questionText.length;
            const end = textarea?.selectionEnd ?? start;
            this.questionText = `${this.questionText.slice(0, start)}_____${this.questionText.slice(end)}`;
            this.syncAnswersToBlanks();
            this.$nextTick?.(() => {
                if (!textarea) return;
                textarea.focus();
                textarea.setSelectionRange(start + 5, start + 5);
            });
        },

        syncEditor() {
            const editor = globalThis.window?.tinymce?.get('question_text');
            if (!editor) return;
            this.questionText = editor.getContent();
            editor.save();
        },

        removeEditor() {
            globalThis.window?.tinymce?.get('question_text')?.remove();
        },

        configureEditor() {
            if (!this.isRichType() || !globalThis.window?.tinymce) return;
            if (globalThis.window.tinymce.get('question_text')) return;

            globalThis.window.tinymce.init({
                selector: '#question_text',
                license_key: 'gpl',
                promotion: false,
                height: 220,
                menubar: false,
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'help', 'wordcount',
                ],
                toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | image link | removeformat',
                images_upload_url: this.editorUploadUrl,
                automatic_uploads: true,
                images_reuse_filename: true,
                setup: (editor) => {
                    editor.on('change input undo redo', () => {
                        this.questionText = editor.getContent();
                    });
                },
            });
        },

        switchType(nextType) {
            if (!nextType || nextType === this.questionType) return;
            if (this.questionType === 'identification') this.removeExistingImage = true;
            const wasRich = this.isRichType();
            this.syncEditor();
            if (wasRich && !this.isRichType(nextType)) {
                this.questionText = stripQuestionHtml(this.questionText);
            }
            if (wasRich) this.removeEditor();

            this.questionType = nextType;
            this.options = defaultOptions(nextType, nextKey);
            this.answers = [''];
            this.answerKeys = [nextKey()];
            this.wordBank = '';
            this.caseSensitive = false;
            this.currentImageUrl = null;
            this.errors = {};
            if (this.$refs?.imageInput) this.$refs.imageInput.value = '';
            this.syncAnswersToBlanks();

            this.$nextTick?.(() => this.configureEditor());
        },

        validationErrors() {
            const errors = {};
            if (!stripQuestionHtml(this.questionText)) errors.question_text = 'Question text is required.';

            if (this.isChoiceType()) {
                if (this.options.length < 2 || this.options.some((option) => !option.text.trim())) {
                    errors.options = 'Provide at least two non-empty answer options.';
                }
                const correctCount = this.correctIndices().length;
                if (['multiple_choice', 'true_false'].includes(this.questionType) && correctCount !== 1) {
                    errors.correct_options = 'Select exactly one correct answer.';
                }
                if (this.questionType === 'multiple_select' && correctCount < 1) {
                    errors.correct_options = 'Select at least one correct answer.';
                }
            }

            if (this.isBlankType()) {
                if (this.blankCount() < 1) errors.question_text = 'Add at least one blank using five underscores (_____).';
                if (this.answers.length !== this.blankCount() || this.answers.some((answer) => !answer.trim())) {
                    errors.acceptable_answers = 'Add one answer for each blank.';
                }
            }

            if (this.questionType === 'fill_blank_select') {
                const words = this.wordBankEntries();
                if (words.length < 1 || words.length > 10) errors.word_bank = 'Use between 1 and 10 words in the Word Bank.';
                if (this.answers.some((answer) => !words.includes(answer.trim()))) {
                    errors.acceptable_answers = 'Every correct answer must appear in the Word Bank.';
                }
            }

            if (this.questionType === 'identification' && this.answers.some((answer) => !answer.trim())) {
                errors.acceptable_answers = 'Provide at least one acceptable answer.';
            }

            return errors;
        },

        submit(event) {
            if (this.$root.closest('fieldset')?.disabled) return;
            this.syncEditor();
            this.errors = this.validationErrors();
            if (Object.keys(this.errors).length === 0) return;
            event.preventDefault();
            this.$nextTick?.(() => {
                const editor = globalThis.window?.tinymce?.get('question_text');
                if (this.errors.question_text && editor) {
                    editor.focus();
                    return;
                }
                this.$root?.querySelector('[aria-invalid="true"]')?.focus();
            });
        },
    };
}
