/**
 * TunnelDeck Lite — Pure Vanilla JavaScript Client Application
 * Zero external JS dependencies • Native Browser Fetch API
 */

// Disable Right-Click Context Menu & Inspect Element shortcuts globally
(function() {
    'use strict';
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        e.stopPropagation();
        return false;
    }, true);

    document.addEventListener('keydown', function(e) {
        const key = e.key ? e.key.toUpperCase() : '';
        const keyCode = e.keyCode || e.which;
        const isCtrlOrMeta = e.ctrlKey || e.metaKey;

        // F12 (DevTools)
        if (key === 'F12' || keyCode === 123) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }

        // Ctrl + Shift + I/J/C (DevTools / Console / Inspect)
        if (isCtrlOrMeta && e.shiftKey && (key === 'I' || key === 'J' || key === 'C' || keyCode === 73 || keyCode === 74 || keyCode === 67)) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }

        // Ctrl + U (View Source)
        if (isCtrlOrMeta && (key === 'U' || keyCode === 85)) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }

        // Ctrl + S (Save Page)
        if (isCtrlOrMeta && (key === 'S' || keyCode === 83)) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }

        // Ctrl + R / Ctrl + Shift + R (Refresh / Reload Page)
        if (isCtrlOrMeta && (key === 'R' || keyCode === 82)) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }

        // F5 / Ctrl + F5 (Refresh Page)
        if (key === 'F5' || keyCode === 116) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }

        // Shift + F10 (Context Menu)
        if (e.shiftKey && (key === 'F10' || keyCode === 121)) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }, true);
})();

// Active State Cache
let activeTab = 'dashboard';
let isSidebarCollapsed = false;
let currentTunnelsData = [];
let currentDomainsData = [];
let pendingDeleteTunnelId = null;
let pendingDeleteDomainId = null;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Initialize icons
    if (window.lucide) window.lucide.createIcons();

    // Initialize onboarding input pre-fills and autosave
    initOnboardingPrefills();
    initOtpInputListeners();
    syncOnboardingExploreState();

    // Check localStorage for sidebar preference
    const savedCollapsed = localStorage.getItem('tunneldeck_sidebar_collapsed') === 'true';
    if (savedCollapsed) {
        toggleSidebar(true);
    }

    // Sync saved password to Settings current password field
    const savedPw = localStorage.getItem('tunneldeck_saved_password') || '123456';
    const settingsCurrentPw = document.getElementById('settings-current-password');
    if (settingsCurrentPw) settingsCurrentPw.value = savedPw;

    const autofillEnabled = localStorage.getItem('tunneldeck_autofill_password_enabled') !== 'false';
    const autofillCheck = document.getElementById('settings-autofill-toggle');
    if (autofillCheck) autofillCheck.checked = autofillEnabled;

    // Start background polling for live metrics & tunnels
    setInterval(refreshDashboardData, 8000);
});

/* ======================================================== */
/* NAVIGATION & TAB SWITCHING */
/* ======================================================== */

function switchTab(tab) {
    activeTab = tab;
    const tabs = ['dashboard', 'tunnels', 'domains', 'settings'];

    tabs.forEach(t => {
        const content = document.getElementById(`tab-content-${t}`);
        const btn = document.getElementById(`nav-btn-${t}`);
        
        if (t === tab) {
            content?.classList.remove('hidden');
            btn?.classList.remove('text-slate-600', 'hover:text-slate-900', 'hover:bg-slate-100/80');
            btn?.classList.add('text-white', 'bg-[#100091]', 'shadow-sm', 'shadow-[#100091]/20');
        } else {
            content?.classList.add('hidden');
            btn?.classList.remove('text-white', 'bg-[#100091]', 'shadow-sm', 'shadow-[#100091]/20');
            btn?.classList.add('text-slate-600', 'hover:text-slate-900', 'hover:bg-slate-100/80');
        }
    });

    // Update topbar title
    const titleMap = {
        'dashboard': 'Dashboard Overview',
        'tunnels': 'Forwarding Tunnels Manager',
        'domains': 'Custom Domains',
        'settings': 'Platform Settings'
    };
    const titleElem = document.getElementById('topbar-title');
    if (titleElem) titleElem.textContent = titleMap[tab] || 'Dashboard';

    if (window.lucide) window.lucide.createIcons();
}

function toggleSidebar(collapse) {
    isSidebarCollapsed = collapse;
    localStorage.setItem('tunneldeck_sidebar_collapsed', collapse ? 'true' : 'false');

    const sidebar = document.getElementById('sidebar');
    const headerExpanded = document.getElementById('sidebar-header-expanded');
    const headerCollapsed = document.getElementById('sidebar-header-collapsed');
    const labels = document.querySelectorAll('.nav-label');
    const userCard = document.getElementById('sidebar-user-card');

    if (collapse) {
        sidebar?.classList.remove('w-64');
        sidebar?.classList.add('w-20');
        headerExpanded?.classList.add('hidden');
        headerCollapsed?.classList.remove('hidden');
        labels.forEach(el => el.classList.add('hidden'));
        if (userCard) {
            userCard.classList.remove('flex-row', 'justify-between');
            userCard.classList.add('flex-col', 'justify-center', 'gap-2', 'p-1.5');
        }
    } else {
        sidebar?.classList.remove('w-20');
        sidebar?.classList.add('w-64');
        headerExpanded?.classList.remove('hidden');
        headerCollapsed?.classList.add('hidden');
        labels.forEach(el => el.classList.remove('hidden'));
        if (userCard) {
            userCard.classList.remove('flex-col', 'gap-2', 'p-1.5');
            userCard.classList.add('flex-row', 'justify-between', 'p-2');
        }
    }
}

/* ======================================================== */
/* MODAL HELPERS */
/* ======================================================== */

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    if (modalId === 'onboarding-modal') {
        syncOnboardingExploreState();
    }
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    if (window.lucide) window.lucide.createIcons();
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    if (modalId === 'modal-pro-welcome') {
        const successView = document.getElementById('pro-upgrade-success-view');
        if (successView && !successView.classList.contains('hidden')) {
            exploreProExperience();
            return;
        }
    }
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

// Close modals when pressing Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('[id^="modal-"]:not(.hidden)');
        modals.forEach(m => closeModal(m.id));
    }
});


/* ======================================================== */
/* CUSTOM VALIDATION & ERROR MODALS */
/* ======================================================== */

let currentConflictedInputId = null;

function showNameTakenModal(enteredName, inputId = 'add-tunnel-name', customMessage = null) {
    currentConflictedInputId = inputId;

    const nameEl = document.getElementById('name-taken-conflicted-name');
    const titleEl = document.getElementById('name-taken-modal-title');
    const badgeEl = document.getElementById('name-taken-modal-badge');
    const descEl = document.getElementById('name-taken-modal-desc');
    const suggestionEl = document.getElementById('name-taken-suggested-name');

    if (nameEl) nameEl.textContent = enteredName || 'Entered Name';
    if (titleEl) titleEl.textContent = customMessage || 'The name has already been taken.';
    if (badgeEl) badgeEl.textContent = 'Duplicate Name Detected';
    if (descEl) descEl.textContent = 'A tunnel with this name already exists in your workspace. Each tunnel requires a unique name so routing and status can be tracked without conflicts.';

    // Generate smart alternative name suggestion
    if (suggestionEl) {
        const cleanName = (enteredName || 'tunnel').trim();
        const match = cleanName.match(/^(.*?)(?:[\s\-_]+(\d+))?$/);
        const base = (match && match[1]) ? match[1].trim() : cleanName;
        const nextNum = (match && match[2]) ? (parseInt(match[2], 10) + 1) : 2;
        const separator = cleanName.includes('-') ? '-' : (cleanName.includes('_') ? '_' : ' ');
        suggestionEl.textContent = `"${base}${separator}${nextNum}"`;
    }

    openModal('modal-name-taken');
    if (window.lucide) window.lucide.createIcons();
}

function closeNameTakenModal(reopenAddModal = true) {
    closeModal('modal-name-taken');
    if (reopenAddModal && currentConflictedInputId === 'add-tunnel-name') {
        openModal('modal-add-tunnel');
    }
    if (currentConflictedInputId) {
        const input = document.getElementById(currentConflictedInputId);
        if (input) {
            setTimeout(() => {
                input.focus();
                input.select();
                input.classList.add('border-rose-500', 'ring-4', 'ring-rose-500/20');
                setTimeout(() => {
                    input.classList.remove('border-rose-500', 'ring-4', 'ring-rose-500/20');
                }, 2500);
            }, 100);
        }
    }
}

function applySuggestedTunnelName() {
    const suggestionEl = document.getElementById('name-taken-suggested-name');
    if (suggestionEl && currentConflictedInputId) {
        const cleanVal = suggestionEl.textContent.replace(/^"|"$/g, '').trim();
        const input = document.getElementById(currentConflictedInputId);
        if (input) {
            input.value = cleanVal;
        }
    }
    closeNameTakenModal(true);
}

// State for port re-verification flow
let currentPortCheckState = { port: null, name: null };

function showPortNotRunningModal(port, name, customMessage = null) {
    currentPortCheckState = { port, name };

    const targetEl = document.getElementById('port-not-running-target');
    const portNumEl = document.getElementById('port-not-running-number');
    const msgEl = document.getElementById('port-not-running-desc');
    const titleEl = document.getElementById('port-not-running-title');
    const recheckBtn = document.getElementById('btn-recheck-port');

    const exactMessage = customMessage || `Port ${port} could not be reached. The port should verify to be in running condition before adding a tunnel.`;

    if (portNumEl) portNumEl.textContent = port;
    if (targetEl) targetEl.textContent = `http://127.0.0.1:${port}`;
    if (msgEl) msgEl.textContent = exactMessage;
    if (titleEl) titleEl.textContent = `Port ${port} is not in running condition`;
    if (recheckBtn) {
        recheckBtn.disabled = false;
        recheckBtn.innerHTML = `<i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i><span>Re-verify Port ${port} &amp; Add Tunnel</span>`;
    }

    closeModal('modal-add-tunnel');
    openModal('modal-port-not-running');
    if (window.lucide) window.lucide.createIcons();
}

function closePortNotRunningModal(reopenAddModal = true) {
    closeModal('modal-port-not-running');
    if (reopenAddModal) {
        openModal('modal-add-tunnel');
        const portInput = document.getElementById('add-tunnel-port');
        if (portInput) {
            setTimeout(() => {
                portInput.focus();
                portInput.select();
                portInput.classList.add('border-amber-500', 'ring-4', 'ring-amber-500/20');
                setTimeout(() => {
                    portInput.classList.remove('border-amber-500', 'ring-4', 'ring-amber-500/20');
                }, 2500);
            }, 100);
        }
    }
}

async function recheckPortAndSubmit() {
    const { port, name } = currentPortCheckState;
    if (!port) return;

    const recheckBtn = document.getElementById('btn-recheck-port');
    if (recheckBtn) {
        recheckBtn.disabled = true;
        recheckBtn.innerHTML = `<span class="inline-block animate-spin mr-1">&#9696;</span><span>Verifying port ${port}...</span>`;
    }

    try {
        const checkRes = await fetch(`/api/tunnels/check-port/${port}`, {
            headers: { 'Accept': 'application/json' }
        });
        const checkData = await checkRes.json();

        if (checkData.running) {
            closeModal('modal-port-not-running');
            showToast(`Port ${port} verified in running condition! Creating tunnel...`, 'success');

            const createRes = await fetch('/api/tunnels', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({ name, port })
            });
            const createData = await createRes.json();
            if (createRes.ok && createData.status === 'success') {
                showToast(`Tunnel "${name}" created successfully!`, 'success');
                await refreshDashboardData();
                await fetchTunnelLimit();
            } else {
                showToast(createData.message || 'Failed to create tunnel.', 'error');
            }
        } else {
            if (recheckBtn) {
                recheckBtn.disabled = false;
                recheckBtn.innerHTML = `<i data-lucide="alert-circle" class="w-3.5 h-3.5 text-rose-300"></i><span>Still Inactive &bull; Click to Retry</span>`;
            }
            const msgEl = document.getElementById('port-not-running-desc');
            if (msgEl) {
                msgEl.textContent = `Port ${port} is still not in running condition. Ensure your application server is started, then try again.`;
            }
            if (window.lucide) window.lucide.createIcons();
        }
    } catch (e) {
        if (recheckBtn) {
            recheckBtn.disabled = false;
            recheckBtn.innerHTML = `<i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i><span>Re-verify Port ${port}</span>`;
        }
        showToast('Error checking port: ' + e.message, 'error');
    }
}

function showValidationErrorModal(field, message, value, inputId) {
    if (field === 'name' || (message && message.toLowerCase().includes('name has already been taken'))) {
        showNameTakenModal(value, inputId, message);
        return;
    }

    if (field === 'port' && message && (message.toLowerCase().includes('running condition') || message.toLowerCase().includes('not in running'))) {
        showPortNotRunningModal(value, '', message);
        return;
    }

    currentConflictedInputId = inputId;
    const nameEl = document.getElementById('name-taken-conflicted-name');
    const titleEl = document.getElementById('name-taken-modal-title');
    const badgeEl = document.getElementById('name-taken-modal-badge');
    const descEl = document.getElementById('name-taken-modal-desc');
    const suggestionEl = document.getElementById('name-taken-suggested-name');

    if (nameEl) nameEl.textContent = value || field;
    if (titleEl) titleEl.textContent = message || `The ${field} is invalid or in use.`;
    if (badgeEl) badgeEl.textContent = `${field.toUpperCase()} Conflict`;
    if (descEl) descEl.textContent = `The ${field} you provided could not be accepted. Please enter a different value.`;
    if (suggestionEl) {
        const nextPort = Number(value) ? Number(value) + 1 : 3001;
        suggestionEl.textContent = `${nextPort}`;
    }

    openModal('modal-name-taken');
    if (window.lucide) window.lucide.createIcons();
}

window.showNameTakenModal = showNameTakenModal;
window.closeNameTakenModal = closeNameTakenModal;
window.applySuggestedTunnelName = applySuggestedTunnelName;
window.showPortNotRunningModal = showPortNotRunningModal;
window.closePortNotRunningModal = closePortNotRunningModal;
window.recheckPortAndSubmit = recheckPortAndSubmit;
window.showValidationErrorModal = showValidationErrorModal;

/* ======================================================== */
/* CLIPBOARD COPY HELPER */
/* ======================================================== */

function copyToClipboard(text, btnElement) {
    navigator.clipboard.writeText(text).then(() => {
        if (btnElement) {
            const originalHTML = btnElement.innerHTML;
            btnElement.innerHTML = '<i data-lucide="check" class="w-4 h-4 text-emerald-600"></i>';
            if (window.lucide) window.lucide.createIcons();
            setTimeout(() => {
                btnElement.innerHTML = originalHTML;
                if (window.lucide) window.lucide.createIcons();
            }, 2000);
        }
    }).catch(err => {
        console.error('Failed to copy:', err);
    });
}

/* ======================================================== */
/* TUNNELS MANAGEMENT (AJAX) & LIMIT ENFORCEMENT */
/* ======================================================== */

let cachedTunnelLimit = null;

async function fetchTunnelLimit() {
    try {
        const res = await fetch('/api/tunnels/limit', {
            headers: { 'Accept': 'application/json' }
        });
        if (res.ok) {
            const body = await res.json();
            if (body && body.data) {
                cachedTunnelLimit = body.data;
                updateTunnelLimitUI(body.data);
                return body.data;
            }
        }
    } catch (e) {
        console.warn('Failed to fetch tunnel limit:', e);
    }
    return null;
}

