@php
    $mode = $experience['homepage_mode'] ?? 'evergreen';
    $events = \App\Support\SahodayaPublicData::upcomingEvents($tenant->id, 1);
    $activeEvent = $events->first();
    $actions = match ($mode) {
        'registration_open' => [
            ['label' => 'Register now', 'detail' => 'Complete school or participant registration', 'url' => '/school-register', 'status' => 'Open'],
            ['label' => 'View programme', 'detail' => 'Dates, categories and participation rules', 'url' => '/fest', 'status' => 'Current'],
            ['label' => 'Download circular', 'detail' => 'Official instructions and deadlines', 'url' => '/downloads', 'status' => 'PDF'],
        ],
        'event_live' => [
            ['label' => 'Live schedule', 'detail' => 'Today’s programme and venue information', 'url' => '/fest', 'status' => 'Live'],
            ['label' => 'Results desk', 'detail' => 'Published item and school results', 'url' => '/fest/results', 'status' => 'Updating'],
            ['label' => 'Venue help', 'detail' => 'Directions and event contact', 'url' => '#contact', 'status' => 'Help'],
        ],
        'results_published' => [
            ['label' => 'View results', 'detail' => 'Final rankings and result summaries', 'url' => '/fest/results', 'status' => 'Published'],
            ['label' => 'Certificates', 'detail' => 'Certificate and report access', 'url' => '/portal', 'status' => 'Portal'],
            ['label' => 'Event archive', 'detail' => 'Circulars, schedules and highlights', 'url' => '/downloads', 'status' => 'Archive'],
        ],
        default => [
            ['label' => 'Upcoming programmes', 'detail' => 'See what is scheduled next', 'url' => '#events-programs', 'status' => 'Explore'],
            ['label' => 'School portal', 'detail' => 'Registration and administrative access', 'url' => '/portal', 'status' => 'Login'],
            ['label' => 'Latest circulars', 'detail' => 'Official notices and downloads', 'url' => '#news-circulars', 'status' => 'Updates'],
        ],
    };
@endphp
<section class="px-4" aria-labelledby="action-hub-heading">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-wrap items-end justify-between gap-5 mb-7">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.2em] text-primary mb-2">{{ ucfirst(str_replace('_', ' ', $mode)) }}</p>
                <h2 id="action-hub-heading" class="text-3xl md:text-4xl font-bold font-heading text-gray-950">{{ $config['heading'] ?? 'What do you need today?' }}</h2>
                @if(!empty($config['subheading']))<p class="mt-2 text-gray-600 max-w-2xl">{{ $config['subheading'] }}</p>@endif
            </div>
            @if($activeEvent)<p class="text-sm font-semibold text-gray-600">Next: {{ $activeEvent->name ?? $activeEvent->title ?? 'Upcoming event' }}</p>@endif
        </div>
        <div class="grid md:grid-cols-3 gap-4">
            @foreach($actions as $action)
                <a href="{{ $action['url'] }}" class="group bg-white border border-gray-200 p-5 rounded-2xl shadow-sm hover:-translate-y-1 hover:shadow-lg transition focus:outline-none focus:ring-4 focus:ring-purple-200">
                    <span class="inline-flex text-[11px] font-bold uppercase tracking-wider rounded-full bg-orange-50 text-orange-700 px-2.5 py-1 mb-8">{{ $action['status'] }}</span>
                    <h3 class="text-xl font-bold text-gray-950 group-hover:text-primary">{{ $action['label'] }} <span aria-hidden="true">→</span></h3>
                    <p class="mt-2 text-sm text-gray-600">{{ $action['detail'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>
