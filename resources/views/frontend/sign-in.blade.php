<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In</title>
    <script src="{{ asset('assets/tailwind.js') }}"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.15.2/dist/cdn.min.js"></script>
    <style>
        /* Maintain the smooth transition for hover effects */
        .transition-colors {
            transition-property: background-color, border-color, color, fill, stroke, opacity, box-shadow, transform;
            transition-duration: 150ms;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Simple spinner animation for the loading state */
        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .spinner {
            animation: spin 1s linear infinite;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-8 sm:p-10 border border-gray-100"
        x-data="{
            email: '',
            password: '',
            rememberMe: false,
            isLoading: false,
            message: '',
        
            async submitForm() {
                this.isLoading = true;
                this.message = ''; // Clear previous messages
        
                try {
                    const response = await fetch('/api/signin', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
        
                        },
                        body: JSON.stringify({
                            email: this.email,
                            password: this.password,
                        })
                    });
        
                    const data = await response.json();
        
        
        
                    if (!response.ok) {
                        // Handle server-side validation errors (422) or authentication errors (401)
                        if (response.status === 422 && data.errors) {
                            // Example: Show the first validation error
                            const firstError = Object.values(data.errors)[0][0];
                            this.message = firstError;
                        } else if (response.status === 401 || response.status === 400) {
                            // General sign-in failure (like your errorResponse)
                            this.message = data.message || 'Authentication failed. Please check your credentials.';
                        } else {
                            this.message = 'An unknown error occurred on the server.';
                        }
                        this.password = ''; // Always clear password on failure
        
                    } else {
                        // SUCCESS response (200 OK)
        
                        console.log(data);
                        console.log(data.data.access_token);
                        let access_token = data.data.access_token;
                          let user = data.data.user;

                        localStorage.setItem('access_token', access_token);
                          localStorage.setItem('role', user.role);

        
                        this.message = data.message || 'Signed in successfully.';
        
                      
                    }
        
                } catch (e) {
                    console.error('Network or parsing error:', e);
                    this.message = 'Could not connect to the server. Please check your network.';
                } finally {
                    this.isLoading = false;
                }
            }
        }">

        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Welcome Back</h1>
            <p class="text-base text-gray-500 mt-2">
                Sign in to your account to continue
            </p>
        </div>

        <form class="space-y-6" @submit.prevent="submitForm">

            <div x-show="message" x-cloak
                :class="{
                    'bg-red-100 border-red-400 text-red-700': message && !message.includes('successfully'),
                    'bg-green-100 border-green-400 text-green-700': message && message.includes('successfully'),
                }"
                class="p-3 border rounded-lg text-sm" role="alert">
                <span x-text="message"></span>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Email address
                </label>
                <input id="email" type="email" placeholder="you@example.com" autocomplete="email" required
                    x-model="email" :disabled="isLoading"
                    class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-black focus:border-black transition-colors disabled:opacity-50 disabled:bg-gray-100">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">
                    Password
                </label>
                <input id="password" type="password" placeholder="••••••••" autocomplete="current-password" required
                    x-model="password" :disabled="isLoading"
                    class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-black focus:border-black transition-colors disabled:opacity-50 disabled:bg-gray-100">
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2 text-gray-600">
                    <input type="checkbox" x-model="rememberMe" :disabled="isLoading"
                        class="h-4 w-4 rounded text-black border-gray-300 focus:ring-black disabled:opacity-50">
                    Remember me
                </label>
                <a href="#" class="font-medium text-gray-800 hover:text-black transition-colors"
                    x-bind:class="{ 'opacity-50 pointer-events-none': isLoading }">
                    Forgot password?
                </a>
            </div>

            <button type="submit" :disabled="isLoading"
                class="w-full rounded-xl bg-black py-3 text-white font-semibold shadow-md shadow-gray-400/50 hover:bg-gray-800 focus:outline-none focus:ring-4 focus:ring-gray-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="!isLoading">Sign In</span>
                <span x-show="isLoading" x-cloak class="flex items-center justify-center">
                    <svg class="spinner mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    Signing In...
                </span>
            </button>

        </form>

        <div class="text-center mt-8 text-sm text-gray-500">
            Don’t have an account?
            <a href="#" class="font-semibold text-gray-800 hover:text-black transition-colors"
                x-bind:class="{ 'opacity-50 pointer-events-none': isLoading }">
                Sign up
            </a>
        </div>

    </div>

</body>

</html>
