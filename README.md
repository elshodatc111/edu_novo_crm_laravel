# Edunova CRM

Ko'p filialli o'quv markazlari uchun boshqaruv tizimi (Laravel 12 · MySQL · Blade + Tailwind + Alpine.js).

> **Joriy holat: v12.** v11 ustiga: mobil ilovada parolni unutganda SMS-kod bilan tiklash, profilni (telefon+rasm) mobil ilovadan tahrirlash, bildirishnomada "havola" (deep link — bosilganda tegishli sahifaga o'tish), ilova versiyasini tekshirish va majburiy yangilanish, sAdmin filial-sarlavhasi (`X-Branch-Id`) endi barcha yozish amallarida (jumladan xodim qo'shishda) bir xilda ishlaydi, zaxira nusxa tizimidagi xatolik aniq sababi bilan ko'rsatiladi va "Hozir zaxira olish" tugmasi qo'shildi, mobil dasturchi uchun `/docs` manzilida onlayn API hujjati va yangilangan Postman kolleksiyasi. 5000 o'quvchilik hajmda ishlashi tekshirilgan.
>
> **v11 dan yangilash:** yangi fayllarni ustiga yozing (`.env` va `storage/` tegilmaydi), `composer install`, `php artisan migrate --force`, `php artisan storage:link` (agar avval bajarilmagan bo'lsa — profil rasmlari uchun kerak) va `php artisan optimize:clear`. Migratsiya faqat qo'shimcha: yangi jadvallar (`password_reset_codes`, `app_versions`) va `users` jadvaliga `photo_path` ustuni qo'shiladi, mavjud ma'lumot buzilmaydi. **Tuzatildi:** oldingi zip'larda `public/storage` bo'sh papka sifatida kelar edi, shu sabab `storage:link` "link already exists" xatosi berishi mumkin edi — v12'dan boshlab bu papka zip'ga kirmaydi; eski o'rnatishda xato chiqsa, bo'sh `public/storage` papkasini o'chirib, buyruqni qayta ishga tushiring. Zaxira olish ishlashi uchun serverda `mysqldump` o'rnatilgan va PHP'da `proc_open` funksiyasi o'chirilmagan bo'lishi kerak — "Tizim holati" sahifasi buni endi aniq ko'rsatadi. Yangi Blade uslublari uchun `public/build` qayta yig'ilgan holda zip ichida keladi (Node shart emas). Batafsil: pastdagi **2.11-bo'lim** va `CHANGELOG.md`.
>
> **v8 dan yangilash:** yangi fayllarni ustiga yozing (`.env` va `storage/` tegilmaydi), `composer install`, `php artisan migrate --force` va `php artisan optimize:clear`. Migratsiya faqat qo'shimcha: `branches` jadvaliga `brand_color` va `public_about` ustunlari qo'shiladi, mavjud ma'lumot buzilmaydi. Yangi `staff.view_all_branches` ruxsati standart holatda faqat YANGI yaratiladigan Operatorlarga avtomatik beriladi — mavjud operatorlarga kerak bo'lsa sAdmin/admin **Ruxsatlar** sahifasidan qo'lda yoqadi. `finance.deposit` ham xuddi shunday — kerakli adminlarga qo'lda yoqiladi. Yangi Blade uslublari uchun `public/build` qayta yig'ilgan holda zip ichida keladi (Node shart emas). Batafsil: pastdagi **2.8-bo'lim** va `CHANGELOG.md`.
>
> **v7 dan yangilash:** yangi fayllarni ustiga yozing (`.env` va `storage/` tegilmaydi), `composer install`, `php artisan migrate` va `php artisan optimize:clear`. Migratsiya faqat qo'shimcha: yangi jadvallar (`expense_categories`, `cash_closings`, `student_notes`, `task_dismissals`, `submission_tokens` va b.) va mavjud jadvallarga yangi ustunlar qo'shiladi, mavjud ma'lumot buzilmaydi; mavjud adminlarga `payments.reverse` (storno) ruxsati avtomatik beriladi. Boshqa yangi ruxsatlarni (`cashbox.close`, `attendance.edit_past`) kerak bo'lsa sAdmin/admin **Ruxsatlar** sahifasidan qo'lda yoqadi. Yangi Blade uslublari ishlatilgani uchun `public/build` qayta yig'ilgan holda zip ichida keladi (Node shart emas). Batafsil: pastdagi **2.7-bo'lim** va `CHANGELOG.md`.
>
> **v6 dan yangilash:** yangi fayllarni ustiga yozing (`.env` va `storage/` tegilmaydi) va `php artisan migrate` ni bajaring, keyin `php artisan optimize:clear`. Migratsiya faqat qo'shimcha o'zgartirishlar qiladi: eski ehson qoldig'i naqt/plastik ehsonga bo'linadi (jurnalga yoziladi), eski balans yozuvlari to'lovlar bilan bog'lanadi (naqt/plastik ko'rinishi uchun), mavjud adminlarga `Qarzi bor o'quvchini guruhga qo'shish (istisno)` ruxsati beriladi, filialsiz jurnal yozuvlari foydalanuvchi filialiga biriktiriladi. Grafiklar uchun `public/build` zip ichida tayyor (Node kerak emas). Batafsil: pastdagi 2.6-bo'lim va `CHANGELOG.md`.
>
> **v5 dan yangilash:** avval v6 qadamlari: migratsiya mavjud telefonlarni `+998 90 123 4567` ko'rinishiga keltiradi; bir filialda bir rolda takrorlangan raqamlarda birinchisi qoladi, qolganlarining raqami bo'shatiladi va noto'g'ri raqamli foydalanuvchilar ro'yxati `storage/logs/laravel.log` ga («Telefon migratsiyasi») yoziladi — ularning raqamini qo'lda kiriting. Mavjud adminlarga `Kassa tarixi` ruxsati avtomatik beriladi.

---

## 1. Kerakli dasturlar (Windows)

| Dastur | Versiya | Izoh |
|---|---|---|
| XAMPP | PHP 8.2+ va MySQL/MariaDB | Sizda PHP 8.2.12 bor - mos |
| Composer | 2.x | Sizda 2.9.3 bor - mos |
| Node.js | ixtiyoriy | Faqat dizaynni o'zgartirmoqchi bo'lsangiz kerak. Tayyor (build qilingan) fayllar zip ichida bor |

**PHP kengaytmalari.** `C:\xampp\php\php.ini` faylida quyidagi qatorlar oldida `;` bo'lmasligi kerak (odatda XAMPPda yoqilgan):
`extension=mbstring`, `extension=openssl`, `extension=pdo_mysql`, `extension=fileinfo`, `extension=curl`, `extension=zip`, `extension=pdo_sqlite` (testlar uchun).

## 2. O'rnatish (lokal kompyuterda)

1. **Zip'ni oching**, masalan `C:\edunova-crm\` papkasiga (XAMPP `htdocs` ichida bo'lishi shart emas).
2. **XAMPP Control Panel** da **MySQL** ni ishga tushiring (Start).
3. Brauzerda `http://localhost/phpmyadmin` oching, **Yangi** (New) bosib baza yarating:
   nomi `edunova_crm`, kodirovka `utf8mb4_unicode_ci`.
4. Loyiha papkasida **Buyruq satrini** (CMD yoki PowerShell) oching va ketma-ket bajaring:

```bat
cd C:\edunova-crm
composer install
copy .env.example .env
php artisan key:generate
```

5. `.env` faylini Notepad bilan oching va tekshiring (XAMPP standarti bo'yicha parol bo'sh):

```
DB_DATABASE=edunova_crm
DB_USERNAME=root
DB_PASSWORD=
SADMIN_USERNAME=sadmin
SADMIN_PASSWORD=Kuchli_parol_yozing_123
```

6. Jadvallarni yarating va birinchi sAdmin akkauntini oching:

```bat
php artisan migrate
php artisan db:seed
```

   Oxirgi buyruq sAdmin login va parolini chiqaradi (`SADMIN_PASSWORD` bo'sh bo'lsa, tasodifiy parol yaratiladi va shu yerda ko'rsatiladi - uni yozib oling).

7. *(Ixtiyoriy)* Sinov ma'lumotlari — **2 ta filial** (Test filiali, Qarshi filiali), har birida admin, 2 menejer, 2 o'qituvchi, 15 o'quvchi, 3 guruh (tugagan, davom etayotgan, boshlanmagan), to'lovlar, qarzdorlar, chegirmalar, davomad, murojaatlar, kassa va ish haqi:
   `php artisan db:seed --class=DemoSeeder` (bir necha soniya). Loginlar: `test.admin`, `test.menejer1`, `test.menejer2`, `test.ustoz1`, `test.ustoz2` (ikkinchi filial: `qarshi.…`), parol hammasida `demo12345`; sAdmin — `.env` dagi `SADMIN_*`. **Haqiqiy serverda bu buyruqni ishlatmang.**

8. Serverni ishga tushiring:

```bat
php artisan serve
```

   *(SMS ishlashi uchun boshqa CMD oynasida `php artisan queue:work` ni ham ishga tushiring — 2.3-bo'lim.)*

   Brauzerda oching: **http://127.0.0.1:8000** - sAdmin login/paroli bilan kiring.

> **Composer "security advisories" xatosi chiqsa:** Laravel 11 versiyalarida ochiq zaifliklar bor, shu sabab loyiha Laravel 12 ga o'tkazilgan (`composer.json` da `"laravel/framework": "^12.0"`). Eski `composer.lock` yoki `vendor` papkasi bo'lsa, o'chirib, `composer install` ni qayta bajaring. Xavfsizlik tekshiruvini o'chirish (`block-insecure=false`) tavsiya etilmaydi.

## 2.1. Ishni boshlash tartibi (2-bosqich)

1. sAdmin → **Filiallar** → filial oching, yuqoridan shu filialni tanlang.
2. **Sozlamalar**: xonalar, dars vaqtlari, narx rejasi (guruh narxi va oldindan to'lov chegirmasi), dam olish kunlari (**"1 yilga to'ldirish"** tugmasi yakshanba va bayramlarni qo'shadi), o'quvchi manbalari. **Kurslar** alohida bo'limda.
3. **Hodimlar** → o'qituvchi qo'shing (o'qituvchiga `Davomadni ko'rish` va `Davomad olish` ruxsatlari avtomatik beriladi).
4. **Guruhlar** → yangi guruh: dars kunlari bayramlarni o'tkazib avtomatik tuziladi; xona yoki o'qituvchi band bo'lsa, tizim ogohlantiradi.
5. **O'quvchilar** → qo'shing (mobil ilova logini telefon raqamidan, paroli bir marta ko'rsatiladi) → guruhga qo'shing: guruh narxi balansdan yechiladi, oldindan to'lagan bo'lsa (guruh boshlanganidan 3 kun ichida) chegirma beriladi.
6. **Bugungi davomad**: faqat guruh dars kuni bo'lsa olinadi; bugun olingan davomadni tahrirlash mumkin, o'tgan kunlarni emas. **Davomad statistikasi**: kunlik va oylik tahlil.

## 2.2. Pul oqimi (3-bosqich)

```
Talaba to'lovi ──► KASSA (naqt / plastik) ──chiqim so'rovi──► admin tasdiqlaydi ──► MOLIYA balansi (naqt / plastik) + EHSON ulushi
                        │                                                              │
                        └── xarajat so'rovi ──► admin tasdiqlaydi (sarflandi)            ├── moliyadan chiqim va xarajat
                                                                                       └── o'qituvchi va hodim ish haqi
```

- **To'lov** o'quvchi sahifasidan qabul qilinadi (naqt + plastik alohida yozilishi mumkin). Pul kassaga tushadi, o'quvchi balansi oshadi.
- **Oldindan to'lov chegirmasi:** guruh boshlanganidan 3 kun ichida shu guruh uchun jami to'lov *(narx − chegirma)* ga yetsa, chegirma avtomatik beriladi (guruhga bir marta). Oldindan to'lagan o'quvchi guruhga qo'shilganda ham chegirma avtomatik.
- **Admin chegirmasi** (`payments.discount`): narx rejasidagi "Admin bera oladigan chegirma" chegarasida, guruh uchun bir marta. Chegirma 0 bo'lsa, admin chegirma bera olmaydi — **Sozlamalar → Narx rejalari** da belgilang.
- **Aksiyalar** (Sozlamalar → Aksiyalar): davr va minimal to'lov summasi; bonus har o'quvchiga bir marta.
- **Qaytarish:** balansdan ko'p qaytarib bo'lmaydi; kassada pul bo'lishi shart; admin keyin tasdiqlaydi (Kassa sahifasi).
- **Kassa:** chiqim/xarajat so'rovida pul kassadan darhol yechiladi. Admin tasdiqlasa: chiqim moliya balansiga o'tadi (ehson foizi alohida ajratiladi), xarajat sarflangan hisoblanadi. Bekor qilinsa pul kassaga qaytadi. Kassa va moliya balansi hech qachon manfiy bo'la olmaydi.
- **O'qituvchi ish haqi:** hisoblangan = faol o'quvchilar × stavka + bonusli o'quvchilar × bonus (bonusli: shu guruhdan keyin boshlangan boshqa guruhda ham faol o'quvchi). "Davomad bo'yicha" = o'tkazilgan darslar / jami darslar × hisoblangan. To'lovlar moliya balansidan.
- Stavkalar guruh yaratishda / tahrirlashda kiritiladi.

## 2.3. Varonka, kurslar, SMS (4-bosqich)

**Lokal ishga tushirishda qo'shimcha 3 ta narsa kerak:**

```bat
php artisan storage:link       REM audio fayllar ko'rinishi uchun (bir marta)
php artisan queue:work         REM SMS yuborish uchun - alohida CMD oynasida doim ochiq tursin
php artisan schedule:work      REM tug'ilgan kun SMS'lari uchun (ixtiyoriy, alohida oyna)
```

Audio fayl yuklashda XAMPP `php.ini` da `upload_max_filesize` va `post_max_size` ni kamida `64M` qiling (standart 40M).

- **Varonka:** sayt uchun ochiq murojaat shakli: `http://127.0.0.1:8000/apply/{filial-kodi}` (havola Varonka sahifasida ko'rinadi). Murojaat kelgach: *Yangi → Ko'rib chiqilmoqda (izoh yozilganda) → Qabul qilindi (o'quvchi yaratildi yoki mavjud o'quvchiga bog'landi) / Bekor qilindi*. Bir raqam takror yuborilsa (10 daqiqa ichida) dublikat yaratilmaydi; raqam allaqachon o'quvchi bo'lsa, "Takror" belgisi chiqadi va dublikat o'quvchi yaratilmaydi.
- **Kurslar:** Kurslar → «Materiallar»: video (havola), audio (fayl yoki havola), test savollari (1 to'g'ri + 3 noto'g'ri javob). **Kitoblar:** Sozlamalar → Kitoblar.
- **O'quvchi testi (mobil API):** har testda 15 tagacha tasodifiy savol, variantlar aralashtiriladi; to'g'ri javob o'quvchiga yuborilmaydi, server topshirilganda tekshiradi (eski tizimda javoblar mijozga yuborilardi). Natija foizda saqlanadi.
- **SMS (Eskiz):** kalit va parol **faqat `.env`** da (`ESKIZ_EMAIL`, `ESKIZ_PASSWORD`, `ESKIZ_FROM`); filialga alohida akkaunt sAdmin tomonidan Filiallar bo'limida kiritiladi. Har filialda SMS **standart holatda o'chiq** — SMS bo'limi → Sozlamalar da yoqing. Shablonlar (xush kelibsiz, to'lov, chegirma, qaytarish, ish haqi, parol, tug'ilgan kun) alohida yoqiladi va tahrirlanadi. Ommaviy yuborish: qarzdorlar / guruh / barcha o'quvchilar (bir raqamga bir marta). Barcha xabarlar tarixi va xato sababi ko'rinadi. Xabarlar navbat orqali yuboriladi, shuning uchun `queue:work` ishlab turishi shart.

## 2.4. Statistika, hisobotlar, import, AI (5-bosqich)

- **Statistika** (`statistics.view`): tanlangan davr uchun tushum, qaytarilgan, sof tushum, chegirmalar, xarajat, ish haqi, foyda, yangi va faol o'quvchilar, qarzdorlar, murojaatlar va qabul foizi, davomad; 12 oylik dinamika; sAdmin "Barcha filiallar" rejimida filiallar solishtiruvi. Barcha raqamlar bitta manbadan (`StatisticsService`) hisoblanadi va sahifada ta'riflari yozilgan; hisobotlar, mobil API va AI ham aynan shu raqamlardan foydalanadi. Pul ko'rsatkichlari tegishli ruxsatsiz ko'rinmaydi.
- **Hisobotlar** (`reports.view`, Excelga yuklash uchun `reports.export`): to'lovlar, qarzdorlar, yangi o'quvchilar, guruhlar, varonka, davomad, o'qituvchi ish haqi, pul harakati. Har biri jadval ko'rinishida chiqadi va ustun kengligi, sarlavha va yig'indilari bilan `.xlsx` ga yuklanadi. Excel fayllar tashqi paketsiz yaratiladi (PHP `zip` kengaytmasi kerak).
- **Excel import** (`students.import`, admin va sAdmin): O'quvchilar bo'limi → «Excel import». Namuna faylni yuklab oling → to'ldiring → yuklang → **tekshiruv oynasi** (nechta o'quvchi qo'shiladi, takror va xato qatorlar, filiallar bo'yicha, **yangi filial: X, Y ta o'quvchi**) → tasdiqlang. Yangi filialni faqat sAdmin ocha oladi (faylda «Filial» ustuni orqali). Har bir o'quvchiga tasodifiy parol yaratiladi; login/parollar fayli import tugagach **bir marta** yuklab olinadi. Boshlang'ich balans (qarz) ham kiritiladi.
- **AI yordamchi** (`ai.chat`: sAdmin va ruxsat berilgan adminlar): filial statistikasi bo'yicha suhbat va tayyor tahlillar (kamchiliklar va tavsiyalar). AI bazaga to'g'ridan-to'g'ri kirmaydi, faqat foydalanuvchi **ruxsati va filiali doirasida** hisoblangan raqamlarni oladi; ism va telefonlar OpenAI'ga yuborilmaydi. Admin faqat o'zida bor ruxsatlarga mos ma'lumotlarni tahlil qiladi, sAdmin barcha filiallarni. Kunlik limit: `OPENAI_DAILY_LIMIT` (standart 200). **Kurslar → Materiallar → Test savollari** bo'limida «AI bilan savol yaratish»: avval loyiha ko'rsatiladi, siz tanlab saqlaysiz.
- **OpenAI kaliti** faqat `.env` da: `OPENAI_API_KEY=...` (chatga yozilgan kalitni OpenAI panelida almashtiring).

## 2.5. v6 yangiliklari

- **Summa maydonlari** barcha joyda yozayotganda `1 000 000` ko'rinishiga keladi (serverga bo'shliqsiz ketadi).
- **Telefon** faqat `+998 90 123 4567` ko'rinishida kiritiladi (maydon o'zi formatlaydi, boshqa format qabul qilinmaydi). Asosiy telefon majburiy. **Bir filialda bir rolda bir telefon faqat bir marta** (bazada ham qat'iy cheklov); bitta raqam o'quvchi, o'qituvchi, menejer va admin sifatida alohida bo'la oladi. Qo'shimcha telefon (ota-ona) takrorlanishi mumkin. Excel importda raqamlar avtomatik formatga keltiriladi, keltirib bo'lmaganlari «Xato».
- **Chegirmalar:** *Sozlamalar → Chegirma qoidalari*: oldindan to'lov chegirmasi guruh boshlanishidan necha kun oldin (standart 30) va necha kun keyingacha (standart 3) berilishi. Guruhga qo'shilganda olingan chegirma to'lovlarga yoziladi va keyingi to'lovda **qayta berilmaydi**. Oldindan to'lagan, lekin guruhga biriktirilmagan o'quvchi guruhga qo'shilganda chegirma oladi (masalan, guruh 600 000, chegirma 100 000, balans 500 000).
- **Aksiyalar** to'lov paytida shartga mos kelsa **avtomatik** qo'shiladi (bir necha aksiya mos kelsa eng katta bonusli; har biri o'quvchiga bir martadan). Tanlash maydoni yo'q.
- **Qarzi bor (balansi manfiy) o'quvchi guruhga qo'shilmaydi** — istisnosiz. Guruhni davom ettirishda qarzli o'quvchilar tanlanmaydi, balanslari ko'rinib turadi.
- **Varonka:** yuqorida tanlangan filialga qarab murojaat formasi havolasi va saytga qo'yish kodi (`iframe`, nusxalash tugmasi bilan) o'zgaradi. Forma orqali kelgan murojaatlar shu filial Varonkasiga tushadi, barcha menejerlar ular bilan ishlaydi. **AI har bir murojaatni tahlil qiladi** (ustuvorlik, qabul ehtimoli, keyingi qadam, suhbat tavsiyalari) — murojaat tushganda va izoh yozilganda yangilanadi, izohlar tarixida «AI fikri» sifatida ko'rinadi. Ism va telefon OpenAI'ga yuborilmaydi. Avtomatik tahlil uchun `php artisan queue:work` ishlab turishi kerak; aks holda «Tahlil qilish» tugmasi bor.
- **Yordam (AI)** — menyudagi yangi bo'lim, **barcha rollar** uchun: tizimdan foydalanish, qoidalar va qadamlar haqida savollarga foydalanuvchi **roli va ruxsatlariga mos** javob beradi (o'qituvchiga davomad, menejerga to'lov, adminga sozlamalar va h.k.; ruxsati bo'lmagan amalni tushuntirmaydi, kimga murojaat qilishni aytadi). Bilim bazasi: `resources/ai/knowledge.md` — tizimga yangi imkoniyat qo'shsangiz, shu faylga ham yozing. Tahlilchi AI ham shu qo'llanmadan foydalanadi.
- **Kassa tarixi** (so'nggi 30 kun) faqat sAdmin va `cashbox.history` ruxsati borlarga (adminlar) ko'rinadi, menejerlarga ko'rinmaydi.

## 2.6. v7 yangiliklari

- **Ikki bosqichli tasdiqlash.** Moliyadan chiqim, xarajat, ehson chiqimi va ish haqi to'lovi endi darrov bajarilmaydi: «Tekshirishga o'tish» → «Tekshiring» sahifasi (kim/qaysi balans, summa, izoh, balans qanday o'zgarishi) → «Ha, to'g'ri — tasdiqlash». Ma'lumot serverda (sessiyada) saqlanadi, tasdiq 15 daqiqa amal qiladi, bir marta ishlaydi va faqat uni boshlagan foydalanuvchi bajara oladi; tasdiqlash paytida ruxsat va mablag' qayta tekshiriladi. (`ConfirmationService`, `/confirm/{token}`)
- **Ehson naqt/plastik.** Moliyada ehson jami va uning naqt/plastik qismi ko'rinadi; kassadan chiqimda ehson ulushi o'sha usul ehsoniga tushadi; «Ehson chiqimi» alohida forma (naqt ehsondan naqt, plastik ehsondan plastik). Yangi hamyonlar: `treasury_charity_cash`, `treasury_charity_card` (eski `treasury_charity` faqat migratsiya uchun qoldi).
- **Qarzdorni istisno tariqasida qo'shish.** Yangi ruxsat `groups.enroll_debtor` (sAdmin doim; adminlarga migratsiya beradi, keyin sAdmin boshqaradi; menejerga berilmaydi). Guruh yoki o'quvchi sahifasida «Qarzga qo'shish (istisno)» belgisi bilan qo'shiladi, jurnalga yoziladi. Guruhni davom ettirishda qarzdorlar avvalgidek o'tkazilmaydi.
- **Chegirma bir nechta to'lov bilan.** Chegirma muddati ochilgandan beri jami to'lov (naqt+plastik, turli kunlarda, guruhsiz ham) «narx − chegirma» ga yetsa va umumiy qarz shu guruh narxidan oshmasa, chegirma avtomatik beriladi; bitta pul ikki guruh uchun ikki marta hisoblanmaydi (`EarlyDiscountService`).
- **Varonka voronkasi** (davr tanlanadi, jadval ko'rinishi bor) va **Statistika / Davomad statistikasi dashboardlari** (Chart.js, yorug'/qorong'i rejim). Har bir grafikda «Grafik / Jadval» almashtirgichi: jadval xuddi shu raqamlarni ko'rsatadi.
- **Yordam** — bitta menyu, ikki rejim: «Qo'llanma» (hamma uchun) va «Tahlil (AI)» (`ai.chat` ruxsati bilan). Eski `/ai` manzili yangi sahifaga yo'naltiradi.
- **Harakatlar jurnali filial bo'yicha:** admin o'z filialini, sAdmin tanlangan filialni yoki (tanlamasa) hammasini ko'radi; sAdmin harakatlari tanlangan filialga yoziladi; sahifada filial/foydalanuvchi/harakat/sana filtrlari.
- **Bosh sahifa kalendari** (filial tanlanganda, barcha hodimlarga): oy jadvali, tanlangan kun darslari (vaqt, xona, guruh, o'qituvchi), xona filtri; guruh nomi bosilsa guruh ochiladi (o'qituvchi faqat o'z guruhini ocha oladi).
- **O'quvchi sahifasida bitta jadval:** balans harakatlari va tarix birlashtirildi; to'lov naqt yoki plastik ekani ko'rinadi (yangi `balance_transactions.payment_id`).

## 2.7. v8 yangiliklari

- **Ikki marta yuborishdan himoya:** pul va boshqa muhim formalar tasodifan ikki marta bosilsa ham bir marta bajariladi; xavfli amallar (bekor qilish, storno) oldin tasdiqlash so'raydi.
- **Storno va qaytarishni rad etish:** noto'g'ri kiritilgan to'lov/chegirma/bonusni admin/sAdmin sabab ko'rsatib bekor qiladi (`payments.reverse`); yozuv o'chmaydi, teskari yozuv bilan yopiladi, barcha hisobot va panellarda to'g'ri hisoblanadi. Noto'g'ri yuborilgan qaytarish so'rovi ham sabab bilan rad etiladi.
- **«Tizim holati»** (faqat sAdmin): baza/zaxira, navbat (queue) va rejalashtiruvchi (cron) ishlab turgani, manfiy balans, production'da debug rejimi yoqiq qolmagani — bittada. Avtomatik kunlik zaxira: `php artisan app:backup` (eskilari o'zi o'chadi).
- **Operator** — menejerga o'xshash yangi lavozim, standart holatda torroq (kassa va kurslarsiz — faqat qabul, oddiy to'lov, davomad, lid).
- **Kunlik vazifalar paneli** — bosh sahifada rolga mos «bugun e'tibor kerak» ro'yxati.
- **Avtomatik SMS:** qarz eslatmasi va darsga kelmaganlik xabari (filial sozlamasida alohida yoqiladi).
- **Chop etish:** to'lov cheki (80mm yoki A5) va o'quv shartnomasi (A5) to'g'ridan-to'g'ri brauzerdan.
- **O'quvchi eslatmalari** — faqat xodimlarga ko'rinadigan ichki eslatmalar (o'quvchiga ko'rinmaydi).
- **Ommaviy SMS'da oldindan ko'rish** — yuborishdan oldin qancha kishiga, qanday matn ketishi ko'rsatiladi, faqat tasdiqlagach navbatga qo'yiladi.
- **O'tgan kun davomatini tuzatish** (`attendance.edit_past`, alohida beriladigan ruxsat) — sababi jurnalga yoziladi.
- **Kassa smenasini yopish** (`cashbox.close`) va **xarajat turlari** — kutilgan/haqiqiy naqtni solishtirish (farq faqat yoziladi, kassaga tegilmaydi), xarajat so'rovlarini turkumlash (Ijara, Kommunal va h.k.).

Batafsil ro'yxat, ma'lumotlar bazasi o'zgarishlari va cheklovlar: `CHANGELOG.md`.

## 2.8. v9 yangiliklari

- **Filiallar xodimlari** (`staff.view_all_branches`, standart holatda Operatorga beriladi) — barcha filiallar bo'yicha faqat ism, rol va filial ko'rsatilgan o'qish uchun ro'yxat; lid/murojaat bilan ishlaganda boshqa filialdagi mas'ul xodimni topish uchun.
- **Moliyaga shaxsiy mablag' kiritish** (`finance.deposit`, admin/sAdmin) — egasi naqt yoki plastik shaxsiy mablag'ini moliyaga darhol kiritadi (tasdiqlash bosqichisiz, ehson ajratilmasdan).
- **Ochiq murojaat sahifasi o'z brendida:** filial tahrirlashda rang (HEX) va qisqa ma'lumot kiritiladi; `/apply/{filial}` sahifasida Edunova o'rniga shu filialning nomi, rangi va ma'lumoti ko'rsatiladi. Filial tanlash sahifasi (`/apply`, bir nechta filial bo'lsa) o'zgarmadi.
- **Guruhga qo'shish ro'yxati** endi guruh nomi bo'yicha alifbo tartibida chiqadi.
- **Leadni o'quvchi qilishda guruhga ham qo'shish** — bitta amalda o'quvchi yaratiladi va ixtiyoriy tanlangan guruhga qo'shiladi (xatolik bo'lsa, ikkalasi ham bekor bo'ladi).
- **Telefonni bir bosishda nusxalash** — murojaatlar ro'yxati va sahifasida telefon raqami probelsiz (masalan `+998901234567`) nusxalanadi.

*Kelgusi reja:* "1Call.uz" qo'ng'iroq-markazi bilan webhook integratsiyasi bo'yicha shartnoma jarayoni davom etmoqda — hali ishlatilmaydi, lekin arxitektura buni bloklamaydi (murojaatlar allaqachon manba/izoh saqlaydi, yangi webhook endpoint va ruxsat keyinroq additiv migratsiya bilan qo'shiladi).

## 2.9. v10 yangiliklari

- **Filiallarni ko'zdan kechirish** (`staff.view_all_branches` egalari uchun, odatda Operator): «Filiallar xodimlari» sahifasidagi har bir filial yonidagi tugma orqali istalgan filialning o'quvchi, guruh va lidlar ro'yxatini sAdmin kabi ko'rish mumkin — **faqat o'qish uchun**, hech qanday tahrirlash yoki amal tugmasi yo'q, va bu ko'rish paytida xodimning haqiqiy ishchi filiali (yozish huquqi) o'zgarmaydi.
- **Guruhni davom ettirishda qarzdorlarni o'tkazish:** admin va sAdmin (`groups.enroll_debtor` ruxsati bilan) qarzi bor o'quvchini ham yangi ochilayotgan davom guruhiga istisno tariqasida o'tkaza oladi.
- **Moliya sahifasi qayta tartiblandi:** «Balansdan chiqim», «Xarajat», «Shaxsiy mablag' kiritish» bitta qatorda, «Ehson chiqimi» va «Ehson foizi» ikkinchi qatorda — teng kenglikda, qulayroq.
- **Xarajat turkumi Moliyada ham:** avval faqat Kassa xarajat so'rovida bor edi, endi Moliya → Xarajat formasida ham ixtiyoriy turkum tanlanadi, pul harakati tarixida ko'rinadi.
- **Navbat (queue) ishchisi tekshiruvi:** «Tizim holati» sahifasida endi cron bilan bir qatorda alohida — `php artisan queue:work` haqiqatan ishlab turganini tasdiqlaydi (SMS, AI tahlil kabi fon vazifalar shunga bog'liq).
- **Filial rangini tanlashda qiymat ko'rinadi:** avval tanlangan rang deyarli ko'rinmas edi, endi HEX qiymati matn sifatida ham aniq chiqadi.
- **Bosh sahifa yangilandi:** «Bugungi qarzdorlar» va «24 soatdan ortiq javobsiz lidlar» panellari scroll bo'ladi (ro'yxat uzun bo'lsa sahifa cho'zilib ketmaydi); faol guruhlar va boshlanishi kutilayotgan guruhlar soni, har birida jami o'quvchilar soni bilan qo'shildi.
- **Filialni butunlay o'chirish** (faqat sAdmin): «Filiallar» sahifasida filial va unga tegishli **BARCHA** ma'lumotni (hodim, o'quvchi, guruh, to'lov, kassa, lid, SMS, AI suhbat va h.k.) qaytarib bo'lmaydigan tarzda o'chiradi. Xato bosishdan himoya: filial nomini aniq qayta yozib tasdiqlash shart. Yagona istisno — harakatlar jurnali (`audit_logs`) o'chirilmaydi, faqat filial bilan bog'lanishi bo'shatiladi.
- **Sinov ma'lumotlari kichraytirildi:** `DemoSeeder` endi 5 ta emas, **2 ta filial** (Test filiali, Qarshi filiali) va kamroq hajmda ma'lumot yaratadi — tezroq va sodda tekshirish uchun (2.-bo'limdagi 7-qadam).

## 2.10. v11 yangiliklari

- **Mobil ilova uchun to'liq API** (Flutter ilova bilan ishlash uchun): v10'da faqat o'quvchi/o'qituvchi uchun bo'lgan API endi xodimlar (admin, menejer, operator — ruxsatiga qarab) uchun ham to'liq: bosh sahifa vazifalari, Varonka (lidlar), Kassa (so'rov/tasdiqlash), Moliya (ko'rish + shaxsiy mablag'), Hodimlar (ro'yxat/qo'shish/tahrirlash/ish haqi ko'rish), SMS (tarix/oldindan ko'rish), boshqa filiallarni ko'rish, guruhga o'quvchi biriktirish. Har biri veb'dagi bilan **bir xil ruxsat va filial cheklovi** ostida ishlaydi. To'liq ro'yxat: `API_DOC.md`.
- **sAdmin mobil ilovada filial almashtirish:** mobil ilova sessiya (cookie) ishlatmagani uchun sAdmin qaysi filial bilan ishlashini `X-Branch-Id` sarlavhasi orqali ko'rsatadi (veb'dagi filial tanlagichning mobil muqobili). Boshqa hech qanday rolga bu tegishli emas — ularning filiali doim o'zinikiga qat'iy bog'langan.
- **To'liq push bildirishnoma (Firebase/FCM):** mobil ilova foydalanuvchilari (o'quvchi va xodimlar) endi telefon ekraniga chiqadigan push xabar oladi. Yangi sAdmin bo'limi — **Bildirishnomalar** (yon menyuda 🔔): sarlavha, matn yozib, «barchaga» yoki bitta filialga yuboriladi. Yuborilgan xabarlar tarixi (nechta kishiga yetgani, nechtasi o'qilgani) shu sahifada. Mobil ilovada `/notifications` orqali foydalanuvchi o'ziga yuborilgan xabarlarni ko'radi va o'qilgan deb belgilaydi — bu ilova-ichi ro'yxat Firebase sozlanmagan bo'lsa ham ishlaydi, faqat push (telefon ekraniga chiqishi) uchun Firebase kerak (pastda, 2-bo'lim, Firebase sozlash).
- **O'quvchi uchun "Mening guruhlarim":** barcha guruhlari (faol/tugagan) bitta ro'yxatda, holati bilan (`/me/groups`) va barcha guruhlar bo'yicha umumlashtirilgan davomat tarixi/foizi (`/me/attendance`).
- **Ataylab mobilga chiqarilmagan amallar** (xavfsizlik uchun, faqat veb'da qoladi, ikki bosqichli tasdiqlash talab qilgani uchun): moliyadan pul CHIQARISH, ish haqi TO'LASH, ommaviy SMS haqiqiy yuborish, kassa smenasini yopish, qaytarishni tasdiqlash/rad etish.
- **Yangi jadvallar** (2 ta migratsiya, qo'shimcha): `device_tokens` (foydalanuvchi qurilma push tokeni), `notifications` + `notification_recipients` (yuborilgan xabarlar va har bir qabul qiluvchining holati).

*Cheklov:* Firebase hisobi ulanmagan bo'lsa, push xabar telefon ekraniga chiqmaydi (lekin ilova ichidagi bildirishnomalar ro'yxatiga baribir tushadi). Firebase'ni ulash — pastdagi "Firebase (push bildirishnoma) sozlash" bo'limida.

## 2.11. v12 yangiliklari

- **Parolni unutgan (mobil):** `POST /auth/forgot-password` — login/email kiritilsa, shu hisobning telefoniga 6 xonali SMS-kod boradi (5 daqiqa amal qiladi, 5 marta xato urinishdan keyin bekor bo'ladi); `POST /auth/reset-password` — kod va yangi parol bilan tiklanadi. Faqat login/email orqali qidiradi (telefon bo'yicha emas, chunki bitta telefon bir necha rolda takrorlanishi mumkin). **sAdmin uchun ishlamaydi** (sAdminning aniq bitta filiali yo'q, SMS filial orqali yuboriladi) — boshqa sAdmin veb orqali tiklaydi.
- **Profilni tahrirlash (mobil):** `POST /me/profile` — o'quvchi va xodim telefon raqami (filial+rol doirasida takrorlanmasligi tekshiriladi) va profil rasmini o'zgartira oladi. F.I.O. va login mobil orqali o'zgartirilmaydi.
- **Bildirishnoma deep link:** xabar yozishda ixtiyoriy «Havola» (`link_type`/`link_id`) tanlanadi — foydalanuvchi bildirishnomani bosganda ilova tegishli sahifaga to'g'ridan-to'g'ri o'tadi.
- **Ilova versiyasi tekshiruvi:** `POST /app-version/check` — ilova joriy versiyasini yuboradi, server «yangilanish bor»/«majburiy yangilanish» belgisi va do'kon havolasini qaytaradi. Yangi sAdmin sozlama sahifasi — **Ilova versiyasi**: Android/iOS uchun alohida minimal/so'nggi versiya, havola va xabar matni.
- **sAdmin filial-sarlavhasi birlashtirildi:** avval xodim qo'shishda (`POST /staff`) boshqacha (so'rov tanasidagi `branch_id`) usul ishlatilardi; endi BARCHA sAdmin yozish amallari bir xilda `X-Branch-Id` sarlavhasidan foydalanadi.
- **Zaxira nusxa diagnostikasi:** «Tizim holati» sahifasida endi zaxira muvaffaqiyatsiz bo'lsa aniq sabab ko'rsatiladi (masalan, `mysqldump` topilmadi yoki `proc_open` o'chirilgan) va «Hozir zaxira olish» tugmasi bilan navbatni kutmasdan darhol zaxira olish mumkin.
- **`/docs` — onlayn API hujjati:** mobil ilova dasturchisi uchun `API_DOC.md`dagi barcha endpoint tavsifi kirishsiz (`/docs`) onlayn o'qiladi.
- **Postman kolleksiyasi yangilandi:** `docs/Edunova_CRM_API.postman_collection.json` v12'dagi barcha (60 ta) endpoint'ni qamrab oladi; kolleksiya haqiqiy marshrutlar bilan avtomatik test orqali solishtiriladi (`tests/Unit/PostmanCollectionTest.php`) — kelajakda yangi endpoint qo'shilib, kolleksiya yangilanishi unutilsa, test buzilib xabar beradi.
- **Hujjat tuzatildi:** `API_DOC.md`da bildirishnoma manzillari noto'g'ri `/me/notifications` deb yozilgan edi — to'g'risi `/notifications` (v11'dan beri shunday ishlagan, faqat hujjat xato edi); shuningdek avval hujjatlanmagan `GET /branches` endpoint'i qo'shildi.

## 2.12. v13 yangiliklari

- Eslatmalar qo'ng'iroqchasi, `payments.view_totals` ruxsati, lavozimni o'zgartirish, operatorga ish haqi to'lash tuzatildi.
- Bir nechta sAdmin (Tizim → Super adminlar) va operatorga qo'shimcha filiallar (Hodimlar → operatorni tahrirlash, faqat sAdmin).
- Hodim faoliyati statistikasi (ish haqi kartochkasi), kun/hafta/oy tushum dinamikasi, guruhlangan yon menyu.
- Yangilash: `php artisan migrate` (4 ta yangi migratsiya), `npm install && npm run build`, `php artisan test`.

## 3. Birinchi qadamlar

1. sAdmin sifatida kiring → **Filiallar** → **Yangi filial**.
2. **Hodimlar** → **Hodim qo'shish** → filialga admin qo'shing (admin ruxsatlari avtomatik beriladi).
3. Adminning **Ruxsatlar** sahifasida (faqat sAdmin uchun) uning imkoniyatlarini cheklang yoki kengaytiring.
4. Admin o'z filialida menejer qo'shadi va **o'zida bor** ruxsatlarni ularga beradi.

## 4. Rollar va ruxsatlar qoidalari

- **sAdmin** - barcha filiallar va barcha ruxsatlar. Yuqoridagi filial tanlagichi orqali bitta filialga o'tadi yoki "Barcha filiallar"ni ko'radi.
- **Admin / Menejer / O'qituvchi** - faqat o'z filiali ma'lumotlari. Foydalanuvchi faqat bitta filialga tegishli.
- **O'quvchi** - veb-panelga kira olmaydi, faqat mobil API.
- Admin ruxsatlarini **faqat sAdmin** belgilaydi. Admin esa menejer va o'qituvchilarga faqat **o'zida bor** ruxsatlarni bera oladi (o'zining vakolatidan tashqaridagi ruxsatlarga tegmaydi).
- Filialni yopish ma'lumotlarni o'chirmaydi: filial "arxiv"ga o'tadi, xodimlar kira olmaydi, sAdmin hisobotlarida "arxiv" belgisi bilan ko'rinadi. Qayta ochish mumkin. **Butunlay o'chirish** (faqat sAdmin, nom tasdiqlash bilan) esa filialga tegishli barcha ma'lumotni qaytarib bo'lmaydigan tarzda o'chiradi — bu ikkisi butunlay boshqa amal.
- `staff.view_all_branches` ruxsati bor xodim (odatda Operator) boshqa filiallarning o'quvchi/guruh/lid ma'lumotini sAdmin kabi ko'ra oladi, lekin **faqat o'qish uchun** — o'z ishchi filialida yozish huquqi o'zgarmaydi.
- Ruxsatlar ro'yxati va rol shablonlari: `config/permissions.php`.

## 5. Mobil API (v1)

Base URL (lokal): `http://127.0.0.1:8000/api/v1` · Format: JSON · Avtorizatsiya: `Authorization: Bearer <token>`

**v11'da API sezilarli kengaydi** (xodimlar uchun to'liq bo'lim, push bildirishnoma, sAdmin filial sarlavhasi). Har bir endpoint, so'rov/javob namunalari va xato formati bilan to'liq hujjat: **`API_DOC.md`** (shu papkada, alohida fayl). Quyida faqat qisqacha xulosa:

| Guruh | Misol endpointlar | Kim uchun |
|---|---|---|
| Kirish | `POST /auth/login`, `/auth/logout`, `/auth/change-password`, `GET /auth/me` | Hamma |
| Profil / qurilma | `GET /me/profile`, `POST /me/device-token`, `DELETE /me/device-token` | Hamma |
| Bildirishnomalar | `GET /me/notifications`, `POST /me/notifications/{id}/read` | Hamma |
| O'quvchi | `GET /me/balance`, `/me/payroll`, `/me/groups`, `/me/attendance`, `/courses`, `/books` | O'quvchi (ba'zilari o'qituvchi ham) |
| Bosh sahifa | `GET /dashboard/todo` | Xodim (ruxsatga qarab) |
| Varonka | `GET/POST /leads`, `/leads/{id}/note`, `/cancel`, `/reopen`, `/convert` | `leads.view` / `leads.manage` |
| Kassa | `GET /cashbox`, `POST /cashbox/requests`, `/{id}/approve`, `/{id}/cancel` | `cashbox.*` |
| Moliya | `GET /finance/overview`, `POST /finance/deposit` | `finance.view` / `finance.deposit` |
| Hodimlar | `GET/POST /staff`, `PUT /staff/{id}`, `GET /staff/{id}/payroll` | `staff.view` / `staff.manage` |
| Boshqa filial | `GET /staff-directory`, `/staff-directory/{branch}` | `staff.view_all_branches` |
| Guruh-o'quvchi | `POST /groups/{id}/students`, `DELETE /groups/{id}/students/{student}` | `groups.members` |
| SMS | `GET /sms`, `POST /sms/bulk-preview` | `sms.view` / `sms.send` |
| Filiallar (sAdmin) | `GET /branches` | sAdmin |
| Davomad | `GET/POST /groups/{id}/attendance` | O'qituvchi / xodim |

Barcha javoblar: `{"success": true|false, "message": "...", "data": ..., "errors": {...}}`.
Postman: `docs/Edunova_CRM_API.postman_collection.json` faylini import qiling.

### 5.1. sAdmin uchun filial sarlavhasi (`X-Branch-Id`)

Mobil ilova sessiya (cookie) ishlatmaydi. Shuning uchun **faqat sAdmin** filial talab qiladigan so'rovlarda (masalan lid qo'shish, kassa so'rovi, moliyaga mablag' kiritish) qo'shimcha sarlavha yuborishi kerak:

```
X-Branch-Id: 3
```

Sarlavha bo'lmasa, shu turdagi yozish amallari uchun `422` xatosi qaytadi ("Bu amal uchun filialni ko'rsating"). O'qish uchun so'rovlarda (masalan `/students`) sarlavha bo'lmasa — sAdmin **barcha filiallar** ma'lumotini ko'radi (veb'dagi "Barcha filiallar" holatiga mos). Boshqa hech qanday rol (admin, menejer, o'qituvchi, o'quvchi, operator) bu sarlavhaga e'tibor bermaydi — ularning filiali doim o'z hisobiga qattiq bog'langan.

### 5.2. Firebase (push bildirishnoma) sozlash

Push xabar (telefon ekraniga chiqadigan bildirishnoma) ixtiyoriy — sozlanmasa ham tizim ishlayveradi, faqat xabarlar ilova ichidagi ro'yxatga tushadi, ekranga chiqmaydi. Yoqish uchun:

1. [Firebase Console](https://console.firebase.google.com) da loyiha yarating (yoki mavjudini oching) va Flutter ilovani shu loyihaga ulang.
2. **Project settings → Service accounts → Generate new private key** — bitta JSON fayl yuklanadi. Bu faylni serverda xavfsiz joyga qo'ying (masalan `storage/app/firebase-service-account.json`) — **hech qachon Git'ga yoki zip'ga qo'shmang**.
3. `.env` faylga qo'shing:
   ```
   FIREBASE_PROJECT_ID=sizning-loyiha-id
   FIREBASE_CREDENTIALS_PATH=/to/liq/yol/storage/app/firebase-service-account.json
   ```
4. `php artisan config:cache` (agar production'da keshlangan bo'lsa).
5. Tekshirish: **Bildirishnomalar** sahifasidan sinov xabar yuboring, mobil ilova (haqiqiy qurilma tokeni bilan) push xabarni oladimi tekshiring.

Ilova tomonida (Flutter, mobil dasturchi tomonidan qilinadi): Firebase SDK ulanadi, qurilma push tokeni olinadi va login/kirishdan keyin `POST /me/device-token` orqali serverga yuboriladi.

## 6. Serverga joylash (keyinroq)

1. Fayllarni serverga yuklang (`vendor/`, `node_modules/`, `.env` ni **yuklamang**). Serverda `composer install --no-dev` bajaring.
2. Serverda `.env` ni yangidan yarating: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://edunova.uz/public`, yangi DB parollari.
3. `php artisan key:generate`, `php artisan migrate --force`, `php artisan db:seed --force`.
4. `storage/` va `bootstrap/cache/` papkalariga yozish ruxsati bering.
5. Loyiha `public_html` ichiga qo'yilsa, ildizdagi `.htaccess` tayyor: sayt `https://edunova.uz/public/...` manzilida ishlaydi va `.env`, `vendor`, `database` kabi papkalar tashqaridan yopiladi. Yaxshiroq variant: hosting sozlamasida document root'ni `public` papkasiga o'zgartirish (API manzili `https://edunova.uz/api/...` bo'ladi).
6. **Xavfsizlik:** eski loyiha arxividagi barcha parol va kalitlar (DB, SMS, pochta, OpenAI) ochiq bo'lib qolgan hisoblanadi - ularni almashtiring.
7. **Zaxira nusxa (MySQL/MariaDB):** kunlik avtomatik zaxira (`php artisan app:backup`, `routes/console.php`da soat 03:00ga rejalashtirilgan) serverda `mysqldump` dasturi va PHP'da `proc_open` funksiyasi yoqilgan bo'lishini talab qiladi. Ko'p shared hosting/cPanel muhitida `proc_open` xavfsizlik uchun o'chirilgan bo'ladi - shuni yoqishni (yoki VPS/ixtisoslashtirilgan hostingga o'tishni) so'rang. Muammo bo'lsa, aniq sababi «Tizim holati» sahifasida ko'rinadi (log fayliga kirish shart emas); sahifadagi **«Hozir zaxira olish»** tugmasi bilan tuzatgandan keyin darhol qayta sinab ko'rish mumkin.

## 6.1. Katta yuklamada: navbat va keshni Redis'ga o'tkazish (ixtiyoriy)

Standart holatda navbat (`queue:work`) va kesh baza/fayl orqali ishlaydi — kichik va o'rta filial soni uchun yetarli. O'quvchi va SMS/AI so'rovlari ko'payib sezilarli yuklama paydo bo'lsa (masalan bir nechta `queue:work` jarayonini parallel ishlatish kerak bo'lsa), **kod o'zgarmasdan** Redis'ga o'tish mumkin — `config/queue.php`, `config/cache.php` va `config/database.php` buni allaqachon to'liq qo'llab-quvvatlaydi:

1. Serverga Redis o'rnating (masalan `apt install redis-server`) va `composer require predis/predis` bilan PHP kutubxonasini qo'shing (yoki serverda tayyor `redis` PHP kengaytmasini yoqing).
2. `.env`: `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `REDIS_HOST=127.0.0.1`, `REDIS_PORT=6379` (kerak bo'lsa `REDIS_PASSWORD`).
3. `php artisan config:cache`, so'ng `queue:work` ni (kerak bo'lsa `supervisor` bilan bir nechta nusxada) qayta ishga tushiring.

Bu faqat sozlama, dastur kodiga tegmaydi; bitta serverda o'rtacha yuklamada hozircha shart emas.

## 7. Testlar

```bat
php artisan test
```

Testlar xotiradagi SQLite bazasida ishlaydi (MySQL'ga tegmaydi). Ular kirish, filiallar (yopish va butunlay o'chirish), filial cheklovi (shu jumladan boshqa filialni faqat ko'rish), ruxsatlar, pul oqimi (ikki bosqichli tasdiqlash, storno, ehson, chegirmalar, shaxsiy mablag' kiritish, xarajat turkumi), statistika/dashboardlar, kalendar, kassa smenasi, filiallar xodimlari, ochiq murojaat brendlashi, navbat (queue) va cron tekshiruvi, mobil API (xodim va o'quvchi bo'limlari, `X-Branch-Id` sarlavhasi), push bildirishnoma (Firebase, FCM javobi soxtalashtirilib) va API xato formatini tekshiradi (**429 ta**). MariaDB'da qat'iy `ONLY_FULL_GROUP_BY` rejimida ham to'liq o'tgan. MariaDB bilan tekshirish uchun: `DB_CONNECTION=mysql DB_DATABASE=edunova_test ... php artisan test` (bo'sh sinov bazasi kerak).

## 8. Dizaynni o'zgartirish (ixtiyoriy)

```bat
npm install
npm run dev      REM ishlab chiqish
npm run build    REM tayyor fayllarni yig'ish (public/build)
```

Ranglar `tailwind.config.js` da (`brand` - Edunova qizili), komponent stillari `resources/css/app.css` da.

## 9. Loyiha tuzilmasi

```
app/Enums          Role, BranchStatus, UserStatus
app/Models         Branch, User, AuditLog, ... (Concerns/BelongsToBranch - avtomatik filial filtri)
app/Policies       UserPolicy - kim kimni boshqaradi
app/Services       AuthService, PermissionAssignmentService, GroupService, EnrollmentService, BalanceService,
                   AttendanceService, PaymentService, WalletService, CashboxService, FinanceService, PayrollService,
                   FcmService (push/Firebase), NotificationService (v11), ...
app/Http/Controllers/Api   Mobil API kontrollerlari (v11: Me, Notification, Branch, Dashboard, Lead, Cashbox,
                   Finance, Staff, StaffDirectory, Sms, GroupStudent va boshqalar)
app/Support        BranchContext (joriy filial, v11: sAdmin uchun `X-Branch-Id` sarlavhasi), PermissionRegistry
config/permissions.php   Barcha ruxsatlar va rol shablonlari
resources/views    Blade sahifalar (o'zbek tilida)
routes/web.php, routes/api.php
tests/             Avtomatik testlar
API_DOC.md         To'liq mobil API hujjati (v11)
```

**Yangi filialga tegishli jadval qo'shish qoidasi:** jadvalda `branch_id` ustuni bo'ladi, modelda `use BelongsToBranch;` yoziladi - shundan keyin foydalanuvchi avtomatik faqat o'z filiali yozuvlarini ko'radi va yangi yozuvga `branch_id` o'zi qo'yiladi.

## 10. Reja (keyingi bosqichlar)

- ✅ 1-bosqich: asos, filiallar, ruxsatlar
- ✅ 2-bosqich: o'quvchilar, guruhlar, davomad, sozlamalar
- ✅ 3-bosqich: to'lovlar, kassa, moliya, o'qituvchi va hodim ish haqi
- ✅ 4-bosqich: varonka (lidlar), kurslar (video/audio/test), SMS (Eskiz)
- ✅ 5-bosqich: hisobot, statistika, Excel import/eksport, AI yordamchi, mobil API to'liq
- ✅ v6: summa/telefon formatlash, chegirma qoidalari, aksiyalar avtomatik, Yordam (AI)
- ✅ v7: ikki bosqichli tasdiqlash, ehson naqt/plastik, grafikli dashboardlar, harakatlar jurnali
- ✅ v8: storno/qaytarishni rad etish, Tizim holati, Operator lavozimi, avtomatik SMS, chek/shartnoma chop etish, kassa smenasi
- ✅ v9: filiallar xodimlari ro'yxati, moliyaga shaxsiy mablag' kiritish, ochiq murojaat sahifasida filial brendi, lead→o'quvchi+guruh, telefon nusxalash
- ✅ v10: filiallarni faqat ko'zdan kechirish (Operator), guruhni davom ettirishda qarzdor istisnosi, Moliya sahifasi va xarajat turkumi, navbat (queue) tekshiruvi, filial rangi ko'rinishi, bosh sahifa yangilanishi, filialni butunlay o'chirish, kichikroq sinov ma'lumotlari
- ✅ v11: mobil ilova (Flutter) uchun to'liq API — xodimlar bo'limi (Varonka, Kassa, Moliya ko'rish, Hodimlar, SMS, boshqa filial, guruhga biriktirish), o'quvchi uchun "Mening guruhlarim" va umumiy davomat tarixi, to'liq push bildirishnoma (Firebase/FCM) va sAdmin uchun yangi Bildirishnomalar bo'limi, sAdmin mobil filial almashtirish (`X-Branch-Id`), to'liq `API_DOC.md`

Keyingi mumkin bo'lgan takomillashtirishlar: **1Call.uz qo'ng'iroq-markazi bilan webhook integratsiyasi (shartnoma jarayonida)**, SMS matnlarini AI bilan tayyorlash, o'quvchi ketib qolish xavfini baholash, onlayn (karta) to'lov, ikki bosqichli kirish (2FA), Flutter mobil ilovaning o'zi (bu loyihada faqat server tomoni — API — tayyorlanadi).
