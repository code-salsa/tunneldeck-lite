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
                        @if(isset($domains) && $domains->count() > 0)
                        @foreach($domains as $dom)
                            <option value="{{ $dom->id }}" data-domain="{{ $dom->domain }}">{{ $dom->domain }}</option>
                        @endforeach
                        @endif
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

        <!-- DUMMY TUNNELING STEPS SIMULATION (Shown for demo tunnels) -->
        <div id="routing-dummy-steps-container" class="hidden space-y-4 py-2 animate-in fade-in duration-200">
            <!-- Simulation Badge -->
            <div class="p-3 rounded-2xl bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-300 text-amber-900 text-xs flex items-center justify-between gap-2 shadow-2xs">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                    <span class="font-black uppercase tracking-wider text-[10px] bg-amber-200 text-amber-900 px-2 py-0.5 rounded-full border border-amber-300">Simulation Mode</span>
                    <span class="font-bold text-[11px]">Dummy Tunneling Process &bull; For Preview Only</span>
                </div>
            </div>

            <!-- Steps list -->
            <div class="space-y-3 p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                <!-- Step 1 -->
                <div id="sim-step-1" class="flex items-center justify-between text-xs py-1 transition-all duration-300 opacity-40">
                    <div class="flex items-center gap-3">
                        <div id="sim-step-icon-1" class="w-7 h-7 rounded-xl bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">1</div>
                        <div>
                            <span class="font-bold text-slate-900">Verifying Local Listener Port</span>
                            <p class="text-[11px] text-slate-500">Probing 127.0.0.1:<span id="sim-step-port">3000</span> for local HTTP service</p>
                        </div>
                    </div>
                    <span id="sim-step-badge-1" class="text-[11px] font-bold text-slate-400">Waiting...</span>
                </div>

                <!-- Step 2 -->
                <div id="sim-step-2" class="flex items-center justify-between text-xs py-1 transition-all duration-300 opacity-40">
                    <div class="flex items-center gap-3">
                        <div id="sim-step-icon-2" class="w-7 h-7 rounded-xl bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">2</div>
                        <div>
                            <span class="font-bold text-slate-900">Connecting to Cloudflare Global Edge</span>
                            <p class="text-[11px] text-slate-500">Establishing encrypted QUIC tunnel to nearest Cloudflare PoP</p>
                        </div>
                    </div>
                    <span id="sim-step-badge-2" class="text-[11px] font-bold text-slate-400">Waiting...</span>
                </div>

                <!-- Step 3 -->
                <div id="sim-step-3" class="flex items-center justify-between text-xs py-1 transition-all duration-300 opacity-40">
                    <div class="flex items-center gap-3">
                        <div id="sim-step-icon-3" class="w-7 h-7 rounded-xl bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">3</div>
                        <div>
                            <span class="font-bold text-slate-900">Issuing Zero-Trust TLS Certificate</span>
                            <p class="text-[11px] text-slate-500">Configuring automatic wildcard SSL/TLS certificates</p>
                        </div>
                    </div>
                    <span id="sim-step-badge-3" class="text-[11px] font-bold text-slate-400">Waiting...</span>
                </div>

                <!-- Step 4 -->
                <div id="sim-step-4" class="flex items-center justify-between text-xs py-1 transition-all duration-300 opacity-40">
                    <div class="flex items-center gap-3">
                        <div id="sim-step-icon-4" class="w-7 h-7 rounded-xl bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">4</div>
                        <div>
                            <span class="font-bold text-slate-900">Tunnel Routing Active</span>
                            <p id="sim-step-url" class="text-[11px] font-mono text-[#100091] font-bold truncate max-w-xs">https://demo-nextjs-app.trycloudflare.com</p>
                        </div>
                    </div>
                    <span id="sim-step-badge-4" class="text-[11px] font-bold text-slate-400">Waiting...</span>
                </div>
            </div>

            <!-- 60-Second Auto-Stop Timer Banner inside modal -->
            <div id="sim-timer-banner" class="hidden p-3 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs flex items-center justify-between gap-2 shadow-2xs animate-in fade-in duration-200">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <i data-lucide="timer" class="w-4 h-4 text-amber-600"></i>
                    <span>Tunnel active for demo &bull; <strong id="sim-modal-timer-text" class="text-amber-900 font-bold">Auto stop tunnel in 01:00</strong></span>
                </div>
                <span class="text-[10px] font-bold uppercase bg-amber-200 px-2 py-0.5 rounded-full text-amber-900 border border-amber-300">60s Demo</span>
            </div>

            <div class="p-3 rounded-xl bg-indigo-50/70 border border-indigo-100 text-indigo-900 text-[11px] leading-relaxed flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-indigo-600 shrink-0"></i>
                <span>This simulation demonstrates the exact tunnel connection sequence without launching live OS processes.</span>
            </div>
        </div>
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
<!-- MODALS 5-8: PRO CUSTOM DOMAINS MODALS (PRO ADDON) -->
<!-- ======================================================== -->
@if(view()->exists('partials.domains_modals'))
    @include('partials.domains_modals')
