@php
    $contact = $tenant->getSetting('contact', []);
    $formSlug = $config['form_slug'] ?? 'contact';
    $siteForm = \App\Models\SiteForm::query()
        ->where('tenant_id', $tenant->id)
        ->where('slug', $formSlug)
        ->first();
    $formFields = $siteForm?->fields_json ?: \App\Models\SiteForm::defaultContactFields();
    $formEnabled = ! $siteForm || $siteForm->is_active;
@endphp
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-3xl mx-auto">
        <div class="bg-white rounded-2xl shadow-sm p-8 md:p-12">
            <div class="text-center mb-8">
                @if(!empty($config['badge']))
                <p class="text-sm font-semibold uppercase tracking-widest mb-2" style="color: var(--color-accent)">{{ $config['badge'] }}</p>
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
                    <span class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">{{ $config['phone_label'] ?? 'Phone' }}</span>
                    <span class="font-semibold text-gray-800">{{ $contact['phone'] }}</span>
                </a>
                @endif
                @if(!empty($contact['email']))
                <a href="mailto:{{ $contact['email'] }}" class="rounded-xl bg-gray-50 px-3 py-3 hover:bg-gray-100 transition">
                    <span class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">{{ $config['email_label'] ?? 'Email' }}</span>
                    <span class="font-semibold text-gray-800 break-all">{{ $contact['email'] }}</span>
                </a>
                @endif
                @if(!empty($contact['address']))
                <div class="rounded-xl bg-gray-50 px-3 py-3">
                    <span class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">{{ $config['address_label'] ?? 'Address' }}</span>
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

            @if($formEnabled)
            <form method="POST" action="{{ url('/forms/'.$formSlug) }}" class="space-y-4">
                @csrf
                @if(! $siteForm || $siteForm->honeypot_enabled)
                <div class="hidden" aria-hidden="true">
                    <label>Website <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label>
                </div>
                @endif
                <div class="grid md:grid-cols-2 gap-4">
                    @foreach($formFields as $field)
                    @php
                        $key = $field['key'] ?? null;
                        $type = $field['type'] ?? 'text';
                        $label = $field['label'] ?? $key;
                        $placeholder = $field['placeholder'] ?? $label;
                    @endphp
                    @continue(!$key)
                    <div class="{{ $type === 'textarea' ? 'md:col-span-2' : '' }}">
                        <label for="contact-{{ $key }}" class="block text-xs font-semibold text-gray-600 mb-1.5">
                            {{ $label }}@if(!empty($field['required'])) * @endif
                        </label>
                        @if($type === 'textarea')
                        <textarea id="contact-{{ $key }}" name="{{ $key }}" placeholder="{{ $placeholder }}" rows="5"
                                  class="w-full bg-gray-100 border-0 rounded-xl py-3 px-4 text-sm focus:outline-none focus:ring-2"
                                  style="--tw-ring-color: var(--color-primary)" @required(!empty($field['required']))>{{ old($key) }}</textarea>
                        @else
                        <input id="contact-{{ $key }}" type="{{ $type }}" name="{{ $key }}" value="{{ old($key) }}" placeholder="{{ $placeholder }}"
                               class="w-full bg-gray-100 border-0 rounded-xl py-3 px-4 text-sm focus:outline-none focus:ring-2"
                               style="--tw-ring-color: var(--color-primary)" @required(!empty($field['required']))>
                        @endif
                        @error($key)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    @endforeach
                </div>
                <div class="text-center pt-2">
                    <button type="submit"
                            class="font-semibold px-8 py-3 rounded-full text-white hover:opacity-90 transition"
                            style="background-color: var(--color-accent)">
                        {{ $config['submit_label'] ?? 'Send Message' }}
                    </button>
                </div>
            </form>
            @endif
        </div>
    </div>
</section>
