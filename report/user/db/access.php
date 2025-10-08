<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'gradereport/user:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
