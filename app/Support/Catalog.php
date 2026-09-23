<?php

namespace App\Support;

use App\Models\Book;
use App\Models\Course;
use App\Models\DiscountCampaign;
use App\Models\ExpenseCategory;
use App\Models\LeadSource;
use App\Models\LessonTime;
use App\Models\PricePlan;
use App\Models\Room;

/** Oddiy ro'yxatlar (xona, dars vaqti, narx rejasi, manba, kurs) - bitta umumiy boshqaruv uchun ta'riflar. */
class Catalog
{
    public static function all(): array
    {
        return [
            'courses' => [
                'model' => Course::class, 'title' => 'Kurslar', 'singular' => 'Kurs',
                // 'permission' - qo'shish/tahrirlash/faolsizlantirish uchun; 'view_permission' - faqat ko'rish uchun
                // ham yetadi (v8: courses.view ruxsati shu yerda ishga tushirildi, ilgari umuman ishlatilmasdi).
                'permission' => 'courses.manage', 'view_permission' => 'courses.view',
                'fields' => ['name' => ['label' => 'Kurs nomi', 'type' => 'text', 'rules' => ['required', 'string', 'max:120']]],
                'unique' => ['name'],
            ],
            'rooms' => [
                'model' => Room::class, 'title' => 'Xonalar', 'singular' => 'Xona', 'permission' => 'settings.branch',
                'fields' => ['name' => ['label' => 'Xona nomi', 'type' => 'text', 'rules' => ['required', 'string', 'max:80']]],
                'unique' => ['name'],
            ],
            'lesson-times' => [
                'model' => LessonTime::class, 'title' => 'Dars vaqtlari', 'singular' => 'Dars vaqti', 'permission' => 'settings.branch',
                'fields' => [
                    'starts_at' => ['label' => 'Boshlanishi', 'type' => 'time', 'rules' => ['required', 'date_format:H:i']],
                    'ends_at' => ['label' => 'Tugashi', 'type' => 'time', 'rules' => ['required', 'date_format:H:i', 'after:starts_at']],
                ],
                'unique' => ['starts_at', 'ends_at'],
            ],
            'price-plans' => [
                'model' => PricePlan::class, 'title' => 'Narx rejalari', 'singular' => 'Narx rejasi', 'permission' => 'settings.branch',
                'fields' => [
                    'name' => ['label' => 'Nomi', 'type' => 'text', 'rules' => ['required', 'string', 'max:120']],
                    'amount' => ['label' => "Guruh narxi (so'm)", 'type' => 'money', 'rules' => ['required', 'integer', 'min:0', 'max:1000000000']],
                    'early_discount' => ['label' => "Oldindan to'lov chegirmasi (so'm)", 'type' => 'money', 'rules' => ['required', 'integer', 'min:0', 'lte:amount']],
                    'max_discount' => ['label' => "Admin bera oladigan chegirma (so'm)", 'type' => 'money', 'rules' => ['required', 'integer', 'min:0', 'lte:amount']],
                ],
                'unique' => ['name'],
            ],
            'campaigns' => [
                'model' => DiscountCampaign::class, 'title' => 'Aksiyalar', 'singular' => 'Aksiya', 'permission' => 'settings.branch',
                'fields' => [
                    'name' => ['label' => 'Aksiya nomi', 'type' => 'text', 'rules' => ['required', 'string', 'max:120']],
                    'amount' => ['label' => "Minimal to'lov (so'm)", 'type' => 'money', 'rules' => ['required', 'integer', 'min:1', 'max:1000000000']],
                    'bonus' => ['label' => "Bonus (so'm)", 'type' => 'money', 'rules' => ['required', 'integer', 'min:1', 'max:1000000000']],
                    'starts_on' => ['label' => 'Boshlanishi', 'type' => 'date', 'rules' => ['required', 'date']],
                    'ends_on' => ['label' => 'Tugashi', 'type' => 'date', 'rules' => ['required', 'date', 'after_or_equal:starts_on']],
                ],
                'unique' => ['name'],
            ],
            'books' => [
                'model' => Book::class, 'title' => 'Kitoblar', 'singular' => 'Kitob', 'permission' => 'settings.branch',
                'fields' => [
                    'name' => ['label' => 'Kitob nomi', 'type' => 'text', 'rules' => ['required', 'string', 'max:255']],
                    'url' => ['label' => 'Havola (URL)', 'type' => 'text', 'rules' => ['required', 'url', 'max:500']],
                ],
                'unique' => ['name'],
            ],
            'lead-sources' => [
                'model' => LeadSource::class, 'title' => "O'quvchi manbalari", 'singular' => 'Manba', 'permission' => 'settings.branch',
                'fields' => ['name' => ['label' => 'Manba nomi (Telegram, Instagram...)', 'type' => 'text', 'rules' => ['required', 'string', 'max:80']]],
                'unique' => ['name'],
            ],
            // v8 B3: kassadan "xarajat" so'rovlarini turkumlash uchun ixtiyoriy ro'yxat.
            'expense-categories' => [
                'model' => ExpenseCategory::class, 'title' => 'Xarajat turlari', 'singular' => 'Xarajat turi', 'permission' => 'settings.branch',
                'fields' => ['name' => ['label' => 'Nomi (masalan: Ijara, Kommunal, Ofis)', 'type' => 'text', 'rules' => ['required', 'string', 'max:80']]],
                'unique' => ['name'],
            ],
        ];
    }

    public static function get(string $key): array
    {
        return self::all()[$key] ?? abort(404);
    }
}
