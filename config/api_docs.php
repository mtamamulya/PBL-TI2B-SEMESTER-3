<?php

return [
    // false = halaman /api/docs hanya terbuka saat APP_ENV=local.
    // true  = terbuka juga di environment lain (mis. server demo).
    'publik' => (bool) env('API_DOCS_PUBLIK', false),

    // Lokasi berkas kontrak.
    'file' => base_path('docs/openapi.yaml'),
];
