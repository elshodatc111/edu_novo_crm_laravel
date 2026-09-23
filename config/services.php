<?php

return [

    // Eskiz.uz SMS xizmati. Filialning o'z akkaunti kiritilmagan bo'lsa, umumiy akkaunt ishlatiladi.
    'eskiz' => [
        'base_url' => env('ESKIZ_BASE_URL', 'https://notify.eskiz.uz/api'),
        'email' => env('ESKIZ_EMAIL'),
        'password' => env('ESKIZ_PASSWORD'),
        'from' => env('ESKIZ_FROM', '4546'),
    ],

    // OpenAI (AI yordamchi). Kalit faqat .env faylida saqlanadi.
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'daily_limit' => (int) env('OPENAI_DAILY_LIMIT', 200),
    ],

    // v11: Firebase Cloud Messaging (mobil ilova push-bildirishnomasi). Xizmat hisobi
    // JSON kaliti faqat serverda saqlanadi, hech qachon zip/git'ga kirmaydi.
    'fcm' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS_PATH', storage_path('app/firebase-service-account.json')),
    ],

];
