<?php

namespace App\Services\Tenancy;

use App\Models\AdmissionEnquiry;
use App\Models\Achievement;
use App\Models\Alumni;
use App\Models\BoardResult;
use App\Models\DataChangeLog;
use App\Models\Download;
use App\Models\Event;
use App\Models\FestCateringOrder;
use App\Models\FestClashRequest;
use App\Models\FestEventInvoice;
use App\Models\FestEventSchoolPartition;
use App\Models\FestFoodCoupon;
use App\Models\FestHouseSchool;
use App\Models\FestLevelRegistration;
use App\Models\FestRegistration;
use App\Models\FestResult;
use App\Models\FestSchoolEventFee;
use App\Models\FestSchoolVerification;
use App\Models\FestSubstitutionRequest;
use App\Models\GalleryAlbum;
use App\Models\JobVacancy;
use App\Models\KalotsavResult;
use App\Models\McqQuestionBank;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\MembershipPayment;
use App\Models\NewsArticle;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\SchoolDocument;
use App\Models\SchoolLockOverride;
use App\Models\SchoolRegionAssignment;
use App\Models\SchoolUserEventScope;
use App\Models\SchoolYearSubmission;
use App\Models\SiteSection;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\StudentEditChangeRequest;
use App\Models\TcRequest;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\Testimonial;
use App\Models\TrainingInvoice;
use App\Models\TrainingRegistration;
use App\Models\TrainingSchoolFee;
use App\Models\UploadedFileBackup;
use App\Models\User;
use App\Models\UserProfileChangeRequest;
use Illuminate\Support\Facades\File;

