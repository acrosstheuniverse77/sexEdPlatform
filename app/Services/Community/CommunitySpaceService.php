<?php

namespace App\Services\Community;

use App\Models\CommunitySpace;
use App\Models\Connector;

class CommunitySpaceService
{
    public function spaceForConnector(Connector $connector): CommunitySpace
    {
        return CommunitySpace::query()->firstOrCreate(
            ['connector_id' => $connector->id],
            [
                'name' => $connector->name.' Community',
                'status' => 'active',
            ],
        );
    }
}
