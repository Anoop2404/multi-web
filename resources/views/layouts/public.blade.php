<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', $tenant->name ?? 'School')</title>
    <meta name="description" content="@yield('description', '')">
    @yield('meta')

    {{-- Theme CSS variables injected server-side --}}
    @include('partials.theme-vars')

    @vite(['resources/css/app.css', 'resources/js/public.js'])
</head>
<body class="font-body text-gray-800 bg-white">

    @include('partials.navbar')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

</body>
</html>