function updateTunnelLimitUI(limit) {
    if (!limit) return;
    cachedTunnelLimit = limit;

    const banner = document.getElementById('tunnel-limit-banner');
    const counter = document.getElementById('tunnel-limit-counter');
    const modalMax = document.getElementById('modal-limit-max');
    const modalUsed = document.getElementById('modal-limit-used');

    if (modalMax) {
        modalMax.textContent = limit.is_unlimited ? 'Unlimited' : (limit.max_tunnels || 5);
    }
    if (modalUsed && typeof limit.total_created !== 'undefined') {
        modalUsed.textContent = `${limit.total_created} Tunnel${limit.total_created === 1 ? '' : 's'}`;
    }

    if (limit.package) {
        const pkg = limit.package.toLowerCase();
        const editionText = pkg === 'pro' ? 'Pro' : (pkg === 'enterprise' ? 'Enterprise' : 'Lite');
        document.querySelectorAll('.app-edition-label, [data-app-edition]').forEach(el => {
            el.textContent = editionText;
        });
        const brandBadge = document.getElementById('brand-edition-badge');
        if (brandBadge) {
            brandBadge.textContent = editionText;
            if (pkg === 'pro') {
                brandBadge.className = 'text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-300';
            } else if (pkg === 'enterprise') {
                brandBadge.className = 'text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-purple-100 text-purple-800 border border-purple-300';
            } else {
                brandBadge.className = 'text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-indigo-50 text-[#100091] border border-indigo-200/80';
            }
        }
    }

    const isReached = Boolean(limit.limit_reached);

    if (banner) {
        if (isReached) {
            banner.classList.remove('hidden');
            if (counter) {
                counter.textContent = `(${limit.total_created}/${limit.max_tunnels || 5} Allowed)`;
            }
        } else {
            banner.classList.add('hidden');
        }
    }

    // Disable both the Add Tunnel button and the New Tunnel button in the header
    const addBtn = document.getElementById('btn-open-add-tunnel');
    const headerBtn = document.getElementById('btn-header-new-tunnel');

    [addBtn, headerBtn].forEach(btn => {
        if (!btn) return;
        if (isReached) {
            btn.disabled = true;
            btn.classList.add('opacity-60', 'cursor-not-allowed', 'pointer-events-none', 'bg-slate-200', 'text-slate-400');
            btn.classList.remove('cursor-pointer', 'hover:bg-[#0b0068]', 'bg-[#100091]', 'text-white', 'shadow-md');
            btn.title = 'Tunnel creation limit reached. Upgrade to Pro for unlimited tunnels.';
        } else {
            btn.disabled = false;
            btn.classList.remove('opacity-60', 'cursor-not-allowed', 'pointer-events-none', 'bg-slate-200', 'text-slate-400');
            btn.classList.add('cursor-pointer', 'hover:bg-[#0b0068]', 'bg-[#100091]', 'text-white', 'shadow-md');
            btn.title = btn.id === 'btn-header-new-tunnel' ? 'New Tunnel' : 'Add New Tunnel';
        }
    });
}

function showTunnelLimitReachedModal(limit) {
    if (limit) updateTunnelLimitUI(limit);
    openModal('modal-tunnel-limit');
    if (window.lucide) window.lucide.createIcons();
}

window.fetchTunnelLimit = fetchTunnelLimit;
window.updateTunnelLimitUI = updateTunnelLimitUI;
window.showTunnelLimitReachedModal = showTunnelLimitReachedModal;

function openAddTunnelModal() {
    if (cachedTunnelLimit && cachedTunnelLimit.limit_reached) {
        showTunnelLimitReachedModal(cachedTunnelLimit);
        return;
    }
    const nameInput = document.getElementById('add-tunnel-name');
    const portInput = document.getElementById('add-tunnel-port');
    if (nameInput) nameInput.value = '';
    if (portInput) portInput.value = '';
    openModal('modal-add-tunnel');
}

async function handleAddTunnel(e) {
    e.preventDefault();
    if (cachedTunnelLimit && cachedTunnelLimit.limit_reached) {
        closeModal('modal-add-tunnel');
        showTunnelLimitReachedModal(cachedTunnelLimit);
        return;
    }
    const nameInput = document.getElementById('add-tunnel-name');
    const portInput = document.getElementById('add-tunnel-port');
    const submitBtn = document.getElementById('btn-submit-add-tunnel');

    const name = nameInput.value.trim();
    const port = Number(portInput.value);

    if (!name || !port) return;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>Adding...</span>';

    try {
        const res = await fetch('/api/tunnels', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({ name, port })
        });

        const data = await res.json();
        if (!res.ok || data.status === 'error') {
            if (res.status === 403 || data.error_code === 'TUNNEL_LIMIT_REACHED') {
                closeModal('modal-add-tunnel');
                const limitInfo = data.limit || {
                    max_tunnels: 5,
                    total_created: 5,
                    limit_reached: true,
                    is_unlimited: false
                };
                updateTunnelLimitUI(limitInfo);
                showTunnelLimitReachedModal(limitInfo);
                return;
            }

            const nameError = data.errors?.name?.[0];
            const portError = data.errors?.port?.[0];
            const msg = nameError || portError || data.message || 'Failed to create tunnel.';

            // GOLDEN RULE 1: Tunnel name should be unique
            if (data.error_code === 'TUNNEL_NAME_TAKEN' || nameError || msg.toLowerCase().includes('name has already been taken')) {
                closeModal('modal-add-tunnel');
                showNameTakenModal(name, 'add-tunnel-name', nameError || msg);
                return;
            }

            // GOLDEN RULE 2: The port should verify to be in running condition
            if (data.error_code === 'PORT_NOT_RUNNING' || (portError && (portError.toLowerCase().includes('running condition') || portError.toLowerCase().includes('not in running')))) {
                closeModal('modal-add-tunnel');
                showPortNotRunningModal(port, name, portError || msg);
                return;
            }

            if (portError || msg.toLowerCase().includes('port has already been taken')) {
                closeModal('modal-add-tunnel');
                showValidationErrorModal('port', portError || msg, port, 'add-tunnel-port');
                return;
            }

            showToast(msg, 'error');
            return;
        }

        closeModal('modal-add-tunnel');
        showToast('Tunnel added successfully.', 'success');
        await refreshDashboardData();
        await fetchTunnelLimit();
    } catch (err) {
        showToast(err.message || 'Failed to create tunnel.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Add Tunnel</span>';
    }
}

// Start Tunnel Routing Modal Flow
let currentRoutingStrategy = 'direct';

function openStartRoutingModal(tunnelId) {
    resetRoutingModalState();
    const row = document.getElementById(`tunnel-row-${tunnelId}`);
    const portElem = row?.querySelector('[data-port]') || row?.querySelector('td:nth-child(3) span');
    let port = 3000;
    if (portElem) {
        const match = portElem.textContent.match(/:(\d+)/);
        if (match) port = match[1];
    }

    document.getElementById('routing-tunnel-id').value = tunnelId;
    document.getElementById('routing-tunnel-port').value = port;
    document.getElementById('routing-preview-port').textContent = port;

    // Reset fields to default
    const domainSelect = document.getElementById('routing-target-domain');
    if (domainSelect) domainSelect.value = 'default';
    selectRoutingStrategy('direct');

    const subInput = document.getElementById('routing-subdomain-input');
    if (subInput) subInput.value = '';

    handleRoutingDomainChange();
    openModal('modal-start-routing');
}

function handleRoutingDomainChange() {
    const domainSelect = document.getElementById('routing-target-domain');
    const strategyContainer = document.getElementById('routing-strategy-container');
    const selectedOpt = domainSelect?.selectedOptions[0];
    const val = domainSelect?.value;

    if (val === 'default' || !val) {
        strategyContainer?.classList.add('hidden');
        document.getElementById('routing-subdomain-input-container')?.classList.add('hidden');
    } else {
        strategyContainer?.classList.remove('hidden');
        const domainName = selectedOpt?.getAttribute('data-domain') || selectedOpt?.textContent || 'yourdomain.com';
        const apexLabel = document.getElementById('strategy-apex-domain-label');
        if (apexLabel) apexLabel.textContent = domainName;
        const suffixLabel = document.getElementById('routing-subdomain-suffix');
        if (suffixLabel) suffixLabel.textContent = '.' + domainName;

        if (currentRoutingStrategy === 'subdomain') {
            document.getElementById('routing-subdomain-input-container')?.classList.remove('hidden');
        }
    }

    updateRoutingPreview();
}

function selectRoutingStrategy(strategy) {
    currentRoutingStrategy = strategy;

    const cardDirect = document.getElementById('strategy-card-direct');
    const radioDirect = document.getElementById('strategy-radio-direct');
    const cardSub = document.getElementById('strategy-card-subdomain');
    const radioSub = document.getElementById('strategy-radio-subdomain');
    const subInputContainer = document.getElementById('routing-subdomain-input-container');

    if (strategy === 'direct') {
        cardDirect?.classList.add('border-2', 'border-blue-600', 'bg-white', 'ring-2', 'ring-blue-100/50', 'shadow-2xs');
        cardDirect?.classList.remove('border-slate-200');
        radioDirect?.classList.add('border-2', 'border-blue-600');
        radioDirect?.classList.remove('border-slate-300');
        radioDirect.innerHTML = '<div class="w-2 h-2 rounded-full bg-blue-600"></div>';

        cardSub?.classList.remove('border-2', 'border-blue-600', 'ring-2', 'ring-blue-100/50', 'shadow-2xs');
        cardSub?.classList.add('border-slate-200');
        radioSub?.classList.remove('border-2', 'border-blue-600');
        radioSub?.classList.add('border-slate-300');
        radioSub.innerHTML = '';

        subInputContainer?.classList.add('hidden');
    } else {
        cardSub?.classList.add('border-2', 'border-blue-600', 'bg-white', 'ring-2', 'ring-blue-100/50', 'shadow-2xs');
        cardSub?.classList.remove('border-slate-200');
        radioSub?.classList.add('border-2', 'border-blue-600');
        radioSub?.classList.remove('border-slate-300');
        radioSub.innerHTML = '<div class="w-2 h-2 rounded-full bg-blue-600"></div>';

        cardDirect?.classList.remove('border-2', 'border-blue-600', 'ring-2', 'ring-blue-100/50', 'shadow-2xs');
        cardDirect?.classList.add('border-slate-200');
        radioDirect?.classList.remove('border-2', 'border-blue-600');
        radioDirect?.classList.add('border-slate-300');
        radioDirect.innerHTML = '';

        subInputContainer?.classList.remove('hidden');
    }

    updateRoutingPreview();
}

function updateRoutingPreview() {
    const domainSelect = document.getElementById('routing-target-domain');
    const badge = document.getElementById('routing-preview-badge');
    const urlElem = document.getElementById('routing-preview-url');
    const selectedOpt = domainSelect?.selectedOptions[0];
    const val = domainSelect?.value;

    if (val === 'default' || !val) {
        if (badge) {
            badge.textContent = 'Quick Tunnel (trycloudflare)';
            badge.className = 'text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200/80 text-slate-600';
        }
        if (urlElem) urlElem.textContent = 'https://[unique-hash].trycloudflare.com';
    } else {
        const domainName = selectedOpt?.getAttribute('data-domain') || selectedOpt?.textContent || 'yourdomain.com';

        if (currentRoutingStrategy === 'direct') {
            if (badge) {
                badge.textContent = 'Root Domain (Direct)';
                badge.className = 'text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200';
            }
            if (urlElem) urlElem.textContent = `https://${domainName}`;
        } else {
            if (badge) {
                badge.textContent = 'Subdomain';
                badge.className = 'text-[10px] font-bold px-2 py-0.5 rounded bg-slate-200/80 text-slate-600';
            }
            const subInput = document.getElementById('routing-subdomain-input');
            const subVal = subInput ? subInput.value.trim().toLowerCase().replace(/[^a-z0-9-]/g, '') : '';
            if (subVal) {
                if (urlElem) urlElem.textContent = `https://${subVal}.${domainName}`;
            } else {
                if (urlElem) urlElem.textContent = `https://[random-friendly-name].${domainName}`;
            }
        }
    }
}

async function handleConfirmStartTunnel(e) {
    e.preventDefault();
    const tunnelId = document.getElementById('routing-tunnel-id').value;
    const domainSelect = document.getElementById('routing-target-domain');
    const submitBtn = document.getElementById('btn-submit-start-routing');

    const isCustom = domainSelect.value !== 'default';
    const customDomainId = isCustom ? Number(domainSelect.value) : null;
    const isRoot = isCustom ? currentRoutingStrategy === 'direct' : false;
    const subInput = document.getElementById('routing-subdomain-input');
    const customSubdomain = (isCustom && currentRoutingStrategy === 'subdomain') ? (subInput.value.trim() || null) : null;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div><span>Starting...</span>';

    try {
        const res = await fetch(`/api/tunnels/${tunnelId}/start`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({
                custom_domain_id: customDomainId,
                is_root_domain: isRoot,
                custom_subdomain: customSubdomain
            })
        });

        const data = await res.json();
        if (!res.ok || data.status === 'error') {
            throw new Error(data.message || 'Failed to start tunnel.');
        }

        // If it's a dummy exploration tunnel, run simulated tunneling steps in modal
        if (data.is_dummy) {
            await runDummyTunnelingSteps(data.tunnel, data.simulation_steps);
            closeModal('modal-start-routing');
            resetRoutingModalState();
            showToast('Demo tunnel started in simulation mode! [Preview URL active]', 'success');
            await refreshDashboardData();
            return;
        }

        closeModal('modal-start-routing');
        showToast('Tunnel started successfully.', 'success');
        await refreshDashboardData();
    } catch (err) {
        showToast(err.message || 'Failed to start tunnel.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i data-lucide="play" class="w-3.5 h-3.5 fill-white"></i><span>Start Tunnel</span>';
        if (window.lucide) window.lucide.createIcons();
    }
}

// Global Dummy Tunnels Auto-Stop Countdown Engine (60 Seconds)
let dummyTunnelTimers = {};
let dummyTimerInterval = null;

function formatTimerDisplay(totalSeconds) {
    const s = Math.max(0, parseInt(totalSeconds, 10) || 0);
    const mins = Math.floor(s / 60);
    const secs = s % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

function syncDummyTunnelTimers(tunnels) {
    if (!tunnels || !Array.isArray(tunnels)) return;

    tunnels.forEach(t => {
        if (t.is_dummy && t.status === 'running') {
            const rem = (typeof t.demo_remaining_seconds === 'number' && t.demo_remaining_seconds > 0)
                ? t.demo_remaining_seconds
                : (dummyTunnelTimers[t.id] ?? 60);
            dummyTunnelTimers[t.id] = rem;
        } else if (t.is_dummy) {
            delete dummyTunnelTimers[t.id];
        }
    });

    startDummyTimerTickLoop();
}

function startDummyTimerTickLoop() {
    if (dummyTimerInterval) return;

    dummyTimerInterval = setInterval(async () => {
        const tunnelIds = Object.keys(dummyTunnelTimers);
        if (tunnelIds.length === 0) {
            clearInterval(dummyTimerInterval);
            dummyTimerInterval = null;
            return;
        }

        for (const idStr of tunnelIds) {
            const id = parseInt(idStr, 10);
            if (typeof dummyTunnelTimers[id] === 'undefined') continue;

            dummyTunnelTimers[id]--;
            const remaining = dummyTunnelTimers[id];

            // Update UI elements in table and overview
            const formatted = formatTimerDisplay(remaining);
            const tableTimerEl = document.getElementById(`timer-dummy-${id}`);
            if (tableTimerEl) tableTimerEl.textContent = formatted;

            const overviewTimerEl = document.getElementById(`overview-timer-dummy-${id}`);
            if (overviewTimerEl) overviewTimerEl.textContent = formatted;

            // When countdown reaches 0, auto stop the dummy tunnel
            if (remaining <= 0) {
                delete dummyTunnelTimers[id];
                try {
                    await fetch(`/api/tunnels/${id}/stop`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken()
                        }
                    });
                    showToast(`Demo tunnel #${id} auto-stopped (60s simulation elapsed).`, 'success');
                    await refreshDashboardData();
                } catch (e) {
                    console.error('Failed to auto stop dummy tunnel:', e);
                }
            }
        }
    }, 1000);
}

function resetRoutingModalState() {
    const form = document.getElementById('form-start-routing');
    const steps = document.getElementById('routing-dummy-steps-container');
    const timerBanner = document.getElementById('sim-timer-banner');
    if (form) form.classList.remove('hidden');
    if (steps) steps.classList.add('hidden');
    if (timerBanner) timerBanner.classList.add('hidden');

    for (let i = 1; i <= 4; i++) {
        const stepRow = document.getElementById(`sim-step-${i}`);
        const icon = document.getElementById(`sim-step-icon-${i}`);
        const badge = document.getElementById(`sim-step-badge-${i}`);
        if (stepRow) stepRow.className = 'flex items-center justify-between text-xs py-1 transition-all duration-300 opacity-40';
        if (icon) {
            icon.className = 'w-7 h-7 rounded-xl bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs';
            icon.textContent = String(i);
        }
        if (badge) {
            badge.className = 'text-[11px] font-bold text-slate-400';
            badge.textContent = 'Waiting...';
        }
    }
}

