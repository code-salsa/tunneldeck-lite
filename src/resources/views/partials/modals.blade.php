<!-- ======================================================== -->
<!-- MODAL 1: ADD NEW TUNNEL (Matching Screenshot 1) -->
<!-- ======================================================== -->
<div id="modal-add-tunnel" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150">
    <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-md p-6 relative shadow-2xl space-y-5">
        <!-- Header -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
                <i data-lucide="plus-circle" class="w-5 h-5 text-blue-600 shrink-0"></i>
                <h3 class="text-base font-bold text-slate-900 tracking-tight">Add New Tunnel</h3>
            </div>
            <button type="button" onclick="closeModal('modal-add-tunnel')" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer" title="Close">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Form -->
        <form id="form-add-tunnel" onsubmit="handleAddTunnel(event)" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Tunnel Name</label>
                <input
                    type="text"
                    id="add-tunnel-name"
                    required
                    placeholder="e.g. Dev Server"
                    class="w-full px-3.5 py-2.5 bg-white border border-blue-400 rounded-xl text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-100 transition shadow-2xs"
                >
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Port Number</label>
                <input
                    type="number"
                    id="add-tunnel-port"
                    required
                    min="1"
                    max="65535"
                    placeholder="e.g. 3000, 8080"
                    class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100 transition"
                >
            </div>

            <div class="border-t border-slate-100 pt-4 mt-6 flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal('modal-add-tunnel')" class="px-5 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="btn-submit-add-tunnel" class="px-5 py-2 rounded-xl bg-[#2563eb] hover:bg-[#1d4ed8] text-white text-xs font-bold shadow-sm transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-1.5">
                    <span>Add Tunnel</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 2: START TUNNEL ROUTING (Matching Screenshots 1-4) -->
<!-- ======================================================== -->
<div id="modal-start-routing" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150">
    <div class="bg-white border border-slate-100 rounded-3xl w-full max-w-lg p-6 sm:p-7 relative shadow-2xl space-y-5">
        <!-- Top Icon Matching Screenshot -->
        <div class="text-center">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50/80 border border-indigo-100/80 flex items-center justify-center mx-auto mb-3 shadow-2xs">
                <svg class="w-6 h-6 text-[#2563eb]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="4" width="16" height="7" rx="2" fill="#2563eb" stroke="none" />
                    <circle cx="8" cy="7.5" r="1" fill="white" />
                    <circle cx="12" cy="7.5" r="1" fill="white" />
                    <line x1="12" y1="11" x2="12" y2="16" stroke="#2563eb" stroke-width="2.2" />
                    <line x1="7" y1="16" x2="17" y2="16" stroke="#2563eb" stroke-width="2.2" />
                    <circle cx="7" cy="16" r="1.5" fill="#2563eb" />
                    <circle cx="17" cy="16" r="1.5" fill="#2563eb" />
                </svg>
            </div>
            <h3 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Start Tunnel Routing</h3>
            <p class="text-xs text-slate-500 mt-1">Configure target domain and routing strategy for your tunnel</p>
        </div>

        <form id="form-start-routing" onsubmit="handleConfirmStartTunnel(event)" class="space-y-4">
            <!-- Hidden Tunnel ID -->
            <input type="hidden" id="routing-tunnel-id">
            <input type="hidden" id="routing-tunnel-port">

            <!-- 1. Target Domain Section -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <i data-lucide="globe" class="w-4 h-4 text-blue-600"></i>
                        <label class="text-xs sm:text-sm font-bold text-slate-700">Target Domain</label>
                    </div>
                    <span class="text-[11px] text-slate-400 font-medium">Cloudflare Network</span>
                </div>

                <div class="relative">
                    <select
                        id="routing-target-domain"
                        onchange="handleRoutingDomainChange()"
                        class="w-full px-4 py-2.5 bg-white border-2 border-blue-400 rounded-xl text-sm font-semibold text-slate-800 shadow-2xs focus:outline-none focus:ring-4 focus:ring-blue-100 appearance-none cursor-pointer pr-10 transition"
                    >
                        <option value="default">Default Cloudflare Domain (trycloudflare.com)</option>
                        @foreach($domains as $dom)
                            <option value="{{ $dom->id }}" data-domain="{{ $dom->domain }}">{{ $dom->domain }}</option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-500">
                        <i data-lucide="chevron-down" class="w-4 h-4"></i>
                    </div>
                </div>
            </div>

            <!-- 2. Routing Strategy Section (Hidden when default) -->
            <div id="routing-strategy-container" class="hidden space-y-2 pt-1 animate-in fade-in duration-150">
                <div class="flex items-center gap-1.5">
                    <i data-lucide="git-fork" class="w-4 h-4 text-blue-600 rotate-90"></i>
                    <label class="text-xs sm:text-sm font-bold text-slate-700">Routing Strategy</label>
                </div>

                <div class="space-y-2.5">
                    <!-- Option 1: Direct Apex -->
                    <div id="strategy-card-direct" onclick="selectRoutingStrategy('direct')" class="p-3.5 rounded-xl border cursor-pointer transition select-none flex items-start gap-3 border-2 border-blue-600 bg-white ring-2 ring-blue-100/50 shadow-2xs">
                        <div class="shrink-0 mt-0.5">
                            <div id="strategy-radio-direct" class="w-4 h-4 rounded-full border-2 border-blue-600 flex items-center justify-center">
                                <div class="w-2 h-2 rounded-full bg-blue-600"></div>
                            </div>
                        </div>
                        <div class="space-y-1 flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <i data-lucide="globe" class="w-4 h-4 text-blue-600 shrink-0"></i>
                                    <span class="text-xs sm:text-sm font-bold text-slate-900 truncate">Use added domain directly</span>
                                </div>
                                <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[10px] font-bold border border-emerald-200 shrink-0">Direct Apex</span>
                            </div>
                            <p class="text-[11px] text-slate-500 leading-normal">
                                Route directly to root apex <span id="strategy-apex-domain-label" class="px-1.5 py-0.5 rounded bg-indigo-50 text-[#100091] font-mono font-bold text-[11px]">yourdomain.com</span> with no subdomain prefix
                            </p>
                        </div>
                    </div>

                    <!-- Option 2: Subdomain -->
                    <div id="strategy-card-subdomain" onclick="selectRoutingStrategy('subdomain')" class="p-3.5 rounded-xl border cursor-pointer transition select-none flex items-start gap-3 border border-slate-200 bg-white hover:border-slate-300">
                        <div class="shrink-0 mt-0.5">
                            <div id="strategy-radio-subdomain" class="w-4 h-4 rounded-full border border-slate-300 flex items-center justify-center"></div>
                        </div>
                        <div class="space-y-1 flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <i data-lucide="network" class="w-4 h-4 text-blue-600 shrink-0"></i>
                                    <span class="text-xs sm:text-sm font-bold text-slate-900 truncate">Use a subdomain</span>
                                </div>
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-bold border border-slate-200 shrink-0">sub.domain</span>
                            </div>
                            <p class="text-[11px] text-slate-500 leading-normal">
                                Route using a custom named subdomain or auto-generated friendly prefix
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Custom Subdomain (Optional) Input (Shown only when Subdomain strategy selected) -->
            <div id="routing-subdomain-input-container" class="hidden space-y-1.5 pt-1 animate-in fade-in duration-150">
                <div class="flex items-center gap-1.5">
                    <i data-lucide="link-2" class="w-3.5 h-3.5 text-blue-600"></i>
                    <label class="text-xs font-semibold text-slate-700">Custom Subdomain (Optional)</label>
                </div>

                <div class="flex items-center border border-slate-300 rounded-xl overflow-hidden focus-within:border-blue-400 focus-within:ring-4 focus-within:ring-blue-100 transition bg-white shadow-2xs">
                    <span class="px-3 text-slate-400 font-mono text-sm select-none">#</span>
                    <input
                        type="text"
                        id="routing-subdomain-input"
                        oninput="updateRoutingPreview()"
                        placeholder="e.g. app or api"
                        class="w-full py-2.5 px-1 bg-transparent text-sm text-slate-900 placeholder-slate-400 focus:outline-none"
                    >
                    <span id="routing-subdomain-suffix" class="px-3 py-2 text-xs font-mono font-medium text-slate-500 bg-slate-50 border-l border-slate-200 select-none shrink-0">.yourdomain.com</span>
                </div>

                <p class="text-[11px] text-slate-400 flex items-center gap-1.5 pt-0.5">
                    <span>ⓘ</span>
                    <span>Leave blank to automatically assign a friendly 4-word random name.</span>
                </p>
            </div>

            <!-- 4. PUBLIC ENDPOINT PREVIEW Box -->
            <div class="rounded-2xl border border-slate-200/90 bg-slate-50/70 p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">PUBLIC ENDPOINT PREVIEW</span>
                    </div>
                    <span id="routing-preview-badge" class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200/80 text-slate-600">Quick Tunnel (trycloudflare)</span>
                </div>

                <div class="flex items-center gap-2 text-slate-900">
                    <i data-lucide="shield" class="w-5 h-5 text-emerald-600 fill-emerald-600/10 shrink-0"></i>
                    <span id="routing-preview-url" class="text-sm sm:text-base font-extrabold tracking-tight truncate">https://[unique-hash].trycloudflare.com</span>
                </div>

                <div class="flex items-center justify-between pt-1 border-t border-slate-200/60 text-xs">
                    <span class="text-slate-400 font-medium flex items-center gap-1">
                        <span>&rarr;</span>
                        <span>Forwarding target</span>
                    </span>
                    <span class="px-2.5 py-0.5 rounded bg-white border border-slate-200 text-xs font-mono text-slate-700 shadow-2xs">
                        127.0.0.1:<strong id="routing-preview-port" class="text-[#2563eb]">3000</strong>
                    </span>
                </div>
            </div>

            <!-- 5. Footer Action Buttons -->
            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal('modal-start-routing')" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs sm:text-sm font-semibold transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="btn-submit-start-routing" class="px-5 py-2.5 rounded-xl bg-[#059669] hover:bg-[#047857] text-white text-xs sm:text-sm font-bold shadow-sm transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-2">
                    <i data-lucide="play" class="w-3.5 h-3.5 fill-white"></i>
                    <span>Start Tunnel</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 3: EDIT TUNNEL -->
<!-- ======================================================== -->
<div id="modal-edit-tunnel" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150">
    <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-md p-6 relative shadow-2xl space-y-5">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
                <i data-lucide="pencil" class="w-5 h-5 text-blue-600 shrink-0"></i>
                <h3 class="text-base font-bold text-slate-900 tracking-tight">Edit Tunnel</h3>
            </div>
            <button type="button" onclick="closeModal('modal-edit-tunnel')" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer" title="Close">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="form-edit-tunnel" onsubmit="handleSaveEditTunnel(event)" class="space-y-4">
            <input type="hidden" id="edit-tunnel-id">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Tunnel Name</label>
                <input
                    type="text"
                    id="edit-tunnel-name"
                    required
                    placeholder="e.g. Dev Server"
                    class="w-full px-3.5 py-2.5 bg-white border border-blue-400 rounded-xl text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-100 transition"
                >
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Port Number</label>
                <input
                    type="number"
                    id="edit-tunnel-port"
                    required
                    min="1"
                    max="65535"
                    placeholder="e.g. 3000, 8080"
                    class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100 transition"
                >
            </div>

            <div class="border-t border-slate-100 pt-4 mt-6 flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal('modal-edit-tunnel')" class="px-5 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="btn-submit-edit-tunnel" class="px-5 py-2 rounded-xl bg-[#2563eb] hover:bg-[#1d4ed8] text-white text-xs font-bold shadow-sm transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-1.5">
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 4: DELETE TUNNEL CONFIRMATION -->
<!-- ======================================================== -->
<div id="modal-delete-tunnel" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md p-7 relative shadow-2xl text-center space-y-4">
        <div class="w-14 h-14 rounded-2xl bg-rose-50 border border-rose-100 text-rose-500 flex items-center justify-center mx-auto shadow-2xs">
            <i data-lucide="trash-2" class="w-6 h-6 stroke-[2.2]"></i>
        </div>

        <div class="space-y-1.5">
            <h3 class="text-xl font-extrabold text-slate-900 tracking-tight">Delete Tunnel</h3>
            <p class="text-xs text-slate-500 leading-relaxed px-2">
                Are you sure you want to stop and delete tunnel <strong id="delete-tunnel-name-label" class="text-slate-800">Dev Server</strong> (Port <span id="delete-tunnel-port-label">3000</span>)? The local process and public URL will be stopped immediately.
            </p>
        </div>

        <div class="pt-2 flex items-center justify-center gap-3">
            <button type="button" onclick="closeModal('modal-delete-tunnel')" class="w-full py-2.5 px-4 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition cursor-pointer">
                Cancel
            </button>
            <button type="button" id="btn-confirm-delete-tunnel" onclick="executeDeleteTunnel()" class="w-full py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-md shadow-rose-600/20 transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-1.5">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                <span>Delete Tunnel</span>
            </button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 5: ADD CUSTOM DOMAIN -->
<!-- ======================================================== -->
<div id="modal-add-domain" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150">
    <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg p-7 relative shadow-2xl">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-2">
                <i data-lucide="globe" class="w-5 h-5 text-blue-600 stroke-[2.2]"></i>
                <h3 class="text-lg font-bold text-slate-900 tracking-tight">Add Custom Domain</h3>
            </div>
            <button type="button" onclick="closeModal('modal-add-domain')" class="text-slate-400 hover:text-slate-600 transition cursor-pointer">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="form-add-domain" onsubmit="handleAddDomain(event)" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">Custom Root Domain Name</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="globe" class="w-4 h-4"></i>
                    </div>
                    <input
                        type="text"
                        id="add-domain-input"
                        required
                        placeholder="e.g. mycompany.com or api-server.net"
                        class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition shadow-2xs"
                    >
                </div>
                <p class="text-[11px] text-slate-400 mt-1.5 flex items-center gap-1">
                    <span>&bull;</span>
                    <span>Enter your root apex domain name as configured in your Cloudflare account.</span>
                </p>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal('modal-add-domain')" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="btn-submit-add-domain" class="px-5 py-2.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-bold shadow-md shadow-[#100091]/25 transition disabled:opacity-50 cursor-pointer flex items-center gap-1.5">
                    <span>Save &amp; Validate Domain</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 6: DELETE DOMAIN CONFIRMATION -->
<!-- ======================================================== -->
<div id="modal-delete-domain" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md p-7 relative shadow-2xl text-center space-y-4">
        <div class="w-14 h-14 rounded-2xl bg-rose-50 border border-rose-100 text-rose-500 flex items-center justify-center mx-auto shadow-2xs">
            <i data-lucide="trash-2" class="w-6 h-6 stroke-[2.2]"></i>
        </div>

        <div class="space-y-1.5">
            <h3 class="text-xl font-extrabold text-slate-900 tracking-tight">Delete Custom Domain</h3>
            <p class="text-xs text-slate-500 leading-relaxed px-2">
                Are you sure you want to remove domain <strong id="delete-domain-name-label" class="text-slate-800">yourdomain.com</strong>? Any tunnels currently mapped to this domain will revert to offline.
            </p>
        </div>

        <div class="pt-2 flex items-center justify-center gap-3">
            <button type="button" onclick="closeModal('modal-delete-domain')" class="w-full py-2.5 px-4 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition cursor-pointer">
                Cancel
            </button>
            <button type="button" id="btn-confirm-delete-domain" onclick="executeDeleteDomain()" class="w-full py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-md shadow-rose-600/20 transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-1.5">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                <span>Delete Domain</span>
            </button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 7: STEP-BY-STEP CLOUDFLARE GUIDE MODAL -->
<!-- ======================================================== -->
<div id="modal-cloudflare-guide" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl p-6 sm:p-8 relative shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-start justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-orange-50 border border-orange-200 text-orange-500 flex items-center justify-center shadow-2xs">
                    <i data-lucide="cloud" class="w-6 h-6 fill-current"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900 tracking-tight">How to Connect Your Domain in Cloudflare</h3>
                    <p class="text-xs text-slate-500">Step-by-step free setup guide for Cloudflare edge routing</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('modal-cloudflare-guide')" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg transition cursor-pointer">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="space-y-4 text-xs">
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <h4 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-[#100091] text-white flex items-center justify-center text-xs">1</span>
                    Sign Up for a Free Cloudflare Account
                </h4>
                <p class="text-slate-600 leading-relaxed pl-8">
                    Go to <a href="https://dash.cloudflare.com/sign-up" target="_blank" rel="noreferrer" class="text-[#100091] font-bold hover:underline">dash.cloudflare.com/sign-up</a> and create an account. The Free tier includes everything required.
                </p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <h4 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-[#100091] text-white flex items-center justify-center text-xs">2</span>
                    Add Your Domain
                </h4>
                <p class="text-slate-600 leading-relaxed pl-8">
                    In Cloudflare, click "+ Add a Site", enter your root domain (e.g. <code>mycompany.com</code>), and select the <strong>Free ($0)</strong> plan.
                </p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <h4 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-[#100091] text-white flex items-center justify-center text-xs">3</span>
                    Update Nameservers at Domain Registrar
                </h4>
                <p class="text-slate-600 leading-relaxed pl-8">
                    Log in to your registrar (GoDaddy, Namecheap, Google Domains/Squarespace) and change your nameservers to the two Cloudflare nameservers provided.
                </p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1">
                <h4 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-[#100091] text-white flex items-center justify-center text-xs">4</span>
                    Validate in TunnelDeck Lite
                </h4>
                <p class="text-slate-600 leading-relaxed pl-8">
                    Once nameservers propagate, click <strong>Validate</strong> next to your domain. TunnelDeck will authenticate your zone and enable live edge routing!
                </p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
            <button type="button" onclick="closeModal('modal-cloudflare-guide')" class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                Close
            </button>
            <a href="https://dash.cloudflare.com" target="_blank" rel="noopener noreferrer" class="px-5 py-2.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-[#100091]/25">
                <span>Open Cloudflare Dashboard</span>
                <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
            </a>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 8: CLOUDFLARE DOMAIN VALIDATION PROGRESS -->
<!-- ======================================================== -->
<div id="modal-domain-validation" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md p-7 relative shadow-2xl space-y-5">
        <div class="text-center space-y-2">
            <div id="validation-spinner-box" class="w-14 h-14 rounded-2xl bg-indigo-50 border border-indigo-100 text-[#100091] flex items-center justify-center mx-auto shadow-2xs">
                <i data-lucide="refresh-cw" class="w-6 h-6 animate-spin"></i>
            </div>
            <h3 id="validation-modal-title" class="text-xl font-extrabold text-slate-900 tracking-tight">Validating Domain</h3>
            <p id="validation-modal-subtitle" class="text-xs text-slate-500">Connecting to Cloudflare edge DNS to verify zone status...</p>
        </div>

        <div class="space-y-3 p-4 rounded-2xl bg-slate-50 border border-slate-100 text-xs">
            <div class="flex items-center justify-between" id="val-step-1">
                <span class="text-slate-700 font-medium">1. Cloudflared Binary Ready</span>
                <span id="val-step-1-badge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700">Passed</span>
            </div>
            <div class="flex items-center justify-between" id="val-step-2">
                <span class="text-slate-700 font-medium">2. Cloudflare Zone Query</span>
                <span id="val-step-2-badge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 animate-pulse">Checking...</span>
            </div>
            <div class="flex items-center justify-between" id="val-step-3">
                <span class="text-slate-700 font-medium">3. DNS Nameservers Verification</span>
                <span id="val-step-3-badge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-500">Pending</span>
            </div>
        </div>

        <div id="validation-action-btns" class="hidden pt-2 flex items-center justify-end gap-3">
            <button type="button" onclick="closeModal('modal-domain-validation')" class="w-full py-2.5 px-4 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition cursor-pointer">
                Close
            </button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 9: LICENSING SERVER BIDIRECTIONAL DIAGNOSTIC -->
<!-- ======================================================== -->
<div id="modal-license-diagnostic" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-150 overflow-y-auto">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl p-6 sm:p-7 relative shadow-2xl space-y-5 my-8 max-h-[90vh] flex flex-col">
        <!-- Header -->
        <div class="flex items-start justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl bg-indigo-50 border border-indigo-100 text-[#100091] flex items-center justify-center shadow-2xs shrink-0">
                    <i data-lucide="shield-check" class="w-6 h-6"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-lg font-extrabold text-slate-900 tracking-tight">Licensing Server Diagnostic</h3>
                        <span id="diagnostic-header-badge" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                            Ready
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">Bidirectional verification with <code class="font-mono text-indigo-700 font-semibold">https://licensing.tunneldeck.online</code></p>
                </div>
            </div>
            <button type="button" onclick="closeModal('modal-license-diagnostic')" class="w-8 h-8 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Diagnostic Summary Banner -->
        <div id="diagnostic-summary-card" class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div class="space-y-0.5">
                <span class="text-slate-400 block text-[11px] font-medium">Target Cloud Endpoint:</span>
                <span class="font-mono font-semibold text-slate-800 break-all">https://licensing.tunneldeck.online/api/v1</span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="text-slate-400 text-[11px] font-medium">Overall Status:</span>
                <span id="diagnostic-overall-pill" class="px-2.5 py-1 rounded-lg font-bold text-[11px] bg-slate-200 text-slate-700">
                    Idle
                </span>
            </div>
        </div>

        <!-- Step Results Container (Scrollable) -->
        <div id="diagnostic-steps-container" class="space-y-3 overflow-y-auto pr-1 flex-1 min-h-[220px]">
            <!-- Steps will be rendered here dynamically via JS -->
            <div id="diagnostic-empty-placeholder" class="py-12 text-center space-y-2">
                <i data-lucide="activity" class="w-8 h-8 text-slate-300 mx-auto animate-pulse"></i>
                <p class="text-xs text-slate-500 font-medium">Click "Run Live Diagnostic" below to execute all 5 outgoing and incoming license operations.</p>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="flex items-center justify-between pt-4 border-t border-slate-100">
            <span id="diagnostic-timer-label" class="text-xs text-slate-400 font-medium">Total time: --</span>
            <div class="flex items-center gap-3">
                <button type="button" onclick="closeModal('modal-license-diagnostic')" class="px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition cursor-pointer">
                    Close
                </button>
                <button type="button" id="btn-run-diagnostic-modal" onclick="runLicenseDiagnosticFlow(this)" class="px-4 py-2.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-bold transition flex items-center gap-2 shadow-sm cursor-pointer">
                    <i data-lucide="play" class="w-3.5 h-3.5"></i>
                    <span>Run Live Diagnostic</span>
                </button>
            </div>
        </div>
    </div>
</div>