@endif

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

        <!-- Hardware ID (HWID) Fingerprint Banner (Layer 3 Hardening) -->
        <div class="px-4 py-3 rounded-2xl bg-indigo-50/70 border border-indigo-100 flex items-center justify-between text-xs">
            <div class="flex items-center gap-2.5">
                <i data-lucide="cpu" class="w-4 h-4 text-[#100091]"></i>
                <div>
                    <span class="text-slate-400 text-[10px] uppercase font-bold tracking-wider block">Hardware Device ID (Layer 3 HWID)</span>
                    <span class="font-mono font-bold text-indigo-950 text-xs">{{ app(\App\Services\HardwareFingerprintService::class)->getHwid() }}</span>
                </div>
            </div>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                Bound &amp; Protected
            </span>
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

<!-- ======================================================== -->
<!-- MODAL 10: BUY ME A COFFEE APPRECIATION -->
<!-- ======================================================== -->
<div id="modal-coffee-thanks" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-150">
    <div class="bg-white border border-amber-200/90 rounded-3xl w-full max-w-md p-6 sm:p-7 relative shadow-2xl text-center space-y-5">
        <!-- Coffee Icon -->
        <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-amber-100 to-orange-100 text-amber-700 border border-amber-200 flex items-center justify-center mx-auto shadow-inner">
            <i data-lucide="coffee" class="w-8 h-8"></i>
        </div>

        <div class="space-y-1.5">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-[11px] font-bold uppercase tracking-wider">
                <i data-lucide="heart" class="w-3 h-3 text-rose-500 fill-current"></i>
                <span>Gratitude &amp; Support</span>
            </span>
            <h3 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Thank You for the Coffee! ☕</h3>
            <p class="text-xs text-slate-500 leading-relaxed max-w-sm mx-auto">
                Your support directly fuels TunnelDeck <span class="app-edition-label" data-app-edition>{{ (auth()->user() && auth()->user()->isPro()) ? 'Pro' : 'Lite' }}</span> development, server costs, and regular updates.
            </p>
        </div>

        <!-- Receipt / Summary Box -->
        <div class="p-4 rounded-2xl bg-amber-50/60 border border-amber-200/80 text-left space-y-2 text-xs">
            <div class="flex items-center justify-between">
                <span class="text-slate-500">Amount Donated:</span>
                <span id="coffee-thanks-amount" class="font-extrabold text-amber-900 text-sm">₹250</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-500">Payment Reference:</span>
                <span id="coffee-thanks-txn" class="font-mono text-slate-700 font-semibold truncate max-w-[180px]">pay_...</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-500">Donation Time:</span>
                <span id="coffee-thanks-time" class="text-slate-700 font-medium">Just now</span>
            </div>
            <div id="coffee-thanks-note-container" class="hidden pt-1.5 border-t border-amber-200/60">
                <span class="text-slate-400 block text-[10px] mb-0.5">Your Note:</span>
                <p id="coffee-thanks-note" class="text-slate-700 italic text-[11px]"></p>
            </div>
        </div>

        <button type="button" onclick="closeModal('modal-coffee-thanks')" class="w-full py-2.5 px-4 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition shadow-sm cursor-pointer">
            You're Most Welcome!
        </button>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 11: PRO PLAN UPGRADE CELEBRATION & PROGRESS -->
