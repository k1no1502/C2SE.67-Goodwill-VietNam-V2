<?php
return [
    // Gemini API key for chatbot (Google AI Studio)
    'gemini_api_key' => 'AIzaSyAtXi17Js7QdelHPQM9fSzHI0hUdt0FG3k',

    // Google Maps Platform API key (enable Places API; billing required)
    // How to set:
    // 1) Create API key in Google Cloud Console
    // 2) Enable "Places API" and "Maps JavaScript API"
    // 3) Restrict key to your domain (e.g. http://localhost/* during dev)
    'maps_api_key' => '',

    // Google Drive API Configuration
    'drive' => [
        'enabled' => false, // Set to true khi đã cấu hình xong
        'keyfile' => __DIR__ . '/google-drive-key.json', // Path tới file JSON key
        'donation_folder_id' => '', // ID của folder trên Google Drive để lưu hình ảnh
        'auto_backup' => true, // Tự động backup hình ảnh lên Google Drive
    ]
];

