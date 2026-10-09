# O'zgarishlar tarixi

## 1-bosqich (2026-09-20) — Yangi asos

Loyiha noldan, toza arxitektura bilan qayta yozildi (eski koddagi xatolar ko'chirilmadi).

**Xavfsizlik**
- `.env`, `.sql`, `vendor` kabi maxfiy fayllar ildiz `.htaccess` orqali yopildi; ma'lumotlar bazasi dumpi va parollar loyihadan chiqarildi.
- Kirishda urinishlar cheklovi (5 ta xato → 1 daqiqa kutish), bloklangan foydalanuvchi va yopilgan filial xodimlari darhol chiqariladi.
- Mobil tokenlar 30 kunda tugaydi; foydalanuvchi bloklansa yoki parol almashsa, tokenlar bekor qilinadi.
- Boshqa filial ma'lumotlariga kirish tuzilma darajasida yopilgan (global filial filtri + siyosatlar).

**Yangiliklar**
- Ko'p filiallik: filial ochish, tahrirlash, yopish (arxiv), qayta ochish; sAdmin uchun filial almashtirgich.
- Filialning o'z Eskiz akkaunti (bo'sh bo'lsa, umumiy akkaunt).
- Batafsil ruxsatlar tizimi (`config/permissions.php`): sAdmin admin/menejer/o'qituvchi ruxsatlarini belgilaydi; admin faqat o'zidagi ruxsatlarni o'z filialidagi menejer va o'qituvchilarga beradi.
- Hodimlar (admin, menejer) boshqaruvi, harakatlar jurnali (audit), profil.
- Yangi ATKO dizayni: qizil-oq, yorug'/qorong'i rejim, telefonga moslashgan.
- Mobil API v1 asosi: login, profil, parol, chiqish; yagona javob va xato formati.
- Barcha interfeys va xato xabarlari o'zbek tilida.

**Sifat** 
- 33 ta avtomatik test (kirish, filial cheklovi, ruxsat qoidalari, API).
- Nomlar tozalandi: `techer`→`teacher`, `meneger`→`manager` va h.k.
- Keraksiz paketlar (`laravel/ui`, Bootstrap, Sass va boshqalar) olib tashlandi.

**1.1 (tuzatish):** Laravel 11 dagi ochiq zaifliklar sababli `composer install` bloklanardi - framework `^12.0` ga o'tkazildi.

## 2-bosqich (2026-09-21) — O'quvchilar, guruhlar, davomad

**Yangiliklar**
- **O'quvchilar:** qo'shish, tahrirlash, qidiruv, qarzdorlar, arxiv; profil (balans, guruhlar tarixi, balans harakatlari, harakatlar tarixi). Mobil logini telefondan, paroli tasodifiy va bir marta ko'rsatiladi.
- **Guruhlar:** dars kunlari (toq/juft/har kuni) dam olish kunlarini o'tkazib avtomatik tuziladi; xona va o'qituvchi band bo'lsa xato (bazada ham unique-cheklov); narx guruh ochilganda saqlanadi; guruhni davom ettirish.
- **Balans jurnali:** har bir yechish/qaytarish/jarima/chegirma `balance_transactions` da qoladi, balans qulflangan holda yangilanadi.
- **Davomad:** faqat dars kunida, faqat bugungi kun; olingan davomadni shu kun tahrirlash mumkin; o'qituvchi faqat o'z guruhida; davomad jadvali (olingan/olinmagan/kelgusi kunlar).
- **Davomad statistikasi:** kunlik (guruhlar kesimida) va oylik (guruhlar, kunlar dinamikasi, eng ko'p qoldirayotganlar).
- **Sozlamalar:** xonalar, dars vaqtlari, narx rejalari, o'quvchi manbalari, dam olish kunlari (1 yilga avtomatik), kurslar. Eskini o'chirish o'rniga faolsizlantiriladi.
- O'qituvchilar boshqaruvi (Hodimlar bo'limida), menejerga `teachers.manage` ruxsati bilan.
- Mobil API: guruhlar, guruh ma'lumoti, davomad jadvali va davomad saqlash.

**Eski koddagi kamchiliklar tuzatildi**
- Xona/o'qituvchi band bo'lganda ham guruh ochilardi - endi taqiqlanadi.
- Davomadni istalgan kun uchun saqlash mumkin edi - endi faqat dars kuni va faqat bugun.
- Guruhga qo'shish va chiqarish tranzaksiyasiz edi (ikki marta bosilsa balans buzilardi) - endi qulflangan tranzaksiyada.
- Chegirma qoidasi va jarima chegaralari aniq va testlangan.

**Sifat:** avtomatik testlar 66 tagacha ko'paydi; MariaDB (MySQL) bilan ham sinaldi.

## 3-bosqich (2026-09-22) — To'lovlar, kassa, moliya, ish haqi

**Yangiliklar**
- **To'lovlar:** naqt/plastik qabul qilish, to'lovlar ro'yxati (davr, tur, kassir bo'yicha jami summalar), qarzdorlar; o'quvchi sahifasida to'lov/chegirma/qaytarish paneli.
- **Chegirmalar:** oldindan to'lov chegirmasi (guruh bo'yicha jami to'lov asosida, bir marta), admin chegirmasi (narx rejasidagi chegara bilan), aksiyalar (davr + minimal summa, o'quvchiga bir marta).
- **Qaytarish:** balans va kassa bilan tekshiriladi, admin tasdiqlaydi.
- **Kassa:** chiqim/xarajat so'rovi → tasdiqlash/bekor qilish; bugungi tushum.
- **Moliya:** naqt, plastik va ehson balanslari, ehson foizi, moliyadan chiqim va xarajat, 90 kunlik harakatlar.
- **Ish haqi:** o'qituvchi hisob-kitobi (stavka, bonus, davomad bo'yicha), to'lash, tarix; hodimlar ish haqi.
- Bosh sahifada bugungi tushum va qarzdorlar.
- Mobil API: `GET /me/balance`.

