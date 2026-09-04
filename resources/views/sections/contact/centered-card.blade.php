@php
    $contact = $tenant->getSetting('contact', []);
@endphp
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-2xl shadow-sm p-8 md:p-12">
            <div class="text-center mb-8">
                @if(!empty($config['badge']))
                <p class="text-sm font-semibold uppercase tracking-widest mb-2" style="color: var(--color-primary)">{{ $config['badge'] }}</p>
                @endif
                <h2 class="text-3xl font-bold font-heading text-gray-900 mb-3">{{ $config['heading'] ?? 'Contact Us' }}</h2>
                @if(!empty($config['intro']))
                <p class="text-gray-500">{{ $config['intro'] }}</p>
                @endif
            </div>

            @if(!empty($contact['phone']) || !empty($contact['email']) || !empty($contact['address']))
            <div class="grid sm:grid-cols-3 gap-4 mb-8 text-center text-sm">
                @if(!empty($contact['phone']))
                <a href="tel:{{ preg_replace('/\s+/', '', $contact['phone']) }}" class="rounded-xl bg-gray-50 px-3 py-3 hover:bg-gray-100 transition">
                    <span class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Phone</span>
                    <span class="font-semibold text-gray-800">{{ $contact['phone'] }}</span>
                </a>
                @endif
                @if(!empty($contact['email']))
                <a href="mailto:{{ $contact['email'] }}" class="rounded-xl bg-gray-50 px-3 py-3 hover:bg-gray-100 transition">
                    <span class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Email</span>
                    <span class="font-semibold text-gray-800 break-all">{{ $contact['email'] }}</span>
                </a>
                @endif
                @if(!empty($contact['address']))
                <div class="rounded-xl bg-gray-50 px-3 py-3">
                    <span class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">Address</span>
                    <span class="font-semibold text-gray-800">{{ $contact['address'] }}</span>
                </div>
                @endif
            </div>
            @endif

            @if(session('success'))
            <div class="mb-6 rounded-lg px-4 py-3 text-sm" style="background-color: color-mix(in srgb, var(--color-primary) 12%, white); color: var(--color-primary)">
                {{ session('success') }}
            </div>
            @endif

            <form method="POST" action="{{ url('/forms/'.($config['form_slug'] ?? 'contact')) }}" class="space-y-4">
                @csrf
                <div class="grid md:grid-cols-2 gap-4">
                    <input type="text" name="name" placeholder="Name" required
                           class="w-full bg-gray-100 border-0 rounded-xl py-3 px-4 text-sm focus:outline-none focus:ring-2"
                           style="--tw-ring-color: var(--color-primary)">
                    <input type="email" name="email" placeholder="Email" required
                           class="w-full bg-gray-100 border-0 rounded-xl py-3 px-4 text-sm focus:outline-none focus:ring-2"
                           style="--tw-ring-color: var(--color-primary)">
                </div>
                <input type="text" name="subject" placeholder="Subject"
                       class="w-full bg-gray-100 border-0 rounded-xl py-3 px-4 text-sm focus:outline-none focus:ring-2"
                       style="--tw-ring-color: var(--color-primary)">
                <textarea name="message" placeholder="Message" rows="5" required
                          class="w-full bg-gray-100 border-0 rounded-xl py-3 px-4 text-sm focus:outline-none focus:ring-2"
                          style="--tw-ring-color: var(--color-primary)"></textarea>
                <div class="text-center pt-2">
                    <button type="submit"
                            class="font-semibold px-8 py-3 rounded-full text-white hover:opacity-90 transition"
                            style="background-color: var(--color-primary)">
                        {{ $config['submit_label'] ?? 'Send Message' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
