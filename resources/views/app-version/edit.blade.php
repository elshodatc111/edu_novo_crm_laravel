@extends('layouts.app')
@section('title', 'Mobil ilova versiyasi')

@section('content')
    <x-page-header title="Mobil ilova versiyasi" subtitle="Eski ilova versiyalarini serverni qayta joylashtirmasdan majburiy yangilashga undash" />

    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200 mb-6">
        Ilova ochilganda <code>GET /api/v1/app/version</code> so'raladi. Foydalanuvchi versiyasi <b>eng kam versiya</b>dan past bo'lsa,
        ilova "majburiy yangilash" oynasini ko'rsatishi kerak (dasturchiga API_DOC.md'da yozilgan). <b>So'nggi versiya</b> esa faqat
        yumshoq taklif (yopish mumkin bo'lgan bildirishnoma) uchun.
    </div>

    @foreach ($platforms as $platform)
        @php($row = $rows[$platform] ?? null)
        <form method="POST" action="{{ route('app-version.update') }}" class="card card-body mb-6 grid gap-5 sm:grid-cols-2">
            @csrf @method('PUT')
            <input type="hidden" name="platform" value="{{ $platform }}">

            <h3 class="sm:col-span-2 text-base font-semibold text-ink-900 dark:text-white">{{ ucfirst($platform) }}</h3>

            <div>
                <label class="label" for="min_version_{{ $platform }}">Eng kam versiya (bundan past - majburiy yangilash)</label>
                <input id="min_version_{{ $platform }}" name="min_version" class="input" placeholder="1.0.0"
                       value="{{ old('platform') === $platform ? old('min_version') : ($row->min_version ?? '1.0.0') }}" required>
                @if (old('platform') === $platform)
                    @error('min_version')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @endif
            </div>

            <div>
                <label class="label" for="latest_version_{{ $platform }}">So'nggi versiya (yumshoq taklif)</label>
                <input id="latest_version_{{ $platform }}" name="latest_version" class="input" placeholder="1.0.0"
                       value="{{ old('platform') === $platform ? old('latest_version') : ($row->latest_version ?? '1.0.0') }}" required>
                @if (old('platform') === $platform)
                    @error('latest_version')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @endif
            </div>

            <div>
                <label class="label" for="update_url_{{ $platform }}">Yuklab olish havolasi (ixtiyoriy)</label>
                <input id="update_url_{{ $platform }}" name="update_url" class="input" placeholder="https://play.google.com/..."
                       value="{{ old('platform') === $platform ? old('update_url') : $row?->update_url }}">
                @if (old('platform') === $platform)
                    @error('update_url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @endif
            </div>

            <div>
                <label class="label" for="message_{{ $platform }}">Xabar matni (ixtiyoriy, ilovada ko'rsatiladi)</label>
                <input id="message_{{ $platform }}" name="message" class="input" maxlength="255"
                       value="{{ old('platform') === $platform ? old('message') : $row?->message }}">
                @if (old('platform') === $platform)
                    @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @endif
            </div>

            <div class="sm:col-span-2"><button class="btn-primary">{{ ucfirst($platform) }} - saqlash</button></div>
        </form>
    @endforeach
@endsection
