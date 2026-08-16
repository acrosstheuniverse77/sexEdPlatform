<div class="border-b border-brand-100 bg-brand-50/45 px-6 py-5">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6" data-testid="admin-users-filter-bar">
        <label class="block xl:col-span-2">
            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Search</span>
            <input
                type="text"
                x-model="filters.search"
                @input.debounce.350ms="refresh(true)"
                placeholder="Search name or email"
                class="w-full rounded-2xl border border-brand-100 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-gray-300 focus:ring-2 focus:ring-gray-100"
            >
        </label>

        <label class="block">
            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Account Type</span>
            <select x-model="filters.account_type" @change="refresh(true)" class="w-full rounded-2xl border border-brand-100 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-gray-300 focus:ring-2 focus:ring-gray-100">
                <option value="">All account types</option>
                <option value="learner-child">Learner Child</option>
                <option value="learner-teen">Learner Teen</option>
                <option value="learner-adult">Learner Adult</option>
                <option value="guardian">Guardian</option>
                <option value="instructor">Instructor</option>
                <option value="admin">Admin</option>
            </select>
        </label>

        <label class="block">
            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Role</span>
            <select x-model="filters.role" @change="refresh(true)" class="w-full rounded-2xl border border-brand-100 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-gray-300 focus:ring-2 focus:ring-gray-100">
                <option value="">All roles</option>
                <option value="admin">Admin</option>
                <option value="instructor">Instructor</option>
                <option value="learner">Learner</option>
            </select>
        </label>

        <label class="block">
            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Status</span>
            <select x-model="filters.status" @change="refresh(true)" class="w-full rounded-2xl border border-brand-100 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-gray-300 focus:ring-2 focus:ring-gray-100">
                <option value="">All status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="suspended">Suspended</option>
                <option value="archived">Archived</option>
            </select>
        </label>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-2">
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Date From</span>
                <input type="date" x-model="filters.created_from" @change="refresh(true)" class="w-full rounded-2xl border border-brand-100 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-gray-300 focus:ring-2 focus:ring-gray-100">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Date To</span>
                <input type="date" x-model="filters.created_to" @change="refresh(true)" class="w-full rounded-2xl border border-brand-100 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-gray-300 focus:ring-2 focus:ring-gray-100">
            </label>
        </div>
    </div>
</div>

<div class="flex flex-wrap items-center justify-end gap-2 border-b border-gray-100 px-6 py-4">
    <select x-model="filters.per_page" @change="refresh(true)" class="rounded-xl border border-brand-200 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-brand-300 focus:ring-2 focus:ring-brand-100">
        <option value="10">10 / page</option>
        <option value="25">25 / page</option>
        <option value="50">50 / page</option>
        <option value="100">100 / page</option>
    </select>
    <button type="button" @click="clearColumnFilters()" class="inline-flex items-center rounded-xl border border-brand-200 bg-brand-50/70 px-4 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-100">Clear Filters</button>
</div>

<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-brand-50/45">
            <tr>
                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.2em] text-gray-500">No.</th>
                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.2em] text-gray-500">User</th>
                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Account Type</th>
                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Role</th>
                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Status</th>
                <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Joined</th>
                <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-[0.2em] text-gray-500">Actions</th>
            </tr>
        </thead>
        <tbody x-ref="rowsWrapper" class="divide-y divide-gray-100 bg-white">
            @include('admin.users.partials.users-table-rows', ['users' => $users])
        </tbody>
    </table>
</div>

<div x-ref="paginationWrapper" class="border-t border-gray-100">
    @include('admin.users.partials.users-pagination', ['users' => $users])
</div>
