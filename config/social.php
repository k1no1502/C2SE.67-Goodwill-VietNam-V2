<?php
return [
    'google' => [
        'client_id' => '832045696488-t9lf7l0lbvlq3uf1jr3dhm6m4sfseige.apps.googleusercontent.com',
        'client_secret' => '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',
        'redirect_uri' => 'http://localhost/GW_VN%20Ver%20Final/social-auth.php?provider=google',
        'scope' => 'openid email profile',
    ],
    'facebook' => [
        'app_id' => '1977211796537539',
        'app_secret' => 'd41935ea2d93cd5ce0c8be9481ad1582',
        'redirect_uri' => 'http://localhost/GW_VN%20Ver%20Final/social-auth.php?provider=facebook',
        'scope' => 'email,public_profile',
    ],
];
