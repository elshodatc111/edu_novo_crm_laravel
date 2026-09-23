@extends(($embed ?? false) ? 'layouts.embed' : 'layouts.public')
@section('title', $branch->name)

@section('content')
    @if (session('sent'))
        <div class="card card-body text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><x-icon name="check" class="h-7 w-7" /></span>
            <h1 class="mt-4 text-xl font-bold text-ink-900">Rahmat!</h1>
            <p class="mt-2 text-sm text-ink-600">Murojaatingiz qabul qilindi. Tez orada siz bilan bog'lanamiz.</p>
            <a href="{{ route('apply.show', [$branch->code, 'embed' => ($embed ?? false) ? 1 : null]) }}" class="btn-secondary mt-5">Yana murojaat qoldirish</a>
        </div>
    @else
        <form method="POST" action="{{ route('apply.store', $branch->code) }}" class="card card-body space-y-5">
            @if ($embed ?? false)<input type="hidden" name="embed" value="1">@endif
            @csrf
            <div>
                <h1 class="text-xl font-bold text-ink-900">{{ $branch->name }}</h1>
                <p class="mt-1 text-sm text-ink-500">Ma'lumotlaringizni qoldiring, biz siz bilan bog'lanamiz.</p>
            </div>
            <x-input name="name" label="Ism familiya" required />
            <x-input name="phone" phone label="Telefon raqami" required />
            <x-input name="address" label="Manzil (ixtiyoriy)" />
            @if ($sources->isNotEmpty())
                <x-select name="lead_source_id" label="Bizni qayerdan eshitdingiz?">
                    <option value="">Tanlang</option>
                    @foreach ($sources as $s)<option value="{{ $s->id }}" @selected(\App\Support\SafeInput::string(old('lead_source_id')) === (string) $s->id)>{{ $s->name }}</option>@endforeach
                </x-select>
            @endif
            {{-- Botlar uchun tuzoq: odam bu maydonni ko'rmaydi --}}
            <div class="hidden" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
            <button class="btn-primary w-full py-3">Yuborish</button>
        </form>
    @endif
@endsection
