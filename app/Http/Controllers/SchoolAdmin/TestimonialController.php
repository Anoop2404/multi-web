<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Testimonial;
use Illuminate\Http\Request;

class TestimonialController extends SchoolAdminController
{
    public function index()
    {
        $testimonials = Testimonial::where('tenant_id', $this->school->id)
            ->orderBy('display_order')
            ->get();

        return $this->inertia('School/Testimonials/Index', compact('testimonials'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'designation'   => 'nullable|string|max:255',
            'quote'         => 'required|string',
            'display_order' => 'nullable|integer|min:0',
            'is_active'     => 'nullable|boolean',
            'photo'         => 'nullable|image|max:4096',
        ]);

        $data['tenant_id'] = $this->school->id;
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('testimonials/' . $this->school->id, 's3');
        }

        Testimonial::create($data);
        return back()->with('success', 'Testimonial added.');
    }

    public function update(Request $request, string $tenantId, Testimonial $testimonial)
    {
        abort_if($testimonial->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'designation'   => 'nullable|string|max:255',
            'quote'         => 'required|string',
            'display_order' => 'nullable|integer|min:0',
            'is_active'     => 'nullable|boolean',
            'photo'         => 'nullable|image|max:4096',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('testimonials/' . $this->school->id, 's3');
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