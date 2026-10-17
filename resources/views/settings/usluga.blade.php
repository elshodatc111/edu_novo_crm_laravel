@extends('layouts.app')
@section('title', 'Usluga')

@section('content')
    <x-page-header title="Usluga (staj ustamasi)" subtitle="O'qituvchining ish stajiga qarab har bir o'quvchi uchun qat'iy qo'shimcha summa" />
    @include('partials.settings-tabs')

    <div class="mb-6 rounded-xl border border-ink-200 bg-ink-50 p-4 text-sm leading-relaxed text-ink-700 dark:border-ink-700 dark:bg-ink-800/50 dark:text-ink-200">
        <b>Qanday hisoblanadi:</b> usluga (o'quvchi) = o'quvchilar soni × «O'quvchi usluga»; usluga (bonus) = bonusli o'quvchilar soni × «Bonus usluga».
        Pog'ona o'qituvchining <b>guruh boshlangan sanadagi</b> staji (ishga olingan sanadan) bo'yicha tanlanadi. Hisob davomadga mutanosib (hozirgi ish haqi kabi).
        Guruh boshlangach summa guruhga <b>qotiriladi</b>: keyin sozlamani o'zgartirsangiz, boshlangan guruhlar o'zgarmaydi. Mavjud hisob-kitob va to'langan ish haqiga tegilmaydi.
    </div>

    @if (! $canEdit)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">Sozlamalarni faqat sAdmin o'zgartira oladi. Siz ko'rish rejimidasiz.</div>
    @endif

    <form method="POST" action="{{ route('usluga-settings.update') }}" class="card card-body max-w-3xl space-y-6">
        @csrf @method('PUT')
        <fieldset @disabled(! $canEdit) class="space-y-6">
            <div>
                <x-input name="usluga_from" type="date" label="Usluga qo'llanadigan sana" :value="$branch->usluga_from?->format('Y-m-d')"
                         hint="Shu sanada va undan keyin boshlangan guruhlarga qo'llanadi (masalan, 01.10.2026). Bo'sh qoldirsangiz usluga o'chiq. Undan oldin boshlangan guruhlar o'zgarmaydi." />
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Staj (guruh boshlanganda)</th><th>O'quvchi usluga (so'm)</th><th>Bonus usluga (so'm)</th></tr></thead>
                    <tbody>
                    @foreach ($tiers as $i => $label)
                        <tr>
                            <td class="font-medium">{{ $label }}@if ($i === 4)<div class="text-xs font-normal text-ink-400">o'zgarmas maksimum</div>@endif</td>
                            <td><x-input :name="'usluga_student_'.$i" label="" money :value="$rates[$i]['student'] ?? 0" /></td>
                            <td><x-input :name="'usluga_bonus_'.$i" label="" money :value="$rates[$i]['bonus'] ?? 0" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </fieldset>

        @if ($canEdit)<div><button class="btn-primary">Saqlash</button></div>@endif
    </form>
@endsection
