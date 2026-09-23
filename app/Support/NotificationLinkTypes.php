<?php

namespace App\Support;

/**
 * v12: sAdmin bildirishnoma yuborayotganda ilovada bosilganda qaysi sahifaga o'tishini
 * tanlaydi ("deep link"). Qiymat `Notification::data` (`link_type` + `link_id`) sifatida
 * saqlanadi va FCM push xabarining `data` qismida ham (barcha `data` maydonlari kabi)
 * mobil ilovaga string sifatida yetkaziladi - dasturchi shu ro'yxatga qarab navigatsiya
 * qiladi (to'liq kontrakt API_DOC.md'da).
 */
class NotificationLinkTypes
{
    public const NONE = 'none';

    /** @return array<string,string> kalit => admin panelida ko'rinadigan nomi */
    public static function options(): array
    {
        return [
            self::NONE => "Yo'q (oddiy xabar, hech qayerga o'tmaydi)",
            'group' => "Guruh sahifasi (link_id = guruh ID)",
            'lead' => "Lid sahifasi - Varonka (link_id = lid ID)",
            'student' => "O'quvchi sahifasi (link_id = o'quvchi ID)",
        ];
    }

    /** @return array<int,string> */
    public static function keys(): array
    {
        return array_keys(self::options());
    }

    /** @return array<int,string> ID talab qiladigan turlar */
    public static function typesRequiringId(): array
    {
        return array_values(array_diff(self::keys(), [self::NONE]));
    }
}
