@extends('layouts.app')
@section('title', 'Shartnoma')

@section('content')
    <x-page-header title="Shartnoma" subtitle="O'quvchi guruhga qo'shilganda chop etiladigan shartnoma matni" />
    @include('partials.settings-tabs')

    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200 mb-6">
        Bu — avtomatik to'ldiriladigan <b>namuna</b> shartnoma. Imzolashdan oldin uni litsenziyalangan yuristga ko'rsatib, O'zbekiston qonunchiligiga moslashtirishingiz tavsiya etiladi.
    </div>

    <form method="POST" action="{{ route('contract-settings.update') }}" class="card card-body max-w-3xl space-y-5">
        @csrf @method('PUT')

        <div class="grid gap-5 sm:grid-cols-3">
            <x-input name="stir" label="Filial STIR" :value="$branch->stir" maxlength="30" />
            <x-input name="director_name" label="Direktor F.I.O." :value="$branch->director_name" maxlength="120" />
            <x-input name="director_title" label="Direktor lavozimi" :value="$branch->director_title" placeholder="Direktor" maxlength="60" />
        </div>

        <div>
            <label class="label" for="contract_template">Shartnoma matni</label>
            <textarea id="contract_template" name="contract_template" rows="24" maxlength="20000" class="input font-mono text-xs leading-relaxed">{{ old('contract_template', $branch->contract_template) }}</textarea>
            @error('contract_template')<p class="error-text">{{ $message }}</p>@enderror
            @if ($isDefault)
                <p class="hint">Hozir standart namuna ishlatilmoqda (yuqorida ko'rsatilgan). O'zgartirib saqlasangiz, shu matn ishlatiladi.</p>
            @endif
        </div>

        <div class="rounded-xl bg-ink-100 p-4 text-xs leading-relaxed text-ink-600 dark:bg-ink-800 dark:text-ink-300">
            <b>O'rinbosarlar (avtomatik to'ldiriladi):</b>
            <div class="mt-2 grid gap-x-4 gap-y-1 sm:grid-cols-2">
                @foreach (\App\Services\ContractService::PLACEHOLDERS as $token => $desc)
                    <div><code class="rounded bg-white px-1 dark:bg-ink-900">{{ $token }}</code> — {{ $desc }}</div>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button class="btn-primary">Saqlash</button>
            @unless ($isDefault)
                <button type="submit" formaction="{{ route('contract-settings.reset') }}" class="btn-ghost btn-sm" onclick="return confirm('Standart namunaga qaytarilsinmi? Yozgan matningiz o\'chadi.')">Standart namunaga qaytarish</button>
            @endunless
        </div>
    </form>
@endsection
