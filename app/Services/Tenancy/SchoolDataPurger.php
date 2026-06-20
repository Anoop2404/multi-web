<?php

namespace App\Services\Tenancy;

use App\Models\AdmissionEnquiry;
use App\Models\Achievement;
use App\Models\Alumni;
use App\Models\BoardResult;
use App\Models\DataChangeLog;
use App\Models\Download;
use App\Models\Event;
use App\Models\GalleryAlbum;
use App\Models\JobVacancy;
use App\Models\KalotsavResult;
use App\Models\MembershipPayment;
use App\Models\NewsArticle;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\SchoolYearSubmission;
use App\Models\SiteSection;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\TcRequest;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\Testimonial;
use App\Models\UploadedFileBackup;
use App\Models\User;
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
