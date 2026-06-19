<?php

namespace App\Http\Controllers\SchoolAdmin;

class SchoolClassController extends SchoolAdminController
{
    public function index()
    {
        return redirect("/school-admin/{$this->school->id}/students");
    }
}
