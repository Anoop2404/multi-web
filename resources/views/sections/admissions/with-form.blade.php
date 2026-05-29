<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-3xl mx-auto">
        <div class="text-center mb-10">
            @if(!empty($config['eyebrow']))
            <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Admissions Enquiry' }}</h2>
            @if(!empty($config['description']))
            <p class="text-gray-500 mt-3">{{ $config['description'] }}</p>
            @endif
        </div>

        @if(session('admission_success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl p-4 mb-6 text-center">
            {{ session('admission_success') }}
        </div>
        @endif

        <form action="/admission-enquiry" method="POST"
              class="bg-white rounded-2xl shadow-sm p-8 space-y-5">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Student Name *</label>
                    <input type="text" name="student_name" required value="{{ old('student_name') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 @error('student_name') border-red-400 @enderror">
                    @error('student_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Date of Birth *</label>
                    <input type="date" name="dob" required value="{{ old('dob') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Class Applying For *</label>
                    <select name="class_applying" required
                            class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 bg-white">
                        <option value="">-- Select --</option>
                        @foreach(['Nursery','LKG','UKG'] + range(1,12) as $cls)
                        <option value="{{ $cls }}" {{ old('class_applying') == $cls ? 'selected' : '' }}>
                            {{ is_numeric($cls) ? "Class $cls" : $cls }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Parent / Guardian Name *</label>
                    <input type="text" name="parent_name" required value="{{ old('parent_name') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Phone *</label>
                    <input type="tel" name="phone" required value="{{ old('phone') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Address</label>
                <textarea name="address" rows="2"
                          class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 resize-none">{{ old('address') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Message / Additional Info</label>
                <textarea name="message" rows="3"
                          class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 resize-none">{{ old('message') }}</textarea>
            </div>

            <button type="submit"
                    class="w-full font-semibold py-3 rounded-xl text-white transition hover:opacity-90"
                    style="background-color: var(--color-primary)">
                Submit Enquiry
            </button>
        </form>
    </div>
</section>
