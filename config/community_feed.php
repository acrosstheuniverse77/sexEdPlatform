<?php

return [
    'post_types' => [
        'announcement' => 'Announcement',
        'event' => 'Event',
        'resource' => 'Educational Resource',
        'moderated_question' => 'Q&A',
        'discussion_prompt' => 'Discussion',
    ],
    'post_statuses' => [
        'draft' => 'Draft',
        'pending_review' => 'Pending Review',
        'published' => 'Published',
        'hidden' => 'Hidden',
        'locked' => 'Locked',
        'removed' => 'Removed',
        'escalated' => 'Escalated',
        'archived' => 'Archived',
    ],
    'comment_statuses' => [
        'visible' => 'Visible',
        'pending_review' => 'Pending Review',
        'hidden' => 'Hidden',
        'removed' => 'Removed',
        'escalated' => 'Escalated',
    ],
    'reactions' => [
        'learned' => 'Learned',
        'helpful' => 'Helpful',
        'question' => 'Question',
        'support' => 'Support',
    ],
    'report_reasons' => [
        'inappropriate_content' => 'Inappropriate Content',
        'spam' => 'Spam',
        'misleading_information' => 'Misleading Information',
        'harassment' => 'Harassment or Abusive Content',
        'off_topic' => 'Off-topic Content',
        'community_guidelines_violation' => 'Community Guidelines Violation',
        'duplicate_content' => 'Duplicate Content',
        'other' => 'Other',
    ],
    'link_allowlist_hosts' => [
        'doh.gov.ph',
        'who.int',
        'unicef.org',
    ],
    'rate_limits' => [
        'posts_per_minute' => 3,
        'comments_per_minute' => 6,
        'reports_per_minute' => 6,
    ],
    'default_suspended_connector_visibility' => 'read_only',
];