async function runDummyTunnelingSteps(tunnel, stepsData) {
    const form = document.getElementById('form-start-routing');
    const stepsContainer = document.getElementById('routing-dummy-steps-container');
    const timerBanner = document.getElementById('sim-timer-banner');
    if (form) form.classList.add('hidden');
    if (stepsContainer) {
        stepsContainer.classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
    }

    const portEl = document.getElementById('sim-step-port');
    if (portEl && tunnel) portEl.textContent = tunnel.port;
    const urlEl = document.getElementById('sim-step-url');
    if (urlEl && tunnel) urlEl.textContent = tunnel.public_url || `https://${tunnel.name}.trycloudflare.com`;

    const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

    const stepConfigs = [
        { 
            row: 'sim-step-1', icon: 'sim-step-icon-1', badge: 'sim-step-badge-1', 
            pendingLabel: 'Verifying Port...', 
            doneLabel: '✓ Verified (Simulated)',
            duration: 850
        },
        { 
            row: 'sim-step-2', icon: 'sim-step-icon-2', badge: 'sim-step-badge-2', 
            pendingLabel: 'Connecting Edge...', 
            doneLabel: '✓ Edge Connected',
            duration: 900
        },
        { 
            row: 'sim-step-3', icon: 'sim-step-icon-3', badge: 'sim-step-badge-3', 
            pendingLabel: 'Issuing TLS...', 
            doneLabel: '✓ TLS Active',
            duration: 900
        },
        { 
            row: 'sim-step-4', icon: 'sim-step-icon-4', badge: 'sim-step-badge-4', 
            pendingLabel: 'Routing URL...', 
            doneLabel: '✓ TryCloudflare Ready',
            duration: 850
        },
    ];

    for (let i = 0; i < stepConfigs.length; i++) {
        const cfg = stepConfigs[i];
        const row = document.getElementById(cfg.row);
        const icon = document.getElementById(cfg.icon);
        const badge = document.getElementById(cfg.badge);

        if (row) row.classList.remove('opacity-40');
        if (icon) {
            icon.className = 'w-7 h-7 rounded-xl bg-indigo-100 text-[#100091] flex items-center justify-center font-bold text-xs shrink-0 shadow-xs border border-indigo-200';
            icon.innerHTML = '<div class="w-3.5 h-3.5 border-2 border-[#100091] border-t-transparent rounded-full animate-spin"></div>';
        }
        if (badge) {
            badge.className = 'text-[11px] font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full border border-indigo-200 animate-pulse';
            badge.textContent = cfg.pendingLabel;
        }

        await sleep(cfg.duration);

        if (icon) {
            icon.className = 'w-7 h-7 rounded-xl bg-emerald-500 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-xs';
            icon.innerHTML = '✓';
        }
        if (badge) {
            badge.className = 'text-[11px] font-extrabold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200';
            badge.textContent = cfg.doneLabel;
        }
    }

    // Show 60s auto-stop timer banner inside modal
    if (timerBanner) {
        timerBanner.classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
    }

    // Initialize timer for 60s
    if (tunnel && tunnel.id) {
        dummyTunnelTimers[tunnel.id] = 60;
        startDummyTimerTickLoop();
    }

    await sleep(1200);
}

async function stopTunnel(tunnelId) {
    if (dummyTunnelTimers[tunnelId]) {
        delete dummyTunnelTimers[tunnelId];
    }
    try {
        const res = await fetch(`/api/tunnels/${tunnelId}/stop`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            }
        });

        const data = await res.json();
        if (!res.ok || data.status === 'error') {
            throw new Error(data.message || 'Failed to stop tunnel.');
        }

        showToast('Tunnel stopped successfully.', 'success');
        await refreshDashboardData();
    } catch (err) {
        showToast(err.message || 'Failed to stop tunnel.', 'error');
    }
}

// Edit Tunnel Flow
function openEditTunnelModal(id, name, port) {
    document.getElementById('edit-tunnel-id').value = id;
    document.getElementById('edit-tunnel-name').value = name;
    document.getElementById('edit-tunnel-port').value = port;
    openModal('modal-edit-tunnel');
}

async function handleSaveEditTunnel(e) {
    e.preventDefault();
    const id = document.getElementById('edit-tunnel-id').value;
    const name = document.getElementById('edit-tunnel-name').value.trim();
    const port = Number(document.getElementById('edit-tunnel-port').value);
    const submitBtn = document.getElementById('btn-submit-edit-tunnel');

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>Saving...</span>';

    try {
        const res = await fetch(`/api/tunnels/${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({ name, port })
        });

        const data = await res.json();
        if (!res.ok || data.status === 'error') {
            const nameError = data.errors?.name?.[0];
            const portError = data.errors?.port?.[0];
            const msg = nameError || portError || data.message || 'Failed to update tunnel.';

            // GOLDEN RULE 1: Tunnel name should be unique
            if (data.error_code === 'TUNNEL_NAME_TAKEN' || nameError || msg.toLowerCase().includes('name has already been taken')) {
                closeModal('modal-edit-tunnel');
                showNameTakenModal(name, 'edit-tunnel-name', nameError || msg);
                return;
            }

            // GOLDEN RULE 2: Port should verify to be in running condition
            if (data.error_code === 'PORT_NOT_RUNNING' || (portError && (portError.toLowerCase().includes('running condition') || portError.toLowerCase().includes('not in running')))) {
                closeModal('modal-edit-tunnel');
                showPortNotRunningModal(port, name, portError || msg);
                return;
            }

            if (portError || msg.toLowerCase().includes('port has already been taken')) {
                closeModal('modal-edit-tunnel');
                showValidationErrorModal('port', portError || msg, port, 'edit-tunnel-port');
                return;
            }

            showToast(msg, 'error');
            return;
        }

        closeModal('modal-edit-tunnel');
        showToast('Tunnel updated successfully.', 'success');
        await refreshDashboardData();
    } catch (err) {
        showToast(err.message || 'Failed to update tunnel.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Save Changes</span>';
    }
}

// Delete Tunnel Flow
function openDeleteTunnelModal(id, name, port) {
    pendingDeleteTunnelId = id;
    document.getElementById('delete-tunnel-name-label').textContent = name;
    document.getElementById('delete-tunnel-port-label').textContent = port;
    openModal('modal-delete-tunnel');
}

async function executeDeleteTunnel() {
    if (!pendingDeleteTunnelId) return;
    const btn = document.getElementById('btn-confirm-delete-tunnel');
    btn.disabled = true;
    btn.innerHTML = '<span>Deleting...</span>';

    try {
        const res = await fetch(`/api/tunnels/${pendingDeleteTunnelId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            }
        });

        const data = await res.json();
        if (!res.ok || data.status === 'error') {
            throw new Error(data.message || 'Failed to delete tunnel.');
        }

        selectedTunnelIds.delete(pendingDeleteTunnelId);
        updateSelectAllCheckboxState();
        updateBulkActionBar();

        closeModal('modal-delete-tunnel');
        showToast('Tunnel deleted successfully.', 'success');
        await refreshDashboardData();
        await fetchTunnelLimit();
    } catch (err) {
        showToast(err.message || 'Failed to delete tunnel.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="trash-2" class="w-3.5 h-3.5"></i><span>Delete Tunnel</span>';
        if (window.lucide) window.lucide.createIcons();
    }
}

/* ======================================================== */
/* BULK TUNNELS SELECTION & DELETION */
/* ======================================================== */
const selectedTunnelIds = new Set();
let cachedTunnelsList = [];
let currentTunnelsPage = 1;
const tunnelsPerPage = 20;

function escapeHtml(str) {
    if (!str && str !== 0) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function handleTunnelCheckboxChange(id, checkbox) {
    const numId = parseInt(id, 10);
    if (checkbox.checked) {
        selectedTunnelIds.add(numId);
    } else {
        selectedTunnelIds.delete(numId);
    }
    applyRowHighlight(numId, checkbox.checked);
    updateSelectAllCheckboxState();
    updateBulkActionBar();
}

function applyRowHighlight(id, isSelected) {
    const row = document.getElementById(`tunnel-row-${id}`);
    if (!row) return;
    if (isSelected) {
        row.classList.add('bg-indigo-50/70', 'border-[#100091]');
        row.classList.remove('border-transparent');
    } else {
        row.classList.remove('bg-indigo-50/70', 'border-[#100091]');
        row.classList.add('border-transparent');
    }
}

function toggleSelectAllTunnels(isChecked) {
    const checkboxes = document.querySelectorAll('.tunnel-row-checkbox');
    checkboxes.forEach(cb => {
        const id = parseInt(cb.value, 10);
        cb.checked = isChecked;
        if (isChecked) {
            selectedTunnelIds.add(id);
        } else {
            selectedTunnelIds.delete(id);
        }
        applyRowHighlight(id, isChecked);
    });
    updateBulkActionBar();
}

function updateSelectAllCheckboxState() {
    const master = document.getElementById('check-all-tunnels');
    if (!master) return;
    const checkboxes = document.querySelectorAll('.tunnel-row-checkbox');
    if (checkboxes.length === 0) {
        master.checked = false;
        master.indeterminate = false;
        return;
    }
    const checkedCount = document.querySelectorAll('.tunnel-row-checkbox:checked').length;
    master.checked = checkedCount === checkboxes.length;
    master.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
}

function updateBulkActionBar() {
    const bar = document.getElementById('bulk-tunnels-action-bar');
    const countEl = document.getElementById('bulk-selected-count');
    const labelEl = document.getElementById('btn-bulk-delete-label');
    const count = selectedTunnelIds.size;

    if (!bar) return;

    if (count > 0) {
        bar.classList.remove('hidden');
        if (countEl) countEl.textContent = count;
        if (labelEl) {
            labelEl.textContent = count === 1 ? 'Delete 1 Tunnel' : `Delete ${count} Tunnels`;
        }
    } else {
        bar.classList.add('hidden');
    }
}

function deselectAllTunnels() {
    selectedTunnelIds.clear();
    const master = document.getElementById('check-all-tunnels');
    if (master) {
        master.checked = false;
        master.indeterminate = false;
    }
    document.querySelectorAll('.tunnel-row-checkbox').forEach(cb => {
        cb.checked = false;
        const id = parseInt(cb.value, 10);
        applyRowHighlight(id, false);
    });
    updateBulkActionBar();
}

function openBulkDeleteModal() {
    const count = selectedTunnelIds.size;
    if (count === 0) return;

    const titleEl = document.getElementById('bulk-delete-title');
    const countLabelEl = document.getElementById('bulk-delete-count-label');
    const listEl = document.getElementById('bulk-delete-names-list');
    const confirmLabelEl = document.getElementById('btn-confirm-bulk-label');

    if (titleEl) {
        titleEl.textContent = count === 1 ? 'Delete 1 Tunnel' : `Delete ${count} Tunnels`;
    }
    if (countLabelEl) {
        countLabelEl.textContent = count === 1 ? '1 selected tunnel' : `${count} selected tunnels`;
    }
    if (confirmLabelEl) {
        confirmLabelEl.textContent = count === 1 ? 'Stop & Delete 1 Tunnel' : `Stop & Delete ${count} Tunnels`;
    }

    if (listEl) {
        let itemsHtml = '';
        selectedTunnelIds.forEach(id => {
            const cb = document.querySelector(`.tunnel-row-checkbox[value="${id}"]`);
            let name = `Tunnel #${id}`;
            let port = '';
            let status = 'stopped';

            if (cb) {
                name = cb.getAttribute('data-name') || name;
                port = cb.getAttribute('data-port') || '';
                status = cb.getAttribute('data-status') || 'stopped';
            } else if (cachedTunnelsList && cachedTunnelsList.length > 0) {
                const found = cachedTunnelsList.find(t => t.id === id);
                if (found) {
                    name = found.name;
                    port = found.port;
                    status = found.status;
                }
            }

            const isRunning = status === 'running';
            itemsHtml += `
                <div class="flex items-center justify-between py-1.5 px-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full ${isRunning ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'}"></span>
                        <span class="font-bold text-slate-800">${escapeHtml(name)}</span>
                        ${port ? `<span class="font-mono text-[11px] text-slate-500 bg-white border border-slate-200 px-1.5 py-0.5 rounded">: ${escapeHtml(String(port))}</span>` : ''}
                    </div>
                    <span class="text-[10px] uppercase font-bold ${isRunning ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : 'text-slate-500 bg-slate-100'} px-2 py-0.5 rounded-full">
                        ${isRunning ? 'Will Stop &amp; Delete' : 'Will Delete'}
                    </span>
                </div>
            `;
        });
        listEl.innerHTML = itemsHtml;
    }

    openModal('modal-bulk-delete-tunnels');
    if (window.lucide) window.lucide.createIcons();
}

async function executeBulkDeleteTunnels() {
    const ids = Array.from(selectedTunnelIds);
    if (ids.length === 0) return;

    const btn = document.getElementById('btn-confirm-bulk-delete');
    const labelEl = document.getElementById('btn-confirm-bulk-label');
    if (btn) btn.disabled = true;
    if (labelEl) labelEl.textContent = 'Stopping & Deleting...';

    try {
        const res = await fetch('/api/tunnels/bulk-delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({ ids: ids })
        });

        const data = await res.json();
        if (!res.ok || data.status === 'error') {
            throw new Error(data.message || 'Failed to bulk delete tunnels.');
        }

        closeModal('modal-bulk-delete-tunnels');
        showToast(data.message || `Successfully stopped and deleted ${ids.length} tunnels.`, 'success');
        deselectAllTunnels();
        await refreshDashboardData();
        await fetchTunnelLimit();
    } catch (err) {
        showToast(err.message || 'Bulk delete failed.', 'error');
    } finally {
        if (btn) btn.disabled = false;
        if (labelEl) labelEl.textContent = 'Stop & Delete Tunnels';
        if (window.lucide) window.lucide.createIcons();
    }
}

/* ======================================================== */
/* SETTINGS (PASSWORD & AUTOFILL) */
/* ======================================================== */

function toggleInputVisibility(inputId, eyeIconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(eyeIconId);
    if (!input) return;

    const isPw = input.type === 'password';
    input.type = isPw ? 'text' : 'password';
    if (icon) {
        icon.setAttribute('data-lucide', isPw ? 'eye-off' : 'eye');
        if (window.lucide) window.lucide.createIcons();
    }
}

function isSimulationModeActive() {
    if (typeof window.IS_SIMULATION_MODE !== 'undefined' && window.IS_SIMULATION_MODE) {
        return true;
    }
    const settingsBanner = document.getElementById('settings-demo-banner');
    if (settingsBanner && !settingsBanner.classList.contains('hidden')) {
        return true;
    }
    const dummyBanner = document.getElementById('dummy-tunnels-banner');
    if (dummyBanner && !dummyBanner.classList.contains('hidden')) {
        return true;
    }
    return false;
}

function updateSettingsSimulationMode(isActive) {
    window.IS_SIMULATION_MODE = !!isActive;

    const banner = document.getElementById('settings-demo-banner');
    if (banner) {
        if (isActive) banner.classList.remove('hidden');
        else banner.classList.add('hidden');
    }

    const lockedBadge = document.getElementById('settings-locked-badge');
    if (lockedBadge) {
        if (isActive) lockedBadge.classList.remove('hidden');
        else lockedBadge.classList.add('hidden');
    }

    const notice = document.getElementById('settings-password-locked-notice');
    if (notice) {
        if (isActive) notice.classList.remove('hidden');
        else notice.classList.add('hidden');
    }

    const bottomNotice = document.getElementById('settings-disabled-bottom-notice');
    if (bottomNotice) {
        if (isActive) bottomNotice.classList.remove('hidden');
        else bottomNotice.classList.add('hidden');
    }

    const newPw = document.getElementById('settings-new-password');
    const confirmPw = document.getElementById('settings-confirm-password');
    const autofillToggle = document.getElementById('settings-autofill-toggle');
    const saveBtn = document.getElementById('save-password-btn');

    if (newPw) newPw.disabled = isActive;
    if (confirmPw) confirmPw.disabled = isActive;
    if (autofillToggle) autofillToggle.disabled = isActive;

    if (saveBtn) {
        saveBtn.disabled = isActive;
        if (isActive) {
            saveBtn.innerHTML = '<i data-lucide="lock" class="w-4 h-4"></i><span id="save-password-btn-text">Locked in Simulation Mode</span>';
        } else {
            saveBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i><span id="save-password-btn-text">Save Password</span>';
        }
        if (window.lucide) window.lucide.createIcons();
    }
}

