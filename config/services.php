<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'tts' => [
        'base_url' => env('TTS_BASE_URL', 'https://motivation.mywebsolutions.co.in/api'),
        'api_key' => env('TTS_API_KEY'),
    ],

    'azure_tts' => [
        'key'    => env('AZURE_KEY', ''),
        'region' => env('AZURE_REGION', 'centralindia'),
    ],

    'vastai_xtts' => [
        'url'    => env('VASTAI_XTTS_URL', ''),
        'secret' => env('VASTAI_XTTS_SECRET', ''),
    ],

    'sms_gateway' => [
        'secret' => env('SMS_GATEWAY_SECRET', ''),
        'ws_url' => env('SMS_GATEWAY_WS_URL', 'wss://mentalfitness.store:8091'),
        'pending_ttl' => (int) env('SMS_GATEWAY_PENDING_TTL', 900),
        'max_pending' => (int) env('SMS_GATEWAY_MAX_PENDING', 25),
    ],

    'whatsapp' => [
        'enabled' => env('WHATSAPP_OTP_ENABLED', true),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v25.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),
        'app_secret' => env('WHATSAPP_APP_SECRET', ''),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', ''),
        'template' => env('WHATSAPP_OTP_TEMPLATE', 'mental_fitness_single_param'),
        'template_language' => env('WHATSAPP_OTP_TEMPLATE_LANGUAGE', 'en'),
        'template_body_parameter_name' => env('WHATSAPP_OTP_TEMPLATE_BODY_PARAMETER_NAME', ''),
        'template_body_parameter_names' => env('WHATSAPP_OTP_TEMPLATE_BODY_PARAMETER_NAMES', ''),
        'template_login_context' => env('WHATSAPP_OTP_TEMPLATE_LOGIN_CONTEXT', ''),
        'otp_context' => env(
            'WHATSAPP_OTP_CONTEXT',
            'https://mentalfitness.store Login/Register. The OTP is valid for 10 minutes. Call support if you did not perform this request.'
        ),
        'template_has_copy_code_button' => env('WHATSAPP_OTP_TEMPLATE_HAS_COPY_CODE_BUTTON', true),
        'template_button_sub_type' => env('WHATSAPP_OTP_TEMPLATE_BUTTON_SUB_TYPE', 'url'),
        'timeout' => (int) env('WHATSAPP_HTTP_TIMEOUT', 10),
    ],

];