class SchoolDataPurger
{
    /** @return array<string, int> */
    public function purgeTenantData(Tenant $school): array
    {
        $sahodaya = Tenant::query()->findOrFail($school->parent_id);
        $counts = [];

        $sahodaya->run(function () use ($school, &$counts) {
            $id = $school->id;

            $counts['membership_payments'] = MembershipPayment::query()->where('school_id', $id)->delete();
            $counts['registrations'] = Registration::query()->where('school_id', $id)->delete();
            $counts['school_year_submissions'] = SchoolYearSubmission::query()->where('school_id', $id)->delete();
            $counts['students'] = Student::query()->where('tenant_id', $id)->delete();
            $counts['school_classes'] = SchoolClass::query()->where('tenant_id', $id)->delete();
            $counts['uploaded_file_backups'] = UploadedFileBackup::query()->where('school_id', $id)->delete();
            $counts['data_change_logs'] = DataChangeLog::query()->where('school_id', $id)->delete();
            $counts['kalotsav_results'] = KalotsavResult::query()->where('school_tenant_id', $id)->delete();

            // Fest/Sports — deleting the registration row first lets its DB-level
            // cascadeOnDelete() FKs clean up fest_groups/participants/attendance/marks.
            $counts['fest_registrations'] = FestRegistration::query()->where('school_id', $id)->delete();
            $counts['fest_results'] = FestResult::query()->where('school_id', $id)->delete();
            $counts['fest_school_event_fees'] = FestSchoolEventFee::query()->where('school_id', $id)->delete();
            $counts['fest_house_schools'] = FestHouseSchool::query()->where('school_id', $id)->delete();
            $counts['fest_catering_orders'] = FestCateringOrder::query()->where('school_id', $id)->delete();
            $counts['fest_food_coupons'] = FestFoodCoupon::query()->where('school_id', $id)->delete();
            $counts['fest_event_invoices'] = FestEventInvoice::query()->where('school_id', $id)->delete();
            $counts['fest_event_school_partitions'] = FestEventSchoolPartition::query()->where('school_id', $id)->delete();
            $counts['fest_school_verifications'] = FestSchoolVerification::query()->where('school_id', $id)->delete();
            $counts['fest_substitution_requests'] = FestSubstitutionRequest::query()->where('school_id', $id)->delete();
            $counts['fest_clash_requests'] = FestClashRequest::query()->where('school_id', $id)->delete();
            $counts['fest_level_registrations'] = FestLevelRegistration::query()->where('school_id', $id)->delete();

            // Talent Search / MCQ — deleting mcq_registrations first cascades mcq_marks.
            $counts['mcq_registrations'] = McqRegistration::query()->where('school_id', $id)->delete();
            $counts['mcq_question_banks'] = McqQuestionBank::query()->where('school_id', $id)->delete();
            $counts['mcq_school_fees'] = McqSchoolFee::query()->where('school_id', $id)->delete();

            // Teacher Training — deleting training_registrations first cascades training_attendance.
            $counts['training_registrations'] = TrainingRegistration::query()->where('school_id', $id)->delete();
            $counts['training_school_fees'] = TrainingSchoolFee::query()->where('school_id', $id)->delete();
            $counts['training_invoices'] = TrainingInvoice::query()->where('school_id', $id)->delete();

            // Pending workflow / governance rows scoped to this school.
            $counts['student_edit_change_requests'] = StudentEditChangeRequest::query()->where('school_id', $id)->delete();
            $counts['school_lock_overrides'] = SchoolLockOverride::query()->where('school_id', $id)->delete();
            $counts['user_profile_change_requests'] = UserProfileChangeRequest::query()->where('school_id', $id)->delete();
            $counts['school_user_event_scopes'] = SchoolUserEventScope::query()->where('school_id', $id)->delete();
            $counts['school_documents'] = SchoolDocument::query()->where('school_id', $id)->delete();
            $counts['school_region_assignments'] = SchoolRegionAssignment::query()->where('school_id', $id)->delete();

            $counts['news_articles'] = NewsArticle::query()->where('tenant_id', $id)->delete();
            $counts['events'] = Event::query()->where('tenant_id', $id)->delete();
            $counts['gallery_albums'] = GalleryAlbum::query()->where('tenant_id', $id)->delete();
            $counts['staff_members'] = StaffMember::query()->where('tenant_id', $id)->delete();
            $counts['achievements'] = Achievement::query()->where('tenant_id', $id)->delete();
            $counts['testimonials'] = Testimonial::query()->where('tenant_id', $id)->delete();
            $counts['alumni'] = Alumni::query()->where('tenant_id', $id)->delete();
            $counts['downloads'] = Download::query()->where('tenant_id', $id)->delete();
            $counts['job_vacancies'] = JobVacancy::query()->where('tenant_id', $id)->delete();
            $counts['board_results'] = BoardResult::query()->where('tenant_id', $id)->delete();
            $counts['admission_enquiries'] = AdmissionEnquiry::query()->where('tenant_id', $id)->delete();
            $counts['tc_requests'] = TcRequest::query()->where('tenant_id', $id)->delete();
            $counts['site_sections'] = SiteSection::query()->where('tenant_id', $id)->delete();
            $counts['tenant_settings'] = TenantSetting::query()->where('tenant_id', $id)->delete();
        });

        return $counts;
    }

    public function purgeCentralData(Tenant $school): int
    {
        $users = User::query()->where('tenant_id', $school->id)->get();
        foreach ($users as $user) {
            $user->syncRoles([]);
            $user->delete();
        }

        $school->domains()->delete();
        $school->delete();

        return $users->count();
    }

    public function purgeStorage(Tenant $school): bool
    {
        $path = storage_path('tenant'.$school->id);

        if (! is_dir($path)) {
            return false;
        }

        File::deleteDirectory($path);

        return true;
    }

    /** @return array{tenant: array<string, int>, users: int, storage_removed: bool}> */
    public function purge(Tenant $school): array
    {
        abort_unless($school->type === 'school', 422, 'Only school tenants can be purged.');

        $tenantCounts = $this->purgeTenantData($school);
        $users = $this->purgeCentralData($school);
        $storageRemoved = $this->purgeStorage($school);

        return [
            'tenant' => $tenantCounts,
            'users' => $users,
            'storage_removed' => $storageRemoved,
        ];
    }
}
