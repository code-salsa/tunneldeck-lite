<!-- ======================================================== -->
<!-- MULTI-STEP ONBOARDING MODAL (First Time Login) -->
<!-- ======================================================== -->
<div id="onboarding-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md transition-opacity duration-300">
    <div class="relative w-full max-w-xl bg-white rounded-3xl shadow-2xl border border-slate-100 overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-200">
        
        <!-- Modal Top Header & Branding -->
        <div class="px-8 pt-8 pb-4 border-b border-slate-100 bg-linear-to-b from-indigo-50/50 to-white">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-[#100091] flex items-center justify-center text-white shadow-md shadow-[#100091]/25">
                        <i data-lucide="radio" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xl font-extrabold text-slate-900 tracking-tight">TunnelDeck</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-[#100091] text-white">Lite</span>
                        </div>
                        <p class="text-[11px] text-slate-500 font-medium">Welcome &amp; Initial Account Setup</p>
                    </div>
                </div>

                <div class="text-right">
                    <span id="onboarding-step-counter" class="text-xs font-bold text-[#100091] bg-indigo-50 px-2.5 py-1 rounded-full border border-indigo-100">Step 1 of 3</span>
                </div>
            </div>

            <!-- Multi-Step Progress Tracker -->
            <div class="flex items-center justify-between relative px-2">
                <!-- Connecting lines -->
                <div class="absolute left-6 right-6 top-1/2 -translate-y-1/2 h-0.5 bg-slate-200 z-0"></div>
                <div id="onboarding-progress-bar" class="absolute left-6 top-1/2 -translate-y-1/2 h-0.5 bg-[#100091] transition-all duration-300 z-0" style="width: 0%;"></div>

                <!-- Step 1 Dot -->
                <div class="relative z-10 flex flex-col items-center">
                    <div id="step-dot-1" class="w-8 h-8 rounded-full bg-[#100091] text-white flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-sm transition-all duration-200">
                        <span>1</span>
                    </div>
                    <span class="text-[10px] font-bold text-slate-700 mt-1">Profile</span>
                </div>

                <!-- Step 2 Dot -->
                <div class="relative z-10 flex flex-col items-center">
                    <div id="step-dot-2" class="w-8 h-8 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-sm transition-all duration-200">
                        <span>2</span>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400 mt-1">Location</span>
                </div>

                <!-- Step 3 Dot -->
                <div class="relative z-10 flex flex-col items-center">
                    <div id="step-dot-3" class="w-8 h-8 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-sm transition-all duration-200">
                        <span>3</span>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400 mt-1">Security</span>
                </div>
            </div>
        </div>

        <!-- Onboarding Form Form Container -->
        <form id="onboarding-form" onsubmit="handleOnboardingSubmit(event)" class="px-8 py-6 space-y-6">
            @csrf

            <!-- ================= STEP 1: PERSONAL PROFILE ================= -->
            <div id="onboarding-step-1" class="space-y-4">
                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-slate-900 tracking-tight">Let's set up your profile</h3>
                    <p class="text-xs text-slate-500">Enter your name and primary email address to personalize your TunnelDeck instance.</p>
                </div>

                <div class="space-y-3 pt-2">
                    <div>
                        <label for="onb-name" class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center gap-1.5">
                            <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>Full Name <span class="text-rose-500">*</span></span>
                        </label>
                        <input type="text" id="onb-name" name="name" required placeholder="e.g. Aarav Sharma"
                            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition font-medium">
                        <p id="onb-name-error" class="text-[11px] text-rose-500 mt-1 hidden"></p>
                    </div>

                    <div>
                        <label for="onb-email" class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center gap-1.5">
                            <i data-lucide="mail" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>Email Address <span class="text-rose-500">*</span></span>
                        </label>
                        <input type="email" id="onb-email" name="email" required placeholder="e.g. aarav@example.com"
                            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition font-medium">
                        <p id="onb-email-error" class="text-[11px] text-rose-500 mt-1 hidden"></p>
                    </div>
                </div>

                <!-- Explore Platform Notice Callout -->
                <div class="p-3 rounded-xl bg-amber-50/70 border border-amber-200/80 flex items-center justify-between gap-3 text-amber-800 text-xs">
                    <div class="flex items-start gap-2.5">
                        <i data-lucide="compass" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                        <div class="space-y-0.5 text-[11px]">
                            <span class="font-bold text-amber-900">Just want to look around?</span>
                            <p class="text-amber-700 leading-relaxed">
                                Enter your email and explore the platform right away without a password.
                            </p>
                        </div>
                    </div>
                    <button type="button" onclick="triggerExplorePlatform(this)" class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-[11px] shrink-0 transition cursor-pointer shadow-xs">
                        <span>Explore &rarr;</span>
                    </button>
                </div>

                <div class="pt-4 flex items-center justify-between border-t border-slate-100">
                    <button type="button" onclick="triggerExplorePlatform(this)" class="text-xs font-semibold text-slate-500 hover:text-[#100091] transition cursor-pointer flex items-center gap-1">
                        <i data-lucide="compass" class="w-3.5 h-3.5"></i>
                        <span>Explore the platform &rarr;</span>
                    </button>

                    <button type="button" onclick="goToOnboardingStep(2)" class="px-5 py-2.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white font-bold text-xs transition cursor-pointer flex items-center gap-1.5 shadow-md shadow-[#100091]/25">
                        <span>Next: Location</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </div>

            <!-- ================= STEP 2: LOCATION & REGION ================= -->
            <div id="onboarding-step-2" class="space-y-4 hidden">
                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-slate-900 tracking-tight">Where are you located?</h3>
                    <p class="text-xs text-slate-500">Used to optimize Cloudflare edge routing and automatic currency detection.</p>
                </div>

                <div class="space-y-3 pt-2">
                    <div>
                        <label for="onb-city" class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center gap-1.5">
                            <i data-lucide="map-pin" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>City</span>
                        </label>
                        <input type="text" id="onb-city" name="city" placeholder="e.g. Bengaluru, San Francisco, London"
                            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition font-medium">
                    </div>

                    <div>
                        <label for="onb-country" class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center gap-1.5">
                            <i data-lucide="globe" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>Country</span>
                        </label>
                        <select id="onb-country" name="country"
                            class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 focus:outline-hidden focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition font-medium">
                            <option value="India" selected>India (Auto-currency: INR)</option>
                            <option value="United States">United States (Auto-currency: USD)</option>
                            <option value="United Kingdom">United Kingdom (USD)</option>
                            <option value="Canada">Canada (USD)</option>
                            <option value="Australia">Australia (USD)</option>
                            <option value="Germany">Germany (USD)</option>
                            <option value="France">France (USD)</option>
                            <option value="Singapore">Singapore (USD)</option>
                            <option value="United Arab Emirates">United Arab Emirates (USD)</option>
                            <option value="Japan">Japan (USD)</option>
                            <option value="Other">Other Country</option>
                        </select>
                    </div>
                </div>

                <!-- Currency Detection Info Box -->
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70 flex items-center gap-2.5 text-slate-600 text-[11px]">
                    <i data-lucide="badge-percent" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                    <span>India residents automatically receive <strong>INR</strong> pricing on package tiers; all other countries default to <strong>USD</strong>.</span>
                </div>

                <div class="pt-4 flex items-center justify-between border-t border-slate-100">
                    <button type="button" onclick="goToOnboardingStep(1)" class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold text-xs transition cursor-pointer flex items-center gap-1.5">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        <span>Back</span>
                    </button>

                    <div class="flex items-center gap-3">
                        <button type="button" onclick="triggerExplorePlatform(this)" class="text-xs font-semibold text-slate-500 hover:text-[#100091] transition cursor-pointer">
                            Explore with email
                        </button>
                        <button type="button" onclick="goToOnboardingStep(3)" class="px-5 py-2.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white font-bold text-xs transition cursor-pointer flex items-center gap-1.5 shadow-md shadow-[#100091]/25">
                            <span>Next: Security</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= STEP 3: SECURITY & PASSWORD ================= -->
            <div id="onboarding-step-3" class="space-y-4 hidden">
                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-slate-900 tracking-tight">Secure your account</h3>
                    <p class="text-xs text-slate-500">Replace the default <code class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-800 font-mono text-[11px]">123456</code> password with your own secure password.</p>
                </div>

                <div class="space-y-3 pt-2">
                    <div>
                        <label for="onb-password" class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center gap-1.5">
                            <i data-lucide="lock" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>New Password <span class="text-rose-500">*</span></span>
                        </label>
                        <div class="relative">
                            <input type="password" id="onb-password" name="password" required minlength="6" placeholder="Enter new password (min. 6 chars)"
                                class="w-full px-3.5 pr-10 py-2.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition font-medium">
                            <button type="button" onclick="togglePasswordVisibility('onb-password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="onb-password-confirmation" class="block text-xs font-bold text-slate-700 mb-1.5 flex items-center gap-1.5">
                            <i data-lucide="check-check" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>Confirm New Password <span class="text-rose-500">*</span></span>
                        </label>
                        <div class="relative">
                            <input type="password" id="onb-password-confirmation" name="password_confirmation" required minlength="6" placeholder="Re-enter password to confirm"
                                class="w-full px-3.5 pr-10 py-2.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition font-medium">
                            <button type="button" onclick="togglePasswordVisibility('onb-password-confirmation')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <p id="onb-password-error" class="text-[11px] text-rose-500 mt-1 hidden"></p>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-indigo-50/70 border border-indigo-200/60 flex items-start gap-2.5 text-indigo-900 text-xs">
                    <i data-lucide="shield-check" class="w-4 h-4 text-[#100091] shrink-0 mt-0.5"></i>
                    <p class="text-[11px] text-indigo-800 leading-relaxed">
                        Completing this replaces the default superadmin credentials with your newly entered email and password. Your license will be registered automatically with TunnelDeck.
                    </p>
                </div>

                <div class="pt-4 flex items-center justify-between border-t border-slate-100">
                    <button type="button" onclick="goToOnboardingStep(2)" class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold text-xs transition cursor-pointer flex items-center gap-1.5">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        <span>Back</span>
                    </button>

                    <button type="submit" id="onb-submit-btn" class="px-6 py-2.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white font-bold text-xs transition cursor-pointer flex items-center gap-2 shadow-md shadow-[#100091]/25">
                        <span id="onb-submit-text">Complete Setup &amp; Launch</span>
                        <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </div>
        </form>

        <!-- Bottom Explore Platform Footer Bar -->
        <div class="px-8 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
            <span>TunnelDeck Lite Licensing Engine</span>
            <button type="button" onclick="triggerExplorePlatform(this)" class="font-bold text-[#100091] hover:underline cursor-pointer flex items-center gap-1">
                <span>Explore the platform</span>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
            </button>
        </div>
    </div>
</div>

