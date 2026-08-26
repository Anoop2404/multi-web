<?php

namespace App\Http\Controllers\SahodayaAdmin;

class FestToolsHubController extends SahodayaAdminController
{
    public function index()
    {
        $base = "/sahodaya-admin/{$this->sahodaya->id}";

        return $this->inertia('Sahodaya/Fest/ToolsHub', [
            'links' => [
                'appeals' => "{$base}/fest/appeals",
                'payments' => "{$base}/fest/payments",
                'display_screens' => "{$base}/display-screens",
                'certificate_templates' => "{$base}/certificate-templates",
                'id_card_templates' => "{$base}/id-card-templates",
                'find_certificate' => "{$base}/events/certificates/search",
            ],
        ]);
    }
}
