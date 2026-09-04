const moderationStatusClasses = {
    draft: ['border-slate-200', 'bg-slate-50', 'text-slate-700'],
    pending_review: ['border-amber-200', 'bg-amber-50', 'text-amber-800'],
    published: ['border-emerald-200', 'bg-emerald-50', 'text-emerald-700'],
    hidden: ['border-slate-300', 'bg-slate-100', 'text-slate-700'],
    locked: ['border-blue-200', 'bg-blue-50', 'text-blue-700'],
    removed: ['border-rose-200', 'bg-rose-50', 'text-rose-700'],
    escalated: ['border-amber-200', 'bg-amber-50', 'text-amber-800'],
    archived: ['border-gray-200', 'bg-gray-100', 'text-gray-600'],
};

const moderationBadgeBaseClasses = [
    'inline-flex',
    'items-center',
    'rounded-full',
    'border',
    'px-2.5',
    'py-1',
    'text-[11px]',
    'font-bold',
    'leading-none',
];

const moderationFeedback = (type, message) => {
    if (!message) {
        return;
    }

    if (window.toast?.[type]) {
        window.toast[type](message);
    }

    document.querySelectorAll('[data-community-moderation-feedback]').forEach((node) => {
        node.textContent = message;
    });
};

const moderationErrorMessage = (error) => {
    const payload = error?.response?.data;
    const validationMessage = Object.values(payload?.errors ?? {}).flat()[0];

    return validationMessage || payload?.message || 'Unable to update this post. Please try again.';
};

const setModerationLoading = (form, loading) => {
    const button = form.querySelector('button[type="submit"]');
    const icon = form.querySelector('[data-community-moderation-icon]');
    const spinner = form.querySelector('[data-community-moderation-spinner]');

    if (!button) {
        return;
    }

    button.disabled = loading;
    button.setAttribute('aria-busy', loading ? 'true' : 'false');
    icon?.toggleAttribute('hidden', loading);
    spinner?.toggleAttribute('hidden', !loading);
};

const updateModerationStatus = (payload) => {
    const statusNode = document.querySelector('[data-community-post-status]');
    const badge = statusNode?.querySelector('[data-community-status-badge]');

    if (!statusNode || !badge || !payload?.status) {
        return;
    }

    const classes = moderationStatusClasses[payload.status] ?? ['border-gray-200', 'bg-gray-50', 'text-gray-700'];

    statusNode.dataset.status = payload.status;
    badge.className = [...moderationBadgeBaseClasses, ...classes].join(' ');
    badge.textContent = payload.status_label || payload.status;
};

const disableModerationActions = () => {
    document.querySelectorAll('[data-community-moderation-form] button[type="submit"]').forEach((button) => {
        button.disabled = true;
    });
};

const confirmModerationAction = (form) => window.confirm([
    form.dataset.confirmTitle || 'Confirm this action?',
    form.dataset.confirmText || 'This action will be recorded.',
].join('\n\n'));

const submitModerationForm = async (form) => {
    if (form.dataset.submitting === 'true') {
        return;
    }

    if (!confirmModerationAction(form)) {
        return;
    }

    form.dataset.submitting = 'true';
    setModerationLoading(form, true);

    try {
        const response = await window.axios.post(form.action, new FormData(form), {
            headers: { Accept: 'application/json' },
        });
        const payload = response.data ?? {};

        updateModerationStatus(payload);
        disableModerationActions();
        moderationFeedback('success', payload.message || 'Post updated.');
    } catch (error) {
        moderationFeedback('error', moderationErrorMessage(error));
        setModerationLoading(form, false);
        form.dataset.submitting = 'false';
    }
};

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches('[data-community-moderation-form]')) {
        return;
    }

    event.preventDefault();
    submitModerationForm(form);
}, true);
