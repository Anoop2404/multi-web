# Kannur MCQ admit-card template

The supplied first A4 page is rasterized unchanged at 2400 px high in `resources/mcq-templates/kannur/admit-card-background.png`. Its 2025 wording, dates, times, instructions, logo and seal are intentionally preserved at the user's request. The PDF's second page contains only the STSE heading and is not an admit-card page.

The controller's supplied signature is a separate image field. Each school's principal signature remains independent. The preset prints one card on A4 with dynamic registration number, examination centre number/name, student name, gender and class. A separate dynamic date overlay is disabled because the background already contains the original date text.

## Apply after deployment

Run in the Laravel application directory on the server hosting Kannur Sahodaya:

```sh
SAHODAYA_UUID=71fcc096-e12e-4eb3-bc45-ecbc9c4ab306 MCQ_EXAM_ID=1 php artisan db:seed --class=KannurMcqHallTicketTemplateSeeder --force
```

The seeder switches to the tenant database, stores the images on the configured upload disk, creates or updates the Kannur template, and assigns its design to the selected exam. It preserves exam dates, class schedules, candidate records, registration numbers and other settings. It does not change the default template for other exams. Rerunning restores the supplied preset, replacing any later edits to this exam's design.

The Kannur production tenant is absent from this development database; the production setup command must be run in the environment where that tenant exists.

## Edit afterwards

Open **MCQ exam → Hall tickets → Design admit cards**. Upload/replace the controller signature in the left panel. Select **Controller signature** in the alignment panel to move it and change its width/height. Save the exam design. The template library also supports uploading/replacing/removing controller signatures.

Existing saved PDFs remain snapshots. Generate new saved PDFs to include a changed background or signature.
