@if ($paginator->hasPages())
    <nav role="navigation" class="flex flex-col items-center justify-between gap-3 border-t border-ink-100 px-4 py-3 text-sm dark:border-ink-800 sm:flex-row">
        <p class="text-ink-500 dark:text-ink-400">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }}
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn-ghost btn-sm pointer-events-none opacity-40">Oldingi</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn-secondary btn-sm">Oldingi</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-ink-400">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn btn-sm bg-brand-600 text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="btn-ghost btn-sm">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn-secondary btn-sm">Keyingi</a>
            @else
                <span class="btn-ghost btn-sm pointer-events-none opacity-40">Keyingi</span>
            @endif
        </div>
    </nav>
@endif
