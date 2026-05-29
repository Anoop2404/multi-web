<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Alumni;
use Illuminate\Http\Request;

class AlumniController extends SchoolAdminController
{
    public function index()
    {
        $alumni = Alumni::where('tenant_id', $this->school->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->inertia('School/Alumni/Index', compact('alumni'));
    }

    public function approve(Request $request, string $tenantId, Alumni $alumnus)
    {
        abort_if($alumnus->tenant_id !== $this->school->id, 403);
        $alumnus->update(['is_approved' => !$alumnus->is_approved]);
        return back()->with('success', $alumnus->is_approved ? 'Alumni approved.' : 'Alumni hidden.');
    }

    public function feature(Request $request, string $tenantId, Alumni $alumnus)
    {
        abort_if($alumnus->tenant_id !== $this->school->id, 403);
        $alumnus->update(['is_featured' => !$alumnus->is_featured]);
        return back()->with('success', $alumnus->is_featured ? 'Marked as featured.' : 'Removed from featured.');
    }

    public function destroy(string $tenantId, Alumni $alumnus)
    {
        abort_if($alumnus->tenant_id !== $this->school->id, 403);
        $alumnus->delete();
        return back()->with('success', 'Alumni removed.');
    }
}
