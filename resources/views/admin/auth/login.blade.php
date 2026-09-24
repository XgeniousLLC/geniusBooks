<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" href="/favicon.ico">
    <title>Sign In — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .grid-pattern {
            background-image:
                linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 32px 32px;
        }
    </style>
</head>
<body class="bg-white antialiased">

<div class="min-h-screen flex">

    {{-- Left panel --}}
    <div class="hidden lg:flex lg:w-1/2 relative bg-slate-900 flex-col justify-between p-12 overflow-hidden">
        {{-- Grid pattern --}}
        <div class="absolute inset-0 grid-pattern"></div>
        {{-- Gradient orb --}}
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-indigo-600 rounded-full opacity-20 blur-3xl"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-violet-600 rounded-full opacity-20 blur-3xl"></div>

        {{-- Logo --}}
        <div class="relative z-10">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-indigo-500 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <span class="text-white font-semibold text-lg">{{ config('app.name') }}</span>
            </div>
        </div>

        {{-- Center content --}}
        <div class="relative z-10">
            <h1 class="text-4xl font-bold text-white leading-tight mb-4">
                Admin made<br>
                <span class="text-indigo-400">simple.</span>
            </h1>
            <p class="text-slate-400 text-lg leading-relaxed max-w-sm">
                Manage content, users, and settings — all in one place.
            </p>

            <div class="mt-10 space-y-4">
                @foreach([
                    ['icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'text' => 'Secure admin authentication'],
                    ['icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16', 'text' => 'Content & page management'],
                    ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'text' => 'SEO analysis & meta management'],
                ] as $feat)
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $feat['icon'] }}"/>
                        </svg>
                    </div>
                    <span class="text-slate-300 text-sm">{{ $feat['text'] }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <div class="relative z-10 text-slate-600 text-xs">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>

    {{-- Right panel — form --}}
    <div class="flex-1 flex items-center justify-center px-6 py-12 sm:px-12">
        <div class="w-full max-w-md">

            {{-- Mobile logo --}}
            <div class="lg:hidden flex items-center gap-2 mb-8">
                <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <span class="font-semibold text-gray-900">{{ config('app.name') }}</span>
            </div>

            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900">Welcome back</h2>
                <p class="mt-1 text-sm text-gray-500">Sign in to your admin account</p>
            </div>

            {{-- Error --}}
            @if ($errors->any())
            <div class="mb-6 flex gap-3 items-start bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form method="POST" action="{{ route('admin.login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                    <input
                        id="email" name="email" type="email" autocomplete="email" required
                        value="{{ old('email') }}"
                        autofocus
                        placeholder="you@example.com"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-900 placeholder-gray-400
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white
                               transition-all duration-150"
                    >
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    </div>
                    <div class="relative" x-data="{ show: false }">
                        <input
                            id="password" name="password" :type="show ? 'text' : 'password'"
                            autocomplete="current-password" required
                            placeholder="••••••••"
                            class="w-full px-4 py-3 pr-11 rounded-xl border border-gray-200 bg-gray-50 text-sm text-gray-900 placeholder-gray-400
                                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white
                                   transition-all duration-150"
                        >
                        <button type="button" @click="show = !show"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg x-show="show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" value="1"
                               class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                        <span class="text-sm text-gray-600">Remember me</span>
                    </label>
                </div>

                <button type="submit"
                        class="w-full flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700
                               text-white text-sm font-semibold py-3 px-4 rounded-xl
                               focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500
                               transition-colors duration-150 shadow-sm shadow-indigo-200">
                    Sign in to Admin
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </button>
            </form>

            {{-- Demo credentials --}}
            <div class="mt-6 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                <p class="text-xs font-semibold text-amber-700 mb-1">Demo credentials</p>
                <p class="text-xs text-amber-600 font-mono">admin@example.com / password</p>
            </div>

            <p class="mt-6 text-center text-xs text-gray-400">
                Customer portal?
                <a href="/portal/login" class="text-indigo-600 hover:underline font-medium">Sign in here</a>
            </p>
        </div>
    </div>
</div>

</body>
</html>
