<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hall Ticket Preview</title>
    <style>body { padding: 16px; background: #f8fafc; }</style>
</head>
<body>
    @include('mcq.partials.hall-ticket-card', ['design' => $design, 'logoUrl' => $logoUrl, 'sample' => $sample])
</body>
</html>
