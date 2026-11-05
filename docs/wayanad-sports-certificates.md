# Wayanad sports certificates

The supplied merit and participation scans are preserved in `resources/certificate-templates/wayanad-sports/`. The original PNG backgrounds are retained as references. The current JPG backgrounds supplied on 5 November 2026 retain the printed wording and blank signature areas. The presets fill the student, class, school, item, age-category label, date and 2026–2027 year; only merit adds the finishing position.

Participation settings appear on Certificates and Participation Certificates. Choose **one per student** (the existing default) or **one per student per item**. Save, then generate participation certificates to apply the grouping. The per-item mode uses item-specific participation templates when available, otherwise the event's participation template. Standby, disqualified and absent entries remain excluded. Merit generation is unaffected. Changed grouping marks participation PDFs stale; generation may remove obsolete duplicate certificates as before.

Before using the new setting, deploy the code and run the new tenant migration using the normal tenant migration procedure:
`2026_10_30_000001_add_participation_certificate_mode_to_fest_events.php`.

To install these backgrounds for the intended active sports event, supply its exact event ID (the seeder deliberately refuses to choose a draft automatically):

```sh
SAHODAYA_UUID=b639d1e5-cd18-4dc8-88d5-7bd8e0bbc697 FEST_EVENT_ID=<active-sports-event-id> php artisan db:seed --class=WayanadSportsCertificateTemplatesSeeder --force
```

The setup creates separate active merit and participation templates for that event and selects per-item participation. It does not generate certificates or publish results. Existing same-named templates are preserved on reruns.

### Apply corrected alignment to existing templates

The layouts are calibrated separately against the two backgrounds at A4 landscape
(1123 × 794 CSS pixels). Participation name, class, school, item and category now
share the printed text baselines; dates use a smaller font to fit their blanks, and
the academic year suffixes sit after the preprinted `20`.

Existing templates are preserved by default. To replace only their custom field
positions with the corrected preset, run the seeder with an explicit event ID:

```sh
SAHODAYA_UUID=b639d1e5-cd18-4dc8-88d5-7bd8e0bbc697 FEST_EVENT_ID=<active-sports-event-id> WAYANAD_SPORTS_REFRESH_LAYOUT=true php artisan db:seed --class=WayanadSportsCertificateTemplatesSeeder --force
```

This replaces any manually edited custom fields on these two named templates.
The background and page dimensions are also updated to match the new artwork; other layout settings are retained. Affected cached certificates are marked stale;
regenerate their PDFs through the normal certificate workflow to use the new alignment.
