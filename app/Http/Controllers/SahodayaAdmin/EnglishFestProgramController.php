<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\ForwardsSahodayaProgramDashboard;

class EnglishFestProgramController extends SahodayaAdminController
{
    use ForwardsSahodayaProgramDashboard;

    protected function sahodayaProgramSlug(): string
    {
        return 'english-fest';
    }
}
