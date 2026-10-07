# Edunova CRM — Mobil API hujjati (v12)

Ushbu hujjat Flutter (yoki boshqa) mobil ilova uchun Edunova CRM serverining **to'liq API'sini** tavsiflaydi — kirish, profil, o'quvchi/xodim bo'limlari, push-bildirishnoma va v12'da qo'shilgan yangi imkoniyatlar (parolni SMS kod bilan tiklash, profil rasmi, ilova versiyasi tekshiruvi, bildirishnoma "deep link"i).

> Eski versiyalar (v1–v11) uchun alohida hujjat yo'q — API doim **eng oxirgi versiyaga** mos yuritiladi. Loyihani yangilashda faqat shu faylga qarang. Loyiha avval "ATKO CRM" deb atalgan — hozirgi nomi **Edunova CRM** (kod, DB, API maydon nomlari o'zgarmagan, faqat matn/brend).

## Mundarija

1. Umumiy qoidalar (asos manzil, avtorizatsiya, javob formati, xatolar)
2. Ilova versiyasini tekshirish (v12 — login talab qilmaydi)
3. Kirish, parolni tiklash va profil
4. sAdmin uchun filial sarlavhasi (`X-Branch-Id`) — **v12: barcha yozish amallari uchun izchil**
5. Push bildirishnoma (qurilma tokeni, ro'yxat, v12: "deep link")
6. O'quvchi bo'limi (balans, guruhlar, davomat, kurslar, testlar, kitoblar, ish haqi)
7. Guruh va davomad (umumiy, o'qituvchi/xodim)
8. Xodimlar bo'limi: bosh sahifa, Varonka, Kassa, Moliya, Hodimlar, boshqa filial, guruhga biriktirish, SMS
9. Statistika
10. Ataylab mobilga chiqarilmagan amallar
11. Firebase (FCM) sozlash — serverga qo'yish

---

## 1. Umumiy qoidalar

**Asos manzil (lokal):** `http://127.0.0.1:8000/api/v1`
**Asos manzil (production):** administrator bilan tasdiqlang — odatda `https://<domen>/api/v1` (agar hosting document root `public` papkasiga o'rnatilgan bo'lsa) yoki `https://<domen>/public/api/v1` (aks holda). README'ning "Serverga joylash" bo'limiga qarang.
**Format:** JSON (`Content-Type: application/json`, `Accept: application/json`) — rasm yuklanadigan endpoint (`POST /me/profile`) bundan mustasno, u `multipart/form-data` qabul qiladi.
**Avtorizatsiya:** Laravel Sanctum bearer token — `Authorization: Bearer <token>` (login qilinganidan keyin olinadi). Login talab qilmaydigan endpointlar: `GET /app/version`, `POST /auth/login`, `POST /auth/forgot-password`, `POST /auth/reset-password`.

### Javob formati

Har bir javob quyidagi ko'rinishda:

```json
{
  "success": true,
  "message": "Ixtiyoriy xabar matni",
  "data": { },
  "meta": { }
}
```

`message` va `meta` har doim ham bo'lmasligi mumkin. Ro'yxat qaytaradigan endpointlarda `meta` sahifalash ma'lumotini beradi: `{"page": 1, "last_page": 3, "total": 42}`.

### Xato formati

```json
{
  "success": false,
  "message": "Xato tavsifi",
  "errors": { "maydon": ["Xato matni"] }
}
```

| HTTP kod | Ma'no |
|---|---|
| 401 | Token yo'q/eskirgan — qayta kirish kerak |
| 403 | Ruxsat yo'q (`Bu amal uchun ruxsatingiz yo'q.`) |
| 404 | Topilmadi (boshqa filialga tegishli yozuv ham 404 qaytaradi — 403 emas, chunki uning borligi ham sir) |
| 422 | Validatsiya xatosi yoki sAdmin uchun filial ko'rsatilmagan (`errors` maydoni bilan yoki sababi bilan) |
| 429 | Juda ko'p so'rov (limit: daqiqasiga 120 so'rov/foydalanuvchi; login uchun daqiqasiga 20; `forgot-password` daqiqasiga 5; `reset-password` daqiqasiga 10) |

### Sahifalash

Ro'yxat endpointlarida `?per_page=20` (standart 20, maksimum 50) va `?page=2` parametrlari ishlatiladi.

---

## 2. Ilova versiyasini tekshirish (v12)

Ilova **ochilganda, login qilishdan oldin** darhol chaqiriladi — eski, endi qo'llab-quvvatlanmaydigan versiyalarni serverni qayta joylashtirmasdan majburiy yangilashga undash uchun.

### `GET /app/version?platform=android&version=1.4.0`

`platform`: `android` | `ios` (majburiy). `version`: joriy o'rnatilgan ilova versiyasi, masalan `1.4.0` (raqam.raqam.raqam ko'rinishida, majburiy).

Javob:
```json
{ "success": true, "data": {
  "force_update": false,
  "update_available": true,
  "min_version": "1.2.0",
  "latest_version": "1.5.0",
  "message": "Yangi funksiyalar qo'shildi.",
  "update_url": "https://play.google.com/store/apps/details?id=..."
}}
```

- **`force_update: true`** — joriy versiya `min_version`dan past. Ilova **davom etishdan oldin** to'liq ekranli "Majburiy yangilash" oynasini ko'rsatishi va `update_url`ga yo'naltirishi kerak (orqaga qaytarib bo'lmaydigan tarzda).
- **`update_available: true`** (`force_update` bo'lmasa) — yumshoq taklif: yopish mumkin bo'lgan bildirishnoma/banner ko'rsatiladi, ishlatishda davom etsa bo'ladi.
- `message` va `update_url` — sAdmin veb-panelda ("Ilova versiyasi" sahifasida) sozlaydi, `null` bo'lishi mumkin.
- sAdmin hali sozlamagan bo'lsa ham, javob har doim keladi (standart: `min_version = latest_version = "1.0.0"`) — endpoint hech qachon xato bermaydi.

---

## 3. Kirish, parolni tiklash va profil

### `POST /auth/login`

So'rov:
```json
{ "login": "aziz.admin", "password": "parol12345", "device_name": "Aziz iPhone 15" }
```
`login` — **username yoki email** (telefon raqami bilan kirish yo'q). `device_name` — qurilmani ajratish uchun (bir xil nom bilan qayta kirilsa, eski token o'chadi — "bitta qurilma = bitta token").

Javob (`201`/`200`):
```json
{
  "success": true,
  "message": "Tizimga muvaffaqiyatli kirdingiz.",
  "data": {
    "token": "1|abcdef...",
    "token_type": "Bearer",
    "user": {
      "id": 5, "name": "Aziz Aliyev", "username": "aziz.admin", "email": null,
      "phone": "+998901234567", "photo_url": null, "role": "admin", "role_label": "Admin",
      "branch": { "id": 1, "name": "Test filiali" },
      "permissions": ["students.view", "payments.create", "..."],
      "created_at": "2026-01-10"
    }
  }
}
```
`photo_url` — **v12**, profil rasmining to'liq (ochiq) manzili, hali yuklanmagan bo'lsa `null`.

Token muddati: 30 kun (`SANCTUM_EXPIRATION`). Muddati tugasa yoki foydalanuvchi bloklansa/paroli almashtirilsa (shu jumladan pastdagi SMS kod orqali tiklansa), token bekor bo'ladi — `401` qaytadi, qayta login kerak.

### `GET /auth/me`
Joriy foydalanuvchi va ruxsatlar ro'yxati — yuqoridagi `user` obyekti bilan bir xil.

### `POST /auth/change-password`
```json
{ "current_password": "eski12345", "password": "yangi12345", "password_confirmation": "yangi12345" }
```
Muvaffaqiyatli bo'lsa, **shu qurilmadan boshqa barcha tokenlar** bekor qilinadi (boshqa qurilmalar chiqib ketadi).

### `POST /auth/logout`
Joriy tokenni o'chiradi. Shu qurilmaga bog'langan push-token (`device_tokens`) ham o'chiriladi — chiqqan foydalanuvchiga endi push kelmaydi.

### `POST /auth/forgot-password` (v12 — "Parolni unutdingizmi", 1-qadam)

Login talab qilmaydi. So'rov:
```json
{ "login": "aziz.admin" }
```
Javob **har doim** bir xil (hisob mavjud yoki yo'qligini bilib bo'lmaydi — xavfsizlik uchun ataylab shunday):
```json
{ "success": true, "message": "Agar bunday hisob mavjud bo'lsa, telefon raqamiga tasdiqlash kodi yuborildi." }
```
Agar hisob **mavjud, faol, filialga biriktirilgan va telefon raqami bor** bo'lsa (student/teacher/manager/operator/admin — **sAdmin BUNDAN MUSTASNO**, pastga qarang), hisobning ro'yxatdan o'tgan telefon raqamiga 6 xonali SMS kod yuboriladi (10 daqiqa amal qiladi). Yangi so'rov eski kodni avtomatik bekor qiladi.

> **sAdmin uchun ishlamaydi.** sAdmin filialga bog'lanmagani uchun bu oqim orqali parolini tiklay olmaydi — boshqa sAdmin orqali (veb-panelda "Hodimlar"da yo'q, chunki sAdmin xodim emas) yoki server administratori `php artisan tinker` bilan qo'lda tiklashi kerak. Ilova UI'da sAdmin uchun alohida oqim ko'rsatish shart emas — bu amaliy jihatdan kam uchraydigan holat.

### `POST /auth/reset-password` (v12 — 2-qadam)

Login talab qilmaydi. So'rov:
```json
{ "login": "aziz.admin", "code": "482913", "password": "yangiParol99", "password_confirmation": "yangiParol99" }
```
Muvaffaqiyatli bo'lsa: parol yangilanadi, foydalanuvchining **BARCHA qurilmalaridagi tokenlar bekor qilinadi** (hisobni tiklash — eski qurilmalar ishonchsiz deb hisoblanadi, hammasi qayta login qilishi kerak).

Xato holatlari (hammasi bitta umumiy xabar bilan, kod haqida ortiqcha ma'lumot bermaslik uchun):
```json
{ "success": false, "message": "Kod noto'g'ri yoki muddati o'tgan.", "errors": { "code": ["Kod noto'g'ri yoki muddati o'tgan."] } }
```
Sabablar: kod noto'g'ri, muddati o'tgan (10 daqiqadan keyin), allaqachon ishlatilgan, yangi so'rov bilan bekor qilingan, yoki **5 marta xato urinilgan** (shundan keyin to'g'ri kod ham ishlamaydi — yangi `forgot-password` so'rovi kerak).

### `GET /me/profile`
Hamma rol uchun qisqa profil:
```json
{ "success": true, "data": { "id": 12, "name": "Nodira", "role": "student", "role_label": "O'quvchi", "phone": "+998901234567", "phone2": null, "photo_url": null, "branch": "Test filiali" } }
```

### `POST /me/profile` (v12 — profilni tahrirlash)

**`multipart/form-data`** (rasm yuklash mumkin bo'lgani uchun). Barcha maydonlar **ixtiyoriy** — faqat yuborilganlari o'zgaradi:

| Maydon | Tavsif |
|---|---|
| `phone` | Yangi telefon (qat'iy `+998 90 123 4567` ko'rinishida). Shu filial+rolda boshqa birov ishlatayotgan bo'lsa, `422`. |
| `photo` | Rasm fayli (jpg/jpeg/png/webp, maksimum 4 MB). Yuborilsa, eski rasm (bo'lsa) avtomatik o'chirib, yangisi bilan almashtiriladi. |
| `remove_photo` | `1`/`true` — mavjud rasmni o'chiradi (yangi rasm yuborilmagan bo'lsa). |

**Ism, login (username), email — bu endpoint orqali o'zgarmaydi** (xodim boshqaruvi orqali, admin/sAdmin tomonidan o'zgaradi, o'z-o'zini emas).

Javob — yangilangan profil (`GET /me/profile` bilan bir xil shakl):
```json
{ "success": true, "message": "Profil yangilandi.", "data": { "id": 12, "name": "Nodira", "role": "student", "role_label": "O'quvchi", "phone": "+998907654321", "phone2": null, "photo_url": "https://.../storage/profile-photos/12/abc123.jpg", "branch": "Test filiali" } }
```

> Serverda `php artisan storage:link` ishga tushirilgan bo'lishi kerak (audio fayllar bilan bir xil disk) — aks holda `photo_url` noto'g'ri (404) bo'ladi.

---

## 4. sAdmin uchun filial sarlavhasi (`X-Branch-Id`)

Veb-panelda sAdmin yuqoridagi filial tanlagichdan filial tanlaydi (sessiyada saqlanadi). Mobil ilova sessiya/cookie ishlatmagani uchun, **faqat sAdmin roli** uchun buning o'rniga har so'rovda HTTP sarlavha yuboriladi:

```
X-Branch-Id: 3
```

- **O'qish uchun so'rovlar** (masalan `GET /students`): sarlavha bo'lmasa — sAdmin **barcha filiallar** ma'lumotini ko'radi (veb'dagi "Barcha filiallar" holati). Sarlavha bo'lsa — faqat o'sha filial.
- **Filial talab qiladigan yozish amallari** (lid qo'shish/aylantirish, kassa so'rovi, moliyaga mablag' kiritish, **v12: hodim qo'shish**, va — sarlavha bo'lmasa 500 o'rniga endi aniq 422 qaytaradigan **v12: `GET /cashbox`, `/cashbox/expense-categories`, `/finance/overview`, `POST /sms/bulk-preview`**): sarlavha bo'lmasa, `422` qaytadi:
  ```json
  { "success": false, "message": "Bu amal uchun filialni ko'rsating (X-Branch-Id sarlavhasi)." }
  ```
- **Boshqa hech qanday rol** (admin, menejer, operator, o'qituvchi, o'quvchi) bu sarlavhaga e'tibor bermaydi — ularning filiali doim o'z hisobiga (`branch_id`) qattiq bog'langan, sarlavha yuborilsa ham e'tiborga olinmaydi.

### `GET /branches`

Filial tanlagich (picker) uchun barcha filiallar ro'yxati — `staff.view_all_branches` ruxsati bor operator yoki sAdmin ko'radi:
```json
{ "success": true, "data": [
  { "id": 1, "name": "Markaziy filial", "status": "active" },
  { "id": 2, "name": "Chilonzor filiali", "status": "active" }
]}
```
Bu **faqat ro'yxat** qaytaradi — tanlashning o'zi Flutter tomonida lokal saqlanadi va yuqoridagi `X-Branch-Id` sarlavhasi orqali keyingi so'rovlarga qo'shiladi (serverda "tanlash" so'rovi yo'q, chunki mobil API stateless).

### ⚠️ v12: dasturchi uchun MUHIM o'zgarish — `POST /staff`

v11'da sAdmin yangi xodim qo'shganda `branch_id`ni so'rov **tanasida** (`body`) yuborishi kerak edi (veb-panel formasidagi kabi). **v12'da bu olib tashlandi** — endi mobil API'da **barcha** yozish amallari bir xil mexanizmga (`X-Branch-Id`) tayanadi:

- Eski (v11, mobilda **ENDI ISHLAMAYDI**): `POST /staff` body'da `"branch_id": 3` yuborish.
- Yangi (v12): `X-Branch-Id: 3` sarlavhasini yuborish, body'da `branch_id` **umuman bo'lmasligi kerak** (bo'lsa `422` — `"branch_id": ["prohibited"]`).

Boshqa barcha sAdmin yozish amallari (`/leads`, `/cashbox/requests`, `/finance/deposit`) allaqachon shu tarzda ishlagan — bu o'zgarish faqat `POST /staff`ga tegishli, uni **X-Branch-Id bilan ishlaydigan qolgan barcha endpointlar bilan bir xil qildi**. Flutter tomonida sAdmin filial tanlaganda, tanlangan `branch_id`ni lokal saqlab, keyingi barcha so'rovlarga shu sarlavhani qo'shib yuborish kerak (masalan `dio` interceptor bilan) — bu allaqachon shunday qilingan bo'lsa, qo'shimcha o'zgarish shart emas.

---

## 5. Push bildirishnoma

### `POST /me/device-token`
Login qilingandan keyin (yoki Firebase tokeni yangilanganda) chaqiriladi:
```json
{ "token": "fcm-device-token-string", "platform": "android", "device_name": "Aziz iPhone 15" }
```
`platform`: `android` | `ios` | `web` (ixtiyoriy, standart `android`). `device_name` berilmasa, joriy token nomi ishlatiladi. Bir xil foydalanuvchi+qurilma nomi bo'yicha yozuv **yangilanadi** (dublikat bo'lmaydi).

### `DELETE /me/device-token`
Shu qurilmaga push kelishini to'xtatadi (masalan foydalanuvchi bildirishnomalarni o'chirib qo'yganda). Body ixtiyoriy: `{ "device_name": "..." }` (berilmasa joriy token nomi olinadi).

### `GET /notifications`

> **Diqqat (hujjat tuzatildi):** bu endpointning haqiqiy manzili `/notifications` — `/me/notifications` **emas** (eski hujjatda xato yozilgan edi, kod hech qachon shunday bo'lmagan).

Foydalanuvchiga yuborilgan bildirishnomalar (sAdmin tomonidan yuborilgan, o'quvchi/xodim — sAdminning o'zidan tashqari hammaga):
```json
{
  "success": true,
  "data": [
    { "id": 7, "title": "Guruh eslatmasi", "body": "Ertaga dars bor.", "data": { "link_type": "group", "link_id": 42 }, "read": false, "created_at": "2026-09-20T10:00:00+00:00" },
    { "id": 6, "title": "Bayram tabrigi", "body": "...", "data": null, "read": true, "created_at": "2026-09-19T09:00:00+00:00" }
  ],
  "meta": { "page": 1, "last_page": 1, "total": 2, "unread_count": 1 }
}
```

#### v12: "deep link" (`data.link_type` / `data.link_id`)

sAdmin bildirishnoma yuborayotganda ilovada bosilganda qayerga o'tishni tanlashi mumkin. `data` bo'lishi mumkin `null` (oddiy xabar) yoki quyidagi shakl:

| `link_type` | Ma'no | `link_id` |
|---|---|---|
| `group` | Guruh sahifasiga o'tish | Guruh ID (`GET /groups/{id}`) |
| `lead` | Lid (Varonka) sahifasiga o'tish | Lid ID (`GET /leads/{id}`) |
| `student` | O'quvchi sahifasiga o'tish | O'quvchi ID (`GET /students/{id}`) |

**Muhim:** bildirishnoma **har qanday rolga** (admin, menejer, o'qituvchi, o'quvchi, operator) yuborilishi mumkin, lekin masalan `student` turi barcha rollar uchun mos bo'lmasligi mumkin (o'quvchi o'ziga tegishli bo'lmagan `/students/{id}`ni ko'ra olmaydi — `404` qaytadi). Ilova tomonida: agar navigatsiya vaqtida server `403`/`404` qaytarsa, oddiygina bildirishnoma tafsilotini ko'rsatish bilan cheklaning (xato ko'rsatmang). Xuddi shu `link_type`/`link_id` juftligi **FCM push xabarining `data` qismida ham** keladi (barcha qiymatlar matn/string ko'rinishida, masalan `link_id: "42"`).

### `POST /notifications/{id}/read`
Bitta xabarni o'qilgan deb belgilaydi.

### `POST /notifications/read-all`
Barcha xabarlarni o'qilgan deb belgilaydi.

> Bu ro'yxat **Firebase sozlanmagan bo'lsa ham** to'liq ishlaydi — faqat shu holatda xabar telefon ekraniga (push sifatida) chiqmaydi, lekin ilova ichida ko'rinadi.

---

## 6. O'quvchi bo'limi

### `GET /me/balance`
```json
{ "success": true, "data": {
  "balance": -50000, "debt": 50000,
  "transactions": [
    { "id": 1, "type": "payment", "type_label": "To'lov", "amount": 300000, "balance_after": -50000, "group": "Matematika-1", "note": null, "created_at": "2026-09-01T09:00:00+00:00" }
  ]
}}
```

### `GET /me/groups`
O'quvchining barcha guruhlari (faol va tugagan) bitta ro'yxatda:
```json
{ "success": true, "data": [
  { "id": 4, "name": "Matematika-1", "course": "Matematika", "teacher": "Botir aka", "status": "current",
    "starts_on": "2026-08-01", "ends_on": "2026-12-01", "is_active_member": true }
]}
```

### `GET /me/attendance`
Barcha guruhlar bo'yicha umumlashtirilgan davomat:
```json
{ "success": true, "data": {
  "groups": [ { "group_id": 4, "group": "Matematika-1", "present": 18, "total": 20, "rate": 90 } ],
  "overall_rate": 90
}}
```
*(v12: server tomonida tezlashtirildi — javob shakli o'zgarmagan, faqat ko'p guruhli o'quvchilarda tezroq ishlaydi.)*

### `GET /courses`, `GET /courses/{id}/videos`, `GET /courses/{id}/audios`, `GET /books`
O'quvchining o'z guruhi kursi materiallari. `courses` javobida video/audio/savollar soni va eng yaxshi test natijasi ham keladi.

### `POST /courses/{id}/tests/start` → `POST /tests/{attempt}/submit`
Test topshirish oqimi: boshlashda savollar va aralashtirilgan variantlar qaytadi (to'g'ri javob **yuborilmaydi**), topshirishda `{"answers": [{"question_id": 1, "choice": 2}, ...]}` yuboriladi, server tekshirib natija qaytaradi.

### `GET /courses/{id}/tests/results`
So'nggi 20 ta test urinishi (ball, to'g'ri javoblar soni, sana).

### `GET /me/payroll`
O'qituvchi uchun: guruhlar bo'yicha hisoblangan ish haqi va to'lovlar tarixi (faqat ko'rish).

---

## 7. Guruh va davomat (umumiy)

### `GET /groups`, `GET /groups/{id}`
Rolga qarab: o'quvchi — o'zinikilar, o'qituvchi — o'zinikilar, xodim (`groups.view`) — filialdagi barchasi. `?status=current|finished|upcoming` filtri bor.

### `GET /groups/{id}/attendance`
Davomat matritsasi (kunlar × o'quvchilar). O'quvchi so'rasa, faqat o'z qatori qaytadi.

### `POST /groups/{id}/attendance`
Bugungi davomatni saqlash/tahrirlash (o'qituvchi yoki `attendance.take` ruxsati bor xodim):
```json
{ "present": [12, 15, 19] }
```
(hozir kelgan o'quvchilar ID ro'yxati; ro'yxatda bo'lmagan faol a'zolar "kelmadi" deb belgilanadi).

---

## 8. Xodimlar bo'limi

Bu bo'lim admin/menejer/operator (ruxsatiga qarab) uchun. Har bir endpoint veb-sahifadagi bilan **bir xil ruxsat va filial cheklovi**dan foydalanadi.

### 8.1. Bosh sahifa

`GET /dashboard/todo` — bugungi qarzdorlar, javobsiz lidlar (`payments.view`/`leads.view` ruxsatiga qarab) va (agar `groups.view` bo'lsa) faol/boshlanmagan guruhlar soni.

### 8.2. Varonka (Lidlar/CRM)

| Metod | URL | Ruxsat |
|---|---|---|
| GET | `/leads?status=new` | `leads.view` |
| GET | `/leads/{id}` | `leads.view` |
| POST | `/leads` | `leads.manage` (+ `X-Branch-Id` sAdmin uchun) |
| POST | `/leads/{id}/note` | `leads.manage` |
| POST | `/leads/{id}/cancel` | `leads.manage` |
| POST | `/leads/{id}/reopen` | `leads.manage` |
| POST | `/leads/{id}/convert` | `leads.manage` + `students.create` |

`POST /leads` so'rovi:
```json
{ "name": "Aziz Aliyev", "phone": "+998901234567", "phone2": null, "address": null, "lead_source_id": 2 }
```

`POST /leads/{id}/convert` — lidni o'quvchiga aylantirish, ixtiyoriy guruhga ham qo'shish (`groups.members` ruxsati bo'lsa):
```json
{ "about": "Izoh", "group_id": 4 }
```
Javobda yangi o'quvchi login/parol (`credentials`) qaytadi (agar yangi hisob yaratilgan bo'lsa).

### 8.3. Kassa

| Metod | URL | Ruxsat |
|---|---|---|
| GET | `/cashbox` | `cashbox.view` (+ `X-Branch-Id` sAdmin uchun) |
| GET | `/cashbox/expense-categories` | `cashbox.request` (+ `X-Branch-Id` sAdmin uchun) |
| POST | `/cashbox/requests` | `kind=expense` uchun `cashbox.request`, `kind=withdrawal` uchun `cashbox.withdraw` (+ `X-Branch-Id`) |
| POST | `/cashbox/requests/{id}/approve` | `cashbox.approve` |
| POST | `/cashbox/requests/{id}/cancel` | `cashbox.approve` yoki so'rov egasi (shu tur ruxsati bilan) |

`POST /cashbox/requests`:
```json
{ "kind": "withdrawal", "method": "cash", "amount": 50000, "description": "Ofis xarajati", "category_id": null }
```
`kind`: `withdrawal` | `expense`. Diqqat: bu **so'rov** — pul darhol kassadan yechiladi, admin tasdiqlashi (yoki bekor qilishi, pul qaytadi) kutiladi. **Kassa smenasini yopish va qaytarishni tasdiqlash/rad etish mobilda YO'Q** (10-bo'lim).

### 8.4. Moliya (faqat ko'rish + kirim)

| Metod | URL | Ruxsat |
|---|---|---|
| GET | `/finance/overview` | `finance.view` (+ `X-Branch-Id` sAdmin uchun) |
| POST | `/finance/deposit` | `finance.deposit` (+ `X-Branch-Id`) |

`GET /finance/overview`:
```json
{ "success": true, "data": {
  "treasury_cash": 1200000, "treasury_card": 300000,
  "charity_cash": 15000, "charity_card": 5000, "charity_total": 20000,
  "charity_percent": 5.0
}}
```
`POST /finance/deposit` (shaxsiy mablag' kiritish):
```json
{ "method": "cash", "amount": 100000, "description": "O'z mablag'im" }
```
**Balansdan chiqim, xarajat va ehson chiqimi mobilda YO'Q** (10-bo'lim).

### 8.5. Hodimlar

| Metod | URL | Ruxsat |
|---|---|---|
| GET | `/staff?role=&q=` | `viewAny` (User siyosati) |
| GET | `/staff/{id}` | `manage` (User siyosati) |
| POST | `/staff` | `create` (rolga qarab) **+ `X-Branch-Id` sAdmin uchun (v12, pastga qarang)** |
| PUT | `/staff/{id}` | `manage` |
| GET | `/staff/{id}/payroll` | `staff.view` / `teachers.view` |

`POST /staff` (admin faqat o'z filialida qo'shadi; **sAdmin uchun filial `X-Branch-Id` sarlavhasidan olinadi — v12'da body'da `branch_id` YUBORILMAYDI**, 4-bo'limdagi ⚠️ eslatmaga qarang):
```json
{ "name": "Yangi Menejer", "username": "yangi.menejer", "phone": "+998912223344", "status": "active", "password": "parol12345", "password_confirmation": "parol12345", "role": "manager" }
```
`GET /staff/{id}/payroll` — hisoblangan ish haqi va to'lovlar tarixi, **faqat ko'rish** (to'lash mobilda yo'q).

### 8.6. Boshqa filiallarni ko'rish (mobilda ham bor)

| Metod | URL | Ruxsat |
|---|---|---|
| GET | `/staff-directory` | `staff.view_all_branches` |
| GET | `/staff-directory/{branch}` | `staff.view_all_branches` |

`branch` endpointi tanlangan filialning o'quvchi/guruh/lid ro'yxatini **faqat o'qish uchun** qaytaradi (sahifalangan, `students_page`/`groups_page`/`leads_page` query parametrlari bilan).

### 8.7. Guruhga o'quvchi biriktirish

| Metod | URL | Ruxsat |
|---|---|---|
| POST | `/groups/{id}/students` | `groups.members` (guruh siyosati) |
| DELETE | `/groups/{id}/students/{student}` | `groups.members` |

```json
{ "student_id": 12, "note": "Izoh", "allow_debt": false }
```
`allow_debt: true` — qarzi bor o'quvchini ham (istisno tariqasida) qo'shish, faqat tegishli ruxsat (`groups.enroll_debtor`) bo'lsa ishlaydi.

### 8.8. SMS

| Metod | URL | Ruxsat |
|---|---|---|
| GET | `/sms?status=&q=` | `sms.view`/`sms.send`/`sms.manage` |
| POST | `/sms/bulk-preview` | `sms.send` (+ `X-Branch-Id` sAdmin uchun) |

`POST /sms/bulk-preview` — **faqat oldindan ko'rish** (nechta kishiga, qancha turadi):
```json
{ "audience": "all", "group_id": null, "message": "Salom {name}, to'lov muddati yaqinlashdi." }
```
**Haqiqiy ommaviy yuborish mobilda YO'Q** — veb-panelga o'tib, tasdiqlash orqali amalga oshiriladi.

### 8.9. Ataylab mobilga chiqarilmagan (xodimlar bo'limi ichida) — qisqacha

Batafsil sabab 10-bo'limda.

---

## 9. Statistika

`GET /statistics/overview?from=2026-09-01&to=2026-09-30` (`statistics.view`). Foydalanuvchi ruxsatiga qarab pul (`payments.view`) va moliya (`finance.view`) ko'rsatkichlari javobdan chiqarib tashlanadi.

---

## 10. Ataylab mobilga chiqarilmagan amallar

Quyidagi amallar veb-panelda **ikki bosqichli tasdiqlash** ("Tekshiring" sahifasi) yoki alohida diqqat talab qiladigan boshqa himoya bilan qo'riqlangan. Bular ataylab mobil API'ga qo'shilmagan — mobil ilova bu qo'shimcha xavfsizlik qatlamini chetlab o'tmasligi kerak:

- Moliyadan pul **CHIQARISH** (balansdan chiqim, xarajat, ehson chiqimi) — `POST /finance/withdraw`, `/finance/expense`, `/finance/charity-withdraw` **mavjud emas**.
- Ish haqi **TO'LASH** — `POST /staff/{id}/payroll/pay` **mavjud emas** (faqat ko'rish bor, 8.5).
- Ommaviy SMS'ni **haqiqatan yuborish** — faqat oldindan ko'rish bor (8.8), yuborish tugmasi veb'da.
- Kassa **smenasini yopish** — `POST /cashbox/close-shift` **mavjud emas**.
- Kassa **qaytarishni tasdiqlash/rad etish** — `POST /cashbox/refunds/{id}/confirm|reject` **mavjud emas**.

Bu qaror kelajakda ham shu tarzda davom etadi: agar veb'da yangi amal `ConfirmationService` bilan himoyalangan bo'lsa, u mobil API'ga ataylab qo'shilmaydi (mobil ilova o'z ichida ikki bosqichli tasdiqlash oqimini takrorlamaguncha).

---

## 11. Firebase (FCM) sozlash

Push xabar ixtiyoriy — sozlanmasa, tizim (shu jumladan `/notifications` ro'yxati) baribir ishlaydi, faqat ekranga push sifatida chiqmaydi.

1. [Firebase Console](https://console.firebase.google.com)da loyiha yarating, Flutter ilovani ulang (Android/iOS uchun `google-services.json`/`GoogleService-Info.plist`).
2. **Project settings → Service accounts → Generate new private key** — JSON fayl yuklab olinadi.
3. Faylni serverda xavfsiz joyga qo'ying (masalan `storage/app/firebase-service-account.json`, **Git'ga qo'shmang**).
4. `.env`:
   ```
   FIREBASE_PROJECT_ID=sizning-loyiha-id
   FIREBASE_CREDENTIALS_PATH=/to'liq/yo'l/storage/app/firebase-service-account.json
   ```
5. `php artisan config:cache` (production'da kesh ishlatilsa).
6. Serverda push jo'natish HTTP v1 API orqali, qo'shimcha Composer paketsiz amalga oshiriladi (`App\Services\FcmService`) — xizmat hisobi kaliti bilan JWT server ichida imzolanadi.
7. Tekshirish: sAdmin → **Bildirishnomalar** → sinov xabar yuboring; haqiqiy qurilma tokeni ro'yxatdan o'tgan bo'lsa, push kelishi kerak.

**Flutter tomonida (dasturchi qiladi):** Firebase SDK ulash, qurilma push tokenini olish, login muvaffaqiyatli bo'lgach `POST /me/device-token` orqali serverga yuborish, va ilova ochiq turganda kelgan pushni qabul qilib ko'rsatish (foreground notification). Push xabar kelganda `data.link_type`/`data.link_id` bo'lsa, bosilganda mos sahifaga o'tkazish (5-bo'limga qarang).

---

*Ushbu hujjat kod bilan birga yangilanadi — yangi endpoint qo'shilganda yoki xatti-harakat o'zgarganda shu faylga ham qo'shiladi. Onlayn ko'rish uchun tizimga kirgan holda `/docs` sahifasiga o'ting (sAdmin/admin).*
