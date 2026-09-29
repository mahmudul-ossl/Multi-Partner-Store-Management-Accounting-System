<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    |
    | Bangladesh retail defaults. Override with environment variables.
    |
    */

    'currency' => [
        'code' => env('APP_CURRENCY', 'BDT'),
        'symbol' => env('APP_CURRENCY_SYMBOL', '৳'),
        'precision' => 18,
        'scale' => 2,
    ],

    'date_format' => env('APP_DATE_FORMAT', 'd-M-Y'),

    /*
    | Comma-separated proxy addresses, or "*". Empty means forwarded headers
    | are ignored. Read from config so `config:cache` still applies.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'seed' => [
        'super_admin_name' => env('SEED_SUPER_ADMIN_NAME', 'System Administrator'),
        'super_admin_email' => env('SEED_SUPER_ADMIN_EMAIL', 'superadmin@mpstore.test'),
        'super_admin_password' => env('SEED_SUPER_ADMIN_PASSWORD'),
        'demo_password' => env('SEED_DEMO_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Later phases
    |--------------------------------------------------------------------------
    |
    | Approval thresholds will live in the database (configurable multi-level
    | rules), not in this file. The ledger (chart_of_accounts, journal_entries,
    | journal_entry_lines) will be the source of truth for partner statements,
    | profit and loss, the balance sheet, and the trial balance. Inventory
    | on-hand is derived from stock_movements (weighted average). Financial
    | documents and stock history are reversed or voided; they are never
    | hard-deleted.
    |
    */

];
