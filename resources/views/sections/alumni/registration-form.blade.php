<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-3xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['subheading']))
        <p class="text-center text-gray-500 mb-8">{{ $config['subheading'] }}</p>
        @endif
        <form action="{{ route('alumni.register') }}" method="POST" class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 space-y-5">
            @csrf
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="name" required class="w-full rounded-lg border-gray-200 px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent" style="--tw-ring-color: var(--color-primary)">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Batch Year *</label>
                    <input type="number" name="batch_year" required class="w-full rounded-lg border-gray-200 px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent" style="--tw-ring-color: var(--color-primary)">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" required class="w-full rounded-lg border-gray-200 px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent" style="--tw-ring-color: var(--color-primary)">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="tel" name="phone" class="w-full rounded-lg border-gray-200 px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent" style="--tw-ring-color: var(--color-primary)">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Current Role / Position</label>
                <input type="text" name="current_role" class="w-full rounded-lg border-gray-200 px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent" style="--tw-ring-color: var(--color-primary)">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                <textarea name="message" rows="3" class="w-full rounded-lg border-gray-200 px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary focus:border-transparent" style="--tw-ring-color: var(--color-primary)"></textarea>
            </div>
            <button type="submit" class="w-full py-3 rounded-lg text-white font-semibold text-sm transition hover:opacity-90" style="background-color: var(--color-primary);">
                Register as Alumni
            </button>
        </form>
    </div>
</section>