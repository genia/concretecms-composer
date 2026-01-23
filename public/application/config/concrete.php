<?php

return [
    'updates' => [
        // Skip the automatic check of new Concrete versions availability
        'skip_core' => true,
    ],
    'debug' => [
        'hide_keys' => [
            // Hide database password and hostname in whoops output if supported
            '_ENV' => ['DB_PASSWORD', 'DB_HOSTNAME'],
            '_SERVER' => ['DB_PASSWORD', 'DB_HOSTNAME'],
        ]
    ],
    // Constrain uploaded images to prevent memory exhaustion during thumbnail generation
    'file_manager' => [
        'restrict_max_width' => 2000,  // Max width in pixels
        'restrict_max_height' => 2000, // Max height in pixels
    ],
];
