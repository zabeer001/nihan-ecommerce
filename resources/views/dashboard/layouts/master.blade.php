<!DOCTYPE html>
<html lang="en" x-data="{
    sidebarOpen: true,
    darkMode: localStorage.getItem('theme') === 'dark' ? true : false,
}" x-init="$watch('darkMode', val => {
    localStorage.setItem('theme', val ? 'dark' : 'light');
    document.documentElement.classList.toggle('dark', val);
});
// Initialize class based on initial state
document.documentElement.classList.toggle('dark', darkMode);" :class="{ 'dark': darkMode }">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Acme Inc. Dashboard')</title>

    <script src="{{ asset('assets/tailwind.js') }}"></script>
   <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.15.2/dist/cdn.min.js"></script>


<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        // --- Dark Mode Colors ---
                        'dark-bg-950': '#151515',
                        'dark-bg-900': '#1f1f1f',
                        'dark-bg-800': '#2c2c2c',
                        'dark-text-100': '#f0f0f0',
                        'dark-text-400': '#a0a0a0',

                        // --- Light Mode Colors ---
                        'light-bg-50': '#f9f9f9', // Very light background
                        'light-bg-100': '#ffffff', // Card/Internal background
                        'light-bg-200': '#f0f0f0', // Sidebar/Header background
                        'light-border-300': '#e0e0e0', // Border color
                        'light-text-900': '#1f1f1f', // Primary text
                        'light-text-500': '#6b7280', // Secondary text
                        'light-hover-100': '#e5e7eb', // Hover background
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-feature-settings: "rlig" 1, "calt" 1;
        }
    </style>
</head>

<body
    :class="{
        'bg-dark-bg-950 text-dark-text-100': darkMode,
        'bg-light-bg-50 text-light-text-900': !darkMode
    }"
    class="antialiased">

    <div class="flex min-h-screen">

        {{-- 1. Sidebar --}}
        @include('dashboard.layouts.partials.sidebar')

        <div class="flex flex-1 flex-col">

            {{-- 2. Header / Navbar --}}
            @include('dashboard.layouts.partials.header')

            <main class="flex-1 p-6 space-y-6">
                @yield('content')
            </main>



        </div>
    </div>

</body>

</html>
