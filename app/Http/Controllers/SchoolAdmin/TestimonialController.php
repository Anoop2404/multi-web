<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Testimonial;
use App\Support\PersistDefaults;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class TestimonialController extends SchoolAdminController
{
    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();

        $testimonials = Testimonial::where('tenant_id', $this->school->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('designation', 'like', "%{$search}%")
                        ->orWhere('quote', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'hidden', fn ($query) => $query->where('is_active', false))
            ->orderBy('display_order')
            ->paginate(20)
            ->withQueryString();

        return $this->inertia('School/Testimonials/Index', [
            'testimonials' => $testimonials,
            'filters' => compact('search', 'status'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'quote' => 'required|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'photo' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:4096',
        ]);

        $data['tenant_id'] = $this->school->id;
        $data['is_active'] = $request->boolean('is_active');
        $data = PersistDefaults::coalesce($data, ['display_order' => 0]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('testimonials/'.$this->school->id, TenantStorage::uploadDisk());
        }

        Testimonial::create($data);

        return back()->with('success', 'Testimonial added.');
    }

    public function update(Request $request, string $tenantId, Testimonial $testimonial)
    {
        abort_if($testimonial->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'quote' => 'required|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'photo' => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:4096',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data = PersistDefaults::coalesce($data, ['display_order' => $testimonial->display_order ?? 0]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('testimonials/'.$this->school->id, TenantStorage::uploadDisk());
        }

        $testimonial->update($data);

        return back()->with('success', 'Testimonial updated.');
    }

    public function destroy(string $tenantId, Testimonial $testimonial)
    {
        abort_if($testimonial->tenant_id !== $this->school->id, 403);
        $testimonial->delete();

        return back()->with('success', 'Testimonial removed.');
    }
}