function updateSidebarCardsOnboardingState(isOnboarded) {
    window.IS_ONBOARDED = !!isOnboarded;
    const proCard = document.getElementById('sidebar-pro-card');
    const coffeeCard = document.getElementById('sidebar-coffee-card');
    const proBtn = document.getElementById('btn-sidebar-upgrade-pro');
    const coffeeBtn = document.getElementById('btn-sidebar-donate-coffee');
    const coffeeInputs = document.querySelectorAll('#sidebar-coffee-card input, #sidebar-coffee-card .coffee-amount-btn');

    if (isOnboarded) {
        if (proCard) proCard.classList.remove('opacity-60');
        if (coffeeCard) coffeeCard.classList.remove('opacity-60');

        coffeeInputs.forEach(el => {
            el.disabled = false;
            el.classList.remove('cursor-not-allowed', 'opacity-50');
            if (el.classList.contains('coffee-amount-btn')) el.classList.add('cursor-pointer');
        });

        if (proBtn) {
            proBtn.onclick = function() { openProPaymentCheckout(this); };
            proBtn.title = 'Upgrade to Pro';
            proBtn.innerHTML = '<i data-lucide="zap" class="w-3.5 h-3.5 fill-current"></i><span id="sidebar-pro-btn-text">Upgrade to Pro Now</span>';
        }
        if (coffeeBtn) {
            coffeeBtn.onclick = function() { openCoffeePaymentCheckout(this); };
            coffeeBtn.title = 'Donate Coffee';
            coffeeBtn.innerHTML = '<i data-lucide="heart" class="w-3.5 h-3.5 fill-current"></i><span id="sidebar-coffee-btn-text">Donate Coffee</span>';
        }
    } else {
        if (proCard) proCard.classList.add('opacity-60');
        if (coffeeCard) coffeeCard.classList.add('opacity-60');

        coffeeInputs.forEach(el => {
            el.disabled = true;
            el.classList.add('cursor-not-allowed', 'opacity-50');
            el.classList.remove('cursor-pointer');
        });

        if (proBtn) {
            proBtn.onclick = function() { openModal('onboarding-modal'); };
            proBtn.title = 'Complete onboarding first to upgrade';
            proBtn.innerHTML = '<i data-lucide="sparkles" class="w-3.5 h-3.5 text-amber-900"></i><span id="sidebar-pro-btn-text">Complete Onboarding</span>';
        }
        if (coffeeBtn) {
            coffeeBtn.onclick = function() { openModal('onboarding-modal'); };
            coffeeBtn.title = 'Complete onboarding first to donate';
            coffeeBtn.innerHTML = '<i data-lucide="sparkles" class="w-3.5 h-3.5"></i><span id="sidebar-coffee-btn-text">Complete Onboarding</span>';
        }
    }
    if (window.lucide) window.lucide.createIcons();
}

async function handleSaveSettingsPassword(e) {
    e.preventDefault();

    if (isSimulationModeActive()) {
        showToast('Platform settings cannot be changed in simulation mode. Please complete onboarding first.', 'error');
        return;
    }

    const currentPw = document.getElementById('settings-current-password').value;
    const newPw = document.getElementById('settings-new-password').value;
    const confirmPw = document.getElementById('settings-confirm-password').value;
    const autofillToggle = document.getElementById('settings-autofill-toggle').checked;
    const btn = document.getElementById('save-password-btn');

    if (newPw.length < 6) {
        showToast('New password must be at least 6 characters long.', 'error');
        return;
    }

    if (newPw !== confirmPw) {
        showToast('New password and confirmation do not match.', 'error');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<div class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div><span>Saving...</span>';

    try {
        const res = await fetch('/user/password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({
                current_password: currentPw,
                new_password: newPw
            })
        });

        const data = await res.json();
        if (!res.ok || data.status === 'error') {
            throw new Error(data.message || 'Failed to update password.');
        }

        // Save preferences
        localStorage.setItem('tunneldeck_saved_password', newPw);
        localStorage.setItem('tunneldeck_autofill_password_enabled', autofillToggle ? 'true' : 'false');
        document.getElementById('settings-current-password').value = newPw;
        document.getElementById('settings-new-password').value = '';
        document.getElementById('settings-confirm-password').value = '';

        showToast('Password updated successfully! Preferences saved.', 'success');
    } catch (err) {
        showToast(err.message || 'Failed to update password.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="save" class="w-4 h-4"></i><span>Save Password</span>';
        if (window.lucide) window.lucide.createIcons();
    }
}

/* ======================================================== */
/* LIVE REFRESH & AUTO-SYNC */
/* ======================================================== */

async function refreshDashboardData() {
    const refreshIcon = document.getElementById('refresh-icon');
    if (refreshIcon) refreshIcon.classList.add('animate-spin');

    try {
        const hasDomains = !!document.getElementById('domains-table-body');
        const [tunnelsRes, domainsRes, statsRes] = await Promise.all([
            fetch('/api/tunnels', { headers: { 'Accept': 'application/json' } }),
            hasDomains ? fetch('/api/domains', { headers: { 'Accept': 'application/json' } }) : Promise.resolve(null),
            fetch('/api/stats', { headers: { 'Accept': 'application/json' } })
        ]);

        if (tunnelsRes && tunnelsRes.ok) {
            const tData = await tunnelsRes.json();
            const tunnels = tData.data?.tunnels || [];
            const metrics = tData.data?.metrics;

            if (metrics) {
                const total = document.getElementById('metric-total');
                const active = document.getElementById('metric-active');
                const paused = document.getElementById('metric-paused');
                const requests = document.getElementById('metric-requests');
                if (total) total.textContent = metrics.total;
                if (active) active.textContent = metrics.active;
                if (paused) paused.textContent = metrics.paused;
                if (requests) requests.textContent = metrics.total_requests;
            }

            if (tData.data?.limit) {
                updateTunnelLimitUI(tData.data.limit);
            }

            renderTunnelsTableRows(tunnels);
            renderDashboardOverviewTunnels(tunnels);
        }

        if (domainsRes && domainsRes.ok && typeof renderDomainsTableRows === 'function') {
            const dData = await domainsRes.json();
            const domains = dData.domains || [];
            renderDomainsTableRows(domains);
        }
    } catch (err) {
        console.error('Auto-refresh error:', err);
    } finally {
        if (refreshIcon) refreshIcon.classList.remove('animate-spin');
    }
}

