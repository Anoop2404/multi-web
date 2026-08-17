<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 bg-white border-t border-slate-200">
    <div class="max-w-7xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
            @if(!empty($config['eyebrow']))
            <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-primary px-3.5 py-1.5 rounded-full">
                {{ $config['eyebrow'] }}
            </span>
            @endif
            <h2 class="text-3xl sm:text-4xl font-extrabold font-heading text-slate-900 tracking-tight">
                {{ $config['heading'] ?? 'Connect With Us' }}
            </h2>
            <p class="text-sm sm:text-base text-slate-600 font-normal">
                Have an enquiry regarding membership, circulars or upcoming events? Get in touch with our secretariat.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
            {{-- Info cards + Form --}}
            <div class="space-y-6">
                {{-- Contact Info Cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-1">
                        <div class="w-8 h-8 rounded-lg v2-badge-primary flex items-center justify-center mb-2">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                        </div>
                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Secretariat</h4>
                        <p class="text-xs text-slate-600 font-medium leading-snug">{{ $config['address'] ?? ($tenant->name.' Office') }}</p>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-1">
                        <div class="w-8 h-8 rounded-lg v2-badge-accent flex items-center justify-center mb-2">
                            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-2.826-1.47-5.114-3.758-6.585-6.586l1.293-.97c.362-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                        </div>
                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Phone</h4>
                        <p class="text-xs text-slate-600 font-medium leading-snug">{{ $config['phone'] ?? '+91 94470 00000' }}</p>
                    </div>

                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-1">
                        <div class="w-8 h-8 rounded-lg v2-badge-primary flex items-center justify-center mb-2">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                        </div>
                        <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Email</h4>
                        <p class="text-xs text-slate-600 font-medium leading-snug truncate">{{ $config['email'] ?? 'info@sahodaya.org' }}</p>
                    </div>
                </div>

                {{-- Quick Contact Form --}}
                <form action="{{ route('tenant.contact.submit') }}" method="POST"
                      class="v2-card p-6 space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Your Full Name *</label>
                            <input type="text" name="name" required placeholder="e.g. Dr. Ramesh Kumar"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/10 transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Contact Phone</label>
                            <input type="tel" name="phone" placeholder="+91 98765 43210"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/10 transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email Address *</label>
                        <input type="email" name="email" required placeholder="principal@school.ac.in"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/10 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Message / Inquiry *</label>
                        <textarea name="message" rows="4" required placeholder="Describe your request..."
                                  class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/10 transition resize-none"></textarea>
                    </div>
                    <button type="submit"
                            class="v2-btn-primary w-full font-bold py-3 px-6 rounded-xl shadow-md text-sm flex items-center justify-center gap-2">
                        <span>Send Official Enquiry</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                    </button>
                </form>
            </div>

            {{-- Map embed or Info Visual --}}
            <div class="w-full h-full min-h-[420px]">
                @if(!empty($config['map_embed_url']))
                <div class="rounded-2xl overflow-hidden shadow-md border border-slate-200 h-full min-h-[420px]">
                    <iframe src="{{ $config['map_embed_url'] }}"
                            width="100%" height="100%" style="border:0;" allowfullscreen
                            loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                @else
                <div class="v2-card p-8 sm:p-10 h-full min-h-[420px] flex flex-col justify-between text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, var(--color-primary) 0%, #0a0a0f 85%);">
                    {{-- Subtle brand-toned glow, restrained --}}
                    <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full opacity-[0.15] pointer-events-none" style="background: radial-gradient(circle, var(--color-accent), transparent 70%);"></div>
                    <div class="absolute inset-0 opacity-[0.06] bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:22px_22px] pointer-events-none"></div>

                    <div class="space-y-5 relative z-10">
                        <div class="w-14 h-14 rounded-2xl bg-white/10 border border-white/15 backdrop-blur-md flex items-center justify-center text-accent shadow-lg">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.5H4.5V21"/></svg>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-widest text-accent">Secretariat</span>
                            <h3 class="text-2xl sm:text-[1.75rem] font-bold font-heading leading-snug mt-1">{{ $tenant->name }} Desk</h3>
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed max-w-sm">
                            Our network office assists CBSE affiliated schools with member registration, event scheduling, circular clarifications, and inter-school academic coordination.
                        </p>
                    </div>

                    <div class="space-y-3 relative z-10 pt-6 mt-6 border-t border-white/10">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400 font-medium">Office Hours</span>
                            <span class="font-bold text-white">Mon – Sat, 9 AM – 5 PM</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400 font-medium">Response Time</span>
                            <span class="inline-flex items-center gap-1.5 font-bold text-accent">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
                                Within 24 Hours
                            </span>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</section>
