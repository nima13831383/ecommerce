<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public storefront media disk
    |--------------------------------------------------------------------------
    |
    | Product and Blog media displayed by the storefront must use the same
    | explicitly configured public disk. The default is Laravel's standard
    | public disk, which is exposed through the storage:link symlink.
    |
    */
    'public_disk' => env('STOREFRONT_MEDIA_DISK', 'public'),
    'legacy_disk' => env('STOREFRONT_LEGACY_MEDIA_DISK', 'local'),
];
