<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\McqQuestion;
use App\Models\McqQuestionBank;
use App\Models\Tenant;
use App\Support\FestClassGroupScheme;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class TeacherMcqController extends Controller
{
    public function banks(Request $request, string $tenantId)
    {
        $teacher = $request->attributes->get('portalTeacher');
        $school = Tenant::findOrFail($tenantId);

        $banks = McqQuestionBank::where('school_id', $school->id)
            ->where('teacher_id', $teacher->id)
            ->withCount('questions')
            ->latest()
            ->get();

        return inertia('Portal/Teacher/QuestionBanks', [
            'school'  => $school->only('id', 'name'),
            'teacher' => $teacher->only('id', 'name', 'subject'),
            'banks'   => $banks,
            'classGroups' => FestClassGroupScheme::labelsForSahodaya($school->parent_id),
        ]);
    }

    public function storeBank(Request $request, string $tenantId)
    {
        $teacher = $request->attributes->get('portalTeacher');
        $school = Tenant::findOrFail($tenantId);

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'subject'     => 'required|string|max:120',
            'class_group' => 'nullable|in:lp,up,hs,hss,open',
            'description' => 'nullable|string|max:2000',
        ]);

        McqQuestionBank::create([
            ...$data,
            'sahodaya_id'         => $school->parent_id,
            'school_id'           => $school->id,
            'teacher_id'          => $teacher->id,
            'created_by_user_id'  => $request->user()->id,
            'status'              => 'active',
        ]);

        return back()->with('success', 'Question bank created.');
    }

    public function showBank(Request $request, string $tenantId, McqQuestionBank $bank)
    {
        $teacher = $request->attributes->get('portalTeacher');
        abort_if($bank->school_id !== $tenantId || $bank->teacher_id !== $teacher->id, 403);

        $bank->load('questions');

        return inertia('Portal/Teacher/QuestionBankShow', [
            'school'  => Tenant::findOrFail($tenantId)->only('id', 'name'),
            'teacher' => $teacher->only('id', 'name'),
            'bank'    => $bank,
        ]);
    }

    public function storeQuestion(Request $request, string $tenantId, McqQuestionBank $bank)
    {
        $teacher = $request->attributes->get('portalTeacher');
        abort_if($bank->school_id !== $tenantId || $bank->teacher_id !== $teacher->id, 403);

        $data = $request->validate([
            'title'     => 'nullable|string|max:255',
            'body_text' => 'nullable|string|max:5000',
            'document'  => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'options'   => 'nullable|array|min:2|max:6',
            'options.*.key' => 'required_with:options|string|max:10',
            'options.*.label' => 'required_with:options|string|max:500',
            'correct_option_key' => 'nullable|string|max:10',
        ]);

        abort_if(! $data['body_text'] && ! $request->hasFile('document') && empty($data['options']), 422, 'Add question text, options, or upload a document.');

        $path = null;
        if ($request->hasFile('document')) {
            $school = Tenant::findOrFail($tenantId);
            $path = $request->file('document')->store(
                'schools/'.$school->id.'/mcq-banks/'.$bank->id,
                TenantStorage::uploadDisk()
            );
        }

        $options = collect($data['options'] ?? [])
            ->filter(fn ($option) => filled($option['label'] ?? null) && filled($option['key'] ?? null))
            ->map(fn ($option) => [
                'key'   => strtolower((string) $option['key']),
                'label' => (string) $option['label'],
            ])
            ->values()
            ->all();

        McqQuestion::create([
            'bank_id'            => $bank->id,
            'title'              => $data['title'] ?? null,
            'body_text'          => $data['body_text'] ?? null,
            'document_path'      => $path,
            'options_json'       => $options ?: null,
            'correct_option_key' => filled($data['correct_option_key'] ?? null)
                ? strtolower((string) $data['correct_option_key'])
                : null,
            'display_order'      => ($bank->questions()->max('display_order') ?? 0) + 1,
            'created_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Question added.');
    }

    public function destroyQuestion(Request $request, string $tenantId, McqQuestionBank $bank, McqQuestion $question)
    {
        $teacher = $request->attributes->get('portalTeacher');
        abort_if($bank->school_id !== $tenantId || $bank->teacher_id !== $teacher->id, 403);
        abort_if($question->bank_id !== $bank->id, 403);

        $question->delete();

        return back()->with('success', 'Question removed.');
    }
}