function renderTunnelsTableRows(tunnels) {
    const tbody = document.getElementById('tunnels-table-body');
    if (!tbody) return;

    // Toggle dummy exploration banner and settings locked state
    const banner = document.getElementById('dummy-tunnels-banner');
    const hasDummy = !!(tunnels && tunnels.some(t => t.is_dummy));
    if (banner) {
        if (hasDummy) {
            banner.classList.remove('hidden');
        } else {
            banner.classList.add('hidden');
        }
    }
    updateSettingsSimulationMode(hasDummy);

    cachedTunnelsList = tunnels || [];

    // Remove any selected IDs that no longer exist
    const validIds = new Set(cachedTunnelsList.map(t => t.id));
    for (const id of selectedTunnelIds) {
        if (!validIds.has(id)) {
            selectedTunnelIds.delete(id);
        }
    }

    if (tunnels.length === 0) {
        const startEl = document.getElementById('tunnels-pagination-start');
        const endEl = document.getElementById('tunnels-pagination-end');
        const totalEl = document.getElementById('tunnels-pagination-total');
        if (startEl) startEl.textContent = '0';
        if (endEl) endEl.textContent = '0';
        if (totalEl) totalEl.textContent = '0';
        renderTunnelsPaginationControls(1, 1);

        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="py-16 text-center text-slate-500 text-sm">
                    <p class="font-medium text-slate-700">No tunnels registered yet.</p>
                    <p class="text-xs text-slate-400 mt-1">Create your first forwarding tunnel to expose local ports.</p>
                    <button type="button" onclick="openAddTunnelModal()" class="mt-4 px-4 py-2 rounded-xl bg-[#100091] text-white text-xs font-semibold hover:bg-[#0b0068] shadow-sm transition cursor-pointer">
                        + Add New Tunnel
                    </button>
                </td>
            </tr>
        `;
        updateSelectAllCheckboxState();
        updateBulkActionBar();
        return;
    }

    const total = cachedTunnelsList.length;
    const totalPages = Math.max(1, Math.ceil(total / tunnelsPerPage));
    if (currentTunnelsPage > totalPages) {
        currentTunnelsPage = totalPages;
    }
    if (currentTunnelsPage < 1) {
        currentTunnelsPage = 1;
    }

    const startIndex = (currentTunnelsPage - 1) * tunnelsPerPage;
    const endIndex = Math.min(startIndex + tunnelsPerPage, total);
    const startDisplay = total === 0 ? 0 : startIndex + 1;

    const startEl = document.getElementById('tunnels-pagination-start');
    const endEl = document.getElementById('tunnels-pagination-end');
    const totalEl = document.getElementById('tunnels-pagination-total');
    if (startEl) startEl.textContent = startDisplay;
    if (endEl) endEl.textContent = endIndex;
    if (totalEl) totalEl.textContent = total;

    renderTunnelsPaginationControls(totalPages, currentTunnelsPage);

    const pageItems = cachedTunnelsList.slice(startIndex, endIndex);

    tbody.innerHTML = pageItems.map(t => {
        const isRunning = t.status === 'running';
        const isStarting = t.status === 'starting';
        const isChecked = selectedTunnelIds.has(t.id);
        const highlightClasses = isChecked ? 'bg-indigo-50/70 border-[#100091]' : 'border-transparent';

        const dummyTimerHtml = (t.is_dummy && isRunning)
            ? `<div class="mt-1.5 flex items-center gap-1.5 text-[11px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200/90 w-fit">
                <i data-lucide="timer" class="w-3 h-3 text-amber-600 animate-pulse"></i>
                <span>Auto stop tunnel in <span class="font-mono text-amber-900" id="timer-dummy-${t.id}">${formatTimerDisplay(t.demo_remaining_seconds ?? 60)}</span></span>
               </div>`
            : '';

        const statusBadge = isRunning
            ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Running</span>
               </span>${dummyTimerHtml}`
            : isStarting
            ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-ping"></span>
                <span>Starting...</span>
               </span>`
            : `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100/90 text-slate-600 border border-slate-200">
                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                <span>Stopped</span>
               </span>`;

        const publicUrlHtml = isRunning && t.public_url
            ? `<div class="inline-flex items-center gap-2">
                <a href="${t.public_url}" target="_blank" rel="noreferrer" class="text-xs font-mono text-[#100091] hover:underline flex items-center gap-1 font-medium max-w-xs truncate">
                    <span>${t.public_url}</span>
                    <i data-lucide="external-link" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                </a>
                <button type="button" onclick="copyToClipboard('${t.public_url}', this)" class="p-1 text-slate-400 hover:text-slate-600 transition cursor-pointer shrink-0" title="Copy Public URL">
                    <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                </button>
               </div>`
            : `<span class="text-xs text-slate-500 font-normal">Not Running</span>`;

        const actionBtn = isRunning
            ? `<button type="button" onclick="stopTunnel(${t.id})" class="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold flex items-center gap-1.5 shadow-2xs transition cursor-pointer" title="Stop Tunnel">
                <i data-lucide="pause" class="w-3 h-3 fill-current"></i>
                <span>Stop</span>
               </button>`
            : `<button type="button" onclick="openStartRoutingModal(${t.id})" class="px-3.5 py-1.5 rounded-lg bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold flex items-center gap-1.5 shadow-2xs transition cursor-pointer" title="Start Tunnel">
                <i data-lucide="play" class="w-3 h-3 fill-current"></i>
                <span>Start</span>
               </button>`;

        const createdDate = t.created_at ? new Date(t.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '31 Oct 2026';
        const dummyBadge = t.is_dummy
            ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-300 shadow-2xs shrink-0">
                <i data-lucide="sparkles" class="w-3 h-3 text-amber-600"></i>
                <span>Demo Data</span>
               </span>`
            : '';

        return `
            <tr class="hover:bg-slate-50/80 transition group border-l-4 ${highlightClasses}" id="tunnel-row-${t.id}">
                <td class="py-4 pl-6 pr-2 w-12 text-left whitespace-nowrap">
                    <input type="checkbox"
                           class="tunnel-row-checkbox w-4 h-4 rounded border-slate-300 text-[#100091] focus:ring-2 focus:ring-[#100091]/30 cursor-pointer accent-[#100091]"
                           value="${t.id}"
                           data-name="${escapeHtml(t.name)}"
                           data-port="${t.port}"
                           data-status="${t.status}"
                           ${isChecked ? 'checked' : ''}
                           onchange="handleTunnelCheckboxChange(${t.id}, this)">
                </td>
                <td class="py-4 px-6 whitespace-nowrap">${statusBadge}</td>
                <td class="py-4 px-6 font-bold text-slate-900 text-sm whitespace-nowrap">
                    <div class="flex items-center gap-2">
                        <span>${escapeHtml(t.name)}</span>
                        ${dummyBadge}
                    </div>
                </td>
                <td class="py-4 px-6 whitespace-nowrap">
                    <div class="inline-flex items-center gap-2">
                        <span class="px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-xs font-mono text-slate-700">http://localhost:${t.port}</span>
                        <button type="button" onclick="copyToClipboard('http://localhost:${t.port}', this)" class="p-1 text-slate-400 hover:text-slate-600 transition cursor-pointer" title="Copy Local URL">
                            <i data-lucide="clipboard" class="w-4 h-4"></i>
                        </button>
                    </div>
                </td>
                <td class="py-4 px-6 whitespace-nowrap">${publicUrlHtml}</td>
                <td class="py-4 px-6 text-xs text-slate-600 whitespace-nowrap">${createdDate}</td>
                <td class="py-4 px-6 text-right whitespace-nowrap">
                    <div class="inline-flex items-center justify-end gap-2">
                        ${actionBtn}
                        <button type="button" onclick="openEditTunnelModal(${t.id}, '${escapeHtml(t.name).replace(/'/g, "\\'")}', ${t.port})" class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500 hover:text-slate-700 transition cursor-pointer" title="Edit Tunnel">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </button>
                        <button type="button" onclick="openDeleteTunnelModal(${t.id}, '${escapeHtml(t.name).replace(/'/g, "\\'")}', ${t.port})" class="p-1.5 rounded-lg border border-slate-200 hover:bg-rose-50 text-rose-500 hover:text-rose-600 transition cursor-pointer" title="Delete Tunnel">
                            <i data-lucide="trash-2" class="w-4 h-4 stroke-[1.8]"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    updateSelectAllCheckboxState();
    updateBulkActionBar();
    syncDummyTunnelTimers(pageItems);

    if (window.lucide) window.lucide.createIcons();
}

function renderTunnelsPaginationControls(totalPages, currentPage) {
    const container = document.getElementById('tunnels-pagination-controls');
    if (!container) return;

    totalPages = Math.max(1, totalPages || 1);
    currentPage = Math.max(1, Math.min(currentPage || 1, totalPages));

    let html = '';

    // Prev Button
    const prevDisabled = currentPage === 1;
    html += `
        <button type="button"
                onclick="goToTunnelsPage(${currentPage - 1})"
                ${prevDisabled ? 'disabled' : ''}
                class="px-2.5 py-1.5 rounded-lg border text-xs font-semibold transition flex items-center gap-1 ${prevDisabled ? 'border-slate-200 text-slate-300 cursor-not-allowed bg-slate-50' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 shadow-2xs cursor-pointer'}">
            <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i>
            <span>Prev</span>
        </button>
    `;

    // Calculate pages to show
    const pages = [];
    if (totalPages <= 7) {
        for (let i = 1; i <= totalPages; i++) pages.push(i);
    } else {
        if (currentPage <= 4) {
            pages.push(1, 2, 3, 4, 5, '...', totalPages);
        } else if (currentPage >= totalPages - 3) {
            pages.push(1, '...', totalPages - 4, totalPages - 3, totalPages - 2, totalPages - 1, totalPages);
        } else {
            pages.push(1, '...', currentPage - 1, currentPage, currentPage + 1, '...', totalPages);
        }
    }

    pages.forEach(p => {
        if (p === '...') {
            html += `<span class="px-2 py-1 text-xs text-slate-400 font-bold select-none">&hellip;</span>`;
        } else if (p === currentPage) {
            html += `
                <button type="button"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-[#100091] text-white shadow-2xs cursor-default">
                    ${p}
                </button>
            `;
        } else {
            html += `
                <button type="button"
                        onclick="goToTunnelsPage(${p})"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-2xs transition cursor-pointer">
                    ${p}
                </button>
            `;
        }
    });

    // Next Button
    const nextDisabled = currentPage === totalPages;
    html += `
        <button type="button"
                onclick="goToTunnelsPage(${currentPage + 1})"
                ${nextDisabled ? 'disabled' : ''}
                class="px-2.5 py-1.5 rounded-lg border text-xs font-semibold transition cursor-pointer flex items-center gap-1 ${nextDisabled ? 'border-slate-200 text-slate-300 cursor-not-allowed bg-slate-50' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 shadow-2xs'}">
            <span>Next</span>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        </button>
    `;

    container.innerHTML = html;
    if (window.lucide) window.lucide.createIcons();
}

function goToTunnelsPage(page) {
    if (page < 1) return;
    currentTunnelsPage = page;
    renderTunnelsTableRows(cachedTunnelsList);
}
window.goToTunnelsPage = goToTunnelsPage;

function renderDashboardOverviewTunnels(tunnels) {
    const container = document.getElementById('dashboard-overview-tunnels');
    if (!container) return;

    const topTunnels = (tunnels || []).slice(0, 5);
    if (topTunnels.length === 0) {
        container.innerHTML = `
            <div class="py-12 text-center text-slate-500 text-sm flex flex-col items-center justify-center">
                <i data-lucide="server" class="w-8 h-8 text-slate-300 mb-2"></i>
                <p class="font-medium text-slate-600">No tunnels registered yet.</p>
                <button type="button" onclick="openAddTunnelModal()" class="mt-2 text-[#100091] font-semibold hover:underline text-xs">
                    + Create your first tunnel
                </button>
            </div>
        `;
        if (window.lucide) window.lucide.createIcons();
        return;
    }

    container.innerHTML = topTunnels.map(t => {
        const isRunning = t.status === 'running';
        const statusBadge = isRunning
            ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Running
               </span>`
            : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200 shrink-0">
                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                Stopped
               </span>`;

        const publicUrlHtml = isRunning && t.public_url
            ? `<a href="${escapeHtml(t.public_url)}" target="_blank" rel="noopener noreferrer" class="font-mono text-[#100091] bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded truncate max-w-[180px] sm:max-w-[220px] text-[11px] transition">
                ${escapeHtml(t.public_url)}
               </a>`
            : '';

        const actionBtn = isRunning
            ? `<button type="button" onclick="stopTunnel(${t.id})" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold border bg-rose-50 hover:bg-rose-100 text-rose-700 border-rose-200 transition cursor-pointer">
                Stop
               </button>`
            : `<button type="button" onclick="openStartRoutingModal(${t.id})" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold border bg-[#15803d] hover:bg-[#166534] text-white transition cursor-pointer shadow-2xs">
                Start
               </button>`;

        const dummyBadge = t.is_dummy
            ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-300 shadow-2xs shrink-0">
                <i data-lucide="sparkles" class="w-3 h-3 text-amber-600"></i>
                <span>Demo Data</span>
               </span>`
            : '';

        const dummyTimerBadge = (t.is_dummy && isRunning)
            ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 shrink-0">
                <i data-lucide="timer" class="w-2.5 h-2.5 text-amber-600 animate-pulse"></i>
                <span>Auto stop in <span class="font-mono text-amber-900" id="overview-timer-dummy-${t.id}">${formatTimerDisplay(t.demo_remaining_seconds ?? 60)}</span></span>
               </span>`
            : '';

        return `
            <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/70 transition">
                <div class="space-y-1 min-w-0 pr-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-slate-900 text-sm truncate max-w-[160px] sm:max-w-none">${escapeHtml(t.name)}</span>
                        ${dummyBadge}
                        ${statusBadge}
                        ${dummyTimerBadge}
                    </div>
                    <div class="flex items-center gap-2 text-xs flex-wrap">
                        <span class="font-mono text-slate-600 bg-slate-100 px-2 py-0.5 rounded text-[11px]">Port ${escapeHtml(String(t.port))}</span>
                        ${publicUrlHtml}
                    </div>
                </div>
                <div class="shrink-0">
                    ${actionBtn}
                </div>
            </div>
        `;
    }).join('');

    if (window.lucide) window.lucide.createIcons();
}

/* ======================================================== */
/* MULTI-STEP ONBOARDING WIZARD */
/* ======================================================== */

function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag && metaTag.content) {
        return metaTag.content;
    }
    const inputTag = document.querySelector('input[name="_token"]');
    if (inputTag && inputTag.value) {
        return inputTag.value;
    }
    if (typeof CSRF_TOKEN !== 'undefined' && CSRF_TOKEN) {
        return CSRF_TOKEN;
    }
    return '';
}

function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPassword = input.getAttribute('type') === 'password';
    input.setAttribute('type', isPassword ? 'text' : 'password');
    const btn = input.parentElement?.querySelector('button');
    const icon = btn?.querySelector('i[data-lucide]');
    if (icon) {
        icon.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
        if (window.lucide) window.lucide.createIcons();
    }
}

let currentOnboardingStep = 1;
let otpCountdownTimer = null;

function goToOnboardingStep(step) {
    const nameInput = document.getElementById('onb-name');
    const emailInput = document.getElementById('onb-email');
    const nameError = document.getElementById('onb-name-error');
    const emailError = document.getElementById('onb-email-error');

    // Validation when advancing from step 1
    if (step > 1 && currentOnboardingStep === 1) {
        let hasError = false;
        if (!nameInput || !nameInput.value.trim()) {
            if (nameError) {
                nameError.textContent = 'Please enter your full name.';
                nameError.classList.remove('hidden');
            }
            nameInput?.classList.add('border-rose-500');
            hasError = true;
        } else {
            if (nameError) nameError.classList.add('hidden');
            nameInput?.classList.remove('border-rose-500');
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailInput || !emailInput.value.trim() || !emailRegex.test(emailInput.value.trim())) {
            if (emailError) {
                emailError.textContent = 'Please enter a valid email address.';
                emailError.classList.remove('hidden');
            }
            emailInput?.classList.add('border-rose-500');
            hasError = true;
        } else {
            if (emailError) emailError.classList.add('hidden');
            emailInput?.classList.remove('border-rose-500');
        }

        if (hasError) return;
    }

    currentOnboardingStep = step;

    // Show/hide step panels (1 to 4)
    for (let i = 1; i <= 4; i++) {
        const stepEl = document.getElementById(`onboarding-step-${i}`);
        if (stepEl) {
            if (i === step) {
                stepEl.classList.remove('hidden');
            } else {
                stepEl.classList.add('hidden');
            }
        }

        const dot = document.getElementById(`step-dot-${i}`);
        const label = dot?.nextElementSibling;
        if (dot) {
            if (i < step) {
                // Completed
                dot.className = 'w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-sm transition-all duration-200';
                dot.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i>';
                if (label) {
                    label.className = 'text-[10px] font-bold text-emerald-700 mt-1';
                }
            } else if (i === step) {
                // Active
                dot.className = 'w-8 h-8 rounded-full bg-[#100091] text-white flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-sm transition-all duration-200';
                dot.innerHTML = `<span>${i}</span>`;
                if (label) {
                    label.className = 'text-[10px] font-bold text-slate-800 mt-1';
                }
            } else {
                // Upcoming
                dot.className = 'w-8 h-8 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-xs font-bold ring-4 ring-white shadow-sm transition-all duration-200';
                dot.innerHTML = `<span>${i}</span>`;
                if (label) {
                    label.className = 'text-[10px] font-bold text-slate-400 mt-1';
                }
            }
        }
    }

    // Update progress bar
    const bar = document.getElementById('onboarding-progress-bar');
    if (bar) {
        if (step === 1) bar.style.width = '0%';
        if (step === 2) bar.style.width = '33%';
        if (step === 3) bar.style.width = '66%';
        if (step === 4) bar.style.width = '100%';
    }

    const counter = document.getElementById('onboarding-step-counter');
    if (counter) {
        counter.textContent = `Step ${step} of 4`;
    }

    // When navigating to Step 4, initialize OTP input bindings and focus first digit
    if (step === 4) {
        const display = document.getElementById('onb-verify-email-display');
        const emailVal = emailInput ? emailInput.value.trim() : '';
        if (display && emailVal) {
            display.textContent = emailVal;
        }
        initOtpInputListeners();
        setTimeout(() => {
            const firstDigit = document.getElementById('otp-digit-1');
            firstDigit?.focus();
            firstDigit?.select();
        }, 150);
    }

    if (window.lucide) window.lucide.createIcons();
}

/**
 * Handles Step 3 password validation and triggers sending the 4-digit verification code.
 */
async function submitStep3AndSendOtp() {
    const passwordInput = document.getElementById('onb-password');
    const passwordConfInput = document.getElementById('onb-password-confirmation');
    const passwordError = document.getElementById('onb-password-error');
    const emailInput = document.getElementById('onb-email');
    const nameInput = document.getElementById('onb-name');

    const passwordVal = passwordInput ? passwordInput.value : '';
    const passwordConfVal = passwordConfInput ? passwordConfInput.value : '';

    if (passwordVal.length < 6) {
        if (passwordError) {
            passwordError.textContent = 'Password must be at least 6 characters.';
            passwordError.classList.remove('hidden');
        }
        passwordInput?.classList.add('border-rose-500');
        return;
    }

    if (passwordVal !== passwordConfVal) {
        if (passwordError) {
            passwordError.textContent = 'Passwords do not match.';
            passwordError.classList.remove('hidden');
        }
        passwordConfInput?.classList.add('border-rose-500');
        return;
    }

    if (passwordError) passwordError.classList.add('hidden');
    passwordInput?.classList.remove('border-rose-500');
    passwordConfInput?.classList.remove('border-rose-500');

    const emailVal = emailInput ? emailInput.value.trim() : '';
    const nameVal = nameInput ? nameInput.value.trim() : '';

    if (!emailVal) {
        showToast('Please enter your email address in Step 1.', 'error');
        goToOnboardingStep(1);
        return;
    }

    const btn = document.getElementById('onb-to-verify-btn');
    const spinner = document.getElementById('onb-step3-spinner');
    const icon = document.getElementById('onb-step3-btn-icon');
    const text = document.getElementById('onb-step3-btn-text');

    if (btn) btn.disabled = true;
    if (spinner) spinner.classList.remove('hidden');
    if (icon) icon.classList.add('hidden');
    if (text) text.textContent = 'Sending Verification Code...';

    const resetButton = () => {
        if (btn) btn.disabled = false;
        if (spinner) spinner.classList.add('hidden');
        if (icon) icon.classList.remove('hidden');
        if (text) text.textContent = 'Continue & Send Verification Code';
    };

    try {
        const token = getCsrfToken();
        const res = await fetch('/onboarding/send-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({
                email: emailVal,
                name: nameVal,
            }),
        });

        const data = await res.json();
        resetButton();

        if (res.ok && data.success) {
            showToast(data.message || `Verification code sent to ${emailVal}`, 'success');
            goToOnboardingStep(4);
            startOtpCountdown(45);
        } else {
            const msg = data.message || 'Could not send verification code. Please check your email and try again.';
            if (passwordError) {
                passwordError.textContent = msg;
                passwordError.classList.remove('hidden');
            }
            showToast(msg, 'error');
        }
    } catch (err) {
        console.error('Send OTP error:', err);
        resetButton();
        showToast('Network error while dispatching verification email.', 'error');
    }
}

/**
 * Resends the 4-digit verification code.
 */
async function resendOnboardingOtp(btnElement) {
    const emailInput = document.getElementById('onb-email');
    const nameInput = document.getElementById('onb-name');
    const emailVal = emailInput ? emailInput.value.trim() : '';
    const nameVal = nameInput ? nameInput.value.trim() : '';

    if (!emailVal) {
        showToast('Please specify a valid email.', 'error');
        return;
    }

    if (btnElement) {
        btnElement.disabled = true;
        btnElement.classList.add('opacity-50');
        btnElement.textContent = 'Sending...';
    }

    try {
        const token = getCsrfToken();
        const res = await fetch('/onboarding/send-otp', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({
                email: emailVal,
                name: nameVal,
            }),
        });

        const data = await res.json();
        if (res.ok && data.success) {
            showToast('New verification code sent! Please check your inbox.', 'success');
            clearOtpBoxes();
            startOtpCountdown(45);
        } else {
            showToast(data.message || 'Failed to resend code.', 'error');
            if (btnElement) {
                btnElement.disabled = false;
                btnElement.classList.remove('opacity-50');
                btnElement.textContent = 'Resend Code';
            }
        }
    } catch (err) {
        console.error('Resend OTP error:', err);
        showToast('Network error while resending verification code.', 'error');
        if (btnElement) {
            btnElement.disabled = false;
            btnElement.classList.remove('opacity-50');
            btnElement.textContent = 'Resend Code';
        }
    }
}

function clearOtpBoxes() {
    for (let i = 1; i <= 4; i++) {
        const box = document.getElementById(`otp-digit-${i}`);
        if (box) {
            box.value = '';
            box.classList.remove('border-rose-500', 'ring-2', 'ring-rose-500/20');
        }
    }
    const hiddenInput = document.getElementById('onb-otp-code');
    if (hiddenInput) hiddenInput.value = '';
    const errEl = document.getElementById('onb-otp-error');
    if (errEl) errEl.classList.add('hidden');
    document.getElementById('otp-digit-1')?.focus();
}

function startOtpCountdown(seconds = 45) {
    if (otpCountdownTimer) clearInterval(otpCountdownTimer);

    const timerText = document.getElementById('onb-resend-timer-text');
    const countdownEl = document.getElementById('onb-countdown');
    const resendBtn = document.getElementById('onb-resend-btn');

    if (!timerText || !countdownEl || !resendBtn) return;

    timerText.classList.remove('hidden');
    resendBtn.classList.add('hidden');
    countdownEl.textContent = seconds;

    otpCountdownTimer = setInterval(() => {
        seconds--;
        if (seconds <= 0) {
            clearInterval(otpCountdownTimer);
            timerText.classList.add('hidden');
            resendBtn.classList.remove('hidden');
            resendBtn.disabled = false;
            resendBtn.classList.remove('opacity-50');
            resendBtn.textContent = 'Resend Code';
        } else {
            countdownEl.textContent = seconds;
        }
    }, 1000);
}

let otpListenersInitialized = false;
function initOtpInputListeners() {
    if (otpListenersInitialized) return;
    const container = document.getElementById('otp-inputs-container');
    if (!container) return;

    otpListenersInitialized = true;
    const boxes = [
        document.getElementById('otp-digit-1'),
        document.getElementById('otp-digit-2'),
        document.getElementById('otp-digit-3'),
        document.getElementById('otp-digit-4'),
    ].filter(Boolean);

    boxes.forEach((box, index) => {
        // Typing handler
        box.addEventListener('input', (e) => {
            const val = box.value.replace(/\D/g, '');
            box.value = val ? val[0] : '';

            // Clear errors
            box.classList.remove('border-rose-500', 'ring-2', 'ring-rose-500/20');
            document.getElementById('onb-otp-error')?.classList.add('hidden');

            if (box.value && index < boxes.length - 1) {
                boxes[index + 1].focus();
                boxes[index + 1].select();
            }

            syncOtpValue(boxes);
        });

        // Keydown (backspace & navigation)
        box.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !box.value && index > 0) {
                boxes[index - 1].focus();
                boxes[index - 1].select();
            }
        });

        // Paste handler
        box.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text').trim();
            const digits = pasted.replace(/\D/g, '').slice(0, 4);
            if (!digits) return;

            digits.split('').forEach((d, i) => {
                if (boxes[i]) {
                    boxes[i].value = d;
                    boxes[i].classList.remove('border-rose-500', 'ring-2', 'ring-rose-500/20');
                }
            });

            const nextIndex = Math.min(digits.length, boxes.length - 1);
            boxes[nextIndex].focus();
            syncOtpValue(boxes);

            if (digits.length === 4) {
                document.getElementById('onb-submit-btn')?.focus();
            }
        });
    });
}

function syncOtpValue(boxes) {
    const code = boxes.map(b => b.value.trim()).join('');
    const hidden = document.getElementById('onb-otp-code');
    if (hidden) hidden.value = code;
    return code;
}

function isUserInExploringMode() {
    return !!(window.IS_EXPLORING || window.IS_SIMULATION_MODE || (localStorage.getItem('tunneldeck_is_exploring') === 'true'));
}

function syncOnboardingExploreState(forceExploring = null) {
    const isExploring = (forceExploring !== null) ? !!forceExploring : isUserInExploringMode();
    const modal = document.getElementById('onboarding-modal');
    if (!modal) return;

    // 1. Reset all explore buttons inside onboarding modal
    const exploreButtons = modal.querySelectorAll('button[onclick*="triggerExplorePlatform"], button[id^="onb-btn-explore"]');
    exploreButtons.forEach(btn => {
        btn.disabled = false;
        // Remove any spinning loader svg
        const spinSvg = btn.querySelector('svg.animate-spin');
        if (spinSvg) spinSvg.remove();
    });

    // 2. Update text on all explore buttons / text spans
    const textSpans = modal.querySelectorAll('.onb-explore-text');
    textSpans.forEach(span => {
        span.textContent = isExploring ? 'Keep Exploring' : (span.getAttribute('data-default-text') || 'Explore');
    });

    // Clean up any button that had innerHTML overwritten by "Entering exploration..."
    exploreButtons.forEach(btn => {
        if (btn.innerHTML.includes('Entering exploration') || btn.innerHTML.includes('animate-spin')) {
            if (btn.id === 'onb-btn-explore-callout') {
                btn.innerHTML = `<span class="onb-explore-text" data-default-text="Explore &rarr;">${isExploring ? 'Keep Exploring' : 'Explore &rarr;'}</span>`;
            } else if (btn.id === 'onb-btn-explore-step1') {
                btn.innerHTML = `<i data-lucide="compass" class="w-3.5 h-3.5"></i><span class="onb-explore-text" data-default-text="Explore the platform &rarr;">${isExploring ? 'Keep Exploring' : 'Explore the platform &rarr;'}</span>`;
            } else if (btn.id === 'onb-btn-explore-step2') {
                btn.innerHTML = `<span class="onb-explore-text" data-default-text="Explore with email">${isExploring ? 'Keep Exploring' : 'Explore with email'}</span>`;
            } else if (btn.id === 'onb-btn-explore-footer') {
                btn.innerHTML = `<span class="onb-explore-text" data-default-text="Explore the platform">${isExploring ? 'Keep Exploring' : 'Explore the platform'}</span><i data-lucide="chevron-right" class="w-3 h-3"></i>`;
            } else {
                btn.innerHTML = `<span class="onb-explore-text">${isExploring ? 'Keep Exploring' : 'Explore'}</span>`;
            }
        }
    });

    // 3. Update callout headers
    const calloutTitle = document.getElementById('onb-explore-callout-title');
    const calloutDesc = document.getElementById('onb-explore-callout-desc');
    if (calloutTitle && calloutDesc) {
        if (isExploring) {
            calloutTitle.textContent = 'Currently Exploring TunnelDeck';
            calloutDesc.textContent = 'You are exploring the dashboard. You can continue exploring or finish setting up your account.';
        } else {
            calloutTitle.textContent = 'Just want to look around?';
            calloutDesc.textContent = 'Enter your email and explore the platform right away without a password.';
        }
    }

    if (window.lucide) window.lucide.createIcons();
}

async function triggerExplorePlatform(btnElement) {
    const isAlreadyExploring = isUserInExploringMode();

    // If user is already exploring, clicking "Keep Exploring" simply closes modal to let user continue exploring
    if (isAlreadyExploring) {
        closeOnboardingModalToExplore();
        syncOnboardingExploreState(true);
        return;
    }

    const emailInput = document.getElementById('onb-email');
    const emailError = document.getElementById('onb-email-error');
    const nameInput = document.getElementById('onb-name');
    const cityInput = document.getElementById('onb-city');
    const countryInput = document.getElementById('onb-country');

    const emailVal = emailInput ? emailInput.value.trim() : '';
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailVal || !emailRegex.test(emailVal)) {
        goToOnboardingStep(1);
        if (emailError) {
            emailError.textContent = 'Please enter your email to explore the platform.';
            emailError.classList.remove('hidden');
        }
        if (emailInput) {
            emailInput.classList.remove('border-slate-300');
            emailInput.classList.add('border-rose-500', 'ring-2', 'ring-rose-500/20');
            emailInput.focus();
            setTimeout(() => {
                emailInput?.classList.remove('ring-2', 'ring-rose-500/20');
            }, 2500);
        }
        showToast('Please enter your email to explore the platform.', 'error');
        return;
    }

    // Visual button feedback
    const originalContent = btnElement ? btnElement.innerHTML : null;
    if (btnElement) {
        btnElement.disabled = true;
        btnElement.innerHTML = '<svg class="animate-spin -ml-0.5 mr-1.5 h-3.5 w-3.5 inline-block text-current align-middle" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span>Entering exploration...</span>';
    }

    try {
        const token = getCsrfToken();
        const res = await fetch('/onboarding/explore', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({
                email: emailVal,
                name: nameInput?.value.trim() || 'Platform Explorer',
                city: cityInput?.value.trim() || null,
                country: countryInput?.value || null,
                screen_resolution: `${window.screen?.width || 0}x${window.screen?.height || 0}`,
                browser_timezone: (typeof Intl !== 'undefined' && Intl.DateTimeFormat) ? Intl.DateTimeFormat().resolvedOptions().timeZone : null,
            }),
        });

        const data = await res.json();
        if (res.ok && data.success) {
            window.IS_EXPLORING = true;
            window.IS_SIMULATION_MODE = true;
            localStorage.setItem('tunneldeck_is_exploring', 'true');

            // Cache exploring inputs in localStorage
            if (emailVal) localStorage.setItem('tunneldeck_onboarding_email', emailVal);
            if (nameInput?.value.trim() && !['Platform Explorer', 'TunnelDeck Admin'].includes(nameInput.value.trim())) {
                localStorage.setItem('tunneldeck_onboarding_name', nameInput.value.trim());
            }
            if (cityInput?.value.trim()) localStorage.setItem('tunneldeck_onboarding_city', cityInput.value.trim());
            if (countryInput?.value) localStorage.setItem('tunneldeck_onboarding_country', countryInput.value);

            // Re-enable and reset button to "Keep Exploring" immediately
            if (btnElement) {
                btnElement.disabled = false;
            }
            syncOnboardingExploreState(true);

            // Hide modal smoothly without destroying it
            const modal = document.getElementById('onboarding-modal');
            if (modal) {
                modal.classList.add('opacity-0', 'pointer-events-none');
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.classList.remove('opacity-0', 'pointer-events-none');
                }, 250);
            }

            // Reveal the full-width Continue Onboarding bar across the right column
            const bar = document.getElementById('continue-onboarding-bar');
            if (bar) {
                bar.classList.remove('hidden');
                if (window.lucide) window.lucide.createIcons();
            }

            if (data.user) {
                updateSidebarUserData(data.user);
            }

            updateSettingsSimulationMode(true);
            updateSidebarCardsOnboardingState(false);

            showToast(data.message || 'Welcome to TunnelDeck! Exploration mode active.', 'success');
        } else {
            if (btnElement && originalContent) {
                btnElement.disabled = false;
                btnElement.innerHTML = originalContent;
            }
            const errorMsg = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Failed to initialize exploration mode.');
            showToast(errorMsg, 'error');
        }
    } catch (err) {
        console.error('Explore platform error:', err);
        if (btnElement && originalContent) {
            btnElement.disabled = false;
            btnElement.innerHTML = originalContent;
        }
        showToast('Network error while connecting to server.', 'error');
    } finally {
        if (btnElement) {
            btnElement.disabled = false;
            const spinSvg = btnElement.querySelector('svg.animate-spin');
            if (spinSvg) spinSvg.remove();
        }
        syncOnboardingExploreState();
    }
}
}

async function handleOnboardingSubmit(event) {
    event.preventDefault();

    // If on Step 1, advance to Step 2
    if (currentOnboardingStep === 1) {
        goToOnboardingStep(2);
        return;
    }

    // If on Step 2, advance to Step 3
    if (currentOnboardingStep === 2) {
        goToOnboardingStep(3);
        return;
    }

    // If on Step 3, validate password and send OTP
    if (currentOnboardingStep === 3) {
        await submitStep3AndSendOtp();
        return;
    }

    // If on Step 4: Validate OTP and complete onboarding
    const nameInput = document.getElementById('onb-name');
    const emailInput = document.getElementById('onb-email');
    const cityInput = document.getElementById('onb-city');
    const countryInput = document.getElementById('onb-country');
    const passwordInput = document.getElementById('onb-password');
    const passwordConfInput = document.getElementById('onb-password-confirmation');
    const passwordVal = passwordInput ? passwordInput.value : '';
    const passwordConfVal = passwordConfInput ? passwordConfInput.value : '';

    const otpBoxes = [
        document.getElementById('otp-digit-1'),
        document.getElementById('otp-digit-2'),
        document.getElementById('otp-digit-3'),
        document.getElementById('otp-digit-4'),
    ].filter(Boolean);
    const otpCode = otpBoxes.map(b => b.value.trim()).join('');
    const otpError = document.getElementById('onb-otp-error');
    const submitBtn = document.getElementById('onb-submit-btn');
    const submitText = document.getElementById('onb-submit-text');

    if (otpCode.length !== 4) {
        if (otpError) {
            otpError.textContent = 'Please enter the complete 4-digit verification code sent to your email.';
            otpError.classList.remove('hidden');
        }
        otpBoxes.forEach(b => {
            if (!b.value) b.classList.add('border-rose-500', 'ring-2', 'ring-rose-500/20');
        });
        const firstEmpty = otpBoxes.find(b => !b.value) || otpBoxes[0];
        firstEmpty?.focus();
        return;
    }

    if (otpError) otpError.classList.add('hidden');
    otpBoxes.forEach(b => b.classList.remove('border-rose-500', 'ring-2', 'ring-rose-500/20'));

    // Loading state with spinner icon
    const spinner = document.getElementById('onb-submit-spinner');
    const submitIcon = document.getElementById('onb-submit-icon');
    if (submitBtn) submitBtn.disabled = true;
    if (spinner) spinner.classList.remove('hidden');
    if (submitIcon) submitIcon.classList.add('hidden');
    if (submitText) submitText.textContent = 'Verifying Code & Setting Up...';

    const resetSubmitBtn = () => {
        if (submitBtn) submitBtn.disabled = false;
        if (spinner) spinner.classList.add('hidden');
        if (submitIcon) submitIcon.classList.remove('hidden');
        if (submitText) submitText.textContent = 'Verify & Complete Setup';
    };

    try {
        const token = getCsrfToken();
        const res = await fetch('/onboarding/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({
                name: nameInput?.value.trim(),
                email: emailInput?.value.trim(),
                city: cityInput?.value.trim() || null,
                country: countryInput?.value || null,
                password: passwordVal,
                password_confirmation: passwordConfVal,
                otp: otpCode,
                screen_resolution: `${window.screen?.width || 0}x${window.screen?.height || 0}`,
                browser_timezone: (typeof Intl !== 'undefined' && Intl.DateTimeFormat) ? Intl.DateTimeFormat().resolvedOptions().timeZone : null,
            }),
        });

        const body = await res.json();
        if (res.ok && body.success) {
            // Save updated password in localStorage for login autofill
            if (passwordVal) {
                localStorage.setItem('tunneldeck_saved_password', passwordVal);
            }
            // Clear onboarding draft fields
            localStorage.removeItem('tunneldeck_onboarding_email');
            localStorage.removeItem('tunneldeck_onboarding_name');
            localStorage.removeItem('tunneldeck_onboarding_city');
            localStorage.removeItem('tunneldeck_onboarding_country');

            // Remove the continue onboarding bar
            const bar = document.getElementById('continue-onboarding-bar');
            if (bar) {
                bar.classList.add('hidden');
                bar.remove();
            }

            // Hide dummy tunnels banner and unlock settings
            const dummyBanner = document.getElementById('dummy-tunnels-banner');
            if (dummyBanner) {
                dummyBanner.classList.add('hidden');
            }
            updateSettingsSimulationMode(false);
            updateSidebarCardsOnboardingState(true);

            closeOnboardingModal(body.user);
            showToast(body.message || 'Email verified & onboarding completed! Workspace ready.', 'success');

            // Refresh live data: clean all dummy data from UI and make tunnels list blank
            await refreshDashboardData();
            await fetchTunnelLimit();
        } else {
            resetSubmitBtn();
            const msg = body.errors?.otp ? body.errors.otp[0] : (body.message || 'Verification failed. Please check the code.');
            if (otpError) {
                otpError.textContent = msg;
                otpError.classList.remove('hidden');
            }
            otpBoxes.forEach(b => b.classList.add('border-rose-500', 'ring-2', 'ring-rose-500/20'));
            showToast(msg, 'error');
        }
    } catch (err) {
        console.error('Onboarding submit error:', err);
        resetSubmitBtn();
        showToast('Network error during onboarding.', 'error');
    }
}

function resumeOnboarding() {
    const modal = document.getElementById('onboarding-modal');
    if (modal) {
        modal.classList.remove('hidden', 'opacity-0', 'pointer-events-none');
        syncOnboardingExploreState();
        if (window.lucide) window.lucide.createIcons();
    }
}

function closeOnboardingModalToExplore() {
    const modal = document.getElementById('onboarding-modal');
    if (modal) {
        modal.classList.add('opacity-0');
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('opacity-0');
            syncOnboardingExploreState(true);
        }, 200);
    }
    const bar = document.getElementById('continue-onboarding-bar');
    if (bar) {
        bar.classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
    }
}

function updateSidebarUserData(userData) {
    if (!userData) return;
    const userCard = document.getElementById('sidebar-user-card');
    if (userCard) {
        const nameEl = userCard.querySelector('p.text-xs.font-bold');
        if (nameEl && userData.name) nameEl.textContent = userData.name;

        const emailEl = userCard.querySelector('p.text-\\[10px\\]');
        if (emailEl && userData.email) emailEl.textContent = userData.email;

        const avatarEl = userCard.querySelector('.rounded-lg.bg-indigo-100');
        if (avatarEl && userData.initial) avatarEl.textContent = userData.initial;
    }

    if (userData.package) {
        updateLicenseUI(userData.package);
    }
}

function closeOnboardingModal(userData) {
    window.IS_EXPLORING = false;
    window.IS_SIMULATION_MODE = false;
    localStorage.removeItem('tunneldeck_is_exploring');
    const modal = document.getElementById('onboarding-modal');
    if (modal) {
        modal.classList.add('opacity-0');
        setTimeout(() => modal.remove(), 300);
    }

    const bar = document.getElementById('continue-onboarding-bar');
    if (bar) {
        bar.classList.add('hidden');
        bar.remove();
    }

    if (userData) {
        updateSidebarUserData(userData);
    }
}

function initOnboardingPrefills() {
    const emailInput = document.getElementById('onb-email');
    const nameInput = document.getElementById('onb-name');
    const cityInput = document.getElementById('onb-city');
    const countryInput = document.getElementById('onb-country');

    if (!emailInput && !nameInput) return;

    if (emailInput && !emailInput.value) {
        const saved = localStorage.getItem('tunneldeck_onboarding_email');
        if (saved) emailInput.value = saved;
    }
    if (nameInput && !nameInput.value) {
        const saved = localStorage.getItem('tunneldeck_onboarding_name');
        if (saved && !['TunnelDeck Admin', 'Platform Explorer'].includes(saved)) nameInput.value = saved;
    }
    if (cityInput && !cityInput.value) {
        const saved = localStorage.getItem('tunneldeck_onboarding_city');
        if (saved) cityInput.value = saved;
    }
    if (countryInput && (!countryInput.value || countryInput.value === 'India')) {
        const saved = localStorage.getItem('tunneldeck_onboarding_country');
        if (saved) countryInput.value = saved;
    }

    // Auto-save input changes as user types
    [emailInput, nameInput, cityInput, countryInput].forEach(el => {
        if (!el) return;
        el.addEventListener('input', () => {
            if (emailInput?.value?.trim()) localStorage.setItem('tunneldeck_onboarding_email', emailInput.value.trim());
            if (nameInput?.value?.trim() && !['TunnelDeck Admin', 'Platform Explorer'].includes(nameInput.value.trim())) {
                localStorage.setItem('tunneldeck_onboarding_name', nameInput.value.trim());
            }
            if (cityInput?.value?.trim()) localStorage.setItem('tunneldeck_onboarding_city', cityInput.value.trim());
            if (countryInput?.value) localStorage.setItem('tunneldeck_onboarding_country', countryInput.value);
        });
        el.addEventListener('change', () => {
            if (emailInput?.value?.trim()) localStorage.setItem('tunneldeck_onboarding_email', emailInput.value.trim());
            if (countryInput?.value) localStorage.setItem('tunneldeck_onboarding_country', countryInput.value);
        });
    });
}

/* ========================================================
   LICENSE MANAGEMENT & LIVE DIAGNOSTIC TESTING
   ======================================================== */

function updateLicenseUI(packageTier, currency, syncedAt, maxTunnels) {
    const tier = (packageTier || 'lite').toLowerCase();
    const tierUpper = tier.toUpperCase();

    // 1. Sidebar Badge
    const sidebarBadge = document.getElementById('sidebar-user-package');
    if (sidebarBadge) {
        sidebarBadge.textContent = tierUpper;
        if (tier === 'pro') {
            sidebarBadge.className = 'px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase bg-amber-100 text-amber-800 border border-amber-300';
        } else if (tier === 'enterprise') {
            sidebarBadge.className = 'px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase bg-purple-100 text-purple-800 border border-purple-300';
        } else {
            sidebarBadge.className = 'px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase bg-indigo-50 text-indigo-700 border border-indigo-200';
        }
    }

    // 2. Settings License Badge
    const settingsBadge = document.getElementById('settings-license-badge');
    if (settingsBadge) {
        settingsBadge.textContent = tierUpper;
        if (tier === 'pro') {
            settingsBadge.className = 'inline-flex px-2 py-0.5 rounded text-xs font-extrabold uppercase bg-amber-100 text-amber-800 border border-amber-300';
        } else if (tier === 'enterprise') {
            settingsBadge.className = 'inline-flex px-2 py-0.5 rounded text-xs font-extrabold uppercase bg-purple-100 text-purple-800 border border-purple-300';
        } else {
            settingsBadge.className = 'inline-flex px-2 py-0.5 rounded text-xs font-extrabold uppercase bg-indigo-50 text-indigo-700 border border-indigo-200';
        }
    }

    // 3. Max Tunnels Calculation
    const calculatedMax = maxTunnels || (tier === 'enterprise' ? 500 : (tier === 'pro' ? 50 : 10));
    const tunnelsLabel = document.getElementById('settings-license-tunnels');
    if (tunnelsLabel) {
        tunnelsLabel.textContent = `${calculatedMax} Concurrent`;
    }
    const maxTunnelsEngine = document.getElementById('settings-max-tunnels');
    if (maxTunnelsEngine) {
        maxTunnelsEngine.textContent = calculatedMax;
    }

    // 4. Currency
    if (currency) {
        const curEl = document.getElementById('settings-license-currency');
        if (curEl) curEl.textContent = currency;
    }

    // 5. Synced At
    if (syncedAt) {
        const syncEl = document.getElementById('settings-license-synced-at');
        if (syncEl) syncEl.textContent = 'Just now';
    }

    // 6. Sidebar Upgrade & Coffee Cards Suppression
    const proCard = document.getElementById('sidebar-pro-card');
    const coffeeCard = document.getElementById('sidebar-coffee-card');
    if (tier !== 'lite') {
        if (proCard) proCard.classList.add('hidden');
        if (coffeeCard) coffeeCard.classList.add('hidden');
    }
    checkAndUpdateCardsContainer();

    // 7. Top Brand Edition Badge
    const editionText = tier === 'pro' ? 'Pro' : (tier === 'enterprise' ? 'Enterprise' : 'Lite');
    const brandBadge = document.getElementById('brand-edition-badge');
    if (brandBadge) {
        brandBadge.textContent = editionText;
        if (tier === 'pro') {
            brandBadge.className = 'text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-300';
        } else if (tier === 'enterprise') {
            brandBadge.className = 'text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-purple-100 text-purple-800 border border-purple-300';
        } else {
            brandBadge.className = 'text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-indigo-50 text-[#100091] border border-indigo-200/80';
        }
    }

    // 8. Update all .app-edition-label and [data-app-edition] elements across modals, headers, footers
    document.querySelectorAll('.app-edition-label, [data-app-edition]').forEach(el => {
        el.textContent = editionText;
    });

    // 9. Update document.title
    if (document.title && document.title.includes('TunnelDeck')) {
        document.title = document.title.replace(/\b(Lite|Pro|Enterprise)\b/gi, editionText);
    }

    if (window.lucide) window.lucide.createIcons();
}

function checkAndUpdateCardsContainer() {
    const container = document.getElementById('sidebar-cards-container');
    const proCard = document.getElementById('sidebar-pro-card');
    const coffeeCard = document.getElementById('sidebar-coffee-card');
    if (!container) return;

    const proHidden = !proCard || proCard.classList.contains('hidden');
    const coffeeHidden = !coffeeCard || coffeeCard.classList.contains('hidden');

    if (proHidden && coffeeHidden) {
        container.classList.add('hidden');
    } else {
        container.classList.remove('hidden');
    }
}

async function syncLicenseFromCloud(btn) {
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="refresh-cw" class="w-3.5 h-3.5 animate-spin"></i><span>Syncing...</span>';
        if (window.lucide) window.lucide.createIcons();
    }

    try {
        const res = await fetch('/api/license/sync', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            }
        });
        const data = await res.json();
        if (data.suspended || res.status === 403) {
            window.location.href = '/suspended';
            return;
        }

        if (res.ok && data.success) {
            updateLicenseUI(data.package, data.currency, data.synced_at);
            if (data.upgraded) {
                showToast(`Congratulations! Plan upgraded to ${data.package.toUpperCase()} by licensing administrator!`, 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast(`License synced successfully! Plan: ${data.package.toUpperCase()}`, 'success');
            }
        } else {
            showToast(data.message || 'Failed to synchronize license.', 'error');
        }
    } catch (err) {
        console.error('License sync error:', err);
        showToast('Network error while syncing license.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            if (window.lucide) window.lucide.createIcons();
        }
    }
}

async function testPlanUpgrade(toPackage, btn) {
    if (isSimulationModeActive()) {
        showToast('Platform settings cannot be changed in simulation mode. Please complete onboarding first.', 'error');
        return;
    }

    if (toPackage === 'pro') {
        await runProUpgradeProgressFlow('TEST_SIM_' + Date.now(), 1499, 'INR');
        return;
    }

    const originalText = btn ? btn.textContent : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="refresh-cw" class="w-3 h-3 animate-spin inline-block mr-1"></i>...';
        if (window.lucide) window.lucide.createIcons();
    }

    try {
        const res = await fetch('/api/license/upgrade-test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                to_package: toPackage
            })
        });
        const data = await res.json();
        if (res.ok && data.success) {
            updateLicenseUI(data.package, data.sync_result?.currency, data.sync_result?.synced_at);
            showToast(`Upgraded to ${toPackage.toUpperCase()} and synced from licensing cloud!`, 'success');
        } else {
            showToast(data.message || 'Upgrade simulation failed.', 'error');
        }
    } catch (err) {
        console.error('Upgrade test error:', err);
        showToast('Network error during upgrade test.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }
}

function openLicenseDiagnosticModal() {
    openModal('modal-license-diagnostic');
    // Auto-run if empty or first opened
    const stepsContainer = document.getElementById('diagnostic-steps-container');
    if (stepsContainer && stepsContainer.querySelector('#diagnostic-empty-placeholder')) {
        runLicenseDiagnosticFlow();
    }
}

async function runLicenseDiagnosticFlow(btn) {
    const btnModal = document.getElementById('btn-run-diagnostic-modal');
    const targetBtn = btn || btnModal;
    const originalHtml = targetBtn ? targetBtn.innerHTML : '';

    if (targetBtn) {
        targetBtn.disabled = true;
        targetBtn.innerHTML = '<i data-lucide="refresh-cw" class="w-3.5 h-3.5 animate-spin"></i><span>Running Diagnostic...</span>';
    }

    const overallPill = document.getElementById('diagnostic-overall-pill');
    const headerBadge = document.getElementById('diagnostic-header-badge');
    const container = document.getElementById('diagnostic-steps-container');
    const timerLabel = document.getElementById('diagnostic-timer-label');

    if (overallPill) {
        overallPill.className = 'px-2.5 py-1 rounded-lg font-bold text-[11px] bg-blue-100 text-blue-800 animate-pulse';
        overallPill.textContent = 'Executing 5 Steps...';
    }
    if (headerBadge) {
        headerBadge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200';
        headerBadge.textContent = 'In Progress';
    }

    if (container) {
        container.innerHTML = `
            <div class="py-10 text-center space-y-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 text-[#100091] flex items-center justify-center mx-auto">
                    <i data-lucide="refresh-cw" class="w-5 h-5 animate-spin"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-slate-800">Testing Endpoints Live</h4>
                    <p class="text-xs text-slate-500 mt-0.5">Contacting https://licensing.tunneldeck.online API...</p>
                </div>
            </div>
        `;
        if (window.lucide) window.lucide.createIcons();
    }

    const startTime = performance.now();

    try {
        const res = await fetch('/api/license/test-flow', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            }
        });
        const elapsed = Math.round(performance.now() - startTime);
        const data = await res.json();

        if (timerLabel) {
            timerLabel.textContent = `Total time: ${elapsed}ms`;
        }

        if (res.ok && data.steps) {
            renderDiagnosticSteps(data.steps, data.all_passed, elapsed);
            if (data.all_passed) {
                if (overallPill) {
                    overallPill.className = 'px-2.5 py-1 rounded-lg font-bold text-[11px] bg-emerald-100 text-emerald-800 border border-emerald-300';
                    overallPill.textContent = 'All 5 Steps Passed';
                }
                if (headerBadge) {
                    headerBadge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200';
                    headerBadge.textContent = 'Verified Live';
                }
                showToast('Licensing server bidirectional test passed successfully!', 'success');
            } else {
                if (overallPill) {
                    overallPill.className = 'px-2.5 py-1 rounded-lg font-bold text-[11px] bg-rose-100 text-rose-800 border border-rose-300';
                    overallPill.textContent = 'Some Steps Failed';
                }
                if (headerBadge) {
                    headerBadge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200';
                    headerBadge.textContent = 'Issues Detected';
                }
                showToast('Some licensing steps reported errors.', 'error');
            }

            // Also sync the local UI
            const step5 = data.steps[4];
            if (step5 && step5.local_user_package) {
                updateLicenseUI(step5.local_user_package);
            }
        } else {
            showToast(data.message || 'Diagnostic failed to run.', 'error');
        }
    } catch (err) {
        console.error('License diagnostic error:', err);
        showToast('Network error executing diagnostic test.', 'error');
    } finally {
        if (targetBtn) {
            targetBtn.disabled = false;
            targetBtn.innerHTML = originalHtml;
            if (window.lucide) window.lucide.createIcons();
        }
    }
}

function renderDiagnosticSteps(steps, allPassed, totalTime) {
    const container = document.getElementById('diagnostic-steps-container');
    if (!container) return;

    let html = '';
    steps.forEach((step, idx) => {
        const isSuccess = step.success;
        const statusBadge = isSuccess
            ? '<span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1 shrink-0"><i data-lucide="check" class="w-3 h-3"></i> Passed</span>'
            : '<span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-rose-100 text-rose-800 border border-rose-200 flex items-center gap-1 shrink-0"><i data-lucide="alert-circle" class="w-3 h-3"></i> Failed</span>';

        const jsonStr = JSON.stringify(step.response || step, null, 2);

        html += `
            <div class="p-3.5 rounded-xl border ${isSuccess ? 'border-slate-200 bg-white' : 'border-rose-200 bg-rose-50/30'} shadow-2xs space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-5 h-5 rounded-full ${isSuccess ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'} flex items-center justify-center text-[10px] font-bold shrink-0">
                            ${idx + 1}
                        </span>
                        <div class="min-w-0">
                            <h5 class="text-xs font-bold text-slate-900 truncate">${step.name}</h5>
                            <span class="text-[10px] font-mono text-indigo-700 block truncate">${step.endpoint}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-[10px] font-mono text-slate-400 font-medium">${step.duration_ms}ms &bull; HTTP ${step.status}</span>
                        ${statusBadge}
                    </div>
                </div>

                ${step.note ? `<p class="text-[11px] text-slate-500 italic pl-7">${step.note}</p>` : ''}

                <div class="pl-7 pt-1">
                    <button type="button" onclick="toggleDiagnosticJson(${idx})" class="text-[10px] font-semibold text-slate-500 hover:text-slate-800 flex items-center gap-1 transition cursor-pointer">
                        <i data-lucide="chevron-right" id="diag-chevron-${idx}" class="w-3 h-3 transition-transform"></i>
                        <span>Inspect Cloud Response JSON</span>
                    </button>
                    <div id="diag-json-${idx}" class="hidden mt-2 p-2.5 rounded-lg bg-slate-900 text-slate-200 font-mono text-[10px] overflow-x-auto max-h-40 border border-slate-800 select-all">
                        <pre>${jsonStr}</pre>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
    if (window.lucide) window.lucide.createIcons();
}

function toggleDiagnosticJson(index) {
    const box = document.getElementById(`diag-json-${index}`);
    const chevron = document.getElementById(`diag-chevron-${index}`);
    if (box) {
        const isHidden = box.classList.contains('hidden');
        if (isHidden) {
            box.classList.remove('hidden');
            if (chevron) chevron.classList.add('rotate-90');
        } else {
            box.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-90');
        }
    }
}

/* ========================================================
   RAZORPAY PAYMENT GATEWAY & SIDEBAR CARDS (PRO & COFFEE)
   ======================================================== */

let cachedPaymentConfig = null;

function selectCoffeeAmount(amount, btn) {
    const input = document.getElementById('sidebar-coffee-amount');
    if (input) input.value = amount;

    const allButtons = document.querySelectorAll('.coffee-amount-btn');
    allButtons.forEach(b => {
        b.className = 'coffee-amount-btn py-1 px-1.5 rounded-lg text-[11px] font-bold border border-amber-200 bg-white text-slate-700 hover:border-amber-400 hover:bg-amber-100/50 transition cursor-pointer';
    });

    if (btn) {
        btn.className = 'coffee-amount-btn py-1 px-1.5 rounded-lg text-[11px] font-bold border border-amber-300 bg-amber-100/70 text-amber-900 hover:bg-amber-100 transition cursor-pointer';
    }
}

async function fetchPaymentConfig() {
    if (cachedPaymentConfig) return cachedPaymentConfig;

    try {
        const res = await fetch('/api/payment/config', {
            headers: { 'Accept': 'application/json' }
        });
        if (res.ok) {
            const data = await res.json();
            if (data && data.key_id) {
                cachedPaymentConfig = data;
                updatePaymentUIConfig(data);
                return data;
            }
        }
    } catch (e) {
        console.warn('Local payment config fetch failed, attempting cloud fallback:', e);
    }

    try {
        const res2 = await fetch('https://licensing.tunneldeck.online/api/v1/payment/config');
        if (res2.ok) {
            const data2 = await res2.json();
            if (data2 && data2.key_id) {
                cachedPaymentConfig = data2;
                updatePaymentUIConfig(data2);
                return data2;
            }
        }
    } catch (err) {
        console.error('Cloud payment config fetch failed:', err);
    }

    // Default fallback
    cachedPaymentConfig = {
        key_id: 'rzp_test_TuXla2n6Mm2F27',
        currency: 'INR',
        pro_plan: {
            price: 6999,
            price_inr: 6999,
            price_usd: 99,
            currency: 'INR',
            billing: 'annual',
        }
    };
    return cachedPaymentConfig;
}

function updatePaymentUIConfig(config) {
    if (!config) return;
    const isUSD = (config.currency === 'USD') || (config.pro_plan && config.pro_plan.currency === 'USD');
    const symbol = isUSD ? '$' : '₹';
    const priceVal = isUSD
        ? (config.pro_plan?.price_usd || config.pro_plan?.price || 99)
        : (config.pro_plan?.price_inr || config.pro_plan?.price || 6999);
    const formatted = `${symbol}${Number(priceVal).toLocaleString()}`;

    const priceBadge = document.getElementById('sidebar-pro-price-badge');
    if (priceBadge) {
        priceBadge.textContent = formatted;
    }

    const comparisonPrice = document.getElementById('comparison-pro-price');
    if (comparisonPrice) {
        comparisonPrice.textContent = formatted;
    }

    const comparisonCardPrice = document.getElementById('comparison-pro-card-price');
    if (comparisonCardPrice) {
        comparisonCardPrice.textContent = formatted;
    }

    const comparisonValidity = document.getElementById('comparison-pro-validity');
    if (comparisonValidity) {
        comparisonValidity.textContent = '/ per year';
    }
}

function getLoggedInUserDetails() {
    const card = document.getElementById('sidebar-user-card');
    const name = card?.getAttribute('data-user-name') || card?.querySelector('p.text-xs.font-bold')?.textContent || 'TunnelDeck User';
    const email = card?.getAttribute('data-user-email') || card?.querySelector('p.text-\\[10px\\]')?.textContent || 'user@tunneldeck.online';
    return { name, email };
}

async function openProPaymentCheckout(btn) {
    if (typeof window.IS_ONBOARDED !== 'undefined' && !window.IS_ONBOARDED) {
        showToast('Please complete onboarding first before upgrading to Pro.', 'info');
        openModal('onboarding-modal');
        return;
    }

    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="refresh-cw" class="w-3.5 h-3.5 animate-spin"></i><span>Loading Checkout...</span>';
        if (window.lucide) window.lucide.createIcons();
    }

    try {
        const config = await fetchPaymentConfig();
        const user = getLoggedInUserDetails();
        const price = config.pro_plan?.price || 1499;
        const currency = config.currency || 'INR';

        if (typeof window.Razorpay === 'undefined') {
            throw new Error('Razorpay SDK is not loaded. Please ensure internet connectivity.');
        }

        const options = {
            key: config.key_id,
            amount: Math.round(price * 100), // in paise / cents
            currency: currency,
            name: 'TunnelDeck',
            description: 'Pro Plan Upgrade — Unlimited Tunnels & Domains',
            prefill: {
                name: user.name,
                email: user.email,
            },
            theme: {
                color: '#100091'
            },
            handler: async function (response) {
                await handleProPaymentCallback(response, price, currency);
            },
            modal: {
                ondismiss: function () {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                        if (window.lucide) window.lucide.createIcons();
                    }
                }
            }
        };

        const rzp = new window.Razorpay(options);
        rzp.on('payment.failed', function (resp) {
            showToast(resp.error?.description || 'Payment was not completed.', 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if (window.lucide) window.lucide.createIcons();
            }
        });
        rzp.open();
    } catch (err) {
        console.error('Pro checkout error:', err);
        showToast(err.message || 'Unable to open payment gateway.', 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            if (window.lucide) window.lucide.createIcons();
        }
    }
}

async function handleProPaymentCallback(rzpResponse, amount, currency) {
    const paymentId = rzpResponse.razorpay_payment_id || ('pay_' + Date.now());
    await runProUpgradeProgressFlow(paymentId, amount, currency);
}

async function runProUpgradeProgressFlow(paymentId, amount, currency) {
    const progressView = document.getElementById('pro-upgrade-progress-view');
    const successView = document.getElementById('pro-upgrade-success-view');
    const progressBar = document.getElementById('pro-upgrade-progress-bar');
    const percentEl = document.getElementById('pro-upgrade-percentage');
    const statusText = document.getElementById('pro-upgrade-status-text');

    if (progressView) progressView.classList.remove('hidden');
    if (successView) successView.classList.add('hidden');
    if (progressBar) progressBar.style.width = '20%';
    if (percentEl) percentEl.textContent = '20%';
    if (statusText) statusText.textContent = 'Authenticating Pro upgrade...';

    openModal('modal-pro-welcome');
    if (window.lucide) window.lucide.createIcons();

    const setProgress = (percent, text, milestoneIndex) => {
        if (progressBar) progressBar.style.width = `${percent}%`;
        if (percentEl) percentEl.textContent = `${percent}%`;
        if (statusText) statusText.textContent = text;

        if (milestoneIndex) {
            for (let i = 1; i <= 4; i++) {
                const m = document.getElementById(`pro-milestone-${i}`);
                if (!m) continue;
                const origText = m.getAttribute('data-title') || m.querySelector('span')?.textContent || '';
                if (!m.hasAttribute('data-title')) m.setAttribute('data-title', origText);

                if (i < milestoneIndex) {
                    m.className = 'flex items-center gap-2 text-emerald-700 font-medium';
                    m.innerHTML = `<i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i><span>${origText}</span>`;
                } else if (i === milestoneIndex) {
                    m.className = 'flex items-center gap-2 text-[#100091] font-bold';
                    m.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 text-[#100091] animate-spin shrink-0"></i><span>${origText}</span>`;
                } else {
                    m.className = 'flex items-center gap-2 text-slate-400';
                    m.innerHTML = `<i data-lucide="circle" class="w-4 h-4 shrink-0"></i><span>${origText}</span>`;
                }
            }
            if (window.lucide) window.lucide.createIcons();
        }
    };

    setTimeout(() => {
        setProgress(45, 'Downloading Pro version files from licensing API...', 2);
    }, 450);

    try {
        const token = getCsrfToken();
        const res = await fetch('/api/payment/pro-success', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({
                payment_id: paymentId,
                amount: amount || 1499,
                currency: currency || 'INR'
            })
        });

        setProgress(75, 'Extracting and merging Pro files into project src directory...', 3);

        const data = await res.json();

        if (res.ok && data.success) {
            setProgress(100, 'Tunneldeck Lite successfully upgraded to Pro!', 4);

            updateLicenseUI('pro');

            const proCard = document.getElementById('sidebar-pro-card');
            if (proCard) proCard.classList.add('hidden');

            const coffeeCard = document.getElementById('sidebar-coffee-card');
            if (coffeeCard) coffeeCard.classList.add('hidden');

            checkAndUpdateCardsContainer();

            const txnEl = document.getElementById('pro-thanks-txn');
            if (txnEl) txnEl.textContent = paymentId;

            const badgeEl = document.getElementById('pro-merged-badge');
            if (badgeEl && data.files_count) {
                badgeEl.textContent = `✓ ${data.files_count} files merged into src`;
            }

            setTimeout(() => {
                if (progressView) progressView.classList.add('hidden');
                if (successView) successView.classList.remove('hidden');
                if (window.lucide) window.lucide.createIcons();
                showToast('Welcome to TunnelDeck Pro! All features unlocked.', 'success');
            }, 850);
        } else {
            showToast(data.message || 'Payment received, but local merge encountered an issue.', 'error');
            setProgress(100, 'Upgrade completed with warning', 4);
        }
    } catch (err) {
        console.error('Pro upgrade callback error:', err);
        showToast('Payment successful! Syncing license from server...', 'info');
        if (typeof syncLicenseFromCloud === 'function') {
            await syncLicenseFromCloud();
        }
    } finally {
        const btn = document.getElementById('btn-sidebar-upgrade-pro');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="zap" class="w-3.5 h-3.5 fill-current"></i><span>Upgrade to Pro Now</span>';
            if (window.lucide) window.lucide.createIcons();
        }
    }
}

