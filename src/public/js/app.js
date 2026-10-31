/**
 * TunnelDeck Lite — Pure Vanilla JavaScript Client Application
 * Zero external JS dependencies • Native Browser Fetch API
 */

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
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    if (window.lucide) window.lucide.createIcons();
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
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
/* TUNNELS MANAGEMENT (AJAX) */
/* ======================================================== */

function openAddTunnelModal() {
    const nameInput = document.getElementById('add-tunnel-name');
    const portInput = document.getElementById('add-tunnel-port');
    if (nameInput) nameInput.value = '';
    if (portInput) portInput.value = '';
    openModal('modal-add-tunnel');
}

async function handleAddTunnel(e) {
    e.preventDefault();
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
            throw new Error(data.message || 'Failed to create tunnel.');
        }

        closeModal('modal-add-tunnel');
        showToast('Tunnel added successfully.', 'success');
        await refreshDashboardData();
    } catch (err) {
        alert(err.message || 'Failed to create tunnel.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Add Tunnel</span>';
    }
}

// Start Tunnel Routing Modal Flow
let currentRoutingStrategy = 'direct';

function openStartRoutingModal(tunnelId) {
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

        closeModal('modal-start-routing');
        showToast('Tunnel started successfully.', 'success');
        await refreshDashboardData();
    } catch (err) {
        alert(err.message || 'Failed to start tunnel.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i data-lucide="play" class="w-3.5 h-3.5 fill-white"></i><span>Start Tunnel</span>';
        if (window.lucide) window.lucide.createIcons();
    }
}

async function stopTunnel(tunnelId) {
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
        alert(err.message || 'Failed to stop tunnel.');
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
            throw new Error(data.message || 'Failed to update tunnel.');
        }

        closeModal('modal-edit-tunnel');
        showToast('Tunnel updated successfully.', 'success');
        await refreshDashboardData();
    } catch (err) {
        alert(err.message || 'Failed to update tunnel.');
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

        closeModal('modal-delete-tunnel');
        showToast('Tunnel deleted successfully.', 'success');
        await refreshDashboardData();
    } catch (err) {
        alert(err.message || 'Failed to delete tunnel.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="trash-2" class="w-3.5 h-3.5"></i><span>Delete Tunnel</span>';
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

async function handleSaveSettingsPassword(e) {
    e.preventDefault();
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

            renderTunnelsTableRows(tunnels);
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

    if (tunnels.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="py-16 text-center text-slate-500 text-sm">
                    <p class="font-medium text-slate-700">No tunnels registered yet.</p>
                    <p class="text-xs text-slate-400 mt-1">Create your first forwarding tunnel to expose local ports.</p>
                    <button type="button" onclick="openAddTunnelModal()" class="mt-4 px-4 py-2 rounded-xl bg-[#100091] text-white text-xs font-semibold hover:bg-[#0b0068] shadow-sm transition cursor-pointer">
                        + Add New Tunnel
                    </button>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = tunnels.map(t => {
        const isRunning = t.status === 'running';
        const isStarting = t.status === 'starting';

        const statusBadge = isRunning
            ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Running</span>
               </span>`
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

        return `
            <tr class="hover:bg-slate-50/80 transition group" id="tunnel-row-${t.id}">
                <td class="py-4 px-6 whitespace-nowrap">${statusBadge}</td>
                <td class="py-4 px-6 font-bold text-slate-900 text-sm whitespace-nowrap">${t.name}</td>
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
                        <button type="button" onclick="openEditTunnelModal(${t.id}, '${t.name.replace(/'/g, "\\'")}', ${t.port})" class="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500 hover:text-slate-700 transition cursor-pointer" title="Edit Tunnel">
                            <i data-lucide="pencil" class="w-4 h-4"></i>
                        </button>
                        <button type="button" onclick="openDeleteTunnelModal(${t.id}, '${t.name.replace(/'/g, "\\'")}', ${t.port})" class="p-1.5 rounded-lg border border-slate-200 hover:bg-rose-50 text-rose-500 hover:text-rose-600 transition cursor-pointer" title="Delete Tunnel">
                            <i data-lucide="trash-2" class="w-4 h-4 stroke-[1.8]"></i>
                        </button>
                    </div>
                </td>
            </tr>
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

    // Show/hide step panels
    for (let i = 1; i <= 3; i++) {
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
        if (step === 2) bar.style.width = '50%';
        if (step === 3) bar.style.width = '100%';
    }

    const counter = document.getElementById('onboarding-step-counter');
    if (counter) {
        counter.textContent = `Step ${step} of 3`;
    }

    if (window.lucide) window.lucide.createIcons();
}

async function triggerExplorePlatform(btnElement) {
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
        btnElement.innerHTML = '<span class="inline-block animate-spin mr-1">⏳</span> Entering exploration...';
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
            }),
        });

        const data = await res.json();
        if (res.ok && data.success) {
            closeOnboardingModal(data.user);
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
    }
}

