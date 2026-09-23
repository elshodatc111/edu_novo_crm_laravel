<?php

namespace App\Http\Controllers;

use App\Models\AiChat;
use App\Services\AiAnalystService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    /** Alohida "AI yordamchi" sahifasi endi yo'q: bitta "Yordam" sahifasining tahlil rejimiga yo'naltiriladi. */
    public function index(Request $request)
    {
        $this->authorize('ai.chat');

        return redirect()->route('help.index', array_filter(['mode' => 'analyst', 'chat' => $request->input('chat')]));
    }

    public function send(Request $request, AiAnalystService $analyst): RedirectResponse
    {
        $this->authorize('ai.chat');

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1500'],
            'chat_id' => ['nullable', 'integer'],
        ], [], ['message' => 'Xabar']);

        $user = $request->user();
        $chat = ! empty($data['chat_id'])
            ? AiChat::where('user_id', $user->id)->where('kind', 'analytics')->findOrFail($data['chat_id'])
            : AiChat::create(['user_id' => $user->id, 'branch_id' => BranchContext::id(), 'title' => mb_substr($data['message'], 0, 60), 'kind' => 'analytics']);

        try {
            $analyst->ask($user, $chat, $data['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Xatolikda bo'sh suhbat qolib ketmasligi uchun
            if (empty($data['chat_id']) && ! $chat->messages()->exists()) {
                $chat->delete();
            }

            throw $e;
        }

        return redirect()->route('help.index', ['mode' => 'analyst', 'chat' => $chat->id]);
    }

    public function destroy(Request $request, AiChat $chat): RedirectResponse
    {
        $this->authorize('ai.chat');
        abort_unless($chat->user_id === $request->user()->id && $chat->kind === 'analytics', 404);

        $chat->delete();

        return redirect()->route('help.index', ['mode' => 'analyst']);
    }
}
