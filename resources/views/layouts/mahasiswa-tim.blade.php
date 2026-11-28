<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SIKAP - Teknologi Rekayasa Komputer')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('images/logo_trk remove.png') }}?v=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.31.0/dist/tabler-icons.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="frontend-shell mahasiswa-shell h-dvh overflow-hidden bg-page font-sans text-gray-900 antialiased">

<div class="flex h-full overflow-hidden bg-page text-gray-900" data-frontend-shell>
    @include('layouts.partials.pengajuan-sidebar')

    <div class="flex min-w-0 flex-1 flex-col lg:ml-60">
        @include('layouts.partials.pengajuan-header')
        <main class="frontend-main flex min-h-0 min-w-0 flex-1 flex-col gap-6 overflow-y-auto px-4 pb-10 pt-6 sm:px-6 sm:pb-12 sm:pt-8">
            @yield('content')
        </main>
    </div>

    <x-alert />
    <x-preview-dokumen />
</div>

</body>
</html>