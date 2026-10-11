<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo accounts
    |--------------------------------------------------------------------------
    |
    | When enabled, the login screen lists the fictional Harbor & Pine
    | accounts created by the demo seeder. Leave this off in production.
    |
    */

    'demo' => (bool) env('FLEETPILOT_DEMO', false),

    /*
    |--------------------------------------------------------------------------
    | Notification provider
    |--------------------------------------------------------------------------
    |
    | "log" records the rendered message and marks the job sent.
    | "mail" uses Laravel's mailer. A failed send never rolls back the
    | shipment transition that queued the job.
    |
    */

    'notification_provider' => env('NOTIFICATION_PROVIDER', 'log'),

    'sms_webhook_url' => env('SMS_WEBHOOK_URL'),

    'evidence_max_kilobytes' => 10240,

];
