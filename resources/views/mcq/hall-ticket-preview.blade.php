<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hall Ticket Preview</title>
    <style>body { padding: 16px; background: #f8fafc; }</style>
</head>
<body>
@if(!empty($isSample))
    <p style="text-align:center;font-family:system-ui,sans-serif;font-size:13px;color:#b45309;background:#fffbeb;border:1px solid #fcd34d;padding:10px 16px;margin:0 0 12px;border-radius:8px;">
        <strong>Sample preview</strong> — for client demo only. Not a real participant card.
    </p>
@endif
    @include('mcq.partials.hall-ticket-card', ['design' => $design, 'logoUrl' => $logoUrl, 'sample' => $sample])
</body>
</html>
