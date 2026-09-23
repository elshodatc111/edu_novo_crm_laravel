@extends('layouts.app')
@section('title', 'Tizim holati')

@section('content')
    <x-page-header title="Tizim holati" subtitle="Server, zaxira nusxa va ma'lumotlar bazasi holatini tekshirish" />

    <form method="POST" action="{{ route('system-status.backup') }}" class="mb-4">
        @csrf
        <button class="btn-secondary btn-sm" onclick="return confirm('Zaxira nusxa hozir olinsinmi? Bir necha daqiqa vaqt olishi mumkin.')">
            Hozir zaxira olish
        </button>
    </form>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tekshiruv</th><th>Holat</th><th>Tafsilot</th></tr></thead>
                <tbody>
                @foreach ($checks as $c)
                    <tr>
                        <td class="whitespace-nowrap font-medium text-ink-900 dark:text-white">{{ $c['label'] }}</td>
                        <td class="whitespace-nowrap">
                            <span class="{{ ['ok' => 'badge-green', 'warning' => 'badge-amber', 'critical' => 'badge-red'][$c['status']] }}">
                                {{ ['ok' => 'Yaxshi', 'warning' => 'Diqqat', 'critical' => 'Muammo'][$c['status']] }}
                            </span>
                        </td>
                        <td class="text-ink-600 dark:text-ink-300">{{ $c['detail'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
