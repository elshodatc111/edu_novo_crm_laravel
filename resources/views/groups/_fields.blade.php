{{-- Guruh yaratish maydonlari: $withSchedule=true bo'lsa, dars jadvali maydonlari ham ko'rsatiladi --}}
@php $g = $group ?? null; @endphp
<x-input name="name" label="Guruh nomi" :value="$g?->name" required class="sm:col-span-2" />
<x-select name="course_id" label="Kurs" required>
    <option value="">Tanlang</option>
    @foreach ($courses as $c)<option value="{{ $c->id }}" @selected(\App\Support\SafeInput::string(old('course_id', $g?->course_id)) === (string) $c->id)>{{ $c->name }}</option>@endforeach
</x-select>
<x-select name="teacher_id" label="O'qituvchi" required>
    <option value="">Tanlang</option>
    @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected(\App\Support\SafeInput::string(old('teacher_id', $g?->teacher_id)) === (string) $t->id)>{{ $t->name }}</option>@endforeach
</x-select>
@if ($withSchedule)
    @php
        $editing = $g !== null;
        $finished = $editing && ($groupStatus ?? null) === \App\Models\Group::FINISHED;
        $startsLocked = $finished || ($editing && ($lockedCount ?? 0) > 0);
    @endphp
    <x-select name="room_id" :disabled="$finished" label="Xona" required>
        <option value="">Tanlang</option>
        @foreach ($rooms as $r)<option value="{{ $r->id }}" @selected(\App\Support\SafeInput::string(old('room_id', $g?->room_id)) === (string) $r->id)>{{ $r->name }}</option>@endforeach
    </x-select>
    <x-select name="lesson_time_id" :disabled="$finished" label="Dars vaqti" required>
        <option value="">Tanlang</option>
        @foreach ($times as $t)<option value="{{ $t->id }}" @selected(\App\Support\SafeInput::string(old('lesson_time_id', $g?->lesson_time_id)) === (string) $t->id)>{{ $t->label }}</option>@endforeach
    </x-select>
    <x-select name="schedule" :disabled="$finished" label="Dars kunlari" required>
        @foreach ($schedules as $s)<option value="{{ $s->value }}" @selected(\App\Support\SafeInput::string(old('schedule', $g?->schedule?->value)) === $s->value)>{{ $s->label() }}</option>@endforeach
    </x-select>
    @if (! $editing)
        <x-select name="price_plan_id" label="Narx rejasi" required>
            <option value="">Tanlang</option>
            @foreach ($plans as $p)<option value="{{ $p->id }}" @selected(\App\Support\SafeInput::string(old('price_plan_id')) === (string) $p->id)>{{ $p->name }} — {{ \App\Support\Format::money($p->amount) }}</option>@endforeach
        </x-select>
    @elseif ($canChangePrice ?? false)
        <x-select name="price_plan_id" label="Narx rejasi">
            <option value="">Hozirgi narx: {{ \App\Support\Format::money($g->price) }} (o'zgartirilmaydi)</option>
            @foreach ($plans as $p)<option value="{{ $p->id }}" @selected(\App\Support\SafeInput::string(old('price_plan_id')) === (string) $p->id)>{{ $p->name }} — {{ \App\Support\Format::money($p->amount) }}</option>@endforeach
        </x-select>
    @else
        <div>
            <span class="label">Narx</span>
            <div class="input bg-ink-50 text-ink-500 dark:bg-ink-800">{{ \App\Support\Format::money($g->price) }}</div>
            <p class="hint">Narxni o'zgartirish uchun alohida ruxsat kerak.</p>
        </div>
    @endif
    <x-input name="starts_on" type="date" label="Boshlanish sanasi" :value="old('starts_on', $g?->starts_on?->format('Y-m-d') ?? today()->format('Y-m-d'))" required :readonly="$startsLocked"
             :hint="$finished ? 'Guruh yakunlangan, sana o\'zgarmaydi.' : ($startsLocked ? 'Dars boshlangan, sana o\'zgarmaydi.' : ($editing ? 'Bugundan oldingi sanani tanlab bo\'lmaydi.' : 'Dars kunlariga to\'g\'ri kelmasa, eng yaqin dars kuni olinadi.'))" :min="$editing && ! $startsLocked ? today()->format('Y-m-d') : null" />
    <x-input name="lesson_count" type="number" label="Darslar soni" :value="old('lesson_count', $g?->lesson_count ?? 12)" min="1" max="60" required :readonly="$finished"
             :hint="$finished ? 'Guruh yakunlangan, darslar soni o\'zgarmaydi.' : ($startsLocked ? 'Kamida '.$lockedCount.' ta (o\'tgan yoki davomad olingan darslar soni).' : null)" />
    @if ($editing && ($canChangePrice ?? false) && ($membersCount ?? 0) > 0)
        <div class="sm:col-span-2">
            <label class="flex items-start gap-2 text-sm text-ink-700 dark:text-ink-200">
                <input type="checkbox" name="confirm_price_change" value="1" @checked(old('confirm_price_change')) class="mt-1 rounded border-ink-300">
                <span>Narx rejasi o'zgartirilsa, guruhdagi {{ $membersCount }} ta faol o'quvchi balansiga farq avtomatik hisoblanadi (qimmatlashsa yechiladi, arzonlashsa qaytariladi). Shuni tasdiqlayman.</span>
            </label>
            @error('confirm_price_change')<p class="error-text">{{ $message }}</p>@enderror
        </div>
    @endif
@endif
<x-input name="teacher_rate" money label="O'qituvchi stavkasi (o'quvchi boshiga, so'm)" :value="$g?->teacher_rate ?? 0" />
<x-input name="teacher_bonus_rate" money label="Bonus (keyingi guruhga o'tgan o'quvchi uchun, so'm)" :value="$g?->teacher_bonus_rate ?? 0" />
