<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Academic Results — {{ $sahodaya->name }}</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; margin: 0; background: #f7f4ef; color: #1f2937; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 2rem 1.25rem 3rem; }
        h1 { font-size: 2rem; margin: 0 0 .35rem; color: #0f3d2e; }
        .sub { color: #6b7280; margin-bottom: 1.5rem; }
        form { display: flex; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.5rem; align-items: end; }
        label { display: block; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin-bottom: .25rem; }
        select, input[type=text] { padding: .5rem .65rem; border: 1px solid #d1d5db; border-radius: 6px; min-width: 10rem; }
        button, .btn { background: #0f3d2e; color: #fff; border: 0; border-radius: 6px; padding: .55rem 1rem; text-decoration: none; display: inline-block; font-size: .9rem; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.25rem; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: .5rem .4rem; border-bottom: 1px solid #f3f4f6; }
        th { color: #6b7280; font-weight: 600; font-size: .75rem; text-transform: uppercase; }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .award { padding: .75rem; background: #f0fdf4; border-radius: 8px; }
        .award strong { display: block; color: #166534; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>{{ $sahodaya->name }}</h1>
    <p class="sub">Sahodaya-wide CBSE board rankings, toppers, and merit list for {{ $year }}.</p>

    <form method="get" action="{{ url('/academic-results') }}">
        <div>
            <label for="year">Academic year</label>
            <select name="year" id="year">
                @forelse($years as $y)
                    <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                @empty
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforelse
            </select>
        </div>
        <div>
            <label for="q">Search student / school</label>
            <input type="text" name="q" id="q" value="{{ $q }}" placeholder="Name, admission no, school">
        </div>
        <button type="submit">Apply</button>
        <a class="btn" href="{{ url('/academic-results/merit-list.pdf?year='.$year) }}">Download merit list PDF</a>
    </form>

    <div class="card">
        <h2>School rankings (pass %)</h2>
        <table>
            <thead>
            <tr><th>Rank</th><th>School</th><th>Class</th><th>Pass %</th></tr>
            </thead>
            <tbody>
            @forelse($rankings as $row)
                <tr>
                    <td>{{ $row['rank'] }}</td>
                    <td>{{ $row['school'] }}</td>
                    <td>{{ $row['class'] }}</td>
                    <td>{{ number_format((float) $row['pass_percent'], 2) }}%</td>
                </tr>
            @empty
                <tr><td colspan="4">No published rankings for this year yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($awards->isNotEmpty())
    <div class="card">
        <h2>Academic awards</h2>
        <div class="grid">
            @foreach($awards as $award)
                <div class="award">
                    <strong>{{ $award->title }}</strong>
                    <span>{{ $awardSchoolNames[$award->tenant_id] ?? '—' }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="card">
        <h2>Toppers {{ $q ? '(search)' : '' }}</h2>
        <table>
            <thead>
            <tr><th>Student</th><th>School</th><th>Class</th><th>%</th><th>Stream</th></tr>
            </thead>
            <tbody>
            @forelse($toppers as $t)
                <tr>
                    <td>{{ $t->name }}</td>
                    <td>{{ $schoolNames[$t->tenant_id] ?? $t->tenant_id }}</td>
                    <td>{{ $t->boardResult?->class }}</td>
                    <td>{{ number_format((float) $t->percentage, 2) }}%</td>
                    <td>{{ $t->examStream?->label ?? $t->stream ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No toppers found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
