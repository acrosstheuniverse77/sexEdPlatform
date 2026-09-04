@extends('layouts.admin')

@section('title', 'Community Hub Settings')
@section('page-title', 'Community Hub Settings')

@section('content')
<div class="max-w-5xl space-y-8">
    @include('admin.community.partials.navigation')

    <section class="rounded-[30px] border border-gray-200 bg-white p-6 shadow-theme-xs">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-rose-700">Emergency control</p>
                <h2 class="mt-1 text-xl font-bold text-gray-950">Emergency Freeze</h2>
                <p class="mt-1 text-sm text-gray-600">Freeze Community Hub posting and comments globally while platform safety review is underway.</p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $isGloballyFrozen ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">
                {{ $isGloballyFrozen ? 'Frozen' : 'Open' }}
            </span>
        </div>

        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-bold">Use this only for active safety incidents.</p>
            <p class="mt-1">Freezing pauses connector posting, comments, reactions, and reports while admins preserve evidence and decide enforcement.</p>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <form method="POST" action="{{ route('admin.community.freeze') }}" class="space-y-3 rounded-[26px] border border-rose-100 bg-rose-50 p-4" data-confirm-submit data-confirm-title="Freeze Community Hub?" data-confirm-text="This pauses connector posting, comments, reactions, and reports globally while safety review is underway." data-confirm-icon="warning" data-confirm-button="Freeze Hub">
                @csrf
                <label class="block text-sm font-bold text-rose-900" for="reason">Freeze reason</label>
                <textarea id="reason" name="reason" rows="3" class="w-full rounded-xl border-rose-200 text-sm focus:border-rose-400 focus:ring-rose-400" required></textarea>
                <button class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-rose-600 text-white hover:bg-rose-700" title="Freeze Hub" aria-label="Freeze Hub">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V7.5a4.5 4.5 0 0 0-9 0v3m-.75 0h10.5A1.75 1.75 0 0 1 19 12.25v5.5a1.75 1.75 0 0 1-1.75 1.75H6.75A1.75 1.75 0 0 1 5 17.75v-5.5a1.75 1.75 0 0 1 1.75-1.75Z"/></svg>
                </button>
            </form>

            <form method="POST" action="{{ route('admin.community.unfreeze') }}" class="space-y-3 rounded-[26px] border border-emerald-100 bg-emerald-50 p-4" data-confirm-submit data-confirm-title="Reopen Community Hub?" data-confirm-text="Reopen hub actions after platform safety review is complete." data-confirm-icon="question" data-confirm-button="Reopen Hub">
                @csrf
                <p class="text-sm font-bold text-emerald-900">Reopen hub</p>
                <p class="text-sm text-emerald-800">Use after platform safety review confirms connector spaces can resume activity.</p>
                <button class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white hover:bg-emerald-700" title="Reopen Hub" aria-label="Reopen Hub">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 10.5V7.875a3.75 3.75 0 0 1 7.32-1.125M6.75 10.5h10.5A1.75 1.75 0 0 1 19 12.25v5.5a1.75 1.75 0 0 1-1.75 1.75H6.75A1.75 1.75 0 0 1 5 17.75v-5.5a1.75 1.75 0 0 1 1.75-1.75Z"/></svg>
                </button>
            </form>
        </div>
    </section>

    <section class="rounded-[30px] border border-gray-200 bg-white p-6 shadow-theme-xs">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-purple-700">Connector suspension handling</p>
            <h2 class="mt-1 text-xl font-bold text-gray-950">Suspended connector visibility</h2>
            <p class="mt-1 text-sm text-gray-600">Choose how Community Hub spaces behave when a connector is suspended or under platform review.</p>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <input type="radio" name="suspended_connector_visibility" value="read_only" checked class="border-gray-300 text-purple-700 focus:ring-purple-600">
                <span class="ml-2 text-sm font-bold text-gray-900">Read-only</span>
                <p class="mt-2 text-sm text-gray-600">Members can view preserved content, but new posts and comments stay paused.</p>
            </label>

            <label class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <input type="radio" name="suspended_connector_visibility" value="hidden" class="border-gray-300 text-purple-700 focus:ring-purple-600">
                <span class="ml-2 text-sm font-bold text-gray-900">Hidden</span>
                <p class="mt-2 text-sm text-gray-600">The connector space is hidden from members while admins preserve evidence for review.</p>
            </label>
        </div>
    </section>
</div>
@endsection
