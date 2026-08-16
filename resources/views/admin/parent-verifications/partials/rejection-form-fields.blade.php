<div class="space-y-4" data-testid="verification-rejection-form-fields">
    <div>
        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-gray-600">Reason for Rejection</label>
        <p class="mb-2 text-xs text-gray-500">Select the closest reason so guardians can quickly fix and resubmit.</p>
    </div>

    <select x-model="modalReasonCode"
            :disabled="processingApprove || processingReject"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
        <option value="">Select rejection reason</option>
        @foreach($moderationReasons as $reason)
            <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
        @endforeach
    </select>

    <div x-show="modalReasonCode === 'others'" x-cloak>
        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-gray-600">Custom Reason</label>
        <textarea x-model="modalCustomReason"
                  x-ref="customReasonEditor"
                  x-effect="if (rejectModalOpen && modalReasonCode === 'others') { $nextTick(() => window.initParentChildModerationEditor && window.initParentChildModerationEditor($refs.customReasonEditor)); } else if (window.destroyParentChildModerationEditor) { window.destroyParentChildModerationEditor($refs.customReasonEditor); }"
                  maxlength="1000"
                  :disabled="processingApprove || processingReject"
                  class="js-guardian-child-moderation-editor w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
                  placeholder="Enter custom rejection reason"></textarea>
        <p class="mt-2 text-xs text-gray-500">Provide clear, actionable guidance so the guardian understands what to correct.</p>
    </div>
</div>
