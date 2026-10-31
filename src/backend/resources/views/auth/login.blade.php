@extends('layouts.app')

@section('title', 'Sign In — TunnelDeck Lite')

@section('content')
<div class="min-h-screen w-full flex items-center justify-center p-4 bg-slate-50 relative overflow-hidden">
    <!-- Subtle light background gradients -->
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[400px] bg-gradient-to-tr from-[#100091]/5 via-indigo-100/40 to-blue-50 blur-[130px] pointer-events-none rounded-full"></div>
    <div class="absolute -bottom-20 -right-20 w-80 h-80 bg-blue-100/30 blur-[90px] pointer-events-none rounded-full"></div>

    <div class="w-full max-w-md relative z-10">
        <!-- Brand Header with Logo & Lite Pill -->
        <div class="text-center mb-7">
            <!-- Main Logo Header -->
            <div class="flex items-center justify-center gap-3 mb-3.5">
                <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-[#100091] text-white flex items-center justify-center shadow-lg shadow-[#100091]/25 shrink-0">
                    <i data-lucide="radio" class="w-6 h-6 animate-pulse"></i>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 font-sans">
                        TunnelDeck
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 text-[#100091] border border-indigo-200/90 text-xs font-extrabold uppercase tracking-wider shadow-2xs">
                        Lite
                    </span>
                </div>
            </div>

            <!-- SQLite Active Status Pill -->
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-700 text-xs font-semibold mb-3">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <i data-lucide="database" class="w-3.5 h-3.5 text-emerald-600"></i>
                <span>SQLite Active</span>
            </div>

            <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight text-slate-900 mb-1.5">
                Sign in to <span class="text-[#100091]">TunnelDeck</span>
            </h1>
            <p class="text-sm text-slate-500">
                Developer tunnel manager &amp; local port forwarder
            </p>
        </div>

        <!-- White Login Card -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-8 shadow-xl shadow-slate-200/70 relative">
            <!-- Error Alert -->
            <div id="login-error-alert" class="hidden mb-5 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-start gap-2.5">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 mt-0.5 text-rose-600"></i>
                <span id="login-error-text">Invalid email or password. Please verify your credentials.</span>
            </div>

            <form id="login-form" method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Email Address
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </div>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            required
                            autocomplete="email"
                            value="{{ old('email', 'admin@tunneldeck.online') }}"
                            placeholder="admin@tunneldeck.online"
                            class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition shadow-2xs"
                        >
                    </div>
                </div>

                <!-- Password Field -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-semibold text-slate-700">
                            Password
                        </label>
                        <span class="text-[11px] text-slate-400 font-medium">
                            No OTP required
                        </span>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            value="123456"
                            placeholder="••••••••"
                            class="w-full pl-10 pr-10 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition shadow-2xs"
                        >
                        <button
                            type="button"
                            id="toggle-password-btn"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition cursor-pointer"
                            title="Toggle password visibility"
                        >
                            <i data-lucide="eye" id="password-eye-icon" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    id="login-submit-btn"
                    class="w-full py-2.5 px-4 mt-2 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white font-semibold text-sm shadow-md shadow-[#100091]/25 transition duration-150 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                >
                    <span id="btn-text">Sign In</span>
                    <i data-lucide="arrow-right" id="btn-icon" class="w-4 h-4"></i>
                    <div id="btn-spinner" class="hidden w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                </button>
            </form>

            <!-- Footer Features Info -->
            <div className="mt-6 pt-5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500 font-medium">
                <div class="mt-6 pt-5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500 font-medium">
                    <div class="flex items-center gap-1.5 text-emerald-600">
                        <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                        <span>Direct login (no OTP)</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-slate-500">
                        <i data-lucide="database" class="w-3.5 h-3.5"></i>
                        <span>SQLite Database</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('toggle-password-btn');
    const eyeIcon = document.getElementById('password-eye-icon');
    const loginForm = document.getElementById('login-form');
    const errorAlert = document.getElementById('login-error-alert');
    const errorText = document.getElementById('login-error-text');
    const submitBtn = document.getElementById('login-submit-btn');
    const btnText = document.getElementById('btn-text');
    const btnIcon = document.getElementById('btn-icon');
    const btnSpinner = document.getElementById('btn-spinner');

    // Load saved preferences from localStorage if exists
    try {
        const autofillDisabled = localStorage.getItem('tunneldeck_autofill_password_enabled') === 'false';
        const savedPassword = localStorage.getItem('tunneldeck_saved_password') || '123456';
        if (autofillDisabled) {
            passwordInput.value = '';
        } else {
            passwordInput.value = savedPassword;
        }
    } catch (e) {
        // localStorage fallback
    }

    // Toggle password visibility
    togglePasswordBtn?.addEventListener('click', () => {
        const isPassword = passwordInput.getAttribute('type') === 'password';
        passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
        eyeIcon.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
        if (window.lucide) window.lucide.createIcons();
    });

    // AJAX Login Handler
    loginForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorAlert.classList.add('hidden');

        // Loading state
        submitBtn.disabled = true;
        btnText.textContent = 'Signing in...';
        btnIcon.classList.add('hidden');
        btnSpinner.classList.remove('hidden');

        const formData = new FormData(loginForm);
        const email = formData.get('email');
        const password = formData.get('password');

        try {
            const response = await fetch("{{ route('login') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                },
                body: JSON.stringify({ email, password })
            });

            const data = await response.json();

            if (!response.ok || data.status === 'error') {
                throw new Error(data.message || 'Invalid email or password.');
            }

            // Redirect on success
            window.location.href = data.redirect || "{{ route('dashboard') }}";
        } catch (err) {
            errorText.textContent = err.message || 'Failed to sign in. Please try again.';
            errorAlert.classList.remove('hidden');
            submitBtn.disabled = false;
            btnText.textContent = 'Sign In';
            btnIcon.classList.remove('hidden');
            btnSpinner.classList.add('hidden');
        }
    });
});
</script>
@endpush
