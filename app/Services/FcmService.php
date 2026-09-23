<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * v11: Firebase Cloud Messaging (HTTP v1 API) orqali push-bildirishnoma yuborish.
 *
 * Tashqi Composer paketsiz ishlaydi: xizmat hisobi (service account) JSON kaliti orqali
 * o'zimiz RS256 bilan imzolangan JWT tuzib, Google OAuth2 serveridan vaqtinchalik access
 * token olamiz (standart "JWT bearer" oqimi), so'ng shu token bilan FCM'ga so'rov yuboramiz.
 *
 * Sozlash: .env'ga FIREBASE_PROJECT_ID va FIREBASE_CREDENTIALS_PATH (xizmat hisobi JSON fayli
 * yo'li, masalan storage/app/firebase-service-account.json) yoziladi. Ikkalasi ham bo'lmasa,
 * xizmat "sozlanmagan" deb hisoblanadi va xabar jim o'tkazib yuboriladi (asosiy jarayon
 * to'xtamaydi) - API_DOC.md'da to'liq sozlash ko'rsatmasi bor.
 */
class FcmService
{
    public function isConfigured(): bool
    {
        $path = config('services.fcm.credentials');

        return filled(config('services.fcm.project_id')) && filled($path) && is_file($path);
    }

    /**
     * Bitta qurilma tokeniga xabar yuboradi.
     *
     * @return array{ok:bool, unregistered:bool, error:?string}
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'unregistered' => false, 'error' => "Firebase sozlanmagan (.env)."];
        }

        try {
            $accessToken = $this->accessToken();
        } catch (RuntimeException $e) {
            return ['ok' => false, 'unregistered' => false, 'error' => $e->getMessage()];
        }

        $projectId = config('services.fcm.project_id');

        $response = Http::withToken($accessToken)->timeout(15)->post(
            "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
            [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                ],
            ]
        );

        if ($response->successful()) {
            return ['ok' => true, 'unregistered' => false, 'error' => null];
        }

        // FCM o'chirilgan/eskirgan tokenlarni shu kod bilan qaytaradi - shunday tokenni bazadan o'chirib qo'yamiz.
        $status = $response->json('error.status');
        $unregistered = in_array($status, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true);

        return ['ok' => false, 'unregistered' => $unregistered, 'error' => mb_substr((string) $response->body(), 0, 500)];
    }

    private function accessToken(): string
    {
        $path = config('services.fcm.credentials');
        $credentials = json_decode((string) file_get_contents($path), true);

        if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException("Firebase xizmat hisobi fayli noto'g'ri (client_email/private_key topilmadi).");
        }

        return Cache::remember('fcm_access_token_'.md5($credentials['client_email']), now()->addMinutes(50), function () use ($credentials) {
            $now = time();
            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $signature = '';
            $ok = openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

            if (! $ok) {
                throw new RuntimeException("Firebase kalitini imzolab bo'lmadi (private_key noto'g'ri).");
            }

            $jwt = $header.'.'.$claims.'.'.$this->base64UrlEncode($signature);

            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException("Google OAuth2'dan token olib bo'lmadi: ".mb_substr((string) $response->body(), 0, 300));
            }

            return $response->json('access_token');
        });
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