<!-- ======================================================== -->
<div id="modal-pro-welcome" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-150">
    <div class="bg-white border border-indigo-200/90 rounded-3xl w-full max-w-md p-6 sm:p-7 relative shadow-2xl text-center space-y-5">
        
        <!-- STEP 1: UPGRADE PROGRESS VIEW -->
        <div id="pro-upgrade-progress-view" class="space-y-5">
            <!-- Download Cloud Icon -->
            <div class="w-16 h-16 rounded-3xl bg-indigo-50 text-[#100091] border border-indigo-100 flex items-center justify-center mx-auto shadow-inner relative">
                <i data-lucide="cloud-download" class="w-8 h-8 text-[#100091] animate-bounce"></i>
            </div>

            <div class="space-y-1.5">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-200 text-[#100091] text-[11px] font-bold uppercase tracking-wider">
                    <span class="w-2 h-2 rounded-full bg-[#100091] animate-ping"></span>
                    <span>Upgrading Edition</span>
                </span>
                <h3 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight leading-snug">
                    Please wait while we are upgrading your Tunneldeck lite to Pro
                </h3>
                <p class="text-xs text-slate-500 leading-relaxed max-w-sm mx-auto" id="pro-upgrade-step-desc">
                    Downloading Pro version files from licensing API and merging into project src...
                </p>
            </div>

            <!-- Progress Bar -->
            <div class="space-y-2 text-left">
                <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden border border-slate-200">
                    <div id="pro-upgrade-progress-bar" class="h-full bg-gradient-to-r from-indigo-500 via-purple-600 to-[#100091] rounded-full transition-all duration-500" style="width: 20%;"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500 font-medium">
                    <span id="pro-upgrade-status-text" class="truncate font-medium">Authenticating Pro license...</span>
                    <span id="pro-upgrade-percentage" class="font-bold text-[#100091] font-mono shrink-0 ml-2">20%</span>
                </div>
            </div>

            <!-- Progress Milestones -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 text-left space-y-2 text-xs">
                <div class="flex items-center gap-2" id="pro-milestone-1">
                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                    <span class="text-slate-700 font-medium">1. Pro license verified &amp; token issued</span>
                </div>
                <div class="flex items-center gap-2" id="pro-milestone-2">
                    <i data-lucide="loader-2" class="w-4 h-4 text-[#100091] animate-spin shrink-0"></i>
                    <span class="text-slate-700 font-medium">2. Downloading Pro version build from API URL...</span>
                </div>
                <div class="flex items-center gap-2 text-slate-400" id="pro-milestone-3">
                    <i data-lucide="circle" class="w-4 h-4 shrink-0"></i>
                    <span>3. Extracting and merging files into project src directory</span>
                </div>
                <div class="flex items-center gap-2 text-slate-400" id="pro-milestone-4">
                    <i data-lucide="circle" class="w-4 h-4 shrink-0"></i>
                    <span>4. Initializing Custom Domains &amp; Pro engine</span>
                </div>
            </div>
        </div>

        <!-- STEP 2: CELEBRATION / SUCCESS VIEW -->
        <div id="pro-upgrade-success-view" class="hidden space-y-5">
            <!-- Crown Icon -->
            <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-amber-100 via-indigo-100 to-purple-100 text-indigo-900 border border-indigo-200 flex items-center justify-center mx-auto shadow-inner">
                <i data-lucide="crown" class="w-8 h-8 text-amber-500 fill-current"></i>
            </div>

            <div class="space-y-1.5">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-bold uppercase tracking-wider">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Pro License Active</span>
                </span>
                <h3 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Welcome to TunnelDeck Pro!</h3>
                <p class="text-xs text-slate-500 leading-relaxed max-w-sm mx-auto">
                    Enjoy your Pro privileges. Your project src has been upgraded with the Pro codebase and all features unlocked.
                </p>
            </div>

            <!-- Benefits Unlocked -->
            <div class="p-4 rounded-2xl bg-indigo-50/50 border border-indigo-100 text-left space-y-2 text-xs">
                <div class="flex items-center justify-between">
                    <h4 class="font-bold text-slate-800 text-[11px] uppercase tracking-wider">Unlocked Pro Privileges:</h4>
                    <span id="pro-merged-badge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">✓ Merged into src</span>
                </div>
                <div class="space-y-1.5 text-slate-700">
                    <div class="flex items-center gap-2">
                        <i data-lucide="check" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                        <span class="font-bold">Unlimited Active Tunnels</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="check" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                        <span class="font-bold">Unlimited Custom Domains</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="check" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                        <span class="font-medium text-slate-600">Dedicated Edge Bandwidth &amp; SSL</span>
                    </div>
                </div>
                <div class="pt-2 border-t border-indigo-100 flex items-center justify-between text-[11px]">
                    <span class="text-slate-400">Payment ID:</span>
                    <span id="pro-thanks-txn" class="font-mono text-indigo-900 font-semibold truncate max-w-[160px]">pay_...</span>
                </div>
            </div>

            <button type="button" id="btn-explore-pro-experience" onclick="exploreProExperience(this)" class="w-full py-2.5 px-4 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-bold transition shadow-md shadow-[#100091]/25 cursor-pointer flex items-center justify-center gap-2">
                <span>Explore Pro Experience</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </div>

    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 12: NAME ALREADY TAKEN (Custom Validation Error Modal) -->
<!-- ======================================================== -->
<div id="modal-name-taken" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-150">
    <div class="bg-white border border-rose-200/90 rounded-3xl w-full max-w-md p-6 sm:p-7 relative shadow-2xl text-center space-y-5">
        <!-- Close (X) button top-right -->
        <button type="button" onclick="closeNameTakenModal()" class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer" title="Close">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>

        <!-- Warning Icon -->
        <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-rose-50 to-red-100 text-rose-600 border border-rose-200 flex items-center justify-center mx-auto shadow-inner">
            <i data-lucide="alert-triangle" class="w-8 h-8 stroke-[2.2]"></i>
        </div>

        <div class="space-y-1.5">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-50 border border-rose-200 text-rose-700 text-[11px] font-bold uppercase tracking-wider">
                <i data-lucide="info" class="w-3 h-3"></i>
                <span id="name-taken-modal-badge">Duplicate Name Detected</span>
            </span>
            <h3 id="name-taken-modal-title" class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">The name has already been taken.</h3>
            <p id="name-taken-modal-desc" class="text-xs text-slate-500 leading-relaxed max-w-sm mx-auto">
                A tunnel with this name already exists in your workspace. Each tunnel requires a unique name so routing and status can be tracked without conflicts.
            </p>
        </div>

        <!-- Details & Smart Suggestion Box -->
        <div class="p-4 rounded-2xl bg-rose-50/40 border border-rose-200/70 text-left space-y-2 text-xs">
            <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Entered Name:</span>
                <span id="name-taken-conflicted-name" class="font-mono font-bold text-rose-700 bg-rose-100/70 px-2 py-0.5 rounded border border-rose-200 text-xs">Tunnel</span>
            </div>
            <div class="pt-2 border-t border-rose-200/60 flex items-start gap-2">
                <i data-lucide="sparkles" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                <p class="text-[11px] text-slate-600 leading-snug">
                    <span class="font-bold text-slate-800">Suggested Alternative:</span><br>
                    Try using <span id="name-taken-suggested-name" class="font-mono font-bold text-indigo-700">"Tunnel 2"</span>
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-1 flex flex-col gap-2">
            <button type="button" onclick="applySuggestedTunnelName()" class="w-full py-2.5 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white text-xs font-bold transition shadow-sm cursor-pointer flex items-center justify-center gap-1.5">
                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                <span>Use Suggested Name</span>
            </button>
            <button type="button" onclick="closeNameTakenModal()" class="w-full py-2 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 active:scale-[0.98] text-slate-700 text-xs font-bold transition shadow-2xs cursor-pointer flex items-center justify-center gap-1.5">
                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                <span>Change Tunnel Name</span>
            </button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 12B: PORT NOT RUNNING (Custom Port Inactivity Modal) -->
<!-- ======================================================== -->
<div id="modal-port-not-running" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-150">
    <div class="bg-white border border-amber-300 rounded-3xl w-full max-w-md p-6 sm:p-7 relative shadow-2xl text-center space-y-5">
        <!-- Close (X) button top-right -->
        <button type="button" onclick="closePortNotRunningModal()" class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer" title="Close">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>

        <!-- Warning Icon -->
        <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-amber-50 to-orange-100 text-amber-600 border border-amber-200 flex items-center justify-center mx-auto shadow-inner">
            <i data-lucide="power-off" class="w-8 h-8 stroke-[2.2]"></i>
        </div>

        <div class="space-y-1.5">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-[11px] font-bold uppercase tracking-wider">
                <i data-lucide="alert-circle" class="w-3 h-3"></i>
                <span id="port-not-running-badge">Port Inactive &bull; Not Running</span>
            </span>
            <h3 id="port-not-running-title" class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Port is not in running condition</h3>
            <p id="port-not-running-desc" class="text-xs text-slate-500 leading-relaxed max-w-sm mx-auto">
                Port <span id="port-not-running-number" class="font-mono font-bold text-slate-800">3000</span> could not be reached. The port should verify to be in running condition before adding a tunnel.
            </p>
        </div>

        <!-- Port Diagnostic Details Box -->
        <div class="p-4 rounded-2xl bg-amber-50/40 border border-amber-200/70 text-left space-y-2.5 text-xs">
            <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Local Target:</span>
                <span id="port-not-running-target" class="font-mono font-bold text-amber-900 bg-amber-100/70 px-2 py-0.5 rounded border border-amber-200 text-xs">http://127.0.0.1:3000</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Port Status:</span>
                <span class="inline-flex items-center gap-1.5 text-rose-600 font-bold text-[11px]">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                    No Listener &bull; Connection Refused
                </span>
            </div>
            <div class="pt-2 border-t border-amber-200/60 flex items-start gap-2">
                <i data-lucide="info" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                <p class="text-[11px] text-slate-600 leading-snug">
                    <span class="font-bold text-slate-800">Required Action:</span><br>
                    Start your local dev server or service (e.g. <code class="font-mono bg-white px-1 py-0.5 rounded border border-slate-200">npm run dev</code> or <code class="font-mono bg-white px-1 py-0.5 rounded border border-slate-200">php artisan serve</code>) on this port, then re-verify.
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-1 flex flex-col gap-2">
            <button type="button" id="btn-recheck-port" onclick="recheckPortAndSubmit()" class="w-full py-2.5 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white text-xs font-bold transition shadow-sm cursor-pointer flex items-center justify-center gap-1.5">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                <span>Re-verify Port &amp; Add Tunnel</span>
            </button>
            <button type="button" onclick="closePortNotRunningModal()" class="w-full py-2 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 active:scale-[0.98] text-slate-700 text-xs font-bold transition shadow-2xs cursor-pointer flex items-center justify-center gap-1.5">
                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                <span>Change Port Number</span>
            </button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 13: TUNNEL CREATION LIMIT REACHED -->
<!-- ======================================================== -->
<div id="modal-tunnel-limit" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-150">
    <div class="bg-white border border-amber-300 rounded-3xl w-full max-w-md p-6 sm:p-7 relative shadow-2xl text-center space-y-5">
        <button type="button" onclick="closeModal('modal-tunnel-limit')" class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer" title="Close">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>

        <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-amber-100 to-orange-100 text-amber-700 border border-amber-300 flex items-center justify-center mx-auto shadow-inner">
            <i data-lucide="shield-alert" class="w-8 h-8 stroke-[2.2]"></i>
        </div>

        <div class="space-y-1.5">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-[11px] font-bold uppercase tracking-wider">
                <i data-lucide="lock" class="w-3 h-3"></i>
                <span>Tunnel Limit Reached</span>
            </span>
            <h3 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Tunnel Limit Reached</h3>
            <p class="text-xs text-slate-600 leading-relaxed max-w-sm mx-auto">
                You have reached the number of allowed tunnel creation. Upgrade to a pro plan to enjoy unlimited tunnels.
            </p>
        </div>

        <div class="p-4 rounded-2xl bg-amber-50/60 border border-amber-200/80 text-left space-y-2 text-xs">
            <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Your Current Plan:</span>
                <span id="modal-limit-plan-label" class="font-extrabold text-amber-900 uppercase"><span class="app-edition-label" data-app-edition>{{ (auth()->user() && auth()->user()->isPro()) ? 'Pro' : 'Lite' }}</span> (Max <span id="modal-limit-max">5</span> Tunnels)</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-500 font-medium">Active / Created:</span>
                <span id="modal-limit-used" class="font-bold text-slate-800">5 Tunnels</span>
            </div>
            <div class="pt-2 border-t border-amber-200/60 flex items-center gap-1.5 text-indigo-900 font-bold">
                <i data-lucide="sparkles" class="w-4 h-4 text-amber-600 shrink-0"></i>
                <span>Pro unlocks Unlimited Tunnels &amp; Custom Domains</span>
            </div>
        </div>

        <div class="space-y-2 pt-1">
            <button type="button" onclick="closeModal('modal-tunnel-limit'); openProPaymentCheckout(this);" class="w-full py-2.5 px-4 rounded-xl bg-[#100091] hover:bg-[#0b0068] active:scale-[0.98] text-white text-xs font-bold transition shadow-md shadow-[#100091]/25 cursor-pointer flex items-center justify-center gap-2">
                <i data-lucide="zap" class="w-3.5 h-3.5 fill-amber-400 text-amber-400"></i>
                <span>Upgrade to Pro Plan Now</span>
            </button>
            <button type="button" onclick="closeModal('modal-tunnel-limit')" class="w-full py-2 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                Maybe Later
            </button>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 14: BULK DELETE TUNNELS CONFIRMATION -->
<!-- ======================================================== -->
<div id="modal-bulk-delete-tunnels" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150">
    <div class="bg-white border border-slate-200 rounded-3xl w-full max-w-md p-6 sm:p-7 relative shadow-2xl text-center space-y-4">
        <button type="button" onclick="closeModal('modal-bulk-delete-tunnels')" class="absolute top-4 right-4 p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer" title="Close">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>

        <div class="w-14 h-14 rounded-2xl bg-rose-50 border border-rose-100 text-rose-500 flex items-center justify-center mx-auto shadow-2xs">
            <i data-lucide="trash-2" class="w-6 h-6 stroke-[2.2]"></i>
        </div>

        <div class="space-y-1.5">
            <h3 id="bulk-delete-title" class="text-xl font-extrabold text-slate-900 tracking-tight">Delete Selected Tunnels</h3>
            <p class="text-xs text-slate-500 leading-relaxed px-2">
                Are you sure you want to stop and delete <strong id="bulk-delete-count-label" class="text-slate-800">the selected tunnels</strong>? Any running tunnel processes will be stopped immediately before deletion.
            </p>
        </div>

        <!-- Scrollable list of tunnels to be deleted -->
        <div id="bulk-delete-names-list" class="max-h-48 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50/70 p-3 text-left space-y-1.5 text-xs divide-y divide-slate-200/60">
            <!-- Populated dynamically by JS -->
        </div>

        <div class="pt-2 flex items-center justify-center gap-3">
            <button type="button" onclick="closeModal('modal-bulk-delete-tunnels')" class="w-full py-2.5 px-4 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition cursor-pointer">
                Cancel
            </button>
            <button type="button" id="btn-confirm-bulk-delete" onclick="executeBulkDeleteTunnels()" class="w-full py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 active:scale-[0.98] text-white text-xs font-bold shadow-md shadow-rose-600/20 transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-1.5">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                <span id="btn-confirm-bulk-label">Stop &amp; Delete Tunnels</span>
            </button>
        </div>
    </div>
</div>





