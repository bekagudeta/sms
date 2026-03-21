<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Change Password - {{ config('app.name', 'Laravel') }}</title>

    <!-- Favicon -->
    <link rel="icon" href="/favicon.jpg" type="image/jpeg">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @routes
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full bg-white shadow-lg rounded-xl p-8">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-semibold text-gray-900">Change Password</h1>
                <p class="mt-2 text-sm text-gray-600">Create a new password to secure your account.</p>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="/change-password" class="space-y-6" id="changePasswordForm">
                @csrf

                <div class="relative">
                    <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <button
                        type="button"
                        id="togglePassword"
                        class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
                        aria-label="Show password"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" id="togglePasswordIcon">
                            <path d="M10 3C6 3 2.73 5.11 1 8c1.73 2.89 5 5 9 5s7.27-2.11 9-5c-1.73-2.89-5-5-9-5z" />
                            <path d="M10 7a3 3 0 100 6 3 3 0 000-6z" />
                        </svg>
                    </button>
                </div>

                <div class="relative">
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <button
                        type="button"
                        id="toggleConfirm"
                        class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600"
                        aria-label="Show confirm password"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" id="toggleConfirmIcon">
                            <path d="M10 3C6 3 2.73 5.11 1 8c1.73 2.89 5 5 9 5s7.27-2.11 9-5c-1.73-2.89-5-5-9-5z" />
                            <path d="M10 7a3 3 0 100 6 3 3 0 000-6z" />
                        </svg>
                    </button>
                </div>

                <div class="text-sm text-gray-600">
                    <p id="passwordHelp" class="mt-1">For security, your password must be at least 8 characters.</p>
                    <p id="passwordMatch" class="mt-1 text-sm"></p>
                </div>

                <button
                    type="submit"
                    class="w-full inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Change Password
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-gray-500">
                Want help? Contact your administrator if you're stuck.
            </p>

            <script>
                const passwordInput = document.getElementById('password');
                const confirmInput = document.getElementById('password_confirmation');
                const matchMessage = document.getElementById('passwordMatch');
                const togglePassword = document.getElementById('togglePassword');
                const toggleConfirm = document.getElementById('toggleConfirm');
                const togglePasswordIcon = document.getElementById('togglePasswordIcon');
                const toggleConfirmIcon = document.getElementById('toggleConfirmIcon');

                const setIcon = (icon, visible) => {
                    icon.innerHTML = visible
                        ? '<path d="M3.707 2.293a1 1 0 00-1.414 1.414l1.076 1.077C2.4 6.488 1 8.176 1 8c1.73 2.89 5 5 9 5 1.9 0 3.67-.6 5.13-1.62l1.486 1.487a1 1 0 001.414-1.415l-14-14z" /><path d="M7.553 9.637a2 2 0 002.81 2.81l-2.81-2.81z" />'
                        : '<path d="M10 3C6 3 2.73 5.11 1 8c1.73 2.89 5 5 9 5s7.27-2.11 9-5c-1.73-2.89-5-5-9-5z" />\n                        <path d="M10 7a3 3 0 100 6 3 3 0 000-6z" />';
                };

                const toggleField = (input, icon) => {
                    const visible = input.type === 'password';
                    input.type = visible ? 'text' : 'password';
                    setIcon(icon, visible);
                };

                togglePassword.addEventListener('click', () => toggleField(passwordInput, togglePasswordIcon));
                toggleConfirm.addEventListener('click', () => toggleField(confirmInput, toggleConfirmIcon));

                const checkMatch = () => {
                    if (!confirmInput.value) {
                        matchMessage.textContent = '';
                        return;
                    }

                    if (passwordInput.value === confirmInput.value) {
                        matchMessage.textContent = 'Passwords match';
                        matchMessage.className = 'mt-1 text-sm text-green-600';
                    } else {
                        matchMessage.textContent = 'Passwords do not match';
                        matchMessage.className = 'mt-1 text-sm text-red-600';
                    }
                };

                passwordInput.addEventListener('input', checkMatch);
                confirmInput.addEventListener('input', checkMatch);
            </script>
        </div>
    </div>
</body>
</html>
