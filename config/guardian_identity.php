<?php

return [
    'id_types' => [
        'national_id' => ['label' => 'National ID (PhilSys)', 'requires_back' => false],
        'passport' => ['label' => 'Passport', 'requires_back' => false],
        'drivers_license' => ['label' => "Driver's License", 'requires_back' => true],
        'umid' => ['label' => 'UMID', 'requires_back' => true],
        'philhealth' => ['label' => 'PhilHealth ID', 'requires_back' => false],
        'postal_id' => ['label' => 'Postal ID', 'requires_back' => true],
        'senior_citizen_id' => ['label' => 'Senior Citizen ID', 'requires_back' => true],
        'pwd_id' => ['label' => 'PWD ID', 'requires_back' => true],
        'prc_id' => ['label' => 'PRC ID', 'requires_back' => true],
        'voters_id' => ['label' => "Voter's ID (if applicable)", 'requires_back' => true],
        'other' => ['label' => 'Other', 'requires_back' => true],
    ],
];
