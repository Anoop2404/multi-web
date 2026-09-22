<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Alumni;
use Illuminate\Http\Request;

class AlumniController extends SchoolAdminController
{
    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();

        $alumni = Alumni::where('tenant_id', $this->school->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('batch_year', 'like', "%{$search}%")
                        ->orWhere('current_role', 'like', "%{$search}%")
                        ->orWhere('current_organisation', 'like', "%{$search}%");
                });
            })
            ->when($status === 'pending', fn ($query) => $query->where('is_approved', false))
            ->when($status === 'approved', fn ($query) => $query->where('is_approved', true))
            ->when($status === 'featured', fn ($query) => $query->where('is_featured', true))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $base = Alumni::where('tenant_id', $this->school->id);
        $counts = [
            'all' => (clone $base)->count(),
            'pending' => (clone $base)->where('is_approved', false)->count(),
            'approved' => (clone $base)->where('is_approved', true)->count(),
            'featured' => (clone $base)->where('is_featured', true)->count(),
        ];

        return $this->inertia('School/Alumni/Index', [
            'alumni' => $alumni,
            'counts' => $counts,
            'filters' => compact('search', 'status'),
        ]);
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