function exploreProExperience(btn) {
    const targetBtn = btn || document.getElementById('btn-explore-pro-experience');
    if (targetBtn) {
        targetBtn.disabled = true;
        targetBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 inline-block text-white align-middle" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span>Launching Pro Workspace...</span>';
    }

    const modal = document.getElementById('modal-pro-welcome');
    if (modal) modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');

    showToast('Refreshing TunnelDeck with Pro privileges...', 'success');

    // Force hard platform navigation and cache-busting refresh
    const targetUrl = window.location.origin + window.location.pathname + '?upgraded=' + Date.now();
    try {
        window.location.assign(targetUrl);
    } catch (e) {
        window.location.href = targetUrl;
    }
    setTimeout(() => {
        window.location.replace(targetUrl);
    }, 150);
}

async function openCoffeePaymentCheckout(btn) {
    if (typeof window.IS_ONBOARDED !== 'undefined' && !window.IS_ONBOARDED) {
        showToast('Please complete onboarding first before donating coffee.', 'info');
        openModal('onboarding-modal');
        return;
    }

    const amountInput = document.getElementById('sidebar-coffee-amount');
    const messageInput = document.getElementById('sidebar-coffee-message');
    const amount = Number(amountInput?.value) || 250;
    const brandBadge = document.getElementById('brand-edition-badge');
    const currentEdition = brandBadge ? brandBadge.textContent.trim() : 'Lite';
    const message = messageInput?.value.trim() || `Enjoying TunnelDeck ${currentEdition}! Here is a coffee ☕`;

    if (!amount || amount < 10) {
        showToast('Please enter an amount of at least ₹10.', 'error');
        amountInput?.focus();
        return;
    }

    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="refresh-cw" class="w-3.5 h-3.5 animate-spin"></i><span>Processing...</span>';
        if (window.lucide) window.lucide.createIcons();
    }

    try {
        const config = await fetchPaymentConfig();
        const user = getLoggedInUserDetails();
        const currency = config.currency || 'INR';

        if (typeof window.Razorpay === 'undefined') {
            throw new Error('Razorpay SDK is not loaded. Please ensure internet connectivity.');
        }

        const options = {
            key: config.key_id,
            amount: Math.round(amount * 100), // in paise
            currency: currency,
            name: 'Buy Me a Coffee ☕',
            description: `Support TunnelDeck ${currentEdition} — ${user.name}`,
            prefill: {
                name: user.name,
                email: user.email,
            },
            theme: {
                color: '#d97706' // warm amber
            },
            handler: async function (response) {
                await handleCoffeePaymentCallback(response, amount, currency, message);
            },
            modal: {
                ondismiss: function () {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                        if (window.lucide) window.lucide.createIcons();
                    }
                }
            }
        };

        const rzp = new window.Razorpay(options);
        rzp.on('payment.failed', function (resp) {
            showToast(resp.error?.description || 'Coffee donation cancelled.', 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if (window.lucide) window.lucide.createIcons();
            }
        });
        rzp.open();
    } catch (err) {
        console.error('Coffee checkout error:', err);
        showToast(err.message || 'Unable to start coffee donation.', 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            if (window.lucide) window.lucide.createIcons();
        }
    }
}

