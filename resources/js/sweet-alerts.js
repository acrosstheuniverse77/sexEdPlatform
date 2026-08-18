const sweetOptions = {
    buttonsStyling: false,
    customClass: {
        confirmButton: 'rounded-xl bg-brand-700 px-4 py-2 text-sm font-bold text-white hover:bg-brand-800',
        cancelButton: 'rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50',
        popup: 'rounded-2xl',
    },
};

const nativeConfirm = (title, text) => window.confirm([title, text].filter(Boolean).join('\n\n'));

async function confirmAction(form) {
    const title = form.dataset.confirmTitle || 'Confirm this action?';
    const text = form.dataset.confirmText || 'This action will be recorded.';
    const detail = form.dataset.confirmDetail;
    const confirmText = detail ? `${text}\n\nReason: ${detail}` : text;

    if (!window.Swal) {
        return nativeConfirm(title, confirmText);
    }

    const result = await window.Swal.fire({
        ...sweetOptions,
        title,
        text: confirmText,
        icon: form.dataset.confirmIcon || 'warning',
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButton || 'Continue',
        cancelButtonText: form.dataset.cancelButton || 'Cancel',
        reverseButtons: true,
    });

    return result.isConfirmed;
}

function feedback(type, message) {
    if (!message) {
        return;
    }

    if (window.Swal) {
        window.Swal.fire({
            ...sweetOptions,
            toast: true,
            position: 'top-end',
            timer: 3600,
            timerProgressBar: true,
            showConfirmButton: false,
            icon: type,
            title: message,
        });
        return;
    }

    if (window.toast?.[type]) {
        window.toast[type](message);
    }
}

function prepareRejectReason(form) {
    if (!form.matches('[data-reject-form]')) {
        return true;
    }

    const selectedReason = form.querySelector('[name="reject_reason"]')?.value?.trim() || '';
    const customReason = form.querySelector('[name="custom_reject_reason"]')?.value?.trim() || '';
    const reason = selectedReason === 'Other' ? customReason : selectedReason;
    const reasonInput = form.querySelector('[name="reason"]');

    if (!selectedReason) {
        feedback('error', 'Select a rejection reason before rejecting the post.');
        return false;
    }

    if (selectedReason === 'Other' && !customReason) {
        feedback('error', 'Enter a custom rejection reason before rejecting the post.');
        return false;
    }

    if (reasonInput) {
        reasonInput.value = reason;
    }

    form.dataset.confirmDetail = reason;

    return true;
}

function htmlToText(html) {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html || '';

    return wrapper.textContent?.trim() || '';
}

function prepareCommunityReport(form) {
    if (!form.matches('[data-community-report-form]')) {
        return true;
    }

    form.querySelectorAll('[data-community-report-other-editor]').forEach((textarea) => {
        window.tinymce?.get(textarea.id)?.save();
    });

    const reasonSelect = form.querySelector('[name="reason_code"]');
    const selectedReason = reasonSelect?.value?.trim() || '';
    const reasonLabel = reasonSelect?.selectedOptions?.[0]?.textContent?.trim() || '';
    const details = form.querySelector('[name="details"]')?.value || '';

    if (!selectedReason) {
        feedback('error', 'Select a report reason before sending the report.');
        reasonSelect?.focus();
        return false;
    }

    if (selectedReason === 'other' && !htmlToText(details)) {
        feedback('error', 'Enter the other report reason before sending the report.');
        return false;
    }

    form.dataset.confirmDetail = reasonLabel;

    return true;
}

function setReactionState(button, active, count) {
    const activeClasses = ['border-brand-300', 'bg-brand-100', 'text-brand-800', 'shadow-sm', 'ring-1', 'ring-brand-200'];
    const inactiveClasses = ['border-gray-200', 'bg-white', 'text-gray-700', 'hover:border-brand-200', 'hover:bg-brand-50', 'hover:text-brand-700'];
    const remove = active ? inactiveClasses : activeClasses;
    const add = active ? activeClasses : inactiveClasses;

    button.classList.remove(...remove);
    button.classList.add(...add);
    button.dataset.active = active ? 'true' : 'false';
    button.setAttribute('aria-pressed', active ? 'true' : 'false');

    const countNode = button.querySelector('[data-community-reaction-count]');
    if (countNode) {
        countNode.textContent = String(count);
    }
}

async function submitReaction(form) {
    const button = form.querySelector('[data-community-reaction-button]');
    const formData = new FormData(form);

    button?.setAttribute('disabled', 'disabled');

    try {
        const response = await window.axios.post(form.action, formData, {
            headers: { Accept: 'application/json' },
        });

        setReactionState(button, Boolean(response.data?.active), Number(response.data?.count ?? 0));
    } catch (error) {
        feedback('error', error?.response?.data?.message || 'Unable to update reaction.');
    } finally {
        button?.removeAttribute('disabled');
    }
}

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    if (form.matches('[data-community-reaction-form]')) {
        event.preventDefault();
        await submitReaction(form);
        return;
    }

    if (!form.matches('[data-confirm-submit]') || form.dataset.confirmed === 'true') {
        return;
    }

    event.preventDefault();

    if (!prepareRejectReason(form) || !prepareCommunityReport(form)) {
        return;
    }

    if (await confirmAction(form)) {
        form.dataset.confirmed = 'true';
        form.requestSubmit();
    }
}, true);

window.ccSweetAlert = {
    confirmAction,
    feedback,
};
