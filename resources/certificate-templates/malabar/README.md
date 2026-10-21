# Malabar merit and participation certificates

Uses the supplied 2026 artwork, including its logos, signatures and stamp, unchanged.
The editable paragraph sits in the blank centre. The custom 297 × 221.643 mm page
preserves the artwork's aspect ratio. Print with “fit to paper” for A4 landscape.

After deploying and running tenant migrations, install both templates for the Malabar event:

```sh
SAHODAYA_UUID=fe169220-9b5e-43fe-809f-6e0fccd84eae FEST_EVENT_ID=25 php artisan db:seed --class=MalabarKalotsavCertificateTemplateSeeder --force
```

The installer creates each named merit/participation template if missing; it preserves subsequent
manual edits. Verify the tenant/event IDs before using this example on another site.
Without FEST_EVENT_ID the template is a tenant-wide default for merit certificates.

Under **Merit Certificates → Certificate phases — venue and dates**, create a named
certificate phase with its venue and conducted dates, and select its items. The
same template serves every phase. An end date is optional for a single-day phase.
Reopen a phase to change its venue, dates or assigned items. Uncheck items and save
to restore event defaults; selecting items assigned elsewhere moves them to this
phase. A phase may have no items. Existing affected merit certificates are marked
stale; regenerate them before sharing.

- `{school_name}` remains the recipient's school.
- `{host_school}` gives the event venue (compatibility token).
- `{venue}` gives the assigned certificate phase's venue, falling back to the event venue.
- `{conduct_location}` gives the certificate phase's venue, falling back to the event venue. This template uses this token.
- `{event_dates}` uses certificate phase conducted dates for merit certificates.
- Participation certificates and the certificate issue date retain event defaults.

These are certificate-only settings. They do not change the event topology,
schedule, registrations or results. The supplied artwork contains fixed 2026
branding and signatures; use the correct year's artwork for a different event.

See [content.md](content.md) for the template wording and placeholder behaviour.
The assignment list defaults to results published oldest first, unpublished last;
newest-first, name and code sorting are also available.