**Eski koddagi kamchiliklar tuzatildi**
- Barcha pul harakati bitta jurnal (`wallet_transactions`) orqali o'tadi; qoldiq qulflab yangilanadi, manfiyga tushmaydi (eski tizimda kassa/balans manfiy bo'lishi mumkin edi).
- Plastik chiqimda ehson foizi naqt balansdan olinib, uni manfiyga tushirardi — endi o'sha chiqim summasidan ajratiladi.
- Chegirma faqat bitta to'lov aynan (narx − chegirma) ga teng bo'lsa berilardi; endi guruh bo'yicha jami to'lov hisobga olinadi va ikki marta berilmaydi.
- Qaytarish balansdan ko'p bo'lishi mumkin edi — endi taqiqlangan.
- Menejer statistikasi hisoblagichlari (qo'lda "tozalash") o'rniga to'lovlar jadvalidan davr bo'yicha hisob (5-bosqichda hisobotlarda).

**Sifat:** 90 ta avtomatik test; 2-bosqichdan yangilash (migratsiya) MariaDB'da mavjud ma'lumotlar bilan sinaldi.

## 4-bosqich (2026-09-23) — Varonka, kurslar, SMS

**Yangiliklar**
- **Varonka:** ochiq murojaat shakli (`/apply/{filial}`, bot va takror yuborishdan himoyalangan), holatlar, izohlar tarixi, o'quvchiga aylantirish (dublikatsiz), manbalar bo'yicha statistika va qabul foizi.
- **Kurslar:** video, audio (fayl yuklash yoki havola), test savollari; kitoblar ro'yxati.
- **O'quvchi testlari:** server tomonida tekshiriladi, natijalar saqlanadi (mobil API).
- **SMS (Eskiz):** filial bo'yicha yoqish/o'chirish, shablonlar, filialning o'z akkaunti yoki umumiy akkaunt, navbat orqali yuborish (3 marta qayta urinish), tarix va xato sabablari, ommaviy yuborish, tug'ilgan kun tabriklari, telefon raqamini avtomatik tekshirish/formatlash.
- Mobil API: kurslar, video/audio, test boshlash/topshirish/natijalar, kitoblar.

**Eski koddagi kamchiliklar tuzatildi**
- Eskiz login/paroli kodga yozib qo'yilgan edi va log fayliga yozilardi — endi faqat `.env`/filial sozlamasida (shifrlangan), logga yozilmaydi.
- Testning to'g'ri javobi mijozga yuborilardi va natijani mijoz o'zi bildirardi — endi server tekshiradi.
- SMS xabar hisoblagichi qo'lda yuritilib manfiyga tushardi (−1009) — endi tarixdan hisoblanadi.
- SMS o'quvchiga "Parol: password" deb yuborilardi — endi har bir o'quvchiga tasodifiy parol.
- Murojaatni ro'yxatga olishda telefon takrorlansa dublikat o'quvchi yaratilardi.
- Filiallar SMS'i standart holatda o'chiq (kutilmagan xarajatning oldini olish uchun).

**Sifat:** 117 ta avtomatik test; 3-bosqichdan yangilash MariaDB'da mavjud ma'lumotlar bilan sinaldi.

## 5-bosqich (2026-09-24) — Statistika, hisobotlar, import, AI

**Yangiliklar**
- **Statistika:** yagona hisoblash manbasi (`StatisticsService`), aniq ta'riflar, davr tanlash, 12 oylik dinamika, filiallar solishtiruvi (sAdmin). Ruxsatga qarab pul ko'rsatkichlari yashiriladi.
- **8 ta hisobot** + Excelga yuklash (o'zimizning .xlsx yozuvchi: sarlavha, ustun kengligi, qotirilgan sarlavha qatori, yig'indilar).
- **Excel import:** namuna fayl, ikki bosqichli (tekshiruv → tasdiq), takror/xato aniqlash, yangi filial ochish (faqat sAdmin, tasdiqdan keyin), boshlang'ich balans, bir martalik login/parol fayli. `.xlsx` va `.csv`, Excel sana raqamlari qo'llab-quvvatlanadi.
- **AI yordamchi (OpenAI):** ruxsat va filial doirasida ishlaydigan tahlilchi (asboblar orqali), ism/telefonsiz, kunlik limit, suhbatlar tarixi; AI bilan test savoli yaratish (ko'rib chiqib saqlash).
- **Mobil API to'liq:** xodimlar uchun o'quvchilar va to'lov qabul qilish, statistika; o'qituvchi uchun ish haqi.

**Eski koddagi kamchiliklar tuzatildi**
- Hisobotlar va statistika turli joylarda alohida hisoblanardi — endi bitta manba, testlar bilan aniq raqamlar tekshirilgan.
- Excel import eski tizimda ma'lumotlarni ko'rib chiqmasdan yozardi va dublikat yaratardi — endi tekshiruv oynasi va takror himoyasi bor.
- Excel uchun tashqi paket kerak emas (Composer xavfsizlik ogohlantirishlari xavfi yo'q).

**Sifat:** 144 ta avtomatik test; MariaDB'da `ONLY_FULL_GROUP_BY` qat'iy rejimida ham sinaldi; hosil qilingan .xlsx fayllar mustaqil kutubxona (openpyxl) bilan tekshirildi.

## v6 (2026-09-25)

**Yangiliklar**
- Summa maydonlarida avtomatik xona ajratish (`1 000 000`); telefon maydonlarida qat'iy `+998 90 123 4567` niqobi.
- Telefon qoidalari: asosiy telefon majburiy, bir filialda bir rolda takrorlanmaydi (DB unique indeks + tekshiruv), rollar orasida ruxsat; import va murojaatlarda avtomatik formatlash.
- Chegirma muddati filial sozlamasida (oldin/keyin kun); guruhga qo'shilganda berilgan chegirma to'lovlarga yoziladi (ikkinchi marta berilmaydi); aksiyalar avtomatik (eng katta bonusli, bir martadan).
- Qarzi bor o'quvchi guruhga (va davomiy guruhga) qo'shilmaydi; balans ko'rinadi.
- Varonka: tanlangan filial bo'yicha forma havolasi, iframe kodi va yengil embed sahifa; har bir murojaat uchun AI tahlili.
- Yordam (AI): rol va ruxsatga mos, tizim qo'llanmasi (`resources/ai/knowledge.md`) asosida; tahlilchi AI ham shu qo'llanmadan foydalanadi.
- Kassa tarixi uchun alohida ruxsat (`cashbox.history`).
- 5 filialli sinov ma'lumotlari (`DemoSeeder`).

**Tuzatildi**
- Guruhga qo'shilganda berilgan chegirma to'lovlar jadvalida yo'q edi, shu sabab keyingi to'lovda ikkinchi marta chegirma berilishi mumkin edi.
- Oldindan to'lov chegirmasi muddati 3 kunga qattiq bog'langan edi.

**Sifat:** 168 ta avtomatik test; v5 → v6 migratsiyasi takrorlangan va noto'g'ri telefonli ma'lumotlar bilan MariaDB'da sinaldi; barcha rollar (admin, menejer, o'qituvchi) bilan 5 filialli sinov ma'lumotlarida qat'iy SQL rejimida sahifalar tekshirildi.

## v7 (2026-09-26)

**Yangiliklar**
- **Ikki bosqichli tasdiqlash:** moliyadan chiqim, xarajat, ehson chiqimi va ish haqi to'lovi «Tekshiring» sahifasidan o'tadi; tasdiq bir martalik, 15 daqiqalik, foydalanuvchiga bog'langan, bajarilishida ruxsat va mablag' qayta tekshiriladi.
- **Ehson naqt/plastik:** yangi hamyonlar `treasury_charity_cash` / `treasury_charity_card`, moliyada jami va bo'linma ko'rinadi, alohida «Ehson chiqimi» (`finance/charity-withdraw`).
- **Qarzdorni istisno tariqasida guruhga qo'shish:** yangi ruxsat `groups.enroll_debtor` (admin, sAdmin), jurnalga yoziladi.
- **Chegirma bir nechta to'lov bilan:** jami to'lov + umumiy qarz guruh narxidan oshmasligi sharti; guruhlar bo'yicha talab yig'ilib boradi (bitta to'lov ikki marta hisoblanmaydi).
- **Varonka voronkasi** va davr filtri; **Statistika** va **Davomad statistikasi** grafikli dashboardlarga aylantirildi (har birida jadval ko'rinishi), Chart.js qo'shildi (`npm`, tayyor `public/build` zip ichida).
- **Yordam** menyusi birlashtirildi (Qo'llanma + Tahlil (AI)); `/ai` eski manzili yo'naltiradi; suhbatlar rejim bo'yicha ajratilgan.
- **Harakatlar jurnali** filial bo'yicha, sAdmin uchun filial filtri; sAdmin harakatlari tanlangan filialga yoziladi.
- **Bosh sahifa kalendari** (dars kunlari, vaqt, xona, guruh, o'qituvchi; guruhga havola).
- **O'quvchi sahifasida** balans harakatlari va tarix bitta jadvalda, to'lov usuli (naqt/plastik) ko'rinadi.

**Ma'lumotlar bazasi** (`2026_09_26_000001_v7_charity_split_payment_link_permissions`, faqat qo'shimcha): `balance_transactions.payment_id` (eski yozuvlar mos to'lovga bog'lanadi), eski ehson qoldig'ini naqt/plastikka bo'lish (jurnal bilan), adminlarga yangi ruxsat, filialsiz jurnal yozuvlarini foydalanuvchi filialiga biriktirish. `down()` ehson qoldig'ini qaytaradi.

**Tuzatildi**
- Ish haqi va moliya amallarida noto'g'ri kiritilgan summani tuzatib bo'lmasdi (endi tasdiqlashdan oldin tekshiriladi).
- Davomad statistikasidagi CSS ustunlar ko'rinmas edi (yangi grafiklar bilan almashtirildi).
- Alohida «AI yordamchi» ro'yxati yordam suhbatlarini ham aralashtirib ko'rsatardi.

**Sifat:** 199 ta avtomatik test (31 ta yangi); MariaDB 10.11 da `ONLY_FULL_GROUP_BY` qat'iy rejimida to'liq test to'plami o'tdi; v6 → v7 migratsiyasi 5 filialli sinov ma'lumotlarida (290 ta to'lov bog'landi, ehson bo'lindi) va rollback bilan sinaldi; barcha sahifalar admin va sAdmin bilan, yorug' va qorong'i rejimda tekshirildi.

**Cheklovlar**
- «Guruhlar bo'yicha tushum» grafigi faqat guruh ko'rsatilgan to'lovlarni hisoblaydi (guruhsiz to'lovlar va qaytarishlar kirmaydi).
- Kalendarda o'qituvchi boshqa o'qituvchining guruhini ko'radi, lekin ocholmaydi (mavjud ruxsat qoidasi saqlangan).
- Eski o'quvchi balans yozuvlarini to'lovga bog'lash vaqt (±3 soniya), summa va kassir bo'yicha moslanadi; mos kelmaganlarida usul «—» ko'rinadi.

## v8 (2026-09-28)

**Yangiliklar**
- **Rebrend:** loyiha nomi "ATKO CRM" dan **Edunova CRM**ga o'zgardi — matnlar, logotip, brend rangi, favicon; sana/oy nomlari o'zbekcha (`uz_Latn`) chiqadi.
- **Ikki marta yuborishdan himoya:** pul va boshqa muhim formalar bir necha marta bosilganda bir necha marta bajarilib ketmaydi (`_once` token / `Idempotency-Key`); xavfli amallarda (bekor qilish, storno) oldin tasdiqlash oynasi chiqadi.
- **To'lovni bekor qilish (storno):** xato kiritilgan to'lov, chegirma yoki bonusni admin va sAdmin sabab ko'rsatib bekor qila oladi (`payments.reverse`, mavjud adminlarga avtomatik beriladi) — yozuv o'chirilmaydi, teskari yozuv bilan yopiladi; hisobot va o'quvchi sahifasida to'g'ri (ikki marta hisoblanmasdan) ko'rinadi. **Qaytarishni rad etish:** noto'g'ri yuborilgan qaytarish so'rovi ham sabab bilan rad etiladi.
- **Avtomatik zaxira nusxa** (`php artisan app:backup`, jadval bo'yicha kunlik, eskilari avtomatik o'chadi) va sAdmin uchun **"Tizim holati"** sahifasi: baza/zaxira holati, navbat (queue) va rejalashtiruvchi (cron) ishlayotgani, manfiy balanslar, `APP_DEBUG` production'da yoqiq qolmagani kabi muhim belgilar bir joyda.
- **Operator** — yangi lavozim: menejerga o'xshash mexanizmda, lekin standart holatda torroq (kassa va kurslarsiz — faqat o'quvchi qabul qilish, oddiy to'lov, davomad, lid bilan ishlash). Admin xohlasa kengaytiradi yoki toraytiradi.
- **Kunlik vazifalar paneli:** bosh sahifada rolga mos «bugun e'tibor kerak» ro'yxati (masalan yangi murojaatlar, kelmagan to'lovlar, davomad olinmagan guruhlar); ko'rib chiqilgan vazifani belgilab qo'yish mumkin.
- **Avtomatik SMS:** qarzi bor o'quvchiga eslatma va darsga kelmaganida ota-onaga xabar — ikkisi ham filial sozlamasida alohida yoqiladi/o'chiriladi.
- **Chop etish:** to'lov cheki (kassa printeri uchun 80mm yoki A5) va o'quv shartnomasi (A5, filial rekvizitlari bilan) brauzerdan to'g'ridan-to'g'ri chop etiladi.
- **O'quvchi eslatmalari:** xodimlar o'quvchi profiliga faqat xodimlarga ko'rinadigan ichki eslatma yoza oladi (o'quvchiga ko'rinmaydi).
- **Ommaviy SMS'da oldindan ko'rish:** yuborishdan oldin nechta o'quvchiga qanday matn ketishi ko'rsatiladi, faqat tasdiqlangandan keyin navbatga qo'yiladi.
- **O'tgan kun davomatini tuzatish** (`attendance.edit_past`, alohida beriladigan ruxsat): xato kiritilgan o'tgan kungi davomadni tuzatish, sababi jurnalga yoziladi.
- **Kassa smenasini yopish** (`cashbox.close`, admin/sAdmin): tizim kutgan (jurnal bo'yicha) va xodim qo'lda sanagan haqiqiy naqtni solishtirish; farq (kam/ortiqcha) faqat yoziladi — kassa balansi bu bilan hech qachon o'zgartirilmaydi. **Xarajat turlari:** xarajat so'roviga ixtiyoriy turkum (masalan Ijara, Kommunal) biriktiriladi, pul oqimi hisobotida turkum bo'yicha yig'indi chiqadi.

**Xavfsizlik**
- SMS tarixidagi parollar endi berkitib ko'rsatiladi (avval ochiq matn edi).
- HTTP xavfsizlik sarlavhalari, API so'rovlariga chegaralash (throttle) va `trustProxies` sozlandi (proksi/load-balancer orqasida ham foydalanuvchi IP'i to'g'ri aniqlanadi).
- Faqat HTTPS orqali ishlaganda avtomatik yoqiladigan **HSTS**; `.env.example` production uchun eslatmalar bilan to'ldirildi.
- Uzoq vaqt almashtirilmagan parol uchun foydalanuvchiga eslatma.
- Filtr/qidiruv sahifalari kutilmagan qiymat kiritilganda endi 500-xato bermaydi, tushunarli xabar ko'rsatadi.

**Ma'lumotlar bazasi** (10 ta yangi migratsiya, hammasi qo'shimcha — mavjud ma'lumot buzilmaydi): `submission_tokens`; `payments`ga storno va qaytarishni rad etish ustunlari (+ mavjud adminlarga `payments.reverse` avtomatik); SMS parolini berkitish; `users.password_changed_at`; `users.role` enumiga `operator`; `task_dismissals`; SMS avtomatik xabar bayroqlari; guruh/shartnoma maydonlari; `student_notes`; `expense_categories` + `cash_closings` + `cash_requests.category_id`.

**Tuzatildi**
- To'lovlar hisoboti bir nechta alohida yuruvchi hisob-kitob o'rniga bitta yurishda hisoblaydigan qilib soddalashtirildi (natija bir xil, ancha tezroq).
- Sana bo'yicha filtrlanadigan hisobotlar (to'lovlar, o'quvchilar, lidlar, davomad, ish haqi, pul oqimi) ma'lumotlar bazasi indeksidan to'g'ri foydalanadigan ko'rinishga o'tkazildi — katta hajmda sezilarli tezroq.
- Kichik tuzatishlar: kurslarni ko'rish ruxsati (`courses.view`) aniqlashtirildi.

**Sifat**
- **358 ta avtomatik test**, barchasi o'tdi (filial izolyatsiyasi, ruxsatlar, storno/rad etish, kassa smenasi va xarajat turkumlari, xavfsizlik sarlavhalari, ikki marta yuborishdan himoya va boshqalar uchun yangi testlar bilan).
- MariaDB 10.11'da `ONLY_FULL_GROUP_BY` qat'iy rejimida to'liq test to'plami tekshirildi; v7 → v8 migratsiyasi mavjud ma'lumotlar bilan sinaldi.
- **Ishlash tekshiruvi (A8):** 5000 o'quvchi, ~100 000 davomad yozuvi, 15 000 to'lovli sinov ma'lumotida barcha asosiy sahifalar o'lchandi — ko'pchiligi 15-125 ms; ikkita nisbatan sekinroq sahifa (Statistika ~470-490 ms, To'lovlar hisoboti ~400-600 ms) topildi, hisobot sahifasi tezlashtirildi (yuqorida), Statistika shu hajmda ham qabul qilinadigan chegarada qoldi (tez-tez emas, vaqti-vaqti bilan ochiladigan sahifa).

**Cheklovlar**
- O'quv shartnomasi shabloni — namuna matn, yurist tomonidan tasdiqlanmagan; ishlatishdan oldin o'z yuristingizga ko'rsating.
- O'tgan kun davomatini tuzatish o'sha kundagi emas, guruhning joriy tarkibidan foydalanadi.
- Mobil push-bildirishnoma, onlayn (kartadan internet orqali) to'lov va ikki bosqichli kirish (2FA) hali yo'q.
- Statistika sahifasining 5000 o'quvchilik hajmdagi ~470-490 ms yuklanish vaqti tubdan optimallashtirilmagan (admin uchun qabul qilinadigan chegarada deb baholandi).

## v9 (2026-09-29)

**Yangiliklar**
- **Filiallar xodimlari** (`staff.view_all_branches`, yangi ruxsat, standart holatda faqat yangi yaratiladigan Operatorga avtomatik beriladi): barcha filiallar bo'yicha faqat ism, rol va filial ko'rsatilgan, o'qish uchun ro'yxat — o'quvchilar kirmaydi, boshqa ma'lumot (telefon, to'lov, sozlama) ko'rinmaydi. Mavjud, oddiy filial ichidagi «Hodimlar» ro'yxatidan alohida, ataylab tor va faqat o'qish uchun sahifa — filial izolyatsiyasi buzilmaydi.
- **Moliyaga shaxsiy mablag' kiritish** (`finance.deposit`, yangi ruxsat, admin/sAdmin): egasi naqt yoki plastik shaxsiy mablag'ini moliya balansiga kiritadi. Bu o'quvchi to'lovi emas, shuning uchun ehson foizi ajratilmaydi; kirim xavfli emasligi uchun (pul chiqmaydi) boshqa moliya amallaridagi kabi «Tekshiring» bosqichisiz darhol yoziladi va jurnalga yoziladi.
- **Ochiq murojaat (Varonka) sahifasi endi filial brendida:** filial tahrirlash formasida rang (HEX, fayl yuklash yo'q) va qisqa «Filial haqida» matni kiritiladi; `/apply/{filial-kodi}` sahifasida Edunova nomi/logotipi o'rniga shu filialning nomi, rangi va ma'lumoti ko'rsatiladi (rang tanlanmasa, standart rang ishlatiladi). Bir nechta filial bo'lganda filial tanlash sahifasi (`/apply`) o'zgarishsiz qoladi.
- **Guruhga qo'shish ro'yxati** endi guruh nomi bo'yicha alifbo tartibida chiqadi (avval boshlanish sanasi bo'yicha edi).
- **Leadni o'quvchi qilib ro'yxatga olishda guruhga ham qo'shish:** shu forma ichida ixtiyoriy guruh tanlanadi (`groups.members` ruxsati kerak) — bitta amalda o'quvchi yaratiladi va tanlangan guruhga qo'shiladi; guruhga qo'shishda xatolik chiqsa (masalan, band yoki qarz cheklovi), butun amal (o'quvchi yaratish ham) bekor qilinadi.
- **Telefonni bir bosishda nusxalash:** murojaatlar ro'yxati va murojaat sahifasidagi telefon raqami yonidagi tugma raqamni probelsiz (masalan `+998901234567`) buferga nusxalaydi — tashqi platformalarga (masalan qo'ng'iroq markazi) tez joylashtirish uchun.

**Ma'lumotlar bazasi** (`2026_09_29_000001_v9_branch_public_branding`, faqat qo'shimcha): `branches` jadvaliga `brand_color` (nullable, 7 belgi) va `public_about` (nullable, matn) ustunlari qo'shildi. Mavjud filiallarda bu maydonlar bo'sh qoladi — ochiq murojaat sahifasida standart rang va matnsiz ko'rinish ishlatiladi.

**Sifat**
- **376 ta avtomatik test** (18 tasi yangi: filiallar xodimlari, moliyaga shaxsiy mablag' kiritish, guruh saralash, lead→o'quvchi+guruh, telefon nusxalash, filial brendlashi), barchasi o'tdi.
- MariaDB 10.11'da `ONLY_FULL_GROUP_BY` qat'iy rejimida to'liq test to'plami (376 ta) qayta tekshirildi.

**Cheklovlar**
- Filial rangi va «haqida» matni faqat sAdmin tomonidan kiritiladi (admin o'zgartira olmaydi) — mavjud filial boshqaruvi qoidasiga mos.
- "1Call.uz" qo'ng'iroq-markazi bilan webhook integratsiyasi (shartnoma jarayonida) v9'da **ISHLATILMAYDI** — foydalanuvchining talabiga ko'ra ataylab qoldirildi. V10'da qo'shilishi mo'ljallangan; mavjud arxitektura (murojaatlar allaqachon manba/izoh saqlaydi, yangi endpoint va ruxsatlar additiv migratsiya bilan qo'shiladi) buni bloklamaydi, lekin webhook autentifikatsiyasi, tashqi ID moslashtirish va xato holatlari kelishuv tugagach alohida loyihalanadi.

## v10 (2026-09-23)

**Yangiliklar**
- **Filiallarni faqat ko'zdan kechirish** (`staff.view_all_branches` egalari uchun, odatda Operator): «Filiallar xodimlari» sahifasidagi har bir filial yonida yangi tugma — bosilganda sAdmin kabi istalgan filialning o'quvchi, guruh va lidlar ro'yxati ochiladi. Bu **faqat o'qish uchun**: sahifada birorta ham forma yoki amal tugmasi yo'q, va ko'rib chiqish paytida xodimning haqiqiy ishchi filiali (`BranchContext`, ya'ni yozish huquqi) hech qachon o'zgarmaydi — yozuv huquqi boshqa filialga "sizib chiqish" xavfisiz, asosiy filial cheklovidan mustaqil, alohida o'qish yo'li orqali amalga oshirilgan.
- **Guruhni davom ettirishda qarzdorlarni o'tkazish:** `groups.enroll_debtor` ruxsati bor admin/sAdmin endi manfiy balansli o'quvchini ham yangi ochilayotgan davom guruhiga istisno tariqasida o'tkaza oladi (avval bunday o'quvchilar avtomatik cheklab qo'yilardi); ruxsati yo'q xodim uchun eski xatti-harakat o'zgarmadi.
- **Moliya sahifasi qatorlarga qayta tartiblandi:** «Balansdan chiqim», «Xarajat», «Shaxsiy mablag' kiritish» — bir qatorda teng kenglikda; «Ehson chiqimi», «Ehson foizi» — ikkinchi qatorda teng kenglikda. Tor ekranda noqulay bo'lib qolayotgan joylashuv tuzatildi.
- **Xarajat turkumi Moliyada ham:** avval faqat Kassa xarajat so'rovida bor edi (`expense_categories`), endi Moliya → Xarajat formasida ham ixtiyoriy turkum tanlanadi; `wallet_transactions` jadvaliga yangi `category_id` ustuni qo'shildi, pul harakati tarixida turkum nomi ko'rinadi.
- **Navbat (queue) ishchisi alohida tekshiriladi:** «Tizim holati» sahifasida cron bilan bir qatorda yangi qator — `php artisan queue:work` haqiqatan ishlab turganini (shunchaki navbatga topshirilganini emas) tasdiqlaydi: signal faqat vazifa haqiqatan BAJARILGANDA yangilanadi (`Schedule::job()`, `Schedule::call()` emas). Serverga chiqarilganda "qaysi qism ishlamayapti" savoliga tezroq javob beradi.
- **Filial rangini tanlashda qiymat ko'rinadi:** avval native rang tanlagich Tailwind stili bilan deyarli ko'rinmas edi; endi maxsus `.color-swatch` stil va tanlangan rangning HEX qiymati matn sifatida yonida aniq ko'rinadi.
- **Bosh sahifa yangilandi:** «Bugungi qarzdorlar» va «24 soatdan ortiq javobsiz lidlar» panellari endi scroll bo'ladi (ro'yxat uzun bo'lsa sahifa cho'zilib ketmaydi); yangi qator — faol guruhlar va boshlanishi kutilayotgan («boshlanmagan») guruhlar soni, har birida jami (faol) o'quvchilar soni bilan.
- **Filialni butunlay o'chirish** (`BranchDeletionService`, faqat sAdmin): «Filiallar» sahifasidagi yangi «Butunlay o'chirish» tugmasi filial va unga tegishli **BARCHA** ma'lumotni (hodim, o'quvchi, guruh, davomad, to'lov, kassa, lid, SMS, AI suhbat, kurs kontenti va h.k.) bitta tranzaksiyada, FK bog'liqligiga mos qat'iy tartibda, qaytarib bo'lmaydigan tarzda o'chiradi. Xato bosishdan himoya: filial nomini ANIQ (harfma-harf) qayta yozib tasdiqlash shart, aks holda hech narsa o'zgarmaydi. **Yagona istisno** — harakatlar jurnali (`audit_logs`): mavjud sxemadagi `nullOnDelete()` andozasiga mos, bu jadval o'chirilmaydi, faqat o'sha yozuvlarning `branch_id`si bo'shatiladi (o'chirish harakatining o'zi ham jurnalga yoziladi).
- **Sinov ma'lumotlari kichraytirildi:** `DemoSeeder` endi 5 ta emas, **2 ta filial** ("Test filiali", "Qarshi filiali") va sezilarli kamroq hajmda ma'lumot (15 o'quvchi, 2 o'qituvchi, 3 guruh har birida) yaratadi — tezroq ishlaydi va tekshirish osonroq. Bu sof demo/sinov muhiti uchun; production bazasiga (`db:seed`, sAdmin muhitiga) ta'siri yo'q.

**Ma'lumotlar bazasi** (1 ta yangi migratsiya, qo'shimcha): `wallet_transactions`ga `category_id` (nullable, `expense_categories`ga `nullOnDelete`).

**Sifat**
- **400 ta avtomatik test** (24 tasi yangi: filialni faqat ko'rish, guruhni davom ettirishda qarzdor istisnosi, Moliya xarajat turkumi, navbat tekshiruvi, filial rangi ko'rinishi, bosh sahifa guruh statistikasi va scroll, filialni butunlay o'chirish — to'liq o'chirish, boshqa filialga tegilmasligi, audit jurnali saqlanishi, faqat sAdmin, nom tasdiqlash), barchasi o'tdi.
- MariaDB 10.11'da `ONLY_FULL_GROUP_BY` qat'iy rejimida to'liq test to'plami (400 ta) qayta tekshirildi; filialni o'chirish real MariaDB bazasida (2 ta demo filial bilan) qo'lda ham sinaldi — faqat kerakli filial ma'lumoti o'chdi, ikkinchisi to'liq saqlanib qoldi.

**Cheklovlar**
- Filialni butunlay o'chirish operatsiyasi katta hajmdagi filial uchun (o'nlab minglab yozuv) bir necha soniya davom etishi mumkin — sahifa progress-bar ko'rsatmaydi, faqat tugagach natija chiqadi.
- Filiallarni faqat ko'rish sahifasida qidiruv/filtr yo'q — ro'yxatlar 15 tadan sahifalanadi (katta filialda ko'p sahifa kerak bo'lishi mumkin).
- "1Call.uz" qo'ng'iroq-markazi bilan webhook integratsiyasi hali ham **ISHLATILMAYDI** — shartnoma jarayoni tugamagan (v9'dagi cheklov o'zgarishsiz qoladi).

## v11 (2026-09-23)

**Yangiliklar — Mobil ilova (Flutter) uchun to'liq API**
- **Xodimlar bo'limi mobil API'da to'liq:** v10'da mobil API faqat o'quvchi/o'qituvchi uchun edi (balans, ish haqi, kurslar, davomad). v11'da admin/menejer/operator ham (ruxsatiga qarab) mobil orqali ishlay oladi: bosh sahifa vazifalari (`/dashboard/todo`), Varonka/lidlar (ro'yxat, qo'shish, izoh, bekor/qayta ochish, o'quvchiga aylantirish), Kassa (chiqim/xarajat so'rovi yaratish, ko'rish, tasdiqlash/bekor qilish), Moliya (umumiy holat ko'rish, shaxsiy mablag' kiritish), Hodimlar (ro'yxat, qo'shish, tahrirlash, ish haqi ko'rish), boshqa filiallarni faqat ko'rish (`staff.view_all_branches`), guruhga o'quvchi biriktirish/chiqarish, SMS (tarix, ommaviy yuborishni oldindan ko'rish). Har bir endpoint xuddi veb-sahifadagi bilan **bir xil ruxsat tekshiruvi va filial cheklovi**dan foydalanadi (asosan mavjud servislar — `LeadService`, `CashboxService`, `FinanceService`, `EnrollmentService` va h.k. — qayta ishlatildi, mantiq ikki marta yozilmadi).
- **Ataylab mobilga CHIQARILMAGAN amallar** (xavfsizlik uchun, faqat veb'da qoladi): moliyadan pul CHIQARISH (balansdan chiqim, xarajat, ehson chiqimi), ish haqi TO'LASH, ommaviy SMS'ni haqiqatan YUBORISH, kassa smenasini yopish, qaytarishni tasdiqlash/rad etish. Bularning barchasi veb'da ataylab ikki bosqichli tasdiqlash ("Tekshiring" sahifasi) bilan himoyalangan yuqori xavfli amallar — shu himoya darajasi mobilda hali yo'q, shuning uchun ataylab qoldirilgan.
- **O'quvchi uchun "Mening guruhlarim" va umumiy davomat:** `/me/groups` — o'quvchining barcha guruhlari (faol/tugagan) bitta ro'yxatda, holati bilan; `/me/attendance` — barcha guruhlar bo'yicha umumlashtirilgan davomat tarixi va foizi.
- **sAdmin mobil ilovada filial almashtirish (`X-Branch-Id`):** mobil ilova sessiya (cookie) ishlatmaydi, shuning uchun sAdmin har so'rovda `X-Branch-Id: <filial_id>` sarlavhasini yuborib qaysi filial bilan ishlashini ko'rsatadi (veb'dagi filial tanlagichning mobil muqobili). Sarlavha bo'lmasa, yozish amallari uchun `422` xatosi qaytadi; o'qish uchun bo'lsa — barcha filiallar ma'lumoti qaytadi. Boshqa hech qanday rolga bu sarlavha ta'sir qilmaydi (testlangan: `V11BranchHeaderTest`).

**Yangiliklar — To'liq push bildirishnoma (Firebase/FCM)**
- **sAdmin uchun yangi «Bildirishnomalar» bo'limi (veb):** sarlavha va matn yozib, «barchaga» yoki bitta filialga (filtrlab) push+ilova-ichi xabar yuboriladi. Yuborilgan xabarlar tarixi — har biri necha kishiga yetgani va nechtasi o'qilgani bilan.
- **Mobil API orqali bildirishnomalarni ko'rish:** `GET /me/notifications` — foydalanuvchiga yuborilgan xabarlar ro'yxati (o'qilgan/o'qilmagan holati bilan); `POST /me/notifications/{id}/read` va `/me/notifications/read-all` — o'qilgan deb belgilash.
- **Haqiqiy push (Firebase Cloud Messaging, HTTP v1 API):** qo'shimcha Composer paketsiz, native `openssl_sign`/`openssl_pkey_*` funksiyalari bilan xizmat hisobi (service account) JWT'si qo'lda imzolanadi va Firebase'ga so'rov yuboriladi. Har bir qabul qiluvchiga alohida fon vazifa (`SendPushNotificationJob`, 3 marta urinish) orqali yuboriladi — bittasi muvaffaqiyatsiz bo'lsa, qolganlariga ta'sir qilmaydi. Qurilma tokeni noto'g'ri/eskirgan bo'lsa (`UNREGISTERED`), avtomatik o'chiriladi.
- **Firebase sozlanmasa ham ishlaydi:** `.env`da `FIREBASE_PROJECT_ID`/`FIREBASE_CREDENTIALS_PATH` bo'lmasa, xabar baribir `notification_recipients` jadvaliga yoziladi va ilova ichidagi ro'yxatda ko'rinadi (`push_status = skipped`), faqat telefon ekraniga chiqmaydi — funksiya haqiqiy Firebase hisobisiz ham to'liq test qilinadi.
- **Qurilma tokeni ro'yxatdan o'tkazish:** `POST /me/device-token` (login'dan keyin Flutter tomonidan chaqiriladi), `DELETE /me/device-token`; chiqishda (`/auth/logout`) shu qurilmaning tokeni avtomatik o'chiriladi.

**Ma'lumotlar bazasi** (2 ta yangi migratsiya, faqat qo'shimcha):
- `device_tokens` — foydalanuvchi qurilmasining push tokeni (`user_id`, `token`, `platform`, `device_name`; bitta foydalanuvchi + qurilma nomi bo'yicha unikal).
- `notifications` + `notification_recipients` — yuborilgan bildirishnoma va har bir qabul qiluvchining o'qilgan holati/push holati.

**API hujjati**
- **`API_DOC.md`** — yangi, to'liq hujjat: barcha endpointlar (so'rov/javob JSON namunalari bilan), autentifikatsiya oqimi, xato formati, `X-Branch-Id` mexanizmi, Firebase sozlash qadamlari.

**Sifat**
- **429 ta avtomatik test** (29 tasi yangi: xodimlar mobil API'si — bosh sahifa, lidlar, kassa, moliya, hodimlar, boshqa filial, guruh-o'quvchi, SMS — 12 ta; o'quvchi mobil API'si — profil, guruhlar, davomat, xodimga taqiqlangan — 4 ta; bildirishnoma va push — sAdmin yuborish, ruxsatsiz taqiqlanishi, qurilma tokeni, chiqishda o'chirish, FCM integratsiyasi (soxta HTTP javob bilan), push holati — 7 ta; `X-Branch-Id` sarlavhasi — sAdmin bilan/bilansiz, yozish uchun majburiylik, boshqa rollarga ta'sir qilmasligi, veb sessiyaga tegilmasligi — 6 ta), barchasi o'tdi.
- MariaDB 10.11'da `ONLY_FULL_GROUP_BY` qat'iy rejimida to'liq test to'plami (429 ta) qayta tekshirildi.
- Real MariaDB bazasida (seed qilingan ma'lumot bilan) qo'lda ham sinaldi: bildirishnoma qabul qiluvchilar soni, sAdmin istisnosi, push holatlari to'g'ri ishlashi tasdiqlandi.

**Cheklovlar**
- Push xabar ishlashi uchun Firebase hisobi va xizmat kaliti (service account JSON) serverga qo'lda joylashtirilishi kerak — bu loyihaga kiritilmagan (har mijozning o'z Firebase loyihasi bo'ladi). Sozlash qadamlari: `README.md`, "Firebase (push bildirishnoma) sozlash".
- Flutter mobil ilovaning o'zi bu loyihaga kirmaydi — faqat server tomoni (API) tayyorlandi.
- Moliyadan pul chiqarish, ish haqi to'lash, ommaviy SMS yuborish, kassa smenasini yopish, qaytarishni tasdiqlash/rad etish hamon faqat veb'da (yuqorida sabab bilan izohlangan).
- Kurs materiallari (video/audio/test/kitob) va davomad olish/statistika API'lari v10'dan o'zgarishsiz qoldi — v11 faqat yangi bo'limlar (xodim, bildirishnoma) qo'shdi.

## v12 (2026-09-23)

**Yangiliklar — Mobil ilova**
- **Parolni unutgan (SMS-OTP):** `POST /auth/forgot-password` — login/email kiritilsa, shu hisobning telefoniga `SmsService` orqali 6 xonali kod yuboriladi (5 daqiqa amal qiladi, `password_reset_codes` jadvalida `Hash::make()` bilan xeshlangan holda saqlanadi, 5 marta xato urinishdan keyin bekor bo'ladi); `POST /auth/reset-password` — kod va yangi parol bilan tiklanadi, muvaffaqiyatli tiklanganda mavjud mobil tokenlar bekor qilinadi. Qidiruv faqat login/email orqali (telefon bo'yicha emas — bitta telefon bir necha rolda takrorlanishi mumkin, shuning uchun noaniqlik va hisob-sanash xavfining oldi olingan). **sAdmin uchun qo'llab-quvvatlanmaydi** (aniq bitta filiali yo'q, SMS filial orqali yuboriladi) — bu ataylab qilingan cheklov, kodda va hujjatda izohlangan.
- **Profilni tahrirlash:** `POST /me/profile` (multipart) — o'quvchi va xodim telefon raqami (`UzPhone`+`UniquePhonePerRole` bilan tekshiriladi) va profil rasmini (`Storage::disk('public')`, eski rasm avtomatik o'chiriladi) o'zgartira oladi; `remove_photo` bilan rasmni olib tashlash ham mumkin. F.I.O. va login mobil orqali o'zgartirilmaydi. Har bir o'zgarish `AuditLog`ga yoziladi.
- **Bildirishnoma deep link:** `notifications` jadvalidagi mavjud `data` ustuni endi veb compose formasida ham ishlatiladi — ixtiyoriy «Havola» (`link_type`: `payment`/`announcement`/... , `link_id`) tanlansa, FCM payload'ga ham qo'shiladi va mobil ilova bosilganda tegishli sahifaga o'tkazadi. Orqaga qarab mos: eski (havolasiz) xabarlar o'zgarishsiz ishlayveradi.
- **Ilova versiyasi tekshiruvi:** `POST /app-version/check` (`platform`, `version`) — `app_versions` jadvali (Android/iOS uchun alohida qator: `min_version`, `latest_version`, `update_url`, `message`) bilan solishtirib `force_update`/`update_available` belgisini qaytaradi. Yangi sAdmin sozlama sahifasi — **Ilova versiyasi** (yon menyu): ikkala platforma uchun alohida forma, `min_version <= latest_version` tekshiruvi bilan.

**Yangiliklar — sAdmin va tizim**
- **Filial-sarlavha birlashtirildi:** `POST /staff` (xodim qo'shish) avval sAdmin uchun so'rov tanasidagi alohida `branch_id` maydonidan foydalanardi; endi boshqa barcha sAdmin yozish amallari kabi `X-Branch-Id` sarlavhasidan foydalanadi — mobil dasturchi uchun bitta izchil qoida.
- **Navbat (queue) race condition tuzatildi:** `config/queue.php`da barcha ulanishlar uchun `after_commit => true` o'rnatildi — avval bildirishnoma yuborilgandan darhol keyin fon vazifa (push yuborish) ba'zan yozuv tranzaksiyasi hali commit bo'lmasdan ishga tushib, "topilmadi" xatosiga olib kelishi mumkin edi.
- **FCM "sozlanganmi" aniqlashi mustahkamlandi:** `SendPushNotificationJob` avval xato matnini satr sifatida solishtirar edi (`FcmService`dagi matn o'zgarsa, sinardi); endi `FcmService::isConfigured()` orqali aniq tekshiriladi.
- **Davomad tarixi so'rovi tezlashtirildi:** `AttendanceService::studentSummary()` — mobil `/me/attendance` endi butun matritsani emas, faqat kerakli o'quvchi/guruh bo'yicha yig'indini (`COUNT`/`SUM`) so'raydi.
- **Zaxira nusxa diagnostikasi:** `app:backup` endi har urinishdan keyin (muvaffaqiyatli yoki yo'q) `storage/app/backups/.status.json`ga natija va xato sababini yozadi (`mysqldump` topilmadi, `proc_open` o'chirilgan va h.k.); «Tizim holati» sahifasi shu faylni o'qib aniq sababni ko'rsatadi (avval faqat "hali zaxira yo'q" umumiy xabar chiqardi). Yangi **«Hozir zaxira olish»** tugmasi (sAdmin) navbatni kutmasdan darhol ishga tushiradi.
- **`/docs` — onlayn API hujjati:** `API_DOC.md` endi `/docs` manzilida (kirish talab qilinmaydi) o'qiladi — tashqi Composer paketsiz, o'zimizning `App\Support\MarkdownRenderer`i orqali. sAdmin menyusiga «API hujjati» havolasi qo'shildi (yangi oynada ochiladi).
- **Postman kolleksiyasi yangilandi:** `docs/Edunova_CRM_API.postman_collection.json` v12'dagi barcha 60 ta endpoint'ni qamrab oladi (bearer token avtomatik saqlanadi, `X-Branch-Id` sAdmin so'rovlarida ixtiyoriy sarlavha sifatida tayyor). `tests/Unit/PostmanCollectionTest.php` haqiqiy marshrutlar (`route:list`) bilan avtomatik solishtiradi.
- **Zip paketlash xatosi tuzatildi (v7'dan beri mavjud edi):** `public/storage` (odatda `storage:link` bilan yaratiladigan symlink) barcha oldingi zip'larda BO'SH PAPKA sifatida joylashtirilgan edi — bu esa `php artisan storage:link`ni "link already exists" xatosi bilan to'xtatib qo'yardi (audio/rasm fayllar ko'rinmay qolishi mumkin edi). v12'dan boshlab bu papka zip'ga umuman kiritilmaydi, `storage:link` toza o'rnatishda muammosiz ishlaydi. Aniqlangan usul: yangi zip'ni toza papkaga ochib, `composer install` + `migrate` + `storage:link`ni boshidan sinab ko'rish orqali (loyiha qoidasidagi majburiy tekshiruv qadami).
- **Hujjat tuzatildi (v11'dan qolgan xato):** `API_DOC.md`da bildirishnoma manzillari noto'g'ri `/me/notifications` deb yozilgan edi — haqiqiy (va v11'dan beri shunday ishlagan) yo'l `/notifications`. Shuningdek, avval umuman hujjatlanmagan `GET /branches` endpoint'i qo'shildi.

**Ma'lumotlar bazasi** (3 ta yangi migratsiya, faqat qo'shimcha):
- `password_reset_codes` — parolni tiklash kodlari (`user_id`, `code_hash`, `attempts`, `expires_at`, `used_at`).
- `users` jadvaliga `photo_path` (nullable) ustuni — profil rasmi.
- `app_versions` — platforma (unique), minimal/so'nggi versiya, do'kon havolasi, xabar matni; migratsiya android/ios qatorlarini avtomatik yaratadi.

**Sifat**
- **486 ta avtomatik test** (57 tasi yangi: parolni unutish/tiklash — 9, profilni tahrirlash — 6, bildirishnoma deep link — 5, ilova versiyasi — 8, zaxira diagnostikasi — 5, `/docs` sahifasi — 4, Markdown render'i — 9, Postman kolleksiyasi qamrovi — 2, sAdmin xodim qo'shishda `X-Branch-Id` — 4, FCM haqiqiy xatolik/eskirgan token holati — 2, bildirishnoma yuborish jurnali — 2, navbat `after_commit` sozlamasi — 1), barchasi o'tdi.
- MariaDB 10.11'da `ONLY_FULL_GROUP_BY` qat'iy rejimida to'liq test to'plami (486 ta) qayta tekshirildi — shu tekshiruv davomida bitta sinov faylidagi (`V12NotificationDeepLinkTest`) operator ustuvorligi xatosi (`&&` ustuvorligi tufayli noto'g'ri qiymat yozilishi) topildi va tuzatildi: SQLite'da chet kalit (foreign key) tekshiruvi yo'qligi sababli bu xato sezilmay qolgan edi, MariaDB'da chet kalit haqiqatan tekshirilgani uchun aniqlandi. Bu — production kodida emas, faqat sinov faylida bo'lgan xato edi.
- `MarkdownRenderer`dagi cheksiz tsikl xatosi (`API_DOC.md`dagi oddiy matn ichidagi `|` belgisi jadval deb noto'g'ri aniqlanib, paragraf tsiklini to'xtatib qo'yardi) haqiqiy hujjat ustida `timeout` bilan sinab topildi va tuzatildi; qo'shimcha xavfsizlik chizig'i (`$i` albatta oldinga siljishini kafolatlovchi tekshiruv) qo'shildi.

**Cheklovlar**
- Parolni unutish/tiklash faqat login/email orqali ishlaydi va sAdmin uchun mavjud emas (yuqorida izohlangan).
- Profil rasmini yuklash uchun serverda `php artisan storage:link` bajarilgan bo'lishi shart (README'da ko'rsatilgan).
- Ilova versiyasi tekshiruvi faqat ilova o'zi so'rov yuborsa ishlaydi — eski, hali yangilanmagan ilova versiyalari serverga umuman ulanmasa, bu haqda ma'lumot yo'q.
- `/docs` sahifasi ochiq (kirish talab qilinmaydi), chunki maqsadli auditoriya (tashqi mobil dasturchi) CRM hisobiga ega emas; qidiruv tizimlariga ko'rinmasligi uchun `noindex` belgilangan, lekin havolani bilgan har kim o'qiy oladi (maxfiy ma'lumot yo'q, faqat texnik API tavsifi).

## v13 (2026-10-08)

**Yangiliklar**
- **Eslatmalar qo'ng'iroqchasi:** yuqori panelda filialdagi faol o'quvchi eslatmalari soni va ro'yxati (har 15 soniyada yangilanadi); eslatmani o'chirmasdan «Yopish»/«Qayta ochish» (hamma uchun umumiy), son shunga qarab o'zgaradi.
- **Yangi ruxsat `payments.view_totals`:** To'lovlar sahifasidagi umumiy yig'indilarni alohida ko'rsatish/yashirish. Migratsiya mavjud adminlarga uni beradi; menejer/operatorga sAdmin yoki admin alohida beradi.
- **Lavozimni o'zgartirish:** hodimni tahrirlashda (ruxsatlar yangi lavozim shabloniga qaytadi, mobil seanslar tugaydi; tugamagan guruhli o'qituvchi uchun bloklanadi).
- **Bir nechta sAdmin:** Tizim → Super adminlar (qo'shish, bloklash/faollashtirish, olib tashlash; o'zini va oxirgi faol sAdmin'ni himoya).
- **Qo'shimcha filialli operator:** sAdmin operatorga qo'shimcha filiallarni berib/olib tashlaydi (`user_branches` jadvali); operator filial tanlagich (va mobil `X-Branch-Id`) orqali faqat ruxsat etilgan filiallarga o'tadi, boshqa filial yozuvlari ko'rinmaydi.
- **Hodim faoliyati statistikasi:** admin/menejer/operator ish haqi kartochkasida kunlik (30 kun) va oylik (12 oy) to'lovlar soni/summasi, murojaatlar, izohlar, o'quvchilar.
- **Tushum dinamikasi:** Statistikada kun/hafta/oy bo'yicha aniq dinamika, «Bugun/Hafta/Oy» kartalari va o'tgan davr bilan solishtirish.
- **Yon menyu:** bo'limlarga guruhlangan, yig'iladigan/ochiladigan menyu (holat brauzerda eslab qolinadi).

- **Kassa ruxsatlari ajratildi:** «Xarajat so'rovi» (`cashbox.request`) va yangi «Chiqim so'rovi» (`cashbox.withdraw`, moliya balansiga o'tkazish) alohida. Migratsiya `2026_10_08_000004` mavjud `cashbox.request` egalariga `cashbox.withdraw` ni ham beradi (imkoniyat o'zgarmaydi). Yangi menejerga standart shablon faqat xarajatni beradi. Mobil API: `kind=withdrawal` endi `cashbox.withdraw` talab qiladi.

- **To'liq standart shartnoma:** 10 bo'limli o'quv xizmati shartnomasi (predmet, narx va to'lov, davomat, huquq-majburiyatlar, bekor qilish va pulni qaytarish, shaxsiy ma'lumotlar, fors-major, nizolar, rekvizitlar). Sozlamalar → Shartnoma sahifasida standart matn endi oynada ko'rinadi (avval oyna bo'sh turardi) va tahrirlash mumkin. O'zi matn saqlagan filiallarga ta'sir qilmaydi.
- **Shartnoma chop etish ko'rinishi:** endi rasmiy hujjat ko'rinishida — A4, Times New Roman 12 pt, ikki chetga tekislangan bandlar, markazdagi qalin sarlavhalar, imzo bloki ikki ustunli jadvalda, sahifa raqamlari. STIR yoki direktor ismi kiritilmagan bo'lsa, qo'lda to'ldirish uchun chiziq (____) chiqadi. Oddiy matnli eski/maxsus shablonlar ham shu ko'rinishda chiqadi.

- **Guruhni to'liq tahrirlash:** xona, dars vaqti, dars kunlari, boshlanish sanasi, darslar soni va narx rejasi. O'tgan va davomad olingan darslar o'zgarmaydi, kelgusi darslar qayta tuziladi (bandlik tekshiriladi). Narx o'zgarsa faol o'quvchilar balansi servis orqali jurnalga yozib to'g'rilanadi (tasdiq bilan). Yangi ruxsat `groups.change_price` (migratsiya `2026_10_08_000005` mavjud adminlarga beradi).

**Tuzatishlar**
- Filial almashtirilganda oldingi filialning sahifasida (masalan, `/groups/6`) 404 chiqardi: endi filial almashganda har doim bosh sahifaga o'tiladi.
- Operatorga ish haqi to'lashda chiqadigan 404 xatosi (tasdiqlash sahifasi) tuzatildi.

**Yangilash:** `php artisan migrate`, `npm install && npm run build`, `php artisan test`. Yangi migratsiyalar: `2026_10_08_000001..000005`.

### v13.1 — Guruh tahriri qoidalari aniqlashtirildi
- **Yakunlangan guruh** (tugash sanasi o'tgan): xona, dars vaqti, dars kunlari, boshlanish sanasi va darslar soni o'zgarmaydi (forma maydonlari o'chiriladi, server ham rad etadi). Nom, kurs, o'qituvchi, stavka va narx tahrirlanadi.
- **Jarayondagi guruh**: boshlanish sanasi o'zgarmaydi; vaqt/xona/kunlar o'zgarsa faqat bugungi (davomad olinmagan) va kelgusi darslar yangilanadi, o'tgan darslar eski vaqtida qoladi. Davomad olingan bugungi dars o'zgarmaydi.
- **Boshlanmagan guruh**: boshlanish sanasi faqat bugun yoki keyingi kunlarga ko'chiriladi (forma `min` bilan ham cheklangan).
- Migratsiya yo'q. Testlar: `V13GroupEditTest`.
- Tuzatish: `phpunit.xml` ga `APP_URL=http://localhost` qo'shildi (testlar `.env` dagi manzilga bog'liq emas). Tasodifan o'chib ketgan mobil API kontrollerlari (`Group`, `Staff`, `Statistics`, `Finance`) qayta tiklandi, `GET /statistics/charts` qo'shildi, `routes/api.php` v13 (ikki bosqichli chiqim) holatiga keltirildi. Guruh formasidagi Blade sintaksis xatosi tuzatildi.

### v13.2 — sAdmin maxsus chegirmasi
- O'quvchi kartochkasida **«Maxsus chegirma»** bo'limi (faqat sAdmin): guruhga bog'lanmagan, narx rejasidagi `max_discount` bilan cheklanmagan bonus — kam ta'minlangan o'quvchilar uchun. Bitta chegirma **1 000 000 so'mdan oshmaydi**, sabab majburiy.
- Ikki bosqichli: «Tekshiring» sahifasida **sAdmin paroli** so'raladi (noto'g'ri parol bilan bajarilmaydi, 5 ta urinishdan keyin 10 daqiqa kutiladi).
- Faqat o'quvchi balansini oshiradi (`PaymentService::specialDiscount`, jurnalga yoziladi), kassadan pul chiqmaydi, SMS yuborilmaydi. Statistika va hisobotlarda «Chegirmalar» qatoriga kiradi, storno qilinadi.
- Ruxsatlar ro'yxatida yo'q: boshqa foydalanuvchiga (admin ham) berib bo'lmaydi. Mobil API'ga chiqarilmagan. Mavjud admin chegirmasi o'zgarmagan. Migratsiya yo'q.
