@if(count($scopes ?? []) > 1)
<nav class="flex flex-wrap gap-2 {{ $class ?? 'mb-6' }}" aria-label="Event scoreboard scope">
    @foreach($scopes as $scopeOption)
        <a href="{{ route($routeName, array_merge($routeQuery ?? [], ['event' => $event->id, 'scope' => $scopeOption['key']])) }}"
           @if(($selectedScope['key'] ?? 'overall') === $scopeOption['key']) aria-current="page" @endif
           class="px-3.5 py-1.5 rounded-full text-sm font-semibold border transition {{ ($selectedScope['key'] ?? 'overall') === $scopeOption['key'] ? 'v2-btn-accent border-transparent shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50 hover:border-gray-300' }}">
            {{ $scopeOption['label'] }}
        </a>
    @endforeach
</nav>
@endif
