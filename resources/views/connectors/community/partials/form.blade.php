@php($selectedType = old('post_type', $post?->post_type?->value ?? $post?->post_type ?? 'announcement'))

<x-community.safety-reminder />

<div>
    <p class="block text-sm font-bold text-gray-800">Choose post type first</p>
    <div class="mt-2 grid gap-3 sm:grid-cols-3">
        @foreach(\App\Enums\CommunityPostType::cases() as $type)
            @php($inputId = 'community-post-type-'.$type->value)
            <div>
                <input
                    id="{{ $inputId }}"
                    type="radio"
                    name="post_type"
                    value="{{ $type->value }}"
                    class="peer sr-only"
                    @checked($selectedType === $type->value)
                >
                <label
                    for="{{ $inputId }}"
                    class="block min-h-16 cursor-pointer rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-700 transition hover:border-purple-200 hover:bg-purple-50/50 peer-checked:border-purple-300 peer-checked:bg-purple-50 peer-checked:text-purple-900 peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-purple-500"
                >
                <span class="block text-sm font-bold">{{ $type->label() }}</span>
                <span class="mt-1 block text-xs text-gray-500">
                    @if($type->value === 'announcement')
                        Public connector updates.
                    @elseif($type->value === 'resource')
                        Educational links or materials.
                    @else
                        Questions routed through review.
                    @endif
                </span>
                </label>
            </div>
        @endforeach
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-bold text-gray-800" for="title">Title</label>
        <input id="title" name="title" value="{{ old('title', $post?->title) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500" maxlength="160" required>
    </div>
    <div>
        <label class="block text-sm font-bold text-gray-800" for="topic">Category/topic</label>
        <select id="topic" name="topic" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500">
            <option>Consent education</option>
            <option>Healthy relationships</option>
            <option>Community seminar</option>
            <option>Sexual health resource</option>
            <option>Connector announcement</option>
        </select>
    </div>
</div>

<div>
    <label class="block text-sm font-bold text-gray-800" for="body">Content</label>
    <textarea id="body" name="body" rows="8" class="mt-1 w-full rounded-lg border-gray-300 text-sm leading-6 focus:border-purple-500 focus:ring-purple-500" required>{{ old('body', $post?->body) }}</textarea>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-bold text-gray-800" for="resource_url">Attachment or link</label>
        <input id="resource_url" name="resource_url" value="{{ old('resource_url', $post?->resource_url) }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500" placeholder="https://">
    </div>
    <div>
        <label class="block text-sm font-bold text-gray-800" for="visibility">Visibility</label>
        <select id="visibility" name="visibility" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500">
            <option>Adult connector members only</option>
        </select>
    </div>
</div>
