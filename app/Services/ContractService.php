<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\GroupStudent;
use App\Support\Format;

/**
 * v8 B2: o'quv xizmati shartnomasi. Matn filialda tahrirlanadi (bo'sh bo'lsa - standart namuna),
 * o'quvchi guruhga qo'shilganda ({{@see GroupStudent}}) haqiqiy ma'lumotlar bilan to'ldiriladi.
 * Diqqat: bu - avtomatik to'ldiriladigan NAMUNA, imzolashdan oldin yuristga ko'rsatish tavsiya etiladi.
 */
class ContractService
{
    public const PLACEHOLDERS = [
        '{oquvchi_fio}' => "O'quvchi F.I.O.", '{telefon}' => "O'quvchi telefoni", '{guruh_nomi}' => 'Guruh nomi',
        '{kurs_nomi}' => 'Kurs nomi', '{dars_kunlari}' => 'Dars kunlari', '{dars_vaqti}' => 'Dars vaqti',
        '{xona}' => 'Xona', '{boshlanish_sanasi}' => "Kurs boshlanish sanasi", '{tugash_sanasi}' => 'Kurs tugash sanasi',
        '{narx}' => 'Kurs narxi', '{filial_nomi}' => 'Filial nomi', '{filial_manzili}' => 'Filial manzili',
        '{filial_telefon}' => 'Filial telefoni', '{sana}' => "Shartnoma tuzilgan sana", '{shartnoma_raqami}' => 'Shartnoma raqami',
        '{stir}' => 'Filial STIR', '{direktor_lavozimi}' => 'Direktor lavozimi', '{direktor_ismi}' => "Direktor F.I.O.",
    ];

    public function __construct(private SmsService $sms) {}

    public static function defaultTemplate(): string
    {
        return <<<'TXT'
        TA'LIM XIZMATLARI KO'RSATISH SHARTNOMASI № {shartnoma_raqami}

        {filial_manzili}                                          {sana}

        Bir tomondan "{filial_nomi}" o'quv markazi (STIR: {stir}), {direktor_lavozimi}
        {direktor_ismi} shaxsida (bundan buyon - "Markaz"), ikkinchi tomondan
        {oquvchi_fio} (tel: {telefon}) yoki uning qonuniy vakili/ota-onasi
        (bundan buyon - "Buyurtmachi") quyidagi shartnoma tuzdilar:

        1. SHARTNOMA PREDMETI
        1.1. Markaz Buyurtmachi farzandi/o'zi {oquvchi_fio} ("O'quvchi") uchun
             "{kurs_nomi}" kursi bo'yicha "{guruh_nomi}" guruhida ta'lim
             xizmatlarini ko'rsatadi.
        1.2. Dars jadvali: {dars_kunlari}, soat {dars_vaqti}, xona: {xona}.
        1.3. Kurs davomiyligi: {boshlanish_sanasi} dan {tugash_sanasi} gacha.

        2. XIZMAT NARXI VA TO'LOV TARTIBI
        2.1. Kurs narxi {narx} ni tashkil etadi.
        2.2. To'lov oldindan, Markazning ichki tartibiga muvofiq amalga oshiriladi.
        2.3. Chegirma yoki aksiya qo'llanilgan bo'lsa, uning shartlari to'lov
             kvitansiyasida alohida ko'rsatiladi.
        2.4. To'lov kechiktirilsa, Markaz O'quvchini navbatdagi darsga qo'ymaslik
             huquqiga ega.

        3. TOMONLARNING HUQUQ VA MAJBURIYATLARI
        3.1. Markaz: belgilangan jadval bo'yicha sifatli ta'lim xizmati ko'rsatadi,
             O'quvchi davomati va natijalari haqida Buyurtmachini xabardor qiladi.
        3.2. Buyurtmachi: to'lovni o'z vaqtida amalga oshiradi, O'quvchining
             darsga muntazam qatnashishini ta'minlaydi.
        3.3. Tomonlar bir-birining shaxsiy ma'lumotlarini uchinchi shaxslarga
             bermaslikka majburdirlar.

        4. SHARTNOMANI BEKOR QILISH
        4.1. Buyurtmachi istalgan vaqtda shartnomani bir tomonlama bekor qilishi
             mumkin; hisob-kitob Markazning ichki qoidalariga muvofiq amalga
             oshiriladi.
        4.2. Markaz to'lov uzoq muddat kechiktirilganda shartnomani bekor qilish
             huquqiga ega.

        5. YAKUNIY QOIDALAR
        5.1. Shartnoma ikki nusxada, har ikki tomon uchun bir xil yuridik kuchga
             ega holda tuzildi.
        5.2. Tomonlarning imzolari:

           MARKAZ:                              BUYURTMACHI:
           {filial_nomi}                        {oquvchi_fio}
           Manzil: {filial_manzili}             Tel: {telefon}
           Tel: {filial_telefon}                Hujjat: _______________

           _______________ (imzo, muhr)         _______________ (imzo)
        TXT;
    }

    public function template(Branch $branch): string
    {
        return filled($branch->contract_template) ? $branch->contract_template : self::defaultTemplate();
    }

    /** Shartnoma raqami - filial kodi + yozuv ID (barqaror, qayta chop etilganda o'zgarmaydi). */
    public function number(GroupStudent $enrollment): string
    {
        return strtoupper($enrollment->branch?->code ?? 'X').'-'.str_pad((string) $enrollment->id, 5, '0', STR_PAD_LEFT);
    }

    public function render(GroupStudent $enrollment): string
    {
        $enrollment->loadMissing(['group.course', 'group.room', 'group.lessonTime', 'branch', 'student']);
        $group = $enrollment->group;
        $branch = $enrollment->branch;
        $at = $enrollment->created_at ?? now();

        $vars = [
            'oquvchi_fio' => $enrollment->student->name,
            'telefon' => Format::prettyPhone($enrollment->student->phone),
            'guruh_nomi' => $group->name,
            'kurs_nomi' => $group->course?->name ?? '',
            'dars_kunlari' => $group->schedule->label(),
            'dars_vaqti' => $group->lessonTime?->label ?? '',
            'xona' => $group->room?->name ?? '',
            'boshlanish_sanasi' => $group->starts_on->format('d.m.Y'),
            'tugash_sanasi' => $group->ends_on->format('d.m.Y'),
            'narx' => Format::money($group->price),
            'filial_nomi' => $branch->name,
            'filial_manzili' => $branch->address ?? '',
            'filial_telefon' => $branch->phone ?? '',
            'sana' => $at->format('d.m.Y'),
            'shartnoma_raqami' => $this->number($enrollment),
            'stir' => $branch->stir ?? '',
            'direktor_lavozimi' => $branch->director_title ?? 'Direktor',
            'direktor_ismi' => $branch->director_name ?? '',
        ];

        return $this->sms->render($this->template($branch), $vars);
    }
}
