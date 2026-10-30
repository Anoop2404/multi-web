# Wayanad sports certificates

The supplied merit and participation scans are preserved in `resources/certificate-templates/wayanad-sports/`. Upright PNG backgrounds retain the printed wording and blank signature areas. The presets fill the student, class, school, item, age-category label, date and 2026–2027 year; only merit adds the finishing position.

Participation settings appear on Certificates and Participation Certificates. Choose **one per student** (the existing default) or **one per student per item**. Save, then generate participation certificates to apply the grouping. The per-item mode uses item-specific participation templates when available, otherwise the event's participation template. Standby, disqualified and absent entries remain excluded. Merit generation is unaffected. Changed grouping marks participation PDFs stale; generation may remove obsolete duplicate certificates as before.

Before using the new setting, deploy the code and run the new tenant migration using the normal tenant migration procedure:
`2026_10_30_000001_add_participation_certificate_mode_to_fest_events.php`.

To install these backgrounds for the intended active sports event, supply its exact event ID (the seeder deliberately refuses to choose a draft automatically):

```sh
SAHODAYA_UUID=b639d1e5-cd18-4dc8-88d5-7bd8e0bbc697 FEST_EVENT_ID=<active-sports-event-id> php artisan db:seed --class=WayanadSportsCertificateTemplatesSeeder --force
```

The setup creates separate active merit and participation templates for that event and selects per-item participation. It does not generate certificates or publish results. Existing same-named templates are preserved on reruns.
