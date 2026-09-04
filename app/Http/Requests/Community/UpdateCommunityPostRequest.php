<?php

namespace App\Http\Requests\Community;

class UpdateCommunityPostRequest extends StoreCommunityPostRequest
{
    public function authorize(): bool
    {
        $connector = $this->route('connector');
        $post = $this->route('communityPost');

        return $connector
            && $post
            && (int) $post->connector_id === (int) $connector->id
            && app(\App\Services\Community\CommunityAccessService::class)->canEditPost($this->user(), $post);
    }
}
