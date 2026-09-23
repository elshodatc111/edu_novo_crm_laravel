<?php

namespace App\Support;

use App\Models\User;

/**
 * AI yordamchi uchun tizim qo'llanmasi (resources/ai/knowledge.md).
 * Bo'limlar foydalanuvchi roli va ruxsatlariga qarab saralanadi: foydalanuvchi o'zi qila olmaydigan
 * amallar haqida batafsil ko'rsatma olmaydi (faqat kimga murojaat qilish kerakligi aytiladi).
 */
class KnowledgeBase
{
    /** @return array<int, array{title:string, perms:array<int,string>, roles:array<int,string>, body:string}> */
    public static function sections(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $text = (string) file_get_contents(resource_path('ai/knowledge.md'));
        $cache = [];

        foreach (preg_split('/^## /m', $text) as $i => $chunk) {
            if ($i === 0 || trim($chunk) === '') {
                continue;
            }

            [$head, $body] = array_pad(explode("\n", $chunk, 2), 2, '');
            preg_match('/\{perm:\s*([^}]*)\}/', $head, $p);
            preg_match('/\{role:\s*([^}]*)\}/', $head, $r);

            $cache[] = [
                'title' => trim(preg_replace('/\{[^}]*\}/', '', $head)),
                'perms' => isset($p[1]) ? array_map('trim', explode('|', $p[1])) : [],
                'roles' => isset($r[1]) ? array_map('trim', explode('|', $r[1])) : [],
                'body' => trim($body),
            ];
        }

        return $cache;
    }

    public static function forUser(User $user): string
    {
        $out = [];

        foreach (self::sections() as $s) {
            $allowed = ($s['roles'] === [] || in_array($user->role->value, $s['roles'], true))
                && ($s['perms'] === [] || collect($s['perms'])->contains(fn ($perm) => $user->hasPermission($perm)));

            if ($allowed) {
                $out[] = "## {$s['title']}\n{$s['body']}";
            } elseif ($s['perms'] !== [] || $s['roles'] !== []) {
                $out[] = "## {$s['title']}\n(Bu bo'lim bu foydalanuvchining ruxsati doirasida emas. Uni so'rasa, ruxsati yo'qligini ayt va kimga murojaat qilishini ko'rsat: admin yoki sAdmin.)";
            }
        }

        return implode("\n\n", $out);
    }
}
