<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. Do not change this value
    | unless you have a need to customize the guards.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. If this value is null, personal access tokens do
    | not expire. This won't tweak the expiration of session cookies.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Personal Access Token
    |--------------------------------------------------------------------------
    |
    | This array allows customization of the personal access token feature
    | of Sanctum. These values determine the behavior and characteristics
    | of the tokens that are issued to users of your application.
    |
    */

    'personal_access_token' => [
        'name' => 'Personal Access Token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix is used when generating URLs for authentication routes.
    | You may change this prefix to anything you'd like, but typically
    | it should match your application's URL prefix for API routes.
    |
    */

    'prefix' => 'api',

];