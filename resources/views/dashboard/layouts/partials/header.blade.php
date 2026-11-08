<header 
  :class="{ 
    'border-b-dark-bg-800 bg-dark-bg-900': darkMode, 
    'border-b-light-border-300 bg-light-bg-200': !darkMode 
  }"
  class="sticky top-0 z-10 border-b"
>
  <div class="flex h-16 items-center justify-between px-6">
    <h2 class="text-xl font-semibold">@yield('page_title_in_header', 'Documents')</h2>
    <div class="flex items-center space-x-4">
        {{-- Theme Toggle Button --}}
        <button 
            @click="darkMode = !darkMode"
            :class="{ 
                'text-dark-text-400 hover:text-dark-text-100': darkMode, 
                'text-light-text-500 hover:text-light-text-900': !darkMode 
            }"
            class="rounded-full p-2"
            aria-label="Toggle Dark Mode"
        >
            <i x-show="darkMode" class="fa-solid fa-sun text-xl"></i>
            <i x-show="!darkMode" class="fa-solid fa-moon text-xl"></i>
      
        </button>
        <a href="#" 
            :class="{ 
                'text-dark-text-400 hover:text-dark-text-100': darkMode, 
                'text-light-text-500 hover:text-light-text-900': !darkMode 
            }"
            class="text-sm"
        >
            GitHub
        </a>
    </div>
  </div>
</header>