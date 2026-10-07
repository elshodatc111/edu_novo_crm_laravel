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

        Bir tomondan "{filial_nomi}" o'quv markazi (STIR: {stir}), {direktor_lavozimi} {direktor_ismi} shaxsida, Nizom/ishonchnoma asosida harakat qiluvchi (bundan buyon matnda - "Markaz"), ikkinchi tomondan {oquvchi_fio} (tel: {telefon}) yoki uning qonuniy vakili (ota-onasi, vasiysi) (bundan buyon matnda - "Buyurtmachi") O'zbekiston Respublikasi Fuqarolik kodeksi, "Ta'lim to'g'risida"gi Qonun va boshqa amaldagi qonun hujjatlariga muvofiq quyidagi shartnomani tuzdilar:

        1. SHARTNOMA PREDMETI
        1.1. Markaz Buyurtmachi tomonidan belgilangan shaxsga ({oquvchi_fio}, bundan buyon matnda - "O'quvchi") "{kurs_nomi}" kursi bo'yicha "{guruh_nomi}" guruhida ta'lim xizmatlarini ko'rsatish majburiyatini oladi, Buyurtmachi esa xizmat uchun belgilangan haq to'lash majburiyatini oladi.
        1.2. Dars jadvali: {dars_kunlari}, soat {dars_vaqti}, xona: {xona}.
        1.3. Kurs davomiyligi: {boshlanish_sanasi} dan {tugash_sanasi} gacha.
        1.4. Ta'lim xizmati o'quv dasturi va guruh jadvaliga muvofiq, Markazning o'quv-metodik talablari asosida ko'rsatiladi. Ta'lim natijasi (imtihon, sertifikat, ma'lum ball yoki til darajasi) kafolatlanmaydi; u O'quvchining qatnashishi, mustaqil tayyorgarligi va qobiliyatiga ham bog'liq.

        2. XIZMAT NARXI VA TO'LOV TARTIBI
        2.1. Kurs narxi {narx} ni tashkil etadi.
        2.2. To'lov Markaz belgilagan tartibda (naqt yoki plastik karta orqali) oldindan amalga oshiriladi. Har bir to'lov uchun Buyurtmachiga chek (kvitansiya) beriladi; to'lovni tasdiqlovchi hujjat shu chek hisoblanadi, uni saqlab qo'yish tavsiya etiladi.
        2.3. Markaz qo'llaydigan chegirma, aksiya yoki bonus shartlari to'lov paytida e'lon qilinadi va chekda alohida ko'rsatiladi. Chegirma faqat shartlari to'liq bajarilgan taqdirda amal qiladi va boshqa takliflar bilan avtomatik qo'shilmaydi.
        2.4. To'lov muddati kechiktirilsa, Markaz Buyurtmachini ogohlantirib, qarz to'langunga qadar O'quvchini navbatdagi darslarga qo'ymaslik huquqiga ega. Bunda o'tkazib yuborilgan darslar uchun to'lov qayta hisoblanmaydi.
        2.5. Kurs narxi shartnoma tuzilgan paytdagi guruh narxiga muvofiq belgilanadi va shu guruh doirasida o'zgarmaydi. Markaz kelgusi guruhlar uchun narxlarni o'zgartirishi mumkin.
        2.6. To'lov noto'g'ri yoki ortiqcha kiritilgan taqdirda, Buyurtmachi Markazga murojaat qiladi; xato Markaz tomonidan aniqlanib, tuzatiladi yoki ortiqcha summa qaytariladi.

        3. DARS JARAYONI VA DAVOMAD
        3.1. Dars jadvali guruh jadvaliga muvofiq amalga oshiriladi. Markaz jadvalni (vaqt, xona, o'qituvchi) o'zgartirishi mumkin, bu haqda Buyurtmachini oldindan (kamida 1 kun avval) telefon yoki xabar orqali ogohlantiradi.
        3.2. O'qituvchi kasallik yoki boshqa uzrli sabab bilan darsga kela olmasa, Markaz darsni boshqa o'qituvchi bilan o'tkazadi yoki darsni qo'shimcha vaqtda qaytarib beradi.
        3.3. O'quvchi darsni sababsiz qoldirsa, o'tkazib yuborilgan dars qayta o'tilmaydi va to'lov qaytarilmaydi. Uzrli sabab (kasallik, oilaviy holat va h.k.) bilan qoldirilgan darslar bo'yicha Buyurtmachi Markazga oldindan yoki darsdan keyingi 24 soat ichida xabar berishi shart; bunday hollarda Markaz imkoniyat darajasida darsni boshqa guruhda yoki qo'shimcha vaqtda o'tishni taklif qiladi.
        3.4. Rasmiy dam olish va bayram kunlariga to'g'ri kelgan darslar jadvalga muvofiq o'tkazilmaydi; ular kurs davomiyligiga kiritiladi yoki Markaz tomonidan boshqa kunda o'tkaziladi.
        3.5. O'quvchining davomati va o'zlashtirishi Markaz tomonidan yuritiladi; Buyurtmachi so'rasa, bu haqda ma'lumot beriladi.

        4. TOMONLARNING HUQUQ VA MAJBURIYATLARI
        4.1. Markaz majburiyatlari: (a) belgilangan jadval bo'yicha sifatli va malakali o'qituvchilar bilan ta'lim berish; (b) xavfsiz va tegishli sanitariya talablariga javob beradigan o'quv muhitini ta'minlash; (c) O'quvchining davomati va natijalari haqida Buyurtmachini xabardor qilish; (d) Buyurtmachining murojaat va shikoyatlarini ko'rib chiqish.
        4.2. Markaz huquqlari: (a) o'quv jarayonini, jadvalni va dars usullarini mustaqil belgilash; (b) to'lov shartlari buzilganda 2.4-bandda ko'rsatilgan choralarni qo'llash; (c) Markaz ichki tartib qoidalariga rioya qilinmasa, O'quvchiga ogohlantirish berish va qoidabuzarlik takrorlansa, shartnomani bekor qilish.
        4.3. Buyurtmachi majburiyatlari: (a) kurs uchun to'lovni belgilangan muddatda to'lash; (b) O'quvchining darslarga o'z vaqtida va muntazam qatnashishini ta'minlash; (c) Markazning ichki tartib qoidalariga rioya qilish va O'quvchining ularga amal qilishini ta'minlash; (d) telefon raqami va boshqa aloqa ma'lumotlari o'zgarganda Markazni xabardor qilish; (e) Markaz mol-mulkiga yetkazilgan zarar uchun qonunchilikka muvofiq javob berish.
        4.4. Buyurtmachi huquqlari: (a) kurs jarayoni, davomat va natijalar haqida ma'lumot olish; (b) Markazga taklif va shikoyatlar bilan murojaat qilish; (c) shartnomani 5-bo'limda belgilangan tartibda bekor qilish.
        4.5. O'quvchi darslarda tartib-intizomga rioya qilishi, o'qituvchi va boshqa o'quvchilarga hurmat bilan munosabatda bo'lishi, o'quv xonasida tozalik va xavfsizlik qoidalariga amal qilishi shart. Markaz O'quvchining shaxsiy buyumlari saqlanishi uchun javobgar emas, bu buyumlarni o'zi kuzatib turadi.
        4.6. Voyaga yetmagan O'quvchining darsga kelishi va ketishi, shuningdek dars vaqtidan tashqaridagi nazorat Buyurtmachining javobgarligida. Markaz dars vaqtida (jadvalda belgilangan vaqt davomida) O'quvchining xavfsizligi uchun javobgar.

        5. SHARTNOMANI BEKOR QILISH VA PULNI QAYTARISH
        5.1. Buyurtmachi shartnomani istalgan vaqtda bekor qilishi mumkin; buning uchun Markazga kamida 3 kun oldin yozma yoki og'zaki (telefon/xabar orqali) murojaat qiladi.
        5.2. Shartnoma bekor qilinganda hisob-kitob quyidagicha amalga oshiriladi: Markaz O'quvchi qatnashgan va jadval bo'yicha o'tilgan darslar uchun haqni ushlab qoladi, o'tilmagan darslar uchun to'langan qismi esa Buyurtmachiga qaytariladi (kurs narxi umumiy darslar soniga bo'linib, bitta dars qiymati aniqlanadi). Qaytarish shu murojaat kunidan boshlab 10 ish kuni ichida amalga oshiriladi.
        5.3. Kurs boshlanmasdan oldin (birinchi darsgacha) shartnoma bekor qilinsa, to'langan summa to'liq qaytariladi.
        5.4. Markaz kursni o'tkaza olmasa yoki guruh yig'ilmasa, Markaz Buyurtmachiga boshqa guruhni yoki to'langan summani to'liq qaytarishni taklif qiladi.
        5.5. Markaz quyidagi hollarda shartnomani bir tomonlama bekor qilish huquqiga ega: (a) to'lov uzoq muddat (14 kundan ortiq) to'lanmasa; (b) O'quvchi ichki tartib qoidalarini qo'pol yoki takror buzsa va ogohlantirishlarga e'tibor bermasa; (c) Buyurtmachi ma'lumotlarni qasddan yolg'on bergan bo'lsa. Bunda 5.2-bandga muvofiq hisob-kitob qilinadi.
        5.6. Chegirma, aksiya yoki bonus asosida olingan imtiyozlar shartnoma muddatidan oldin bekor qilingan taqdirda, aksiya shartlariga muvofiq qayta hisoblanishi mumkin; bu haqda to'lov paytida ogohlantiriladi.

        6. SHAXSIY MA'LUMOTLAR
        6.1. Buyurtmachi O'quvchining F.I.O., telefon raqami, davomati, o'zlashtirishi va to'lov ma'lumotlari Markaz tomonidan faqat ta'lim xizmatini ko'rsatish, hisob-kitob va aloqa maqsadlarida to'planishi, saqlanishi va ishlatilishiga "Shaxsga doir ma'lumotlar to'g'risida"gi Qonunga muvofiq rozilik beradi.
        6.2. Markaz bu ma'lumotlarni qonunda nazarda tutilgan hollardan tashqari uchinchi shaxslarga bermaydi va ularni himoya qilish choralarini ko'radi.
        6.3. Buyurtmachi Markazning to'lov, qarz, jadval o'zgarishi va tadbirlar haqidagi SMS yoki boshqa xabarlarini olishga rozilik beradi.
        6.4. Markaz o'quv jarayonidan olingan foto va video materiallardan faqat Buyurtmachining alohida roziligi bilan reklama va ijtimoiy tarmoqlarda foydalanadi. Rozilik berilmagan bo'lsa, buni Buyurtmachi Markazga yozma yoki og'zaki bildiradi.

        7. TOMONLARNING JAVOBGARLIGI VA FORS-MAJOR
        7.1. Shartnoma shartlari buzilganda tomonlar O'zbekiston Respublikasi qonunchiligiga muvofiq javobgar bo'ladilar.
        7.2. Tomonlar o'zlariga bog'liq bo'lmagan favqulodda va oldini olib bo'lmaydigan holatlar (tabiiy ofat, epidemiya, karantin, davlat organlarining qaroriga ko'ra faoliyatning to'xtatilishi va h.k.) sababli majburiyatlarini bajara olmasalar, javobgarlikdan ozod etiladilar. Bunday holatda Markaz darslarni masofaviy (onlayn) shaklga o'tkazishi yoki keyinroq o'tkazishi mumkin; imkon bo'lmasa, o'tilmagan darslar uchun to'lov 5.2-bandga muvofiq qaytariladi.
        7.3. Markaz ta'lim natijasi, O'quvchining test, imtihon yoki qabulda erishgan natijasi uchun javobgar emas, Markazning o'z majburiyatlarini buzishi holati bundan mustasno.

        8. NIZOLARNI HAL QILISH
        8.1. Shartnoma bo'yicha kelib chiqadigan nizolar avvalo muzokaralar yo'li bilan hal qilinadi. Shikoyat Markazga yozma yoki og'zaki bildiriladi va 10 kun ichida ko'rib chiqiladi.
        8.2. Kelishuvga erishilmasa, nizo O'zbekiston Respublikasining amaldagi qonunchiligiga muvofiq Markaz joylashgan hududdagi vakolatli sudda hal qilinadi.

        9. SHARTNOMA MUDDATI VA YAKUNIY QOIDALAR
        9.1. Shartnoma imzolangan kundan kuchga kiradi va kurs yakunlanguncha ({tugash_sanasi} gacha) hamda tomonlar o'z majburiyatlarini to'liq bajarmaguncha amal qiladi.
        9.2. Shartnomaga o'zgartirish va qo'shimchalar faqat tomonlarning yozma kelishuvi bilan kiritiladi. Guruh jadvali va xona o'zgarishi 3.1-bandga muvofiq alohida kelishuvsiz amalga oshiriladi.
        9.3. Shartnoma ikki nusxada, har ikki tomon uchun bir xil yuridik kuchga ega holda tuzildi: bir nusxasi Markazda, ikkinchisi Buyurtmachida saqlanadi.
        9.4. Buyurtmachi shartnoma matni bilan tanishganini, uning barcha shartlarini o'qib tushunganini va ularga rozi ekanligini o'z imzosi bilan tasdiqlaydi.

        10. TOMONLARNING REKVIZITLARI VA IMZOLARI

        MARKAZ:                                BUYURTMACHI:
        "{filial_nomi}" o'quv markazi          {oquvchi_fio}
        STIR: {stir}                           Tel: {telefon}
        Manzil: {filial_manzili}               Hujjat (pasport/ID): _______________
        Tel: {filial_telefon}                  Yashash manzili: ___________________

        {direktor_lavozimi}                    Buyurtmachi (imzo):
        {direktor_ismi}

        _______________ (imzo, muhr)          _______________ (imzo)
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
            'stir' => filled($branch->stir) ? $branch->stir : '____________',
            'direktor_lavozimi' => $branch->director_title ?? 'Direktor',
            'direktor_ismi' => filled($branch->director_name) ? $branch->director_name : '______________________',
        ];

        return $this->sms->render($this->template($branch), $vars);
    }

    /**
     * Shartnoma matnini (oddiy matn) rasmiy hujjat ko'rinishidagi xavfsiz HTML'ga aylantiradi:
     *  - birinchi qator - sarlavha (markazda, qalin);
     *  - «1. SARLAVHA» (faqat bosh harflar) - bo'lim sarlavhasi;
     *  - birinchi bo'limdan oldingi, 3 va undan ortiq bo'sh joy bilan ajratilgan qator (manzil ... sana) - chap/o'ngga;
     *  - birinchi bo'limdan keyingi shunday qatorlar (imzo bloki) - ustunli jadval;
     *  - qolgan qatorlar - ikki chetga tekislangan bandlar.
     * Barcha matn escape qilinadi (matn filialda tahrirlanadi va o'quvchi nomlari qo'shiladi).
     */
    public static function toHtml(string $text): string
    {
        $lines = preg_split('/\R/u', trim($text)) ?: [];
        $out = '';
        $titleDone = false;
        $seenHeading = false;
        $table = []; // jadval qatorlari: ustunlar ro'yxati yoki null (oraliq)

        $flush = function () use (&$out, &$table) {
            if ($table === []) {
                return;
            }
            $cols = max(array_map(fn ($r) => $r === null ? 1 : count($r), $table));
            $out .= '<table class="sign"><tbody>';
            foreach ($table as $row) {
                if ($row === null) {
                    $out .= '<tr class="gap"><td colspan="'.$cols.'">&nbsp;</td></tr>';

                    continue;
                }
                $out .= '<tr>';
                for ($c = 0; $c < $cols; $c++) {
                    $cell = trim($row[$c] ?? '');
                    $bold = $cell !== '' && preg_match('/^[\p{Lu}\s.\'’-]+:$/u', $cell) ? ' class="b"' : '';
                    $out .= '<td'.$bold.'>'.e($cell).'</td>';
                }
                $out .= '</tr>';
            }
            $out .= '</tbody></table>';
            $table = [];
        };

        foreach ($lines as $raw) {
            $line = trim($raw);

            if ($line === '') {
                if ($table !== []) {
                    $table[] = null;
                }

                continue;
            }

            if (! $titleDone) {
                $out .= '<h1>'.e($line).'</h1>';
                $titleDone = true;

                continue;
            }

            $isHeading = preg_match('/^\d+\.\s+(.+)$/u', $line, $m) === 1
                && mb_strlen($m[1]) < 90 && $m[1] === mb_strtoupper($m[1]) && preg_match('/\p{L}/u', $m[1]) === 1;
            $isClause = preg_match('/^\d+\.\d+/u', $line) === 1;
            $cells = preg_split('/\s{3,}/u', $line) ?: [$line];
            $multi = count($cells) > 1;

            if ($isHeading) {
                $flush();
                $out .= '<h2>'.e($line).'</h2>';
                $seenHeading = true;

                continue;
            }

            if ($isClause && $table !== []) {
                $flush();
            }

            if ($multi && ! $seenHeading) {
                $out .= '<div class="meta"><span>'.e($cells[0]).'</span><span>'.e(end($cells)).'</span></div>';

                continue;
            }

            if ($multi || $table !== []) {
                $table[] = $cells;

                continue;
            }

            $out .= '<p>'.e($line).'</p>';
        }

        $flush();

        return $out;
    }
}
