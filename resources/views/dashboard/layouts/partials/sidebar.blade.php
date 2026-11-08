<aside
    :class="{
        'border-r-dark-bg-800 bg-dark-bg-900': darkMode,
        'border-r-light-border-300 bg-light-bg-200': !darkMode
    }"
    class="w-64 shrink-0 border-r" x-show="sidebarOpen" x-transition>
    <div class="px-6 py-5 text-xl font-semibold">
        Acme Inc.
    </div>



    <nav :class="{
        'border-b-dark-bg-800': darkMode,
        'border-b-light-border-300': !darkMode
    }"
        class="px-4 space-y-4 border-b pb-4">
        <div class="space-y-1">
            <a href="{{ route('dashboard') }}"
                :class="{
                    // Dark Mode Classes
                    'bg-dark-bg-800 text-dark-text-100': darkMode &&
                        '{{ Route::is('dashboard') }}', // Active Dark
                    'text-dark-text-400 hover:bg-dark-bg-800': darkMode && !
                        '{{ Route::is('dashboard') }}', // Inactive Dark
                
                    // Light Mode Classes
                    'bg-light-bg-100 text-light-text-900': !darkMode &&
                        '{{ Route::is('dashboard') }}', // Active Light
                    'text-light-text-500 hover:bg-light-hover-100': !darkMode && !
                        '{{ Route::is('dashboard') }}', // Inactive Light
                }"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium">
                <span class="w-4 h-4 bg-gray-500 rounded-full"></span> Dashboard
            </a>
            <a href="{{ route('dashboard.employees.index') }}"
                :class="{
                    // Dark Mode Classes
                    'bg-dark-bg-800 text-dark-text-100': darkMode &&
                        '{{ Route::is('dashboard.employees.index') }}', // Active Dark
                    'text-dark-text-400 hover:bg-dark-bg-800': darkMode && !
                        '{{ Route::is('dashboard.employees.index') }}', // Inactive Dark
                
                    // Light Mode Classes
                    'bg-light-bg-100 text-light-text-900': !darkMode &&
                        '{{ Route::is('dashboard.employees.index') }}', // Active Light
                    'text-light-text-500 hover:bg-light-hover-100': !darkMode && !
                        '{{ Route::is('dashboard.employees.index') }}', // Inactive Light
                }"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm">
                <span class="w-4 h-4 bg-gray-500 rounded-full"></span> Employees
            </a>
            <a href="{{ route('dashboard.users.index') }}"
                :class="{
                    // Dark Mode Classes
                    'bg-dark-bg-800 text-dark-text-100': darkMode &&
                        '{{ Route::is('dashboard.users.*') }}', // Active Dark (including sub-routes like create/edit)
                    'text-dark-text-400 hover:bg-dark-bg-800': darkMode && !
                        '{{ Route::is('dashboard.users.*') }}', // Inactive Dark
                
                    // Light Mode Classes
                    'bg-light-bg-100 text-light-text-900': !darkMode &&
                        '{{ Route::is('dashboard.users.*') }}', // Active Light
                    'text-light-text-500 hover:bg-light-hover-100': !darkMode && !
                        '{{ Route::is('dashboard.users.*') }}', // Inactive Light
                }"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm">
                <span class="w-4 h-4 bg-gray-500 rounded-full"></span> Users
            </a>
        </div>
    </nav>

    

    {{-- Footer/Profile Section --}}
    <div :class="{
        'border-t-dark-bg-800 bg-dark-bg-900': darkMode,
        'border-t-light-border-300 bg-light-bg-200': !darkMode
    }"
        class="absolute bottom-0 w-64 p-4 border-t">
        <div class="space-y-2">
            <a href="#"
                :class="{
                    'text-dark-text-400 hover:bg-dark-bg-800': darkMode,
                    'text-light-text-500 hover:bg-light-hover-100': !darkMode
                }"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm">
                Settings
            </a>
            <a href="#"
                :class="{
                    'text-dark-text-400 hover:bg-dark-bg-800': darkMode,
                    'text-light-text-500 hover:bg-light-hover-100': !darkMode
                }"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm">
                Get Help
            </a>
            <a href="#"
                :class="{
                    'text-dark-text-400 hover:bg-dark-bg-800': darkMode,
                    'text-light-text-500 hover:bg-light-hover-100': !darkMode
                }"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm">
                Search
            </a>
        </div>
       
    </div>
</aside>
