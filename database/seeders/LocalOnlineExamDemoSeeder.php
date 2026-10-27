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
        $exam = McqExam::firstOrCreate(['tenant_id' => $cluster->id, 'code' => 'LOCAL-ONLINE-DEMO'], [
            'title' => 'LOCAL DEMO — Online Practice Exam', 'status' => 'published', 'delivery_mode' => 'online',
            'duration_minutes' => 15, 'total_questions' => 5, 'pass_mark' => 40, 'fee_type' => 'none',
            'settings_json' => ['shuffle_questions' => true],
        ]);
        $bank = McqQuestionBank::firstOrCreate(['sahodaya_id' => $cluster->id, 'title' => 'Local demo questions'],
            ['subject' => 'General knowledge', 'status' => 'active']);
        $questions = [
            ['What is 2 + 2?', ['3', '4', '5'], 'b'],
            ['Which planet is called the Red Planet?', ['Earth', 'Mars', 'Venus'], 'b'],
            ['How many sides does a triangle have?', ['3', '4', '5'], 'a'],
            ['Which is the largest ocean?', ['Atlantic', 'Indian', 'Pacific'], 'c'],
            ['Which number is even?', ['7', '9', '12'], 'c'],
        ];
        foreach ($questions as $index => [$body, $options, $answer]) {
            $bank->questions()->firstOrCreate(['display_order' => $index + 1], [
                'type' => 'single', 'body_text' => $body, 'marks' => 1,
                'correct_option_key' => $answer,
                'options_json' => collect($options)->map(fn ($label, $i) => ['key' => chr(97 + $i), 'label' => $label])->all(),
            ]);
        }
        $exam->questionBanks()->syncWithoutDetaching([$bank->id => ['pick_mode' => 'all', 'display_order' => 1]]);
        $class = SchoolClass::firstOrCreate(['tenant_id' => $school->id, 'name' => 'Class 8']);
        foreach (['candidate', 'practice'] as $name) {
            $user = User::firstOrCreate(['email' => $name.'@demo.test'], [
                'tenant_id' => $school->id, 'name' => 'Demo '.$name, 'password' => Hash::make('LocalExamDemo2026!'),
                'email_verified_at' => now(), 'is_active' => true, 'must_change_password' => false,
            ]);
            $user->assignRole('student');
            $student = Student::firstOrCreate(['user_id' => $user->id], ['tenant_id' => $school->id,
                'school_class_id' => $class->id, 'name' => 'Demo '.$name, 'status' => 'active', 'verified_at' => now()]);
            McqRegistration::firstOrCreate(['exam_id' => $exam->id, 'student_id' => $student->id], [
                'school_id' => $school->id, 'status' => 'registered', 'approval_status' => 'approved',
            ]);
        }
        $timeoutExam = McqExam::firstOrCreate(['tenant_id' => $cluster->id, 'code' => 'LOCAL-TIMEOUT-DEMO'], [
            'title' => 'LOCAL DEMO — One-minute Timeout Test', 'status' => 'published', 'delivery_mode' => 'online',
            'duration_minutes' => 1, 'total_questions' => 5, 'fee_type' => 'none',
        ]);
        $timeoutExam->questionBanks()->syncWithoutDetaching([$bank->id => ['pick_mode' => 'all', 'display_order' => 1]]);
        foreach (Student::where('tenant_id', $school->id)->get() as $timeoutStudent) {
            McqRegistration::firstOrCreate(['exam_id' => $timeoutExam->id, 'student_id' => $timeoutStudent->id], [
                'school_id' => $school->id, 'status' => 'registered', 'approval_status' => 'approved',
            ]);
        }
        $templateExam = McqExam::firstOrCreate(['tenant_id' => $cluster->id, 'code' => 'LOCAL-TEMPLATE-DEMO'], [
            'title' => 'LOCAL DEMO — Class paper templates', 'status' => 'published', 'delivery_mode' => 'online',
            'duration_minutes' => 15, 'total_questions' => 5, 'fee_type' => 'none',
        ]);
        foreach (Student::where('tenant_id', $school->id)->get() as $demoStudent) {
            McqRegistration::firstOrCreate(['exam_id' => $templateExam->id, 'student_id' => $demoStudent->id], [
                'school_id' => $school->id, 'status' => 'registered', 'approval_status' => 'approved',
            ]);
        }
        $this->command?->info('Local demo exam '.$exam->id.' ready. Candidate: candidate@demo.test; spare: practice@demo.test; password: LocalExamDemo2026!');
    }
}
