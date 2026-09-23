<?php

namespace App\Http\Controllers;

use App\Models\AiChat;
use App\Services\AiAnalystService;
use App\Services\AiHelpService;
use App\Services\OpenAiService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** "Yordam" AI yordamchisi: barcha panel foydalanuvchilari uchun (rolga mos javoblar). */
class HelpController extends Controller
{
    /** Yagona sahifa, ikki rejim: "Qo'llanma" (tizim haqida savollar) va "Tahlil" (filial statistikasi, ai.chat ruxsati bilan). */
    public function index(Request $request, OpenAiService $ai, AiAnalystService $analyst)
    {
        $user = $request->user();
        $canAnalyst = $user->can('ai.chat');
        $mode = $request->input('mode') === 'analyst' && $canAnalyst ? 'analyst' : 'help';
        $kind = $mode === 'analyst' ? 'analytics' : 'help';

        $chats = AiChat::where('user_id', $user->id)->where('kind', $kind)->latest('updated_at')->limit(30)->get();
        $current = $request->filled('chat') ? AiChat::where('user_id', $user->id)->where('kind', $kind)->findOrFail($request->integer('chat')) : null;

        return view('help.index', [
            'mode' => $mode, 'canAnalyst' => $canAnalyst,
            'chats' => $chats, 'current' => $current, 'messages' => $current ? $current->messages()->get() : collect(),
            'configured' => $ai->configured(),
            'examples' => $mode === 'analyst' ? $this->analystExamples() : $this->examples($user->role->value),
            'tools' => $mode === 'analyst' ? array_keys($analyst->tools($user)) : [],
        ]);
    }

    public function send(Request $request, AiHelpService $help): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:1000'], 'chat_id' => ['nullable', 'integer']], [], ['message' => 'Savol']);

        $user = $request->user();
        $chat = ! empty($data['chat_id'])
            ? AiChat::where('user_id', $user->id)->where('kind', 'help')->findOrFail($data['chat_id'])
            : AiChat::create(['user_id' => $user->id, 'branch_id' => BranchContext::id(), 'title' => mb_substr($data['message'], 0, 60), 'kind' => 'help']);

        try {
            $help->ask($user, $chat, $data['message']);
        } catch (ValidationException $e) {
            if (empty($data['chat_id']) && ! $chat->messages()->exists()) {
                $chat->delete();
            }

            throw $e;
        }

        return redirect()->route('help.index', ['chat' => $chat->id]);
    }

    public function destroy(Request $request, AiChat $chat): RedirectResponse
    {
        abort_unless($chat->user_id === $request->user()->id && $chat->kind === 'help', 404);

        $chat->delete();

        return redirect()->route('help.index');
    }

    private function analystExamples(): array
    {
        return ["Filial holatini umumiy tahlil qil: qanday kamchiliklar bor va nima qilish kerak?", "Shu oy tushum, xarajat va qarzdorlik bo'yicha tahlil ber.", "Davomad va guruhlar to'lishi bo'yicha muammolarni top.", "Varonka manbalarining samaradorligini baholab, tavsiya ber."];
    }

    private function examples(string $role): array
    {
        return match ($role) {
            'teacher' => ['Bugungi davomadni qanday olaman?', "Nega davomad ola olmayapman?", "Ish haqim qanday hisoblanadi?", "O'quvchining balansini ko'ra olamanmi?"],
            'manager' => ["To'lovni qanday qabul qilaman?", "Qarzi bor o'quvchini guruhga qo'shsam bo'ladimi?", "Murojaatni o'quvchi sifatida qanday ro'yxatga olaman?", "Kassadan xarajat qanday so'raladi?"],
            default => ["Chegirma qoidalari qanday ishlaydi?", "Menejerga ruxsatni qanday beraman?", "Excel import qanday bajariladi?", "Filialga SMS ni qanday yoqaman?"],
        };
    }
}
