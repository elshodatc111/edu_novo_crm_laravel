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
    <x-select name="room_id" label="Xona" required>
        <option value="">Tanlang</option>
        @foreach ($rooms as $r)<option value="{{ $r->id }}" @selected(\App\Support\SafeInput::string(old('room_id')) === (string) $r->id)>{{ $r->name }}</option>@endforeach
    </x-select>
    <x-select name="lesson_time_id" label="Dars vaqti" required>
        <option value="">Tanlang</option>
        @foreach ($times as $t)<option value="{{ $t->id }}" @selected(\App\Support\SafeInput::string(old('lesson_time_id')) === (string) $t->id)>{{ $t->label }}</option>@endforeach
    </x-select>
    <x-select name="schedule" label="Dars kunlari" required>
        @foreach ($schedules as $s)<option value="{{ $s->value }}" @selected(old('schedule') === $s->value)>{{ $s->label() }}</option>@endforeach
    </x-select>
    <x-select name="price_plan_id" label="Narx rejasi" required>
        <option value="">Tanlang</option>
        @foreach ($plans as $p)<option value="{{ $p->id }}" @selected(\App\Support\SafeInput::string(old('price_plan_id')) === (string) $p->id)>{{ $p->name }} — {{ \App\Support\Format::money($p->amount) }}</option>@endforeach
    </x-select>
    <x-input name="starts_on" type="date" label="Boshlanish sanasi" :value="old('starts_on', today()->format('Y-m-d'))" required hint="Dars kunlariga to'g'ri kelmasa, eng yaqin dars kuni olinadi." />
    <x-input name="lesson_count" type="number" label="Darslar soni" :value="old('lesson_count', 12)" min="1" max="60" required />
@endif
<x-input name="teacher_rate" money label="O'qituvchi stavkasi (o'quvchi boshiga, so'm)" :value="$g?->teacher_rate ?? 0" />
<x-input name="teacher_bonus_rate" money label="Bonus (keyingi guruhga o'tgan o'quvchi uchun, so'm)" :value="$g?->teacher_bonus_rate ?? 0" />
