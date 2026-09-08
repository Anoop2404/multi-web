<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Bank — {{ $tenant->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: #f8fafc; color: #1e293b; }
        .qb-header { background: #0f3d7a; color: #fff; padding: 1.5rem 1.25rem; }
        .qb-header-inner { max-width: 56rem; margin: 0 auto; }
        .qb-header h1 { font-size: 1.375rem; font-weight: 800; margin: 0; }
        .qb-header p { font-size: .8125rem; color: rgba(255,255,255,.7); margin: .25rem 0 0; }
        .qb-main { max-width: 56rem; margin: 0 auto; padding: 2rem 1.25rem 3rem; }
        .qb-group { margin-bottom: 2rem; }
        .qb-group h2 {
            font-size: .8125rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            color: #0f3d7a; margin: 0 0 .9rem; padding-bottom: .5rem; border-bottom: 2px solid #e2e8f0;
        }
        .qb-doc {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem;
            padding: 1rem 1.25rem; margin-bottom: .75rem; box-shadow: 0 1px 2px rgba(0,0,0,.03);
        }
        .qb-doc-title { font-weight: 700; font-size: .9375rem; color: #0f172a; }
        .qb-doc-meta { font-size: .75rem; color: #64748b; margin-top: .2rem; }
        .qb-doc-actions { display: flex; gap: .5rem; flex-shrink: 0; }
        .qb-btn {
            font-size: .8125rem; font-weight: 600; padding: .5rem 1rem; border-radius: .6rem;
            text-decoration: none; white-space: nowrap;
        }
        .qb-btn-view { border: 1.5px solid #0f3d7a; color: #0f3d7a; }
        .qb-btn-download { background: #0f3d7a; color: #fff; }
        .qb-empty { text-align: center; color: #64748b; padding: 3rem 1rem; }
    </style>
</head>
<body>
    <header class="qb-header">
        <div class="qb-header-inner">
            <h1>{{ $tenant->name }} — Question Bank</h1>
            <p>Class-wise question papers, available to view or download.</p>
        </div>
    </header>

    <main class="qb-main">
        @forelse($groups as $group)
        <div class="qb-group">
            <h2>{{ $group['class_name'] }}</h2>
            @foreach($group['documents'] as $document)
            <div class="qb-doc">
                <div>
                    <div class="qb-doc-title">{{ $document->title }}</div>
                    <div class="qb-doc-meta">
                        @if($document->subject) {{ $document->subject }} &middot; @endif
                        @if($document->academic_year) {{ $document->academic_year }} @endif
                    </div>
                </div>
                <div class="qb-doc-actions">
                    <a href="{{ route('tenant.question-bank.view', $document) }}" target="_blank" rel="noopener" class="qb-btn qb-btn-view">View</a>
                    <a href="{{ route('tenant.question-bank.download', $document) }}" class="qb-btn qb-btn-download">Download</a>
                </div>
            </div>
            @endforeach
        </div>
        @empty
        <p class="qb-empty">No question bank documents published yet.</p>
        @endforelse
    </main>
</body>
</html>
