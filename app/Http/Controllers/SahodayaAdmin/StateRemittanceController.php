<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\StateRemittance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Support\TenantStorage;

class StateRemittanceController extends SahodayaAdminController
{
    public function index()
    {
        $remittances = StateRemittance::where('sahodaya_id', $this->sahodaya->id)
            ->orderByDesc('created_at')
            ->get();

        $summary = [
            'pending'  => $remittances->where('status', 'pending')->count(),
            'submitted'=> $remittances->where('status', 'submitted')->count(),
            'verified' => $remittances->where('status', 'verified')->count(),
            'total_due'=> $remittances->whereIn('status', ['pending', 'submitted'])->sum('amount'),
            'total_paid'=> $remittances->where('status', 'verified')->sum('amount'),
        ];

        return $this->inertia('Sahodaya/StateRemittances/Index', compact('remittances', 'summary'));
    }

    public function uploadProof(Request $request, StateRemittance $remittance)
    {
        abort_if($remittance->sahodaya_id !== $this->sahodaya->id, 403);
        abort_unless(in_array($remittance->status, ['pending', 'rejected'], true), 422, 'Already submitted or verified.');

        $data = $request->validate([
            'proof'           => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'transaction_ref' => 'nullable|string|max:100',
            'bank_name'       => 'nullable|string|max:100',
            'payment_date'    => 'nullable|date',
        ]);

        $path = TenantStorage::storeUploadedFile(
            $request->file('proof'),
            "state-remittances/{$this->sahodaya->id}"
        );

        $remittance->update([
            'status'          => 'submitted',
            'proof_path'      => $path,
            'transaction_ref' => $data['transaction_ref'] ?? null,
            'bank_name'       => $data['bank_name'] ?? null,
            'payment_date'    => $data['payment_date'] ?? now()->toDateString(),
            'rejection_reason'=> null,
        ]);

        return back()->with('success', 'Payment proof submitted for state verification.');
    }
}
