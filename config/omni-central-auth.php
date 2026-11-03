<?php

use App\Models\User;

return [
    'mode' => 'client',
    'enabled' => (bool) env('OMNI_AUTH_ENABLED', false),
    'client' => [
        'server_url' => rtrim(env('OMNI_CLIENT_SERVER_URL', 'https://sivmi.id'), '/'),
        'client_id' => env('OMNI_CLIENT_ID'),
        'client_secret' => env('OMNI_CLIENT_SECRET'),
        'redirect_uri' => env('OMNI_CLIENT_REDIRECT_URI') ?: rtrim(config('app.url'), '/').'/omni/callback',
        'home_url' => '/',
        'button_label' => 'Masuk dengan akun SIVMI',
        'signing_key' => env('OMNI_CENTRAL_SIGNING_KEY'),
    ],
    'dashboard' => ['enabled' => false],
    'load_migrations' => false,
    'user_model' => User::class,
];
