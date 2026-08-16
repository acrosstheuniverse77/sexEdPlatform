<?php

return [
    'verification_statuses' => [
        'not_required' => 'Verified by Guardian Declaration',
        'pending' => 'Verification Required',
        'under_review' => 'Pending Review',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        'resubmission_required' => 'Resubmission Required',
        'revoked' => 'Revoked',
        'reserved' => 'Reserved for Verification',
    ],

    'document_types' => [
        'adoption_order' => 'Adoption Order',
        'court_order' => 'Court Order',
        'legal_guardianship' => 'Legal Guardianship Documentation',
        'official_appointment' => 'Official Appointment Documentation',
        'supporting_legal_document' => 'Supporting Legal Documentation',
        'other' => 'Other Accepted Documentation',
    ],

    'types' => [
        'adoptive_parent' => [
            'requires_verification' => true,
            'document_types' => ['adoption_order', 'court_order', 'supporting_legal_document', 'other'],
        ],
        'foster_parent' => [
            'requires_verification' => true,
            'document_types' => ['court_order', 'official_appointment', 'supporting_legal_document', 'other'],
        ],
        'legal_guardian' => [
            'requires_verification' => true,
            'document_types' => ['court_order', 'legal_guardianship', 'official_appointment', 'other'],
        ],
        'court_appointed_guardian' => [
            'requires_verification' => true,
            'document_types' => ['court_order', 'official_appointment', 'other'],
        ],
        'other' => [
            'requires_verification' => true,
            'document_types' => ['supporting_legal_document', 'other'],
        ],
    ],

    'rejection_reasons' => [
        'unclear_document' => 'Document is unclear',
        'incomplete_document' => 'Document is incomplete',
        'incorrect_document_type' => 'Incorrect document type',
        'cannot_verify' => 'Document cannot be verified',
        'expired_document' => 'Document appears expired',
        'insufficient_support' => 'Information does not sufficiently support the relationship',
        'missing_required_information' => 'Missing required information',
        'other' => 'Other',
    ],
];
