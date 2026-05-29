<section class="py-16 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            @if(!empty($config['eyebrow']))
            <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Contact Us' }}</h2>
        </div>

        <div class="grid lg:grid-cols-2 gap-10 items-start">
            {{-- Info + Form --}}
            <div class="space-y-6">
                {{-- Contact info --}}
                <div class="bg-gray-50 rounded-2xl p-6 space-y-4">
                    @foreach([
                        ['key'=>'address','icon'=>'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z'],
                        ['key'=>'phone','icon'=>'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
                        ['key'=>'email','icon'=>'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                    ] as $item)
                    @if(!empty($config[$item['key']]))
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-full shrink-0 flex items-center justify-center"
                             style="background-color: color-mix(in srgb, var(--color-primary) 15%, transparent)">
                            <svg class="w-4 h-4" style="color: var(--color-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                            </svg>
                        </div>
                        <p class="text-gray-700 pt-1.5">{{ $config[$item['key']] }}</p>
                    </div>
                    @endif
                    @endforeach
                </div>

                {{-- Quick contact form --}}
                <form action="{{ $config['form_action'] ?? '/contact' }}" method="POST"
                      class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm space-y-4">
                    @csrf
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Name</label>
                            <input type="text" name="name" required
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:border-transparent"
                                   style="--tw-ring-color: var(--color-primary)">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Phone</label>
                            <input type="tel" name="phone"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:border-transparent">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Email</label>
                        <input type="email" name="email" required
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Message</label>
                        <textarea name="message" rows="4" required
                                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 resize-none"></textarea>
                    </div>
                    <button type="submit"
                            class="w-full font-semibold py-3 rounded-lg text-white transition hover:opacity-90"
                            style="background-color: var(--color-primary)">
                        Send Message
                    </button>
                </form>
            </div>

            {{-- Map embed --}}
            @if(!empty($config['map_embed_url']))
            <div class="rounded-2xl overflow-hidden shadow-sm h-[500px]">
                <iframe src="{{ $config['map_embed_url'] }}"
                        width="100%" height="100%" style="border:0;" allowfullscreen
                        loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
            @endif
        </div>
    </div>
</section>
