@php
    $disc = $config['disclosure'] ?? [];
@endphp
<section class="py-16 px-4 bg-white">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold font-heading text-gray-900">Mandatory Public Disclosure</h2>
            <p class="text-gray-500 mt-2 text-sm">As per CBSE Affiliation Bye-Laws</p>
        </div>

        @php
        $sections = [
            'A. GENERAL INFORMATION' => [
                'Name of the School'              => $disc['school_name'] ?? $tenant->name,
                'Affiliation No.'                 => $disc['affiliation_no'] ?? '',
                'School Code'                     => $disc['school_code'] ?? '',
                'Complete Address with Pin Code'  => $disc['address'] ?? '',
                'Principal Name & Qualification'  => $disc['principal'] ?? '',
                'School Email Id'                 => $disc['email'] ?? '',
                'Contact Number'                  => $disc['phone'] ?? '',
            ],
            'B. DOCUMENTS AND INFORMATION' => [
                'Copies of Affiliation/Upgradation Letter'        => $disc['docs']['affiliation_letter'] ?? null,
                'Copies of Societies/Trust/Company Registration'  => $disc['docs']['trust_registration'] ?? null,
                'Copy of No Objection Certificate'                => $disc['docs']['noc'] ?? null,
                'Copy of Recognition Certificate'                 => $disc['docs']['recognition'] ?? null,
                'Copy of Valid Building Safety Certificate'       => $disc['docs']['building_safety'] ?? null,
                'Copy of Valid Fire Safety Certificate'           => $disc['docs']['fire_safety'] ?? null,
                'Copy of the DEO Certificate'                     => $disc['docs']['deo_certificate'] ?? null,
                'Copies of Valid Water, Health & Sanitation Certificates' => $disc['docs']['health_sanitation'] ?? null,
            ],
            'C. RESULT AND ACADEMICS' => [
                'Fee Structure of the School'                     => $disc['docs']['fee_structure'] ?? null,
                'Annual Academic Calendar'                        => $disc['docs']['academic_calendar'] ?? null,
                'List of School Management Committee'             => $disc['docs']['smc'] ?? null,
                'List of Parents-Teachers Association Members'    => $disc['docs']['pta'] ?? null,
                'Last Three-Year Result of Board Examination'     => $disc['docs']['board_results'] ?? null,
            ],
            'D. STAFF (TEACHING)' => [
                'Principal'       => $disc['staff_info']['principal'] ?? '',
                'Total Teachers'  => $disc['staff_info']['total_teachers'] ?? '',
                'PGT'             => $disc['staff_info']['pgt'] ?? '',
                'TGT'             => $disc['staff_info']['tgt'] ?? '',
                'PRT'             => $disc['staff_info']['prt'] ?? '',
            ],
            'E. SCHOOL INFRASTRUCTURE' => [
                'Total Campus Area (sq. mt.)'      => $disc['infrastructure']['campus_area'] ?? '',
                'No. and size of the class rooms'  => $disc['infrastructure']['classrooms'] ?? '',
                'No. and size of the labs'         => $disc['infrastructure']['labs'] ?? '',
                'Internet Facility'                => $disc['infrastructure']['internet'] ?? '',
                'No. of Girls\' Toilets'           => $disc['infrastructure']['girls_toilets'] ?? '',
                'No. of Boys\' Toilets'            => $disc['infrastructure']['boys_toilets'] ?? '',
                'Library'                          => $disc['infrastructure']['library'] ?? '',
            ],
        ];
        @endphp

        @foreach($sections as $heading => $rows)
        <div class="mb-8">
            <h3 class="font-bold text-sm uppercase tracking-wide px-4 py-2 rounded-t-lg text-white"
                style="background-color: var(--color-primary)">
                {{ $heading }}
            </h3>
            <div class="border border-t-0 border-gray-200 rounded-b-lg overflow-hidden">
                @foreach($rows as $label => $value)
                <div class="grid grid-cols-2 divide-x divide-gray-100 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                    <div class="px-4 py-3 text-sm text-gray-600 font-medium bg-gray-50">{{ $label }}</div>
                    <div class="px-4 py-3 text-sm text-gray-800">
                        @if(is_string($value) && str_starts_with($value, 'http'))
                        <a href="{{ $value }}" target="_blank"
                           class="font-semibold flex items-center gap-1 hover:underline"
                           style="color: var(--color-primary)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Download / View
                        </a>
                        @elseif($value)
                        {{ $value }}
                        @else
                        <span class="text-gray-300">—</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
</section>
