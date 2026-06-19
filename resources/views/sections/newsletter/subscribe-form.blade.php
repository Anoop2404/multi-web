{{-- newsletter/subscribe-form.blade.php — Email subscription form --}}
<section class="py-16 px-4"
         style="background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);">
    <div class="max-w-3xl mx-auto text-center">
        @if(!empty($config['icon']))
        <div class="text-5xl mb-4">{{ $config['icon'] }}</div>
        @else
        <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4">📧</div>
        @endif

        <h2 class="font-heading text-3xl sm:text-4xl font-bold text-white mb-3">
            {{ $config['heading'] ?? 'Stay Updated' }}
        </h2>
        <p class="text-white/75 text-base max-w-lg mx-auto mb-8">
            {{ $config['subtext'] ?? 'Get the latest circulars, events, and news from our Sahodaya delivered to your inbox.' }}
        </p>

        <form method="POST" action="{{ $config['form_action'] ?? '#' }}"
              class="flex flex-col sm:flex-row gap-3 max-w-md mx-auto">
            @csrf
            <input type="email" name="email" required
                   placeholder="{{ $config['placeholder'] ?? 'Enter your email address' }}"
                   class="flex-1 px-5 py-3.5 rounded-xl text-gray-900 text-sm font-medium placeholder-gray-400 focus:outline-none focus:ring-4 focus:ring-white/30 bg-white shadow-lg">
            <button type="submit"
                    class="px-6 py-3.5 bg-white text-sm font-extrabold rounded-xl shadow-lg hover:scale-105 transition-all whitespace-nowrap"
                    style="color: var(--color-primary)">
                {{ $config['button_label'] ?? 'Subscribe' }}
            </button>
        </form>

        @if(!empty($config['privacy_note']))
        <p class="text-white/50 text-xs mt-4">{{ $config['privacy_note'] }}</p>
        @else
        <p class="text-white/50 text-xs mt-4">No spam, unsubscribe anytime.</p>
        @endif
    </div>
</section>
