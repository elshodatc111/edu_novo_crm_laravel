@extends('layouts.app')
@section('title', $course->name)

@section('content')
<div x-data="{ tab: '{{ $errors->has('question') || $errors->has('correct') || $errors->has('wrong') || $errors->has('wrong.*') ? 'tests' : ($errors->has('file') || $errors->has('url') && old('number') !== null ? 'audios' : 'videos') }}' }">
    <x-page-header :title="$course->name" subtitle="Video, audio va test materiallari">
        <x-slot:actions><a href="{{ route('catalog.index', 'courses') }}" class="btn-secondary">Kurslar</a></x-slot:actions>
    </x-page-header>

    <div class="flex gap-1 overflow-x-auto rounded-xl bg-ink-100 p-1 dark:bg-ink-800">
        @foreach (['videos' => 'Videolar ('.$videos->count().')', 'audios' => 'Audiolar ('.$audios->count().')', 'tests' => 'Test savollari ('.$questions->count().')'] as $k => $label)
            <button type="button" @click="tab = '{{ $k }}'" :class="tab === '{{ $k }}' ? 'bg-white text-brand-700 shadow-sm dark:bg-ink-900 dark:text-brand-300' : 'text-ink-600 dark:text-ink-300'" class="whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium">{{ $label }}</button>
        @endforeach
    </div>

    {{-- VIDEO --}}
    <div x-show="tab === 'videos'" class="mt-4 grid gap-6 lg:grid-cols-3">
        @can('courses.manage')
        <form method="POST" action="{{ route('courses.videos.store', $course) }}" class="card card-body h-fit space-y-4">
            @csrf
            <h2 class="text-base font-semibold text-ink-900 dark:text-white">Video qo'shish</h2>
            <x-input name="number" type="number" min="1" label="Dars raqami" :value="$nextVideo" required />
            <x-input name="title" label="Nomi" required />
            <x-input name="url" type="url" label="Video havolasi" required hint="YouTube yoki boshqa video havolasi." />
            <button class="btn-primary w-full">Qo'shish</button>
        </form>
        @endcan
        <div class="card overflow-hidden {{ auth()->user()->can('courses.manage') ? 'lg:col-span-2' : 'lg:col-span-3' }}">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>#</th><th>Nomi</th><th>Havola</th><th></th></tr></thead>
                <tbody>
                @forelse ($videos as $v)
                    <tr><td>{{ $v->number }}</td><td class="font-medium">{{ $v->title }}</td>
                        <td class="max-w-xs truncate"><a href="{{ $v->url }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">{{ $v->url }}</a></td>
                        <td class="text-right">@can('courses.manage')<form method="POST" action="{{ route('courses.videos.destroy', [$course, $v]) }}" onsubmit="return confirm('O\'chirilsinmi?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm"><x-icon name="trash" class="h-4 w-4" /></button></form>@endcan</td></tr>
                @empty<tr><td colspan="4" class="py-8 text-center text-ink-500">Video yo'q.</td></tr>@endforelse
                </tbody></table></div>
        </div>
    </div>

    {{-- AUDIO --}}
    <div x-show="tab === 'audios'" x-cloak class="mt-4 grid gap-6 lg:grid-cols-3">
        @can('courses.manage')
        <form method="POST" enctype="multipart/form-data" action="{{ route('courses.audios.store', $course) }}" class="card card-body h-fit space-y-4">
            @csrf
            <h2 class="text-base font-semibold text-ink-900 dark:text-white">Audio qo'shish</h2>
            <x-input name="number" type="number" min="1" label="Audio raqami" :value="$nextAudio" required />
            <x-input name="title" label="Nomi" required />
            <div>
                <label class="label" for="file">Audio fayl (mp3, wav, m4a, ogg — 50 MB gacha)</label>
                <input id="file" name="file" type="file" accept=".mp3,.wav,.m4a,.ogg,.aac" class="input">
                @error('file')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <x-input name="url" type="url" label="yoki tashqi havola" />
            <button class="btn-primary w-full">Qo'shish</button>
        </form>
        @endcan
        <div class="card overflow-hidden {{ auth()->user()->can('courses.manage') ? 'lg:col-span-2' : 'lg:col-span-3' }}">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>#</th><th>Nomi</th><th>Turi</th><th></th></tr></thead>
                <tbody>
                @forelse ($audios as $a)
                    <tr><td>{{ $a->number }}</td><td class="font-medium">{{ $a->title }}</td>
                        <td><a href="{{ $a->url }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">{{ $a->is_external ? 'Havola' : 'Fayl' }}</a></td>
                        <td class="text-right">@can('courses.manage')<form method="POST" action="{{ route('courses.audios.destroy', [$course, $a]) }}" onsubmit="return confirm('O\'chirilsinmi?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm"><x-icon name="trash" class="h-4 w-4" /></button></form>@endcan</td></tr>
                @empty<tr><td colspan="4" class="py-8 text-center text-ink-500">Audio yo'q.</td></tr>@endforelse
                </tbody></table></div>
        </div>
    </div>

    {{-- TEST --}}
    <div x-show="tab === 'tests'" x-cloak class="mt-4 grid gap-6 lg:grid-cols-3">
        @can('courses.manage')
        <div class="space-y-6 h-fit">
        <form method="POST" action="{{ route('courses.questions.ai', $course) }}" class="card card-body space-y-3">
            @csrf
            <h2 class="text-base font-semibold text-ink-900 dark:text-white">AI bilan savol yaratish</h2>
            <x-input name="topic" label="Mavzu" placeholder="Masalan: o'tgan zamon fe'llari" required />
            <div class="grid grid-cols-2 gap-3">
                <x-input name="count" type="number" min="1" max="10" label="Soni" :value="5" required />
                <x-select name="level" label="Daraja"><option>boshlang'ich</option><option>o'rta</option><option>yuqori</option></x-select>
            </div>
            <button class="btn-secondary w-full" onclick="this.textContent='AI tayyorlamoqda...'">Yaratish</button>
            <p class="hint">Natija saqlanmaydi: avval ko'rib chiqasiz.</p>
        </form>
        <form method="POST" action="{{ route('courses.questions.store', $course) }}" class="card card-body h-fit space-y-4">
            @csrf
            <h2 class="text-base font-semibold text-ink-900 dark:text-white">Savol qo'shish</h2>
            <div><label class="label" for="question">Savol</label><textarea id="question" name="question" rows="3" class="input" required>{{ old('question') }}</textarea>@error('question')<p class="error-text">{{ $message }}</p>@enderror</div>
            <x-input name="correct" label="To'g'ri javob" required />
            @foreach ([0, 1, 2] as $i)
                <div><label class="label" for="wrong{{ $i }}">Noto'g'ri javob {{ $i + 1 }}</label><input id="wrong{{ $i }}" name="wrong[]" value="{{ old('wrong.'.$i) }}" class="input" required>@error('wrong.'.$i)<p class="error-text">{{ $message }}</p>@enderror</div>
            @endforeach
            @error('wrong')<p class="error-text">{{ $message }}</p>@enderror
            <button class="btn-primary w-full">Qo'shish</button>
            <p class="hint">Variantlar o'quvchiga har safar aralashtirilib ko'rsatiladi. Har testda {{ \App\Services\TestService::QUESTIONS_PER_TEST }} tagacha tasodifiy savol tushadi.</p>
        </form>
        </div>
        @endcan
        <div class="space-y-6 {{ auth()->user()->can('courses.manage') ? 'lg:col-span-2' : 'lg:col-span-3' }}">
        @can('courses.manage')
        @if (session('ai_questions'))
            <form method="POST" action="{{ route('courses.questions.ai-store', $course) }}" class="card card-body space-y-3">
                @csrf
                <h3 class="text-base font-semibold text-ink-900 dark:text-white">AI tayyorlagan savollar — ko'rib chiqing</h3>
                <p class="text-sm text-ink-500">Faqat to'g'ri deb hisoblaganlaringizni belgilab saqlang.</p>
                @error('selected')<p class="error-text">{{ $message }}</p>@enderror
                @foreach (session('ai_questions') as $i => $q)
                    <label class="block rounded-xl border border-ink-200 p-3 text-sm dark:border-ink-700">
                        <span class="flex items-start gap-3"><input type="checkbox" name="selected[]" value="{{ $i }}" class="checkbox mt-1" checked>
                            <span><b>{{ $q['question'] }}</b><br><span class="text-emerald-600">✓ {{ $q['correct'] }}</span><br><span class="text-ink-500">✗ {{ implode('  ✗ ', $q['wrong']) }}</span></span></span>
                        <input type="hidden" name="questions[{{ $i }}][question]" value="{{ $q['question'] }}">
                        <input type="hidden" name="questions[{{ $i }}][correct]" value="{{ $q['correct'] }}">
                        @foreach ($q['wrong'] as $w)<input type="hidden" name="questions[{{ $i }}][wrong][]" value="{{ $w }}">@endforeach
                    </label>
                @endforeach
                <button class="btn-primary">Tanlanganlarni saqlash</button>
            </form>
        @endif
        @endcan
        <div class="card overflow-hidden">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Savol</th><th>To'g'ri javob</th><th></th></tr></thead>
                <tbody>
                @forelse ($questions as $q)
                    <tr><td class="max-w-md">{{ $q->question }}</td><td class="text-emerald-600">{{ $q->correct }}</td>
                        <td class="text-right">@can('courses.manage')<form method="POST" action="{{ route('courses.questions.destroy', [$course, $q]) }}" onsubmit="return confirm('O\'chirilsinmi?')">@csrf @method('DELETE')<button class="btn-ghost btn-sm"><x-icon name="trash" class="h-4 w-4" /></button></form>@endcan</td></tr>
                @empty<tr><td colspan="3" class="py-8 text-center text-ink-500">Savol yo'q.</td></tr>@endforelse
                </tbody></table></div>
        </div>
        </div>
    </div>
</div>
@endsection