async function handleCoffeePaymentCallback(rzpResponse, amount, currency, message) {
    showToast('Payment received! Recording your coffee donation...', 'info');

    const paymentId = rzpResponse.razorpay_payment_id || ('pay_' + Date.now());
    const orderId = rzpResponse.razorpay_order_id || null;

    try {
        const token = getCsrfToken();
        const res = await fetch('/api/payment/coffee-success', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify({
                payment_id: paymentId,
                order_id: orderId,
                amount: amount,
                currency: currency,
                message: message
            })
        });

        const data = await res.json();
        
        // Hide coffee card for next 30 days and mark user as past donor for next month's message
        localStorage.setItem('tunneldeck_coffee_donated_at', new Date().toISOString());
        localStorage.setItem('tunneldeck_has_previously_donated', 'true');
        const coffeeCard = document.getElementById('sidebar-coffee-card');
        if (coffeeCard) coffeeCard.classList.add('hidden');
        checkAndUpdateCardsContainer();

        // Show Appreciation Modal
        const amountEl = document.getElementById('coffee-thanks-amount');
        const txnEl = document.getElementById('coffee-thanks-txn');
        const timeEl = document.getElementById('coffee-thanks-time');
        const noteEl = document.getElementById('coffee-thanks-note');
        const noteContainer = document.getElementById('coffee-thanks-note-container');

        const symbol = (currency === 'USD') ? '$' : '₹';
        if (amountEl) amountEl.textContent = `${symbol}${amount}`;
        if (txnEl) txnEl.textContent = paymentId;
        if (timeEl) timeEl.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        if (noteEl && message) {
            noteEl.textContent = `"${message}"`;
            if (noteContainer) noteContainer.classList.remove('hidden');
        } else if (noteContainer) {
            noteContainer.classList.add('hidden');
        }

        openModal('modal-coffee-thanks');
        showToast('Thank you so much for the coffee! ☕', 'success');

        // Reset message field
        const messageInput = document.getElementById('sidebar-coffee-message');
        if (messageInput) messageInput.value = '';
    } catch (err) {
        console.error('Coffee callback error:', err);
        showToast('Thank you for your generosity! Coffee payment recorded.', 'success');
    } finally {
        const btn = document.getElementById('btn-sidebar-donate-coffee');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="heart" class="w-3.5 h-3.5 fill-current"></i><span>Donate Coffee</span>';
            if (window.lucide) window.lucide.createIcons();
        }
    }
}

