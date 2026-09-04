@props(['connector', 'post', 'media' => null])

@php
    $items = collect($media ?? ($post->relationLoaded('activeMedia') ? $post->activeMedia : $post->activeMedia()->get()))->values();
    $imageCount = $items->where('media_type', 'image')->count();
    $gridClass = match (true) {
        $imageCount <= 1 => 'grid-cols-1',
        $imageCount === 2 => 'grid-cols-2',
        default => 'grid-cols-2 sm:grid-cols-3',
    };
@endphp

@if($items->isNotEmpty())
    <div data-testid="community-media-gallery" class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-gray-50">
        @if($items->first()->media_type === 'video')
            <video controls preload="metadata" playsinline class="max-h-[32rem] w-full bg-black">
                <source src="{{ route('connector.community.media.show', [$connector, $post, $items->first()]) }}" type="{{ $items->first()->mime_type }}">
                Your browser does not support this video.
            </video>
        @else
            <div class="grid {{ $gridClass }} gap-1 bg-white">
                @foreach($items as $item)
                    @php
                        $featureFirst = $imageCount >= 3 && $imageCount % 2 === 1 && $loop->first;
                        $imageClass = $imageCount === 1
                            ? 'max-h-[32rem] w-full object-contain'
                            : ($featureFirst
                                ? 'h-full min-h-64 w-full object-cover sm:col-span-2 sm:row-span-2'
                                : 'aspect-square h-full w-full object-cover');
                    @endphp
                    <img
                        src="{{ route('connector.community.media.show', [$connector, $post, $item]) }}"
                        alt="Image {{ $loop->iteration }} attached to {{ $post->title }}"
                        class="{{ $imageClass }}"
                        loading="lazy"
                    >
                @endforeach
            </div>
        @endif
    </div>
@endif
