<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\McqCertificateTemplate;
use App\Models\McqHallTicketTemplate;
use Illuminate\Http\Request;

class McqTemplateController extends SahodayaAdminController
{
    public function hallTickets()
    {
        $templates = McqHallTicketTemplate::where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();

        return $this->inertia('Sahodaya/Mcq/Templates/HallTickets', [
            'templates' => $templates,
        ]);
    }

    public function storeHallTicket(Request $request)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:120',
            'is_default'   => 'nullable|boolean',
            'design_json'  => 'nullable|array',
        ]);

        if ($request->boolean('is_default')) {
            McqHallTicketTemplate::where('tenant_id', $this->sahodaya->id)->update(['is_default' => false]);
        }

        McqHallTicketTemplate::create([
            'tenant_id'   => $this->sahodaya->id,
            'title'       => $data['title'],
            'design_json' => $data['design_json'] ?? \App\Support\Mcq\McqHallTicketDesign::defaults(),
            'is_default'  => $request->boolean('is_default'),
            'is_active'   => true,
        ]);

        return back()->with('success', 'Hall ticket template saved.');
    }

    public function certificates()
    {
        $templates = McqCertificateTemplate::where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();

        return $this->inertia('Sahodaya/Mcq/Templates/Certificates', [
            'templates' => $templates,
        ]);
    }

    public function storeCertificate(Request $request)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:120',
            'is_default'   => 'nullable|boolean',
            'design_json'  => 'nullable|array',
        ]);

        if ($request->boolean('is_default')) {
            McqCertificateTemplate::where('tenant_id', $this->sahodaya->id)->update(['is_default' => false]);
        }

        McqCertificateTemplate::create([
            'tenant_id'   => $this->sahodaya->id,
            'title'       => $data['title'],
            'design_json' => $data['design_json'] ?? [],
            'is_default'  => $request->boolean('is_default'),
            'is_active'   => true,
        ]);

        return back()->with('success', 'Certificate template saved.');
    }
}