// Pre-fetch payment configuration and sync state on initial load
document.addEventListener('DOMContentLoaded', () => {
    if (window.INITIAL_TUNNELS && Array.isArray(window.INITIAL_TUNNELS)) {
        renderTunnelsTableRows(window.INITIAL_TUNNELS);
    }
    fetchPaymentConfig();
    fetchTunnelLimit();
    updateSelectAllCheckboxState();
    updateBulkActionBar();

    // Check if coffee donation is remembered in localStorage within 30 days
    const donatedAtStr = localStorage.getItem('tunneldeck_coffee_donated_at');
    const hasPreviouslyDonated = localStorage.getItem('tunneldeck_has_previously_donated') === 'true';

    if (donatedAtStr) {
        const donatedAt = new Date(donatedAtStr);
        const daysDiff = (Date.now() - donatedAt.getTime()) / (1000 * 60 * 60 * 24);
        if (daysDiff < 30) {
            const coffeeCard = document.getElementById('sidebar-coffee-card');
            if (coffeeCard) coffeeCard.classList.add('hidden');
            checkAndUpdateCardsContainer();
        } else if (hasPreviouslyDonated) {
            // Next month: 30 days have elapsed, show returning donor message
            const coffeeMsgEl = document.getElementById('sidebar-coffee-message-text');
            if (coffeeMsgEl) {
                coffeeMsgEl.textContent = 'Last month I really enjoyed my coffee that you have paid for. Share another coffee that will help me concentrate on further development';
            }
        }
    }

    // Start automatic license sync & suspension heartbeat
    setTimeout(autoSyncLicenseHeartbeat, 3000);
    setInterval(autoSyncLicenseHeartbeat, 30000);
});

/* ======================================================== */
/* AUTOMATIC LICENSE SYNC & SUSPENSION HEARTBEAT            */
/* Automatically detects admin upgrades (Lite -> Pro) &     */
/* suspensions without requiring a manual refresh           */
/* ======================================================== */
async function autoSyncLicenseHeartbeat() {
    try {
        const res = await fetch('/api/license/sync', {
            headers: { 'Accept': 'application/json' }
        });

        // 1. If 403 or suspension returned, redirect immediately to suspension screen
        if (res.status === 403) {
            window.location.href = '/suspended';
            return;
        }

        if (res.ok) {
            const data = await res.json();

            // 0. If client was deleted from licensing platform, notify and reload to fresh login
            if (data.client_deleted || data.reset_to_defaults) {
                if (typeof showToast === 'function') {
                    showToast('Client account was removed from licensing platform. Resetting to factory defaults...', 'error');
                }
                setTimeout(() => {
                    window.location.href = data.redirect || '/login';
                }, 1500);
                return;
            }

            if (data.suspended) {
                window.location.href = '/suspended';
                return;
            }

            // 2. If activation seat limit exceeded on this device
            if (data.activation_limit_reached) {
                if (typeof showToast === 'function') {
                    showToast(`Device Limit Reached: Your license allows ${data.max_activations || 1} device(s). Pro features are locked on this machine.`, 'warning');
                }
                return;
            }

            // 3. If upgraded by licensing admin, notify user and update UI in real-time
            if (data.upgraded) {
                if (typeof showToast === 'function') {
                    showToast(`Congratulations! Plan upgraded to ${data.package.toUpperCase()} by licensing administrator!`, 'success');
                }
                if (typeof updateLicenseUI === 'function') {
                    updateLicenseUI(data.package, data.currency, data.synced_at);
                }
                if (typeof fetchTunnelLimit === 'function') {
                    fetchTunnelLimit();
                }
                setTimeout(() => window.location.reload(), 1800);
            }
        }
    } catch (e) {
        // Silent catch for network drops
    }
}




