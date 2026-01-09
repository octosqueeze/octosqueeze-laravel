<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OctoSqueeze API Key
    |--------------------------------------------------------------------------
    |
    | Your OctoSqueeze API key. Get one free at https://octosqueeze.com
    |
    */

    'api_key' => env('OCTOSQUEEZE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | API Endpoint
    |--------------------------------------------------------------------------
    |
    | The OctoSqueeze API endpoint. Only change for local development.
    |
    */

    'endpoint' => env('OCTOSQUEEZE_ENDPOINT', 'https://api.octosqueeze.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Compression Mode
    |--------------------------------------------------------------------------
    |
    | Default compression mode: 'size' (smallest), 'balanced', or 'quality'
    |
    */

    'mode' => env('OCTOSQUEEZE_MODE', 'balanced'),

    /*
    |--------------------------------------------------------------------------
    | Output Formats
    |--------------------------------------------------------------------------
    |
    | Formats to generate in addition to the original.
    | Supported: 'webp', 'avif', 'jpeg', 'png'
    |
    */

    'formats' => ['webp', 'avif'],

    /*
    |--------------------------------------------------------------------------
    | Auto Compress
    |--------------------------------------------------------------------------
    |
    | Automatically compress images on upload via queue job.
    |
    */

    'auto_compress' => env('OCTOSQUEEZE_AUTO_COMPRESS', true),

    /*
    |--------------------------------------------------------------------------
    | Queue Connection
    |--------------------------------------------------------------------------
    |
    | The queue connection to use for compression jobs.
    | Set to 'sync' for immediate processing (not recommended for production).
    |
    */

    'queue' => env('OCTOSQUEEZE_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The storage disk for compressed images.
    |
    */

    'disk' => env('OCTOSQUEEZE_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Hash Check
    |--------------------------------------------------------------------------
    |
    | Skip compression for files that have already been compressed (same hash).
    | Saves API credits when re-uploading identical files.
    |
    */

    'hash_check' => env('OCTOSQUEEZE_HASH_CHECK', true),

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    |
    | Disable SSL verification for local development.
    | Never disable in production!
    |
    */

    'verify_ssl' => env('OCTOSQUEEZE_VERIFY_SSL', true),

];
