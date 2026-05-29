<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-2xl mx-auto">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Transfer Certificate Request' }}</h2>
            <p class="text-gray-500 mt-2 text-sm">{{ $config['description'] ?? 'Fill in the form below. You will be contacted once your TC is ready.' }}</p>
        </div>

        @if(session('tc_success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl p-4 mb-6 text-center">
            {{ session('tc_success') }}
        </div>
        @endif

        <form action="/tc-request" method="POST"
              class="bg-white rounded-2xl shadow-sm p-8 space-y-5">
            @csrf
            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Student Name *</label>
                    <input type="text" name="student_name" required value="{{ old('student_name') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Admission Number *</label>
                    <input type="text" name="admission_number" required value="{{ old('admission_number') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                </div>
            </div>

            <div class="grid sm:grid-cols-3 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Class *</label>
                    <input type="text" name="class" required value="{{ old('class') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Division</label>
                    <input type="text" name="division" value="{{ old('division') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Date of Birth *</label>
                    <input type="date" name="dob" required value="{{ old('dob') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Parent / Guardian Name *</label>
                    <input type="text" name="parent_name" required value="{{ old('parent_name') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Phone *</label>
                    <input type="tel" name="phone" required value="{{ old('phone') }}"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Email</label>
                <input type="email" name="email" value="{{ old('email') }}"
                       class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Reason for Transfer *</label>
                <textarea name="reason" rows="3" required
                          class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 resize-none">{{ old('reason') }}</textarea>
            </div>

            <button type="submit"
                    class="w-full font-semibold py-3 rounded-xl text-white transition hover:opacity-90"
                    style="background-color: var(--color-primary)">
                Submit TC Request
            </button>
        </form>
    </div>
</section>
