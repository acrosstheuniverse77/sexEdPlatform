<?php

return [
    'categories' => [
        'government' => 'Government',
        'ngo' => 'NGO',
        'community_based_organization' => 'Community-based Organization',
        'school_educational_institution' => 'School / Educational Institution',
        'health_organization' => 'Health Organization',
        'advocacy_group' => 'Advocacy Group',
        'other' => 'Other',
    ],

    'statuses' => ['pending', 'verified', 'rejected', 'suspended', 'withdrawn'],

    'permissions' => [
        'profile' => [
            'connector.manage_profile' => 'Manage connector profile',
        ],
        'members' => [
            'connector.manage_members' => 'Manage members',
            'connector.invite_members' => 'Invite members',
        ],
        'roles' => [
            'connector.manage_roles' => 'Manage roles and permissions',
        ],
        'seminars' => [
            'connector.manage_seminars' => 'Manage seminars',
        ],
        'community' => [
            'community.view_space' => 'View Community Hub',
            'community.create_post' => 'Create Community Hub posts',
            'community.edit_own_post' => 'Edit own Community Hub posts',
            'community.manage_posts' => 'Manage Community Hub posts',
            'community.approve_posts' => 'Approve Community Hub posts',
            'community.lock_threads' => 'Lock Community Hub threads',
            'community.manage_comments' => 'Manage Community Hub comments',
            'community.escalate_to_platform' => 'Escalate Community Hub cases',
        ],
        'modules' => [
            'connector.manage_modules' => 'Manage modules',
        ],
        'educators' => [
            'connector.manage_educators' => 'Manage educators',
        ],
        'subscription' => [
            'connector.view_subscription' => 'View subscription',
        ],
    ],

    'entitlements' => [
        'seminars' => 'connector.seminars',
        'modules' => 'connector.modules',
        'educators' => 'connector.educators',
    ],
];
