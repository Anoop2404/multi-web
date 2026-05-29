<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\Circular;
use Illuminate\Http\Request;

class CircularController extends SahodayaAdminController
{
    public function index()
    {
        $circulars = Circular::where('tenant_id', $this->sahodaya->id)
            ->orderByDesc('issued_date')
            ->get();

        return $this->inertia('Sahodaya/Circulars/Index', compact('circulars'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'circular_number' => 'nullable|string|max:100',
            'category'        => 'nullable|string|max:100',
            'issued_date'     => 'nullable|date',
            'academic_year'   => 'nullable|string|max:20',
            'file'            => 'required|mimes:pdf,doc,docx|max:10240',
        ]);

        $data['tenant_id']  = $this->sahodaya->id;
        $data['file_path']  = $request->file('file')->store('sahodaya/' . $this->sahodaya->id . '/circulars', 's3');

        unset($data['file']);
        Circular::create($data);

        return back()->with('success', 'Circular uploaded.');
    }

    public function destroy(string $tenantId, Circular $circular)
    {
        abort_if($circular->tenant_id !== $this->sahodaya->id, 403);
        $circular->delete();
        return back()->with('success', 'Circular removed.');
    }
}
