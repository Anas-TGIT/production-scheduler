<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Production Scheduler') · {{ config('app.name') }}</title>
    {{-- Tailwind browser build: no Node build step needed for this assessment (see README). --}}
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.1.11"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <a href="{{ route('schedule.index') }}" class="font-semibold">Production Scheduler</a>
            <div class="flex gap-4 text-sm">
                <a href="{{ route('schedule.index') }}" class="{{ request()->routeIs('schedule.*') ? 'font-semibold text-indigo-700' : 'text-slate-600 hover:text-slate-900' }}">Schedule</a>
                <a href="{{ route('orders.create') }}" class="{{ request()->routeIs('orders.*') ? 'font-semibold text-indigo-700' : 'text-slate-600 hover:text-slate-900' }}">New order</a>
            </div>
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
