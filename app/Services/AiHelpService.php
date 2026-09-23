<?php

namespace App\Services;

use App\Models\AiChat;
use App\Models\AiMessage;
use App\Models\Branch;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\KnowledgeBase;
use App\Support\PermissionRegistry;

/**
 * "Yordam" AI yordamchisi: tizimdan qanday foydalanish haqida foydalanuvchi roli va ruxsatlariga mos javob beradi.
 * Bazadagi ma'lumotlarga kirmaydi; faqat qo'llanma (KnowledgeBase) asosida ishlaydi.
 */
class AiHelpService
{
    public function __construct(private OpenAiService $ai) {}

    public function ask(User $actor, AiChat $chat, string $question): string
    {
        $branchId = BranchContext::id();
        $this->ai->assertQuota($actor, $branchId);

        $messages = [['role' => 'system', 'content' => $this->systemPrompt($actor)]];
        foreach ($chat->messages()->latest('id')->limit(8)->get()->reverse() as $m) {
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }
        $messages[] = ['role' => 'user', 'content' => $question];

        $reply = $this->ai->chat($messages);
        $answer = trim((string) ($reply['content'] ?? '')) ?: "Kechirasiz, javob tayyorlay olmadim. Savolni boshqacha yozib ko'ring.";

        AiMessage::create(['ai_chat_id' => $chat->id, 'branch_id' => $branchId, 'user_id' => $actor->id, 'role' => 'user', 'content' => $question]);
        AiMessage::create(['ai_chat_id' => $chat->id, 'branch_id' => $branchId, 'user_id' => $actor->id, 'role' => 'assistant', 'content' => $answer]);
        $chat->touch();

        return $answer;
    }

    public function systemPrompt(User $user): string
    {
        $branch = BranchContext::id() ? Branch::find(BranchContext::id())?->name : ($user->isSuperAdmin() ? 'Barcha filiallar' : $user->branch?->name);
        $perms = $user->isSuperAdmin()
            ? 'barcha ruxsatlar (sAdmin)'
            : (collect($user->permissionKeys())->map(fn ($k) => PermissionRegistry::label($k))->implode('; ') ?: 'hech qanday qo\'shimcha ruxsat berilmagan');

        return "Sen Edunova CRM tizimi bo'yicha yordamchi-ustozsan. Foydalanuvchiga tizimdan qanday foydalanishni o'zbek tilida, sodda va aniq, qadam-baqadam tushuntirasan (menyu nomlarini ko'rsat).\n"
            ."Foydalanuvchi: rol — {$user->role->label()}; filial — {$branch}.\n"
            ."Uning ruxsatlari: {$perms}.\n\n"
            ."QOIDALAR:\n1) Faqat quyidagi qo'llanmaga tayanib javob ber; qo'llanmada yo'q narsani o'zingdan to'qima, bilmasang «bu haqda ma'lumotim yo'q» de.\n"
            ."2) Foydalanuvchi o'z ruxsati doirasida qila oladigan amallarni tushuntir. Ruxsati bo'lmagan amal so'ralsa, ruxsati yo'qligini ayt va kim (admin yoki sAdmin) bera olishini ko'rsat; boshqa rollarning yashirin ma'lumotlarini ochma.\n"
            ."3) Tizim qoidalari (masalan: qarzi bor o'quvchi guruhga qo'shilmaydi, davomad faqat dars kunida va bugun olinadi, telefon +998 90 123 4567 ko'rinishida) haqida savol berilsa, aniq qoidani ayt.\n"
            ."4) Bazadagi real ma'lumotlarni (o'quvchi ismi, summalar) bilmaysan va ularni so'rama; statistika tahlili uchun shu «Yordam» sahifasidagi «Tahlil (AI)» rejimi borligini eslat (ruxsati bo'lganlar uchun).\n"
            ."5) Javob qisqa bo'lsin, kerak bo'lsa raqamlangan qadamlar bilan.\n\n"
            ."=== QO'LLANMA ===\n".KnowledgeBase::forUser($user);
    }
}
