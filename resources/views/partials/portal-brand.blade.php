@include('partials.portal-logo', ['logoUrl' => $logoUrl ?? null, 'tenant' => $tenant])

<div class="portal-badge">{{ $eyebrow ?? 'CBSE Sahodaya School Complex' }}</div>
<h1 class="portal-title">{{ $tenant->name }}</h1>

@if(!empty($tagline))
    <p class="portal-tagline">{{ $tagline }}</p>
@elseif(!empty($defaultTagline))
    <p class="portal-tagline">{{ $defaultTagline }}</p>
@endif

@if(!empty($motto))
    <p class="portal-motto">"{{ $motto }}"</p>
@endif

@if(!empty($steps))
    <ol class="portal-steps">
        @foreach($steps as $step)
            <li>{{ $step }}</li>
        @endforeach
    </ol>
@endif

@if(($showContacts ?? true) && (!empty($phone) || !empty($email)))
<div class="portal-contacts">
    @if(!empty($phone))
    <span class="portal-contact">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
        {{ $phone }}
    </span>
    @endif
    @if(!empty($email))
    <a href="mailto:{{ $email }}" class="portal-contact">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        {{ $email }}
    </a>
    @endif
</div>
@endif