async function handleOnboardingSubmit(event) {
    event.preventDefault();

    const nameInput = document.getElementById('onb-name');
    const emailInput = document.getElementById('onb-email');
    const cityInput = document.getElementById('onb-city');
    const countryInput = document.getElementById('onb-country');
    const passwordInput = document.getElementById('onb-password');
    const passwordConfInput = document.getElementById('onb-password-confirmation');
    const passwordError = document.getElementById('onb-password-error');
    const submitBtn = document.getElementById('onb-submit-btn');
    const submitText = document.getElementById('onb-submit-text');

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

    // Loading state with spinner icon
    const spinner = document.getElementById('onb-submit-spinner');
    const submitIcon = document.getElementById('onb-submit-icon');
    if (submitBtn) submitBtn.disabled = true;
    if (spinner) spinner.classList.remove('hidden');
    if (submitIcon) submitIcon.classList.add('hidden');
    if (submitText) submitText.textContent = 'Saving & Registering...';

    const resetSubmitBtn = () => {
        if (submitBtn) submitBtn.disabled = false;
        if (spinner) spinner.classList.add('hidden');
        if (submitIcon) submitIcon.classList.remove('hidden');
        if (submitText) submitText.textContent = 'Complete Setup & Launch';
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
            }),
        });

        const body = await res.json();
        if (res.ok && body.success) {
            // Save updated password in localStorage for login autofill
            if (passwordVal) {
                localStorage.setItem('tunneldeck_saved_password', passwordVal);
            }
            closeOnboardingModal(body.user);
            showToast(body.message || 'Onboarding completed successfully!', 'success');
        } else {
            resetSubmitBtn();
            const msg = body.errors ? Object.values(body.errors).flat().join(' ') : (body.message || 'Failed to complete onboarding.');
            showToast(msg, 'error');
        }
    } catch (err) {
        console.error('Onboarding submit error:', err);
        resetSubmitBtn();
        showToast('Network error during onboarding.', 'error');
    }
}

function closeOnboardingModal(userData) {
    const modal = document.getElementById('onboarding-modal');
    if (modal) {
        modal.classList.add('opacity-0');
        setTimeout(() => modal.remove(), 300);
    }

    if (userData) {
        // Update sidebar user details in real-time
        const userCard = document.getElementById('sidebar-user-card');
        if (userCard) {
            const nameEl = userCard.querySelector('p.text-xs.font-bold');
            if (nameEl) nameEl.textContent = userData.name;

            const emailEl = userCard.querySelector('p.text-\\[10px\\]');
            if (emailEl) emailEl.textContent = userData.email;

            const avatarEl = userCard.querySelector('.rounded-lg.bg-indigo-100');
            if (avatarEl && userData.initial) avatarEl.textContent = userData.initial;
        }

        if (userData.package) {
            updateLicenseUI(userData.package);
        }
    }
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

    if (window.lucide) window.lucide.createIcons();
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
        if (res.ok && data.success) {
            updateLicenseUI(data.package, data.currency, data.synced_at);
            showToast(`License synced successfully! Plan: ${data.package.toUpperCase()}`, 'success');
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


