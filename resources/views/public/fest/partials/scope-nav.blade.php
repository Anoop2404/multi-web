@if(count($scopes ?? []) > 1)
<nav class="flex flex-wrap gap-2 {{ $class ?? 'mb-6' }}" aria-label="Event scoreboard scope">
    @foreach($scopes as $scopeOption)
        <a href="{{ route($routeName, array_merge($routeQuery ?? [], ['event' => $event->id, 'scope' => $scopeOption['key']])) }}"
           @if(($selectedScope['key'] ?? 'overall') === $scopeOption['key']) aria-current="page" @endif
           class="px-3 py-1.5 rounded-full text-sm border {{ ($selectedScope['key'] ?? 'overall') === $scopeOption['key'] ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-700 hover:border-amber-400' }}">
            {{ $scopeOption['label'] }}
        </a>
    @endforeach
</nav>
@endif
