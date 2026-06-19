<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\ClassCategory;
use App\Models\ClassCategoryOverride;
use App\Models\MasterClass;
use App\Models\MembershipFeeSlab;
use App\Models\SahodayaProfile;
use App\Models\SahodayaRegistrationWindow;
use App\Models\TeachingType;
use App\Models\TeachingTypeOverride;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Membership\MasterClassService;
use App\Support\AcademicYear;
use App\Support\SahodayaHomepageContent;
use App\Support\SchoolApplicationForm;
use App\Support\TenantBranding;
use App\Services\Audit\DataChangeLogger;
use App\Services\Mail\SahodayaMailer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MembershipSettingsController extends SahodayaAdminController
{
    public function index(EffectiveMasterDataResolver $resolver, MasterClassService $masterClassService)
    {
        $profile = SahodayaProfile::firstOrCreate(
            ['tenant_id' => $this->sahodaya->id],
            ['student_data_mode' => 'not_required', 'membership_fee_type' => 'fixed']
        );

        $masterClassService->ensureForSahodaya($this->sahodaya->id);

        $categoryOverrides = ClassCategoryOverride::where('sahodaya_id', $this->sahodaya->id)
            ->get()
            ->keyBy('class_category_id');

        $academicYear = AcademicYear::forSahodaya($this->sahodaya->id);

        return $this->inertia('Sahodaya/Membership/Settings', [
            'profile'            => array_merge($profile->toArray(), [
                'mail_configured' => $profile->mailIsConfigured(),
            ]),
            'feeSlabs'           => MembershipFeeSlab::where('sahodaya_id', $this->sahodaya->id)->where('academic_year', $academicYear)->orderBy('min_students')->get(),
            'registrationWindow' => SahodayaRegistrationWindow::where('sahodaya_id', $this->sahodaya->id)->where('academic_year', $academicYear)->first(),
            'academicYear'       => $academicYear,
            'calendarYear'       => AcademicYear::calendarCurrent(),
            'academicYearOptions'=> AcademicYear::options(),
            'globalCategories'   => ClassCategory::global()->active()->orderBy('sort_order')->get()->map(function (ClassCategory $category) use ($categoryOverrides) {
                $override = $categoryOverrides->get($category->id);

                return array_merge($category->toArray(), [
                    'sort_order' => $override?->sort_order ?? $category->sort_order,
                ]);
            }),
            'customCategories'   => ClassCategory::forSahodaya($this->sahodaya->id)->orderBy('sort_order')->get(),
            'hiddenCategoryIds'  => ClassCategoryOverride::where('sahodaya_id', $this->sahodaya->id)->where('is_hidden', true)->pluck('class_category_id'),
            'masterClasses'      => $resolver->masterClasses($this->sahodaya->id),
            'globalTypes'        => TeachingType::global()->active()->orderBy('sort_order')->get(),
            'customTypes'        => TeachingType::where('sahodaya_id', $this->sahodaya->id)->orderBy('sort_order')->get(),
            'hiddenTypeIds'      => TeachingTypeOverride::where('sahodaya_id', $this->sahodaya->id)->where('is_hidden', true)->pluck('teaching_type_id'),
            'effectiveCategories'=> $resolver->classCategories($this->sahodaya->id),
            'effectiveTypes'     => $resolver->teachingTypes($this->sahodaya->id),
            'applicationFormFields' => SchoolApplicationForm::resolve($profile),
            'applicationFormGroups' => [
                'school'    => 'School Details',
                'principal' => 'Principal Details',
                'account'   => 'Login Account',
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $profile = SahodayaProfile::firstOrCreate(['tenant_id' => $this->sahodaya->id]);

        $data = $request->validate([
            'name'                         => 'nullable|string|max:255',
            'slug'                         => 'nullable|string|max:100',
            'prefix'                       => 'nullable|string|max:10',
            'cbse_region'                  => 'nullable|string|max:100',
            'address'                      => 'nullable|string|max:1000',
            'contact_email'                => 'nullable|email|max:255',
            'contact_phone'                => 'nullable|string|max:30',
            'student_data_mode'            => 'required|in:full_records,counts_only,not_required',
            'membership_fee_type'          => 'required|in:fixed,variable_by_student_count',
            'fixed_membership_fee_amount'  => 'nullable|numeric|min:0',
            'teacher_registration_enabled' => 'boolean',
            'payment_instructions'         => 'nullable|string|max:5000',
            'payment_bank_name'            => 'nullable|string|max:150',
            'payment_account_no'           => 'nullable|string|max:50',
            'payment_ifsc'                 => 'nullable|string|max:20',
            'payment_upi'                  => 'nullable|string|max:100',
            'active_academic_year'         => ['nullable', 'string', 'max:10', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        if ($profile->prefixes_locked && isset($data['prefix']) && $data['prefix'] !== $profile->prefix) {
            return back()->with('error', 'Sahodaya prefix is locked after first registration number was issued.');
        }

        if (! empty($data['name']) && $data['name'] !== $this->sahodaya->name) {
            $this->sahodaya->update(['name' => $data['name']]);
        }
        unset($data['name']);

        $before = $profile->only(array_keys($data));
        $profile->update($data);

        app(DataChangeLogger::class)->updated(
            $profile,
            'Sahodaya membership settings updated',
            DataChangeLogger::diff($before, $profile->only(array_keys($data))),
            null,
            'sahodaya_settings',
            ['sahodaya_id' => $this->sahodaya->id],
        );

        if (isset($data['contact_phone']) || isset($data['contact_email']) || isset($data['address'])) {
            $footer = $this->sahodaya->getSetting('footer_config', []) ?? [];
            if (isset($data['contact_phone'])) {
                $footer['phone'] = $data['contact_phone'];
            }
            if (isset($data['contact_email'])) {
                $footer['email'] = $data['contact_email'];
            }
            if (isset($data['address'])) {
                $footer['address'] = $data['address'];
            }
            $this->sahodaya->setSetting('footer_config', $footer);

            SahodayaHomepageContent::update($this->sahodaya, array_filter([
                'phone'   => $data['contact_phone'] ?? null,
                'email'   => $data['contact_email'] ?? null,
                'address' => $data['address'] ?? null,
            ]));
        }

        return back()->with('success', 'Membership settings saved.');
    }

    public function updateMembershipFees(Request $request)
    {
        $profile = SahodayaProfile::firstOrCreate(['tenant_id' => $this->sahodaya->id]);

        $data = $request->validate([
            'membership_fee_type'         => 'required|in:fixed,variable_by_student_count',
            'fixed_membership_fee_amount' => 'nullable|numeric|min:0',
        ]);

        $before = $profile->only(array_keys($data));
        $profile->update($data);

        app(DataChangeLogger::class)->updated(
            $profile,
            'Sahodaya membership fees updated',
            DataChangeLogger::diff($before, $profile->only(array_keys($data))),
            null,
            'sahodaya_settings',
            ['sahodaya_id' => $this->sahodaya->id],
        );

        return back()->with('success', 'Membership fees saved.');
    }

    public function updatePaymentDetails(Request $request)
    {
        $profile = SahodayaProfile::firstOrCreate(['tenant_id' => $this->sahodaya->id]);

        $data = $request->validate([
            'payment_instructions' => 'nullable|string|max:5000',
            'payment_bank_name'    => 'nullable|string|max:150',
            'payment_account_no'   => 'nullable|string|max:50',
            'payment_ifsc'         => 'nullable|string|max:20',
            'payment_upi'          => 'nullable|string|max:100',
        ]);

        $before = $profile->only(array_keys($data));
        $profile->update($data);

        app(DataChangeLogger::class)->updated(
            $profile,
            'Sahodaya payment details updated',
            DataChangeLogger::diff($before, $profile->only(array_keys($data))),
            null,
            'sahodaya_settings',
            ['sahodaya_id' => $this->sahodaya->id],
        );

        return back()->with('success', 'Payment details saved. Schools will see these on the membership payment step.');
    }

    public function updateMailSettings(Request $request)
    {
        $profile = SahodayaProfile::firstOrCreate(['tenant_id' => $this->sahodaya->id]);

        $data = $request->validate([
            'mail_host'         => 'nullable|string|max:255',
            'mail_port'         => 'nullable|integer|min:1|max:65535',
            'mail_encryption'   => 'nullable|string|in:tls,ssl',
            'mail_username'     => 'nullable|string|max:255',
            'mail_password'     => 'nullable|string|max:255',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name'    => 'nullable|string|max:255',
        ]);

        if (empty($data['mail_password'])) {
            unset($data['mail_password']);
        }

        $profile->update($data);

        return back()->with('success', 'Zoho mail settings saved.');
    }

    public function testMailSettings(Request $request)
    {
        $profile = SahodayaProfile::firstOrCreate(['tenant_id' => $this->sahodaya->id]);

        $data = $request->validate([
            'test_email' => 'nullable|email|max:255',
        ]);

        if (! $profile->mailIsConfigured()) {
            return back()->with('error', 'Save Zoho SMTP username and password before sending a test email.');
        }

        $to = $data['test_email'] ?? $profile->contact_email ?? $request->user()->email;

        SahodayaMailer::for($this->sahodaya->id)->sendRaw(
            $to,
            'Test email — '.$this->sahodaya->name,
            "This is a test email from {$this->sahodaya->name} membership portal.\n\nIf you received this, Zoho SMTP is configured correctly.",
        );

        return back()->with('success', "Test email sent to {$to}.");
    }

    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => 'required|image|max:2048']);

        TenantBranding::storeUpload($this->sahodaya, $request->file('logo'));

        return back()->with('success', 'Logo updated.');
    }

    public function storeFeeSlab(Request $request)
    {
        $data = $request->validate([
            'academic_year' => 'required|string|max:10',
            'min_students'  => 'required|integer|min:0',
            'max_students'  => 'nullable|integer|min:0',
            'amount'        => 'required|numeric|min:0',
        ]);

        MembershipFeeSlab::create(array_merge($data, ['sahodaya_id' => $this->sahodaya->id]));

        return back()->with('success', 'Fee slab added.');
    }

    public function destroyFeeSlab(string $tenantId, MembershipFeeSlab $feeSlab)
    {
        abort_if($feeSlab->sahodaya_id !== $this->sahodaya->id, 403);
        $feeSlab->delete();

        return back()->with('success', 'Fee slab removed.');
    }

    public function updateRegistrationWindow(Request $request)
    {
        $data = $request->validate([
            'academic_year'          => 'required|string|max:10',
            'registration_starts_at' => 'nullable|date',
            'registration_ends_at'   => 'nullable|date|after_or_equal:registration_starts_at',
        ]);

        SahodayaRegistrationWindow::updateOrCreate(
            ['sahodaya_id' => $this->sahodaya->id, 'academic_year' => $data['academic_year']],
            $data
        );

        return back()->with('success', 'Registration window saved.');
    }

    public function storeCustomCategory(Request $request)
    {
        $data = $request->validate([
            'code'       => 'required|string|max:20',
            'label'      => 'required|string|max:100',
            'min_class'  => 'nullable|integer|min:1|max:12',
            'max_class'  => 'nullable|integer|min:1|max:12',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        ClassCategory::create(array_merge($data, [
            'sahodaya_id' => $this->sahodaya->id,
            'sort_order'  => $data['sort_order']
                ?? ((int) ClassCategory::forSahodaya($this->sahodaya->id)->max('sort_order') + 1),
        ]));

        return back()->with('success', 'Custom category added.');
    }

    public function updateCustomCategory(Request $request, ClassCategory $classCategory)
    {
        abort_if($classCategory->sahodaya_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'code'       => 'required|string|max:20',
            'label'      => 'required|string|max:100',
            'min_class'  => 'nullable|integer|min:1|max:12',
            'max_class'  => 'nullable|integer|min:1|max:12',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $classCategory->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroyCustomCategory(ClassCategory $classCategory)
    {
        abort_if($classCategory->sahodaya_id !== $this->sahodaya->id, 403);

        $classCategory->delete();

        return back()->with('success', 'Category removed.');
    }

    public function storeMasterClass(Request $request, EffectiveMasterDataResolver $resolver)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:50',
            'class_category_id' => ['required', Rule::exists(ClassCategory::class, 'id')],
            'display_order'     => 'nullable|integer|min:0',
        ]);

        $this->assertCategoryVisible($resolver, (int) $data['class_category_id']);

        $order = $data['display_order']
            ?? ((int) MasterClass::forSahodaya($this->sahodaya->id)->max('display_order') + 1);

        MasterClass::create([
            'sahodaya_id'       => $this->sahodaya->id,
            'class_category_id' => $data['class_category_id'],
            'name'              => trim($data['name']),
            'display_order'     => $order,
            'is_active'         => true,
        ]);

        return back()->with('success', 'Class added.');
    }

    public function updateMasterClass(Request $request, MasterClass $masterClass, EffectiveMasterDataResolver $resolver)
    {
        abort_if($masterClass->sahodaya_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name'              => 'required|string|max:50',
            'class_category_id' => ['required', Rule::exists(ClassCategory::class, 'id')],
            'display_order'     => 'nullable|integer|min:0',
        ]);

        $this->assertCategoryVisible($resolver, (int) $data['class_category_id']);

        $masterClass->update([
            'name'              => trim($data['name']),
            'class_category_id' => $data['class_category_id'],
            'display_order'     => $data['display_order'] ?? $masterClass->display_order,
        ]);

        return back()->with('success', 'Class updated.');
    }

    public function destroyMasterClass(MasterClass $masterClass)
    {
        abort_if($masterClass->sahodaya_id !== $this->sahodaya->id, 403);

        $masterClass->delete();

        return back()->with('success', 'Class removed.');
    }

    private function assertCategoryVisible(EffectiveMasterDataResolver $resolver, int $categoryId): void
    {
        abort_unless(
            $resolver->classCategories($this->sahodaya->id)->contains('id', $categoryId),
            422,
            'Selected category is not available for this Sahodaya.'
        );
    }

    public function toggleCategoryOverride(Request $request)
    {
        $data = $request->validate([
            'class_category_id' => ['required', Rule::exists(ClassCategory::class, 'id')],
            'is_hidden'         => 'required|boolean',
        ]);

        ClassCategoryOverride::updateOrCreate(
            ['sahodaya_id' => $this->sahodaya->id, 'class_category_id' => $data['class_category_id']],
            ['is_hidden' => $data['is_hidden']]
        );

        return back()->with('success', 'Category visibility updated.');
    }

    public function updateGlobalCategorySort(Request $request, ClassCategory $classCategory)
    {
        abort_if($classCategory->sahodaya_id !== null, 403);

        $data = $request->validate([
            'sort_order' => 'required|integer|min:0',
        ]);

        ClassCategoryOverride::updateOrCreate(
            ['sahodaya_id' => $this->sahodaya->id, 'class_category_id' => $classCategory->id],
            ['sort_order' => $data['sort_order']]
        );

        return back()->with('success', 'Category sort order updated.');
    }

    public function storeCustomTeachingType(Request $request)
    {
        $data = $request->validate([
            'code'       => 'required|string|max:20',
            'label'      => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        TeachingType::create(array_merge($data, [
            'sahodaya_id' => $this->sahodaya->id,
            'sort_order'  => $data['sort_order'] ?? 0,
        ]));

        return back()->with('success', 'Custom teaching type added.');
    }

    public function toggleTypeOverride(Request $request)
    {
        $data = $request->validate([
            'teaching_type_id' => ['required', Rule::exists(TeachingType::class, 'id')],
            'is_hidden'        => 'required|boolean',
        ]);

        TeachingTypeOverride::updateOrCreate(
            ['sahodaya_id' => $this->sahodaya->id, 'teaching_type_id' => $data['teaching_type_id']],
            ['is_hidden' => $data['is_hidden']]
        );

        return back()->with('success', 'Teaching type visibility updated.');
    }

    public function updateApplicationForm(Request $request)
    {
        $profile = SahodayaProfile::firstOrCreate(['tenant_id' => $this->sahodaya->id]);

        $definitions = SchoolApplicationForm::definitions();
        $rules = ['fields' => 'required|array'];

        foreach ($definitions as $key => $def) {
            if ($def['locked'] ?? false || $key === 'password_confirmation') {
                continue;
            }
            $rules["fields.{$key}"]           = 'array';
            $rules["fields.{$key}.enabled"]   = 'boolean';
            $rules["fields.{$key}.required"]  = 'boolean';
        }

        $data = $request->validate($rules);

        $profile->update([
            'application_form_config' => SchoolApplicationForm::normalizeAdminInput($data['fields']),
        ]);

        return back()->with('success', 'Registration form fields saved.');
    }
}
