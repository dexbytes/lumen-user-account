<?php

return [
	'key' => env('APP_KEY'),
	'cipher' => 'AES-256-CBC',
    'app_url' => env('APP_URL'),
    'directory_url' => env('DIRECTORY_URL'),
	'website_url' => env('WEBSITE_URL'),
    'locale' => 'en',
    'password_client_id' => env('PASSWORD_CLIENT_ID'),
    'password_client_secret' => env('PASSWORD_CLIENT_SECRET'),
    'property_listing_view_hours' => env('PROPERTY_LISTING_VIEW_HOURS'),
];