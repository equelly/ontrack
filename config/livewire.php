<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Morph Algorithm
    |--------------------------------------------------------------------------
    |
    | 'alpine-morph' — быстрее morphdom в 3-5 раз на больших DOM с @foreach.
    | Если будут визуальные баги — попробуй 'dom-diff'.
    |
    */
    'morph' => [
        'algorithm' => 'alpine-morph',
    ],

    'lazy_placeholder' => null,

    'hidden_properties' => [
        'middleware',
        'rules',
        'validationAttributes',
        'validationCustomMessages',
    ],

    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK', config('filesystems.default')),
        'rules' => null,
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'avi', 'mov', 'mp4', 'm4a', 'mp3', 'wav',
            'jpeg', 'jpg', 'webp', 'pdf',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];