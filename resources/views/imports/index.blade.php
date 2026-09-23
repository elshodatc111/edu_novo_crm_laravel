@extends('layouts.app')
@section('title', 'Excel import')

@section('content')
    <x-page-header title="O'quvchilarni Excel orqali import qilish" subtitle="Avval fayl tekshiriladi, siz ko'rib chiqib tasdiqlaysiz">
        <x-slot:actions><a href="{{ route('imports.template') }}" class="btn-secondary">Namuna faylni yuklab olish</a></x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('imports.upload') }}" enctype="multipart/form-data" class="card card-body h-fit space-y-4">
            @csrf
            <h2 class="text-base font-semibold text-ink-900 dark:text-white">Fayl yuklash</h2>
            <div>
                <input type="file" name="file" accept=".xlsx,.csv" class="input" required>
                @error('file')<p class="error-text">{{ $message }}</p>@enderror
                <p class="hint">.xlsx yoki .csv, 10 MB gacha, 5000 qatorgacha.</p>
            </div>
            <button class="btn-primary w-full">Tekshirish</button>
        </form>

        <div class="space-y-6 lg:col-span-2">
            <div class="card card-body text-sm text-ink-600 dark:text-ink-300">
                <h3 class="mb-2 text-base font-semibold text-ink-900 dark:text-white">Fayl tuzilishi</h3>
                <p>Birinchi qatorda ustun nomlari: <b>F.I.O</b> va <b>Telefon</b> majburiy. Ixtiyoriy: Qo'shimcha telefon, Tug'ilgan sana, Manzil, Manba, Balans (boshlang'ich; manfiy - qarz), Eslatma
                    @if (auth()->user()->isSuperAdmin()), <b>Filial</b>@endif.</p>
                @if (auth()->user()->isSuperAdmin())
                    <p class="mt-2">Faylda bazada yo'q filial nomi bo'lsa, tasdiqdan keyin <b>yangi filial ochiladi</b>. Filial ustuni bo'sh bo'lsa, yuqorida tanlangan filial olinadi.</p>
                @else
                    <p class="mt-2">O'quvchilar sizning filialingizga qo'shiladi.</p>
                @endif
                <p class="mt-2">Bazada shu filialda bir xil telefon va ismli o'quvchi bo'lsa, u o'tkazib yuboriladi. Har bir yangi o'quvchiga tasodifiy parol yaratiladi va import tugagach Excel faylida beriladi (bir marta).</p>
            </div>

            <div class="card overflow-hidden">
                <h3 class="px-5 pt-5 text-base font-semibold text-ink-900 dark:text-white sm:px-6">So'nggi importlar</h3>
                <div class="table-wrap mt-3"><table class="table">
                    <thead><tr><th>Sana</th><th>Fayl</th><th>Qatorlar</th><th>Holat</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($batches as $b)
                        <tr><td class="whitespace-nowrap">{{ $b->created_at->format('d.m.Y H:i') }}</td><td>{{ $b->filename }}</td><td>{{ $b->summary['total'] ?? '—' }}</td>
                            <td><span class="{{ ['pending' => 'badge-amber', 'done' => 'badge-green', 'cancelled' => 'badge-gray'][$b->status] }}">{{ ['pending' => 'Tasdiq kutilmoqda', 'done' => 'Bajarilgan', 'cancelled' => 'Bekor qilingan'][$b->status] }}</span></td>
                            <td class="text-right"><a href="{{ route('imports.show', $b) }}" class="btn-ghost btn-sm">Ochish</a></td></tr>
                    @empty<tr><td colspan="5" class="py-8 text-center text-ink-500">Hali import qilinmagan.</td></tr>@endforelse
                    </tbody></table></div>
            </div>
        </div>
    </div>
@endsection
