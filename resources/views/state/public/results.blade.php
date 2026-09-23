@extends('layouts.portal')

@section('title', $event->name.' — Results')

@section('content')
<div class="portal-wrap">
    <div class="portal-page" style="max-width:60rem;">
        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
                <h1 class="portal-card-title">{{ $event->name }} — Results</h1>
                {{-- Said plainly, because the difference matters to anyone waiting on an item. --}}
                <p class="portal-card-sub">Only items whose results have been released appear here. Placings and grades are published; marks are not.</p>
            </div>
            <div class="portal-card-body">
                @include('state.public._nav')

                @forelse ($items as $item)
                    <div style="border:1px solid #e2e8f0;border-radius:.75rem;padding:.85rem 1rem;margin-bottom:.75rem;">
                        <h2 style="margin:0 0 .5rem;font-size:.95rem;color:#1e3a5f;">
                            {{ $item['item'] ?? $item['item_code'] }}
                            <span style="color:#94a3b8;font-weight:400;font-size:.8rem;">{{ $item['item_code'] }}</span>
                        </h2>
                        <table class="state-table">
                            <thead>
                                <tr><th style="width:4rem;">Pos</th><th>Participant</th><th>School</th><th>Sahodaya</th><th style="width:4rem;">Grade</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($item['winners'] as $winner)
                                    <tr>
                                        <td><span class="pos-badge pos-{{ $winner['position'] }}">{{ $winner['position'] }}</span></td>
                                        <td>{{ $winner['participants'] }}</td>
                                        <td>{{ $winner['school'] }}</td>
                                        <td>{{ $winner['sahodaya'] }}</td>
                                        <td>{{ $winner['grade'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <p class="portal-hint">No item results have been released yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
