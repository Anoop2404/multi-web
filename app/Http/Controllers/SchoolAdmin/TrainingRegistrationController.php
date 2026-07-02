<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FeeReceipt;
use App\Models\Teacher;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Support\TenantStorage;
use App\Services\Notifications\SahodayaAdminNotifier;
use Illuminate\Http\Request;

class TrainingRegistrationController extends SchoolAdminController
{
    public function index()
    {
        $sahodayaId = $this->school->parent_id;

        $programs = TrainingProgram::where('tenant_id', $sahodayaId)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->orderByDesc('registration_open')
            ->get();

        $registrations = TrainingRegistration::where('school_id', $this->school->id)
            ->whereIn('program_id', $programs->pluck('id'))
            ->with(['teacher', 'feeReceipt'])
            ->get()
            ->groupBy('program_id');

        $teachers = Teacher::where('tenant_id', $this->school->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'designation']);

        return $this->inertia('School/Training/Index', compact('programs', 'registrations', 'teachers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'program_id'  => 'required|exists:training_programs,id',
            'teacher_id'  => 'required|exists:teachers,id',
        ]);

        $program = TrainingProgram::findOrFail($data['program_id']);
        abort_if($program->tenant_id !== $this->school->parent_id, 403);
        abort_if(! in_array($program->status, ['published', 'ongoing'], true), 422, 'Registration is closed.');

        $teacher = Teacher::findOrFail($data['teacher_id']);
        abort_if($teacher->tenant_id !== $this->school->id, 403);

        TrainingRegistration::firstOrCreate(
            ['program_id' => $program->id, 'teacher_id' => $teacher->id],
            ['school_id' => $this->school->id, 'status' => 'registered']
        );

        return back()->with('success', 'Teacher registered for training.');
    }

    public function uploadPayment(Request $request, string $tenantId, TrainingRegistration $registration)
    {
        abort_if($registration->school_id !== $this->school->id, 403);

        $program = $registration->program;
        abort_if($program->tenant_id !== $this->school->parent_id, 403);
        abort_unless($program->hasFee(), 422, 'This program does not require a fee.');

        $data = $request->validate([
            'payment_proof'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'transaction_ref' => 'nullable|string|max:100',
        ]);

        $path = TenantStorage::storeUploadedFile(
            $request->file('payment_proof'),
            "training-payments/{$this->school->id}"
        );

        $receipt = FeeReceipt::create([
            'feeable_type'        => TrainingRegistration::class,
            'feeable_id'          => $registration->id,
            'file_path'           => $path,
            'transaction_ref'     => $data['transaction_ref'] ?? null,
            'payment_date'        => now()->toDateString(),
            'amount'              => $program->fee_amount,
            'status'              => 'uploaded',
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        $registration->update(['fee_receipt_id' => $receipt->id]);

        app(SahodayaAdminNotifier::class)->notifyAdmins(
            $this->school->parent_id,
            'payment.proof.uploaded',
            [
                'school_name'   => $this->school->name,
                'context_label' => $program->title.' training fee',
            ],
            "/sahodaya-admin/{$this->school->parent_id}/training/{$program->id}"
        );

        return back()->with('success', 'Payment proof uploaded.');
    }
}
