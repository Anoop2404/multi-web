<?php

namespace Database\Seeders;

use App\Models\McqExam;
use App\Models\McqQuestionBank;
use App\Models\McqRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Disposable fixture for the isolated local SQLite online-exam demo. */
class LocalOnlineExamDemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing'])
            && config('database.connections.central.driver') === 'sqlite'
            && str_ends_with(config('database.connections.central.database'), '/online-exam-demo.sqlite'), 403,
            'Use only the isolated local online-exam-demo.sqlite database.');

        $this->call(RolesAndPermissionsSeeder::class);
        $cluster = Tenant::firstOrCreate(['id' => '10000000-0000-4000-8000-000000000001'],
            ['name' => 'LOCAL DEMO Sahodaya', 'type' => 'sahodaya', 'is_active' => true]);
        $cluster->update(['domain' => 'exam-demo.localhost']);
        SahodayaProfile::firstOrCreate(['tenant_id' => $cluster->id], ['prefix' => 'DEMO', 'student_data_mode' => 'full_records']);
        $school = Tenant::firstOrCreate(['id' => '10000000-0000-4000-8000-000000000002'],
            ['name' => 'LOCAL DEMO School', 'type' => 'school', 'parent_id' => $cluster->id,
                'is_active' => true, 'membership_status' => 'approved']);
        $admin = User::firstOrCreate(['email' => 'exam-admin@demo.test'],
            ['tenant_id' => $cluster->id, 'name' => 'Demo Exam Admin', 'password' => Hash::make('LocalExamDemo2026!'),
                'email_verified_at' => now(), 'is_active' => true, 'must_change_password' => false]);
        $admin->assignRole('sahodaya_admin');

        $schoolAdmin = User::firstOrCreate(['email' => 'school-admin@demo.test'],
            ['tenant_id' => $school->id, 'name' => 'Demo School Admin', 'password' => Hash::make('LocalExamDemo2026!'),
                'email_verified_at' => now(), 'is_active' => true, 'must_change_password' => false]);
        $schoolAdmin->assignRole('school_admin');

        $exam = McqExam::updateOrCreate(
            ['tenant_id' => $cluster->id, 'code' => 'LOCAL-ONLINE-DEMO'],
            [
                'title' => 'LOCAL DEMO — Online Practice Exam',
                'status' => 'published',
                'delivery_mode' => 'online',
                'duration_minutes' => 15,
                // 0 = deliver full attached bank (single + multi + T/F + numeric).
                'total_questions' => 0,
                'pass_mark' => 40,
                'fee_type' => 'none',
                'settings_json' => ['shuffle_questions' => false, 'shuffle_options' => false],
            ],
        );

        $bank = McqQuestionBank::firstOrCreate(
            ['sahodaya_id' => $cluster->id, 'title' => 'Local demo questions'],
            ['subject' => 'General knowledge', 'status' => 'active'],
        );
        $bank->update(['status' => 'active']);

        $this->seedMixedPaper($bank);

        $exam->questionBanks()->syncWithoutDetaching([$bank->id => ['pick_mode' => 'all', 'display_order' => 1]]);
        $class = SchoolClass::firstOrCreate(['tenant_id' => $school->id, 'name' => 'Class 8']);
        foreach (['candidate', 'practice'] as $index => $name) {
            $user = User::firstOrCreate(['email' => $name.'@demo.test'], [
                'tenant_id' => $school->id, 'name' => 'Demo '.$name, 'password' => Hash::make('LocalExamDemo2026!'),
                'email_verified_at' => now(), 'is_active' => true, 'must_change_password' => false,
            ]);
            $user->assignRole('student');
            $student = Student::firstOrCreate(['user_id' => $user->id], [
                'tenant_id' => $school->id,
                'school_class_id' => $class->id,
                'name' => 'Demo '.$name,
                'status' => 'active',
                'verified_at' => now(),
                'reg_no' => 'STU/DEMO/'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            ]);
            if (! $student->reg_no) {
                $student->update(['reg_no' => 'STU/DEMO/'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)]);
            }
            McqRegistration::firstOrCreate(['exam_id' => $exam->id, 'student_id' => $student->id], [
                'school_id' => $school->id, 'status' => 'registered', 'approval_status' => 'approved',
            ]);
        }

        $timeoutExam = McqExam::updateOrCreate(
            ['tenant_id' => $cluster->id, 'code' => 'LOCAL-TIMEOUT-DEMO'],
            [
                'title' => 'LOCAL DEMO — One-minute Timeout Test',
                'status' => 'published',
                'delivery_mode' => 'online',
                'duration_minutes' => 1,
                'total_questions' => 0,
                'fee_type' => 'none',
                'settings_json' => ['shuffle_questions' => false],
            ],
        );
        $timeoutExam->questionBanks()->syncWithoutDetaching([$bank->id => ['pick_mode' => 'all', 'display_order' => 1]]);
        foreach (Student::where('tenant_id', $school->id)->get() as $timeoutStudent) {
            McqRegistration::firstOrCreate(['exam_id' => $timeoutExam->id, 'student_id' => $timeoutStudent->id], [
                'school_id' => $school->id, 'status' => 'registered', 'approval_status' => 'approved',
            ]);
        }

        $templateExam = McqExam::updateOrCreate(
            ['tenant_id' => $cluster->id, 'code' => 'LOCAL-TEMPLATE-DEMO'],
            [
                'title' => 'LOCAL DEMO — Mixed question types',
                'status' => 'published',
                'delivery_mode' => 'online',
                'duration_minutes' => 15,
                'total_questions' => 0,
                'fee_type' => 'none',
                'scheduled_at' => now()->subMinute(),
                'settings_json' => ['shuffle_questions' => false, 'shuffle_options' => false],
            ],
        );
        $templateExam->questionBanks()->syncWithoutDetaching([$bank->id => ['pick_mode' => 'all', 'display_order' => 1]]);
        foreach (Student::where('tenant_id', $school->id)->get() as $demoStudent) {
            McqRegistration::firstOrCreate(['exam_id' => $templateExam->id, 'student_id' => $demoStudent->id], [
                'school_id' => $school->id, 'status' => 'registered', 'approval_status' => 'approved',
            ]);
        }

        $this->command?->info('Local demo exam '.$exam->id.' ready (single+multi+T/F+numeric).');
        $this->command?->info('Sahodaya: exam-admin@demo.test · School: school-admin@demo.test · Students: candidate@demo.test / practice@demo.test');
        $this->command?->info('Password: LocalExamDemo2026! — run npm run build if Model exams / system-check pages are blank.');
    }

    private function seedMixedPaper(McqQuestionBank $bank): void
    {
        // Replace any prior demo rows so re-seed always exposes all four types.
        $bank->questions()->delete();

        $bank->questions()->create([
            'display_order' => 1,
            'type' => 'single',
            'body_text' => 'What is 2 + 2?',
            'marks' => 1,
            'correct_option_key' => 'b',
            'options_json' => [
                ['key' => 'a', 'label' => '3'],
                ['key' => 'b', 'label' => '4'],
                ['key' => 'c', 'label' => '5'],
            ],
        ]);
        $bank->questions()->create([
            'display_order' => 2,
            'type' => 'single',
            'body_text' => 'Which planet is called the Red Planet?',
            'marks' => 1,
            'correct_option_key' => 'b',
            'options_json' => [
                ['key' => 'a', 'label' => 'Earth'],
                ['key' => 'b', 'label' => 'Mars'],
                ['key' => 'c', 'label' => 'Venus'],
            ],
        ]);
        $bank->questions()->create([
            'display_order' => 3,
            'type' => 'multiple',
            'body_text' => 'Pick the even numbers',
            'marks' => 2,
            'options_json' => [
                ['key' => 'a', 'label' => '2'],
                ['key' => 'b', 'label' => '3'],
                ['key' => 'c', 'label' => '4'],
                ['key' => 'd', 'label' => '5'],
            ],
            'answer_json' => ['keys' => ['a', 'c'], 'show_count' => true],
        ]);
        $bank->questions()->create([
            'display_order' => 4,
            'type' => 'true_false',
            'body_text' => 'A triangle has three sides.',
            'marks' => 1,
            'options_json' => [
                ['key' => 'a', 'label' => 'True'],
                ['key' => 'b', 'label' => 'False'],
            ],
            'answer_json' => ['keys' => ['a']],
            'correct_option_key' => 'a',
        ]);
        $bank->questions()->create([
            'display_order' => 5,
            'type' => 'numeric',
            'body_text' => 'What is 7 × 8?',
            'marks' => 1,
            'options_json' => [],
            'answer_json' => ['value' => 56, 'tolerance' => 0],
        ]);
    }
}
