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

/**
 * Comprehensive QA fixture for Online MCQ Exams testing:
 * - Single, Multiple (choose N), True/False, and Numeric questions
 * - Wide diagrams (1400x350), Tall diagrams (420x1100), Square diagrams (600x600), Small thumbnails (180x180)
 * - Option images (A, B, C, D choices)
 * - LaTeX / Math formulas (fractions, roots, integrals, chemical formulas)
 * - Multilingual scripts (Malayalam, Hindi, Arabic, Tamil)
 * - Multi-section subject navigation
 * - Post-exam review with explanations and badges
 */
class ComprehensiveMcqQaSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $cluster = Tenant::firstOrCreate(
            ['id' => '10000000-0000-4000-8000-000000000001'],
            ['name' => 'LOCAL DEMO Sahodaya', 'type' => 'sahodaya', 'is_active' => true]
        );
        $cluster->update(['domain' => 'exam-demo.localhost']);
        SahodayaProfile::firstOrCreate(['tenant_id' => $cluster->id], ['prefix' => 'DEMO', 'student_data_mode' => 'full_records']);

        $school = Tenant::firstOrCreate(
            ['id' => '10000000-0000-4000-8000-000000000002'],
            ['name' => 'LOCAL DEMO School', 'type' => 'school', 'parent_id' => $cluster->id, 'is_active' => true, 'membership_status' => 'approved']
        );

        $exam = McqExam::updateOrCreate(
            ['tenant_id' => $cluster->id, 'code' => 'COMPREHENSIVE-MCQ-QA'],
            [
                'title' => 'Comprehensive Online MCQ Assessment — Multilingual & Multi-Media',
                'status' => 'published',
                'delivery_mode' => 'online',
                'duration_minutes' => 60,
                'total_questions' => 0,
                'pass_mark' => 40,
                'fee_type' => 'none',
                'results_published' => true,
                'settings_json' => [
                    'shuffle_questions' => false,
                    'shuffle_options' => false,
                    'result_release' => [
                        'visibility' => 'review',
                        'release_mode' => 'after_completion',
                    ],
                ],
            ]
        );

        // Section 1: Mathematics & Physics
        $bankMath = McqQuestionBank::updateOrCreate(
            ['sahodaya_id' => $cluster->id, 'title' => 'Mathematics & Physics'],
            ['subject' => 'Mathematics & Physics', 'status' => 'active']
        );
        $bankMath->questions()->delete();

        $bankMath->questions()->create([
            'display_order' => 1,
            'type' => 'single',
            'title' => 'Quadratic Formula & Discriminant',
            'body_text' => "Consider the quadratic equation \$ax^2 + bx + c = 0\$ where \$a \\neq 0\$. Which of the following expressions represents the roots given by the quadratic formula?\n\n$$\\Delta = b^2 - 4ac$$",
            'marks' => 2,
            'negative_mark' => 0.5,
            'correct_option_key' => 'a',
            'options_json' => [
                ['key' => 'a', 'label' => '$x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}$'],
                ['key' => 'b', 'label' => '$x = \\frac{b \\pm \\sqrt{b^2 - 4ac}}{2a}$'],
                ['key' => 'c', 'label' => '$x = \\frac{-b \\pm \\sqrt{b^2 + 4ac}}{2a}$'],
                ['key' => 'd', 'label' => '$x = \\frac{-b \\pm \\sqrt{b^2 - 2ac}}{a}$'],
            ],
            'explanation' => "By completing the square on \$ax^2 + bx + c = 0\$, the roots are given by \$x = \\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}\$. The quantity \$\\Delta = b^2 - 4ac\$ is the discriminant.",
        ]);

        $bankMath->questions()->create([
            'display_order' => 2,
            'type' => 'single',
            'title' => 'Kinematics: Displacement vs Time',
            'body_text' => "A particle accelerates uniformly from rest with acceleration \$a\$. Which of the graphs below correctly represents its displacement \$s(t) = \\frac{1}{2}at^2\$ as a function of time?",
            'marks' => 2,
            'negative_mark' => 0.5,
            'correct_option_key' => 'b',
            'options_json' => [
                ['key' => 'a', 'label' => 'Graph A (Linear)', 'image_path' => 'mcq-test-media/opt-graph-a.png'],
                ['key' => 'b', 'label' => 'Graph B (Parabolic Quadratic Curve)', 'image_path' => 'mcq-test-media/opt-graph-b.png'],
                ['key' => 'c', 'label' => 'Graph C (Inverse Decay)', 'image_path' => 'mcq-test-media/opt-graph-c.png'],
                ['key' => 'd', 'label' => 'Graph D (Oscillatory)', 'image_path' => 'mcq-test-media/opt-graph-d.png'],
            ],
            'explanation' => "Because acceleration is constant, displacement \$s(t) \\propto t^2\$, yielding an upward parabolic curve starting at the origin.",
        ]);

        $bankMath->questions()->create([
            'display_order' => 3,
            'type' => 'numeric',
            'title' => 'Calculus: Definite Integral Evaluation',
            'body_text' => "Evaluate the definite integral below and type your exact numeric answer:\n\n$$\\int_{0}^{2} (3x^2 - 2x + 1) \\, dx$$",
            'marks' => 3,
            'negative_mark' => 0,
            'options_json' => [],
            'answer_json' => ['value' => 6, 'tolerance' => 0.05],
            'explanation' => "Evaluating: \$\\int_0^2 (3x^2 - 2x + 1) dx = [x^3 - x^2 + x]_0^2 = (2^3 - 2^2 + 2) - 0 = (8 - 4 + 2) = 6\$.",
        ]);

        // Section 2: Biology & Life Sciences
        $bankBio = McqQuestionBank::updateOrCreate(
            ['sahodaya_id' => $cluster->id, 'title' => 'Biology & Life Sciences'],
            ['subject' => 'Biology & Life Sciences', 'status' => 'active']
        );
        $bankBio->questions()->delete();

        $bankBio->questions()->create([
            'display_order' => 4,
            'type' => 'multiple',
            'title' => 'Signal Cascade (Ultra-Wide Diagram)',
            'document_path' => 'mcq-test-media/wide-circuit.png',
            'body_text' => "Refer to the horizontal multi-stage signal transduction pathway shown in the wide diagram above. Which of the following statements are **TRUE** regarding Stages 2 and 4? (Choose 2 answers)",
            'marks' => 2,
            'negative_mark' => 0,
            'options_json' => [
                ['key' => 'a', 'label' => 'Stage 2 involves intermediate kinase phosphorylation and signal amplification.'],
                ['key' => 'b', 'label' => 'Stage 2 permanently destroys the extracellular membrane receptor.'],
                ['key' => 'c', 'label' => 'Stage 4 represents nuclear effector transcriptional gene activation.'],
                ['key' => 'd', 'label' => 'Stage 4 operates completely independent of earlier pathway stages.'],
            ],
            'answer_json' => ['keys' => ['a', 'c'], 'show_count' => true],
            'explanation' => "Transduction cascades use sequential phosphorylation for signal amplification (Stage 2) and culminate in specific effector nuclear gene activation (Stage 4).",
        ]);

        $bankBio->questions()->create([
            'display_order' => 5,
            'type' => 'single',
            'title' => 'Taxonomic Hierarchy (Tall Diagram)',
            'document_path' => 'mcq-test-media/tall-flowchart.png',
            'body_text' => "Examine the vertical taxonomic classification flowchart above (420x1100). At which node rank do organisms share the highest degree of genetic homology and reproductive compatibility?",
            'marks' => 2,
            'negative_mark' => 0.5,
            'correct_option_key' => 'd',
            'options_json' => [
                ['key' => 'a', 'label' => 'Node Rank A (Kingdom)'],
                ['key' => 'b', 'label' => 'Node Rank C (Class)'],
                ['key' => 'c', 'label' => 'Node Rank E (Genus)'],
                ['key' => 'd', 'label' => 'Node Rank G (Species)'],
            ],
            'explanation' => "Species is the primary fundamental unit of biological classification where individuals possess the highest genetic similarity and can interbreed to produce fertile offspring.",
        ]);

        $bankBio->questions()->create([
            'display_order' => 6,
            'type' => 'true_false',
            'title' => 'Thales Theorem (Square Geometry)',
            'document_path' => 'mcq-test-media/square-geometry.png',
            'body_text' => "In the circle centered at \$O\$ with inscribed triangle \$ABC\$ shown in the geometric diagram above (600x600), if segment \$BC\$ passes through the center \$O\$ as a diameter, then angle \$\\angle BAC\$ is always a right angle (\$90^\\circ\$).",
            'marks' => 1,
            'negative_mark' => 0,
            'correct_option_key' => 'a',
            'options_json' => [
                ['key' => 'a', 'label' => 'True'],
                ['key' => 'b', 'label' => 'False'],
            ],
            'answer_json' => ['keys' => ['a']],
            'explanation' => "By Thales's Theorem, any angle inscribed in a semicircle whose bounding vertices form a diameter subtends an arc of \$180^\\circ\$ and is therefore equal to \$90^\\circ\$.",
        ]);

        // Section 3: World Languages & Literature
        $bankLang = McqQuestionBank::updateOrCreate(
            ['sahodaya_id' => $cluster->id, 'title' => 'World Languages & Literature'],
            ['subject' => 'World Languages & Literature', 'status' => 'active']
        );
        $bankLang->questions()->delete();

        $bankLang->questions()->create([
            'display_order' => 7,
            'type' => 'single',
            'title' => 'Malayalam Literature (മലയാള സാഹിത്യം)',
            'body_text' => "താഴെ പറയുന്ന സാഹിത്യ കൃതികളിൽ മഹാകവി കുമാരനാശാൻ രചിച്ച പ്രശസ്ത ഖണ്ഡകാവ്യം ഏതാണ്?",
            'marks' => 2,
            'negative_mark' => 0.5,
            'correct_option_key' => 'a',
            'options_json' => [
                ['key' => 'a', 'label' => 'കരുണ (Karuna)'],
                ['key' => 'b', 'label' => 'മഗ്ദലനമറിയം (Magdalana Mariyam)'],
                ['key' => 'c', 'label' => 'കേശവീയം (Kesaveeyam)'],
                ['key' => 'd', 'label' => 'ചിത്രയോഗം (Chithrayogam)'],
            ],
            'explanation' => "കരുണ, വീണപൂവ്, നളിനി, ലീല, ദുരവസ്ഥ, ചണ്ഡാലഭിക്ഷുകി തുടങ്ങിയ പ്രശസ്ത ഖണ്ഡകാവ്യങ്ങൾ രചിച്ചത് മഹാകവി കുമാരനാശാനാണ്.",
        ]);

        $bankLang->questions()->create([
            'display_order' => 8,
            'type' => 'single',
            'title' => 'Hindi Literature (हिन्दी साहित्य)',
            'body_text' => "हिन्दी साहित्य में 'छायावाद' (Chhayavad) के चार प्रमुख स्तंभों में निम्नलिखित में से कौन सम्मिलित नहीं हैं?",
            'marks' => 2,
            'negative_mark' => 0.5,
            'correct_option_key' => 'd',
            'options_json' => [
                ['key' => 'a', 'label' => 'जयशंकर प्रसाद (Jaishankar Prasad)'],
                ['key' => 'b', 'label' => 'सूर्यकांत त्रिपाठी \'निराला\' (Suryakant Tripathi Nirala)'],
                ['key' => 'c', 'label' => 'महादेवी वर्मा (Mahadevi Varma)'],
                ['key' => 'd', 'label' => 'रामधारी सिंह \'दिनकर\' (Ramdhari Singh Dinkar)'],
            ],
            'explanation' => "छायावाद के चार प्रमुख स्तंभ जयशंकर प्रसाद, सूर्यकांत त्रिपाठी 'निराला', सुमित्रानंदन पन्त और महादेवी वर्मा हैं। दिनकर जी उत्तर-छायावाद के राष्ट्रीय चेतना के ओजस्वी कवि हैं।",
        ]);

        $bankLang->questions()->create([
            'display_order' => 9,
            'type' => 'single',
            'title' => 'Arabic Language (اللغة العربية - RTL)',
            'body_text' => "أيّ من العناصر الكيميائية التالية يرمز له بالرمز اللاتيني \$\\text{Au}\$ وهو فلز ثمين لا يتآكل بسهولة؟",
            'marks' => 2,
            'negative_mark' => 0.5,
            'correct_option_key' => 'a',
            'options_json' => [
                ['key' => 'a', 'label' => 'الذهب (Gold - Au)'],
                ['key' => 'b', 'label' => 'الفضة (Silver - Ag)'],
                ['key' => 'c', 'label' => 'النحاس (Copper - Cu)'],
                ['key' => 'd', 'label' => 'البلاتين (Platinum - Pt)'],
            ],
            'explanation' => "الرمز الكيميائي \$\\text{Au}\$ مشتق من الكلمة اللاتينية Aurum ويعني عنصر الذهب، وهو فلز نفيس خامل كيميائياً.",
        ]);

        $bankLang->questions()->create([
            'display_order' => 10,
            'type' => 'single',
            'title' => 'Tamil & Chemistry (தமிழ் மற்றும் வேதியியல்)',
            'document_path' => 'mcq-test-media/small-molecule.png',
            'body_text' => "மேலே உள்ள மூலக்கூறு அமைப்பைக் கவனித்து, நீர் (\$\\text{H}_2\\text{O}\$) மூலக்கூறில் உள்ள ஆக்ஸிஜன் மற்றும் ஹைட்ரஜன் அணுக்களுக்கு இடையே உள்ள வேதிப்பிணைப்பு (chemical bond) வகையைக் கண்டறிக:",
            'marks' => 2,
            'negative_mark' => 0.5,
            'correct_option_key' => 'a',
            'options_json' => [
                ['key' => 'a', 'label' => 'துருவ சகப்பிணைப்பு (Polar Covalent Bond)'],
                ['key' => 'b', 'label' => 'அயனிப் பிணைப்பு (Ionic Bond)'],
                ['key' => 'c', 'label' => 'உலோகப் பிணைப்பு (Metallic Bond)'],
                ['key' => 'd', 'label' => 'ஹைட்ரஜன் பிணைப்பு மட்டுமே (Hydrogen Bond only)'],
            ],
            'explanation' => "நீர் (\$\\text{H}_2\\text{O}\$) மூலக்கூறில் ஆக்ஸிஜன் மற்றும் ஹைட்ரஜன் அணுக்கள் எலக்ட்ரான்களைப் பகிர்வதால் துருவ சகப்பிணைப்பு (Polar Covalent Bond) உருவாகிறது.",
        ]);

        $exam->questionBanks()->sync([
            $bankMath->id => ['pick_mode' => 'all', 'display_order' => 1, 'section_title' => 'Mathematics & Physics'],
            $bankBio->id  => ['pick_mode' => 'all', 'display_order' => 2, 'section_title' => 'Biology & Life Sciences'],
            $bankLang->id => ['pick_mode' => 'all', 'display_order' => 3, 'section_title' => 'World Languages & Literature'],
        ]);

        $class = SchoolClass::firstOrCreate(['tenant_id' => $school->id, 'name' => 'Class 8']);
        $studentUser = User::firstOrCreate(['email' => 'candidate@demo.test'], [
            'tenant_id' => $school->id,
            'name' => 'Demo Candidate',
            'password' => Hash::make('LocalExamDemo2026!'),
            'email_verified_at' => now(),
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $studentUser->assignRole('student');

        $student = Student::firstOrCreate(['user_id' => $studentUser->id], [
            'tenant_id' => $school->id,
            'school_class_id' => $class->id,
            'name' => 'Demo Candidate',
            'status' => 'active',
            'verified_at' => now(),
            'reg_no' => 'STU/DEMO/001',
        ]);

        // Reset registration so it starts fresh
        McqRegistration::where('exam_id', $exam->id)->where('student_id', $student->id)->delete();
        $registration = McqRegistration::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'school_id' => $school->id,
            'status' => 'registered',
            'approval_status' => 'approved',
        ]);

        $this->command?->info("Comprehensive MCQ QA Exam created: ID={$exam->id}, RegID={$registration->id}");
    }
}
