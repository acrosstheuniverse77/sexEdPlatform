<?php

namespace App\Http\Requests\Community;

use App\Enums\CommunityPostType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreCommunityPostRequest extends FormRequest
{
    public const MAX_IMAGES = 6;

    public const TOPICS = [
        'Consent education',
        'Healthy relationships',
        'Community seminar',
        'Sexual health resource',
        'Connector announcement',
        'Other',
    ];

    public const IMAGE_MAX_KILOBYTES = 5120;

    public const VIDEO_MAX_KILOBYTES = 25600;

    public const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public const VIDEO_MIME_TYPES = ['video/mp4', 'video/webm', 'video/quicktime'];

    public function authorize(): bool
    {
        $connector = $this->route('connector');

        return $connector && app(\App\Services\Community\CommunityAccessService::class)->canCreatePost($this->user(), $connector);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'custom_topic' => is_string($this->input('custom_topic')) ? trim($this->input('custom_topic')) : $this->input('custom_topic'),
        ]);
    }

    public function rules(): array
    {
        return [
            'post_type' => ['required', Rule::in(CommunityPostType::values())],
            'topic_choice' => ['required', Rule::in(self::TOPICS)],
            'custom_topic' => ['nullable', 'required_if:topic_choice,Other', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:8000'],
            'seminar_id' => ['nullable', 'integer', 'exists:seminars,id'],
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES, function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_array($value) && $value !== [] && ($this->hasFile('video') || $this->hasFile('media'))) {
                    $fail('Choose images or one video, not both.');
                }
            }],
            'images.*' => ['bail', 'file', $this->mediaValidator('image')],
            'video' => ['nullable', 'file', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value instanceof UploadedFile && ($this->imageUploads() !== [] || $this->hasFile('media'))) {
                    $fail('Choose one video or images, not both.');
                }
            }, $this->mediaValidator('video')],
            // Accepted temporarily for compatibility with the previous single-file form.
            'media' => ['nullable', 'file', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value instanceof UploadedFile && ($this->imageUploads() !== [] || $this->hasFile('video'))) {
                    $fail('Choose only one media mode.');
                }
            }, $this->mediaValidator()],
            'remove_media_ids' => ['nullable', 'array', function (string $attribute, mixed $value, \Closure $fail): void {
                $post = $this->route('communityPost');
                $ids = collect(is_array($value) ? $value : [])
                    ->filter(fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false)
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique();

                if ($ids->isNotEmpty() && (! $post || $post->media()->whereNull('removed_at')->whereKey($ids)->count() !== $ids->count())) {
                    $fail('The selected existing media item is not available for this post.');
                }
            }],
            'remove_media_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'images.max' => 'Choose no more than six images.',
            'remove_media_ids.*.distinct' => 'Each existing media item can only be removed once.',
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function imageUploads(): array
    {
        $images = $this->file('images', []);

        return is_array($images) ? $images : [];
    }

    private function mediaValidator(?string $expectedType = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($expectedType): void {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $mimeType = $value->getMimeType() ?: $value->getClientMimeType();
            $isImage = in_array($mimeType, self::IMAGE_MIME_TYPES, true);
            $isVideo = in_array($mimeType, self::VIDEO_MIME_TYPES, true);

            if ((! $isImage && ! $isVideo)
                || ($expectedType === 'image' && ! $isImage)
                || ($expectedType === 'video' && ! $isVideo)) {
                $fail($expectedType === 'image'
                    ? 'Choose JPG, PNG, or WebP images.'
                    : ($expectedType === 'video'
                        ? 'Choose an MP4, WebM, or MOV video.'
                        : 'Choose a JPG, PNG, WebP, MP4, WebM, or MOV file.'));

                return;
            }

            $maximumKilobytes = $isImage ? self::IMAGE_MAX_KILOBYTES : self::VIDEO_MAX_KILOBYTES;

            if ($value->getSize() > ($maximumKilobytes * 1024)) {
                $fail($isImage
                    ? 'Images must be 5 MB or smaller.'
                    : 'Videos must be 25 MB or smaller.');
            }
        };
    }
}
