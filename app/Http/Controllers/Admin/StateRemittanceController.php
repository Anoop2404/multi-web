<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StateRemittance;
use App\Models\Tenant;
use App\Services\Ledger\StateRemittanceLedgerService;
use App\Services\Notifications\SahodayaAdminNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StateRemittanceController extends Controller
{
    public function index(Request $request)
    {
        $remittances = StateRemittance::with('sahodaya')
            ->when($request->get('academic_year'), fn ($q, $y) => $q->where('academic_year', $y))
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $sahodayas = Tenant::where('type', 'sahodaya')->orderBy('name')->get(['id', 'name']);

        $summary = [
            'total'    => StateRemittance::count(),
            'pending'  => StateRemittance::where('status', 'pending')->count(),
            'submitted'=> StateRemittance::where('status', 'submitted')->count(),
            'verified' => StateRemittance::where('status', 'verified')->count(),
            'amount'   => StateRemittance::where('status', 'verified')->sum('amount'),
        ];

        return inertia('StateRemittances/Index', [
            'remittances' => $remittances,
            'sahodayas'   => $sahodayas,
            'summary'     => $summary,
            'filters'     => ['status' => $request->get('status', '')],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sahodaya_id'    => 'required|exists:tenants,id',
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'amount'         => 'required|numeric|min:0.01',
            'due_date'       => 'nullable|date',
            'academic_year'  => 'nullable|string|max:20',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status']     = 'pending';

        $remittance = StateRemittance::create($data);

        app(SahodayaAdminNotifier::class)->notifyAdmins(
            $data['sahodaya_id'],
            'state.remittance.created',
            [
                'title'  => $data['title'],
                'amount' => number_format((float) $data['amount'], 2),
            ],
            "/sahodaya-admin/{$data['sahodaya_id']}/state-remittances",
        );

        return back()->with('success', 'Remittance demand created.');
    }

    public function verify(Request $request, StateRemittance $remittance, StateRemittanceLedgerService $ledger)
    {
        abort_unless($remittance->status === 'submitted', 422, 'Only submitted remittances can be verified.');

        $remittance->update([
            'status'      => 'verified',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $ledger->postVerified($remittance->fresh(), $request->user()->id);

        app(SahodayaAdminNotifier::class)->notifyAdmins(
            $remittance->sahodaya_id,
            'state.remittance.verified',
            [
                'title'  => $remittance->title,
                'amount' => number_format((float) $remittance->amount, 2),
            ],
            "/sahodaya-admin/{$remittance->sahodaya_id}/state-remittances",
        );

        return back()->with('success', 'Remittance verified.');
    }

    public function reject(Request $request, StateRemittance $remittance)
    {
        abort_unless($remittance->status === 'submitted', 422, 'Only submitted remittances can be rejected.');

        $data = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $remittance->update([
            'status'           => 'rejected',
            'rejection_reason' => $data['rejection_reason'] ?? null,
            'reviewed_by'      => $request->user()->id,
            'reviewed_at'      => now(),
        ]);

        app(SahodayaAdminNotifier::class)->notifyAdmins(
            $remittance->sahodaya_id,
            'state.remittance.rejected',
            [
                'title'  => $remittance->title,
                'reason' => $data['rejection_reason'] ?? 'No reason given',
            ],
            "/sahodaya-admin/{$remittance->sahodaya_id}/state-remittances",
        );

        return back()->with('success', 'Remittance rejected.');
    }

    public function proof(StateRemittance $remittance)
    {
        abort_unless($remittance->proof_path, 404);

        $disk = config('filesystems.upload_disk', 'shared');

        if (in_array($disk, ['s3', 'private'], true)) {
            return redirect(Storage::disk($disk)->temporaryUrl($remittance->proof_path, now()->addMinutes(15)));
        }

        return Storage::disk($disk)->download($remittance->proof_path);
    }
}
