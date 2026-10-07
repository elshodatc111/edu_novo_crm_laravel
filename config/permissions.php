<?php

/*
| Tizim ruxsatlari. Har bir ruxsat uchun:
|   label - interfeysda ko'rinadigan nomi
|   roles - bu ruxsatni qaysi rollar olishi mumkin (admin, manager, operator, teacher)
|
| sAdmin barcha ruxsatlarga avtomatik ega. Admin faqat o'zida bor ruxsatlarni
| o'z filialidagi menejer, operator va o'qituvchilarga bera oladi.
|
| Operator menejer bilan bir xil mexanizmga ega (admin qaysi ruxsatni
| berishni o'zi tanlaydi) — faqat boshlang'ich shablon (pastda "defaults")
| torroq: pul chiqimi (cashbox.*) va kurslarsiz.
*/

$staff = ['admin', 'manager', 'operator'];
$all = ['admin', 'manager', 'teacher', 'operator'];

return [

    'groups' => [

        'students' => [
            'label' => "O'quvchilar",
            'items' => [
                'students.view' => ['label' => "O'quvchilarni ko'rish", 'roles' => $staff],
                'students.create' => ['label' => "O'quvchi qo'shish", 'roles' => $staff],
                'students.update' => ['label' => "O'quvchi ma'lumotini tahrirlash", 'roles' => $staff],
                'students.archive' => ['label' => "O'quvchini arxivga o'tkazish", 'roles' => $staff],
                'students.import' => ['label' => "O'quvchilarni Excel orqali import qilish", 'roles' => ['admin']],
                'students.notes' => ['label' => "O'quvchi eslatmalari", 'roles' => $staff],
            ],
        ],

        'groups' => [
            'label' => 'Guruhlar',
            'items' => [
                'groups.view' => ['label' => "Guruhlarni ko'rish", 'roles' => $staff],
                'groups.create' => ['label' => "Guruh yaratish", 'roles' => $staff],
                'groups.update' => ['label' => "Guruhni tahrirlash", 'roles' => $staff],
                'groups.members' => ['label' => "Guruhga o'quvchi qo'shish va chiqarish", 'roles' => $staff],
                'groups.enroll_debtor' => ['label' => "Qarzi bor o'quvchini guruhga qo'shish (istisno)", 'roles' => ['admin']],
            ],
        ],

        'attendance' => [
            'label' => 'Davomad',
            'items' => [
                'attendance.view' => ['label' => "Davomadni ko'rish", 'roles' => $all],
                'attendance.take' => ['label' => "Davomad olish (bugungi kun)", 'roles' => $all],
                'attendance.edit_past' => ['label' => "O'tgan kun davomatini tuzatish", 'roles' => ['admin']],
                'attendance.stats' => ['label' => "Davomad statistikasi", 'roles' => $staff],
            ],
        ],

        'payments' => [
            'label' => "To'lovlar",
            'items' => [
                'payments.view' => ['label' => "To'lovlarni ko'rish", 'roles' => $staff],
                'payments.create' => ['label' => "To'lov qabul qilish", 'roles' => $staff],
                'payments.discount' => ['label' => "Chegirma berish", 'roles' => $staff],
                'payments.refund' => ['label' => "To'lovni qaytarish", 'roles' => $staff],
                // v13: to'lovlar sahifasidagi umumiy yig'indilar (naqt, plastik, chegirma, qaytarilgan) alohida ruxsat bilan ko'rinadi
                'payments.view_totals' => ['label' => "To'lovlarning umumiy yig'indisini ko'rish", 'roles' => $staff],
                'payments.reverse' => ['label' => "To'lov/chegirmani storno qilish (xato yozuvni bekor qilish)", 'roles' => ['admin']],
            ],
        ],

        'cashbox' => [
            'label' => 'Kassa',
            'items' => [
                'cashbox.view' => ['label' => "Kassani ko'rish", 'roles' => $staff],
                // v13: ikkita alohida ruxsat. `cashbox.request` - XARAJAT (sarflangan pul), `cashbox.withdraw` - CHIQIM (moliya balansiga o'tkazish)
                'cashbox.request' => ['label' => "Xarajat so'rovi yaratish (sarflangan pul)", 'roles' => $staff],
                'cashbox.withdraw' => ['label' => "Chiqim so'rovi yaratish (pulni moliya balansiga o'tkazish)", 'roles' => $staff],
                'cashbox.history' => ['label' => "Kassaning so'nggi 30 kunlik tarixini ko'rish", 'roles' => ['admin']],
                'cashbox.approve' => ['label' => "Chiqim va xarajatni tasdiqlash", 'roles' => ['admin']],
                'cashbox.close' => ['label' => "Kassa smenasini yopish (kutilgan/haqiqiy naqtni solishtirish)", 'roles' => ['admin']],
            ],
        ],

        'finance' => [
            'label' => 'Moliya',
            'items' => [
                'finance.view' => ['label' => "Moliyani ko'rish", 'roles' => ['admin']],
                'finance.manage' => ['label' => "Balansdan chiqim va xarajat qilish", 'roles' => ['admin']],
                // v9: egasi shaxsiy mablag'ini (naqt/plastik) moliyaga kiritishi. Kirim xavfli emas,
                // shuning uchun chiqimlardagi kabi tasdiqlash bosqichisiz darhol yoziladi; ehson ajratilmaydi.
                'finance.deposit' => ['label' => "Shaxsiy mablag' kiritish (moliyaga kirim)", 'roles' => ['admin']],
            ],
        ],

        'teachers' => [
            'label' => "O'qituvchilar",
            'items' => [
                'teachers.view' => ['label' => "O'qituvchilarni ko'rish", 'roles' => $staff],
                'teachers.manage' => ['label' => "O'qituvchi qo'shish va tahrirlash", 'roles' => $staff],
                'teachers.pay' => ['label' => "O'qituvchiga ish haqi to'lash", 'roles' => ['admin']],
            ],
        ],

        'staff' => [
            'label' => 'Hodimlar',
            'items' => [
                'staff.view' => ['label' => "Hodimlarni ko'rish", 'roles' => ['admin']],
                'staff.manage' => ['label' => "Menejer va o'qituvchi qo'shish, tahrirlash", 'roles' => ['admin']],
                'staff.pay' => ['label' => "Hodimga ish haqi to'lash", 'roles' => ['admin']],
                // v9: barcha filiallar bo'yicha faqat ism/rol/filial ro'yxati - boshqa filial ma'lumotiga
                // (o'quvchi, to'lov, sozlama) kirish yo'q. Standart holatda Operatorga beriladi.
                'staff.view_all_branches' => ['label' => "Barcha filiallar xodimlarini ko'rish (faqat ism, rol, filial)", 'roles' => $staff],
            ],
        ],

        'leads' => [
            'label' => 'Varonka (lidlar)',
            'items' => [
                'leads.view' => ['label' => "Lidlarni ko'rish", 'roles' => $staff],
                'leads.manage' => ['label' => "Lidlar bilan ishlash", 'roles' => $staff],
            ],
        ],

        'courses' => [
            'label' => 'Kurslar',
            'items' => [
                'courses.view' => ['label' => "Kurslarni ko'rish", 'roles' => $staff],
                'courses.manage' => ['label' => "Kurs, video, audio va testlarni boshqarish", 'roles' => $staff],
            ],
        ],

        'settings' => [
            'label' => 'Filial sozlamalari',
            'items' => [
                'settings.branch' => ['label' => "Xona, dars vaqti, narx, chegirma va bayramlarni sozlash", 'roles' => ['admin']],
            ],
        ],

        'sms' => [
            'label' => 'SMS',
            'items' => [
                'sms.view' => ['label' => "SMS tarixini ko'rish", 'roles' => $staff],
                'sms.send' => ['label' => "Ommaviy SMS yuborish", 'roles' => $staff],
                'sms.manage' => ['label' => "SMS shablon va sozlamalarini boshqarish", 'roles' => ['admin']],
            ],
        ],

        'reports' => [
            'label' => 'Hisobot va statistika',
            'items' => [
                'reports.view' => ['label' => "Hisobotlarni ko'rish", 'roles' => $staff],
                'reports.export' => ['label' => "Hisobotni Excelga yuklab olish", 'roles' => $staff],
                'statistics.view' => ['label' => "Statistikani ko'rish", 'roles' => $staff],
            ],
        ],

        'ai' => [
            'label' => 'AI yordamchi',
            'items' => [
                'ai.chat' => ['label' => "AI yordamchi bilan suhbat", 'roles' => ['admin']],
            ],
        ],

        'system' => [
            'label' => 'Tizim',
            'items' => [
                'permissions.assign' => ['label' => "Menejer va o'qituvchilarga ruxsat berish", 'roles' => ['admin']],
                'audit.view' => ['label' => "Harakatlar jurnalini ko'rish", 'roles' => ['admin']],
            ],
        ],

    ],

    /*
    | Yangi hodim yaratilganda beriladigan boshlang'ich ruxsatlar (shablon).
    | Admin yaratayotganda bu ro'yxat admin o'zida bor ruxsatlar bilan kesishtiriladi.
    */
    'defaults' => [
        'admin' => '*',
        'manager' => [
            'students.view', 'students.create', 'students.update', 'students.notes',
            'groups.view', 'groups.members',
            'attendance.view', 'attendance.take',
            'payments.view', 'payments.create',
            'cashbox.view', 'cashbox.request',
            'leads.view', 'leads.manage',
            'courses.view',
        ],
        'teacher' => ['attendance.view', 'attendance.take'],
        // Menejernikiga o'xshash, lekin pul chiqimi (kassa) va kurslarsiz —
        // faqat oddiy to'lov qabul qilish, o'quvchi/guruh/davomat/lid bilan ishlash.
        // Chegirma (payments.discount) va moliya (finance.*) Menejerga ham standart berilmaydi.
        'operator' => [
            'students.view', 'students.create', 'students.update', 'students.notes',
            'groups.view', 'groups.members',
            'attendance.view', 'attendance.take',
            'payments.view', 'payments.create',
            'leads.view', 'leads.manage',
            // v9: lid/murojaat bilan ishlayotganda boshqa filialdagi mas'ul xodimni topa olishi uchun.
            'staff.view_all_branches',
        ],
    ],

];
