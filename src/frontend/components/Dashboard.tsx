"use client";

import React, { useState, useEffect } from "react";
import {
  Radio,
  Database,
  LogOut,
  Plus,
  RefreshCw,
  Copy,
  Check,
  Play,
  Pause,
  RotateCw,
  Trash2,
  Server,
  Activity as ActivityIcon,
  Layers,
  ArrowUpRight,
  X,
  Globe,
  ExternalLink,
  Cpu,
  Clock,
  ShieldCheck,
  LayoutDashboard,
  Settings as SettingsIcon,
  Shield,
  User as UserIcon,
  Sliders,
  CheckCircle2,
} from "lucide-react";
import { api, Activity, CustomDomain, Tunnel, TunnelMetrics, User } from "@/lib/api";

interface DashboardProps {
  user: User;
  onLogout: () => void;
}

// Custom crisp "half hamburger" icon
function HalfHamburgerIcon({ className = "w-5 h-5" }: { className?: string }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2.2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <line x1="4" y1="6" x2="20" y2="6" />
      <line x1="4" y1="12" x2="13" y2="12" />
      <line x1="4" y1="18" x2="17" y2="18" />
    </svg>
  );
}

type TabType = "dashboard" | "tunnels" | "domains" | "settings";

export default function Dashboard({ user, onLogout }: DashboardProps) {
  // Navigation & UI state
  const [activeTab, setActiveTab] = useState<TabType>("dashboard");
  const [isCollapsed, setIsCollapsed] = useState(false);

  // Data state
  const [tunnels, setTunnels] = useState<Tunnel[]>([]);
  const [domains, setDomains] = useState<CustomDomain[]>([]);
  const [metrics, setMetrics] = useState<TunnelMetrics>({
    total: 0,
    active: 0,
    paused: 0,
    total_requests: 0,
  });
  const [activities, setActivities] = useState<Activity[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoadingId, setActionLoadingId] = useState<number | null>(null);
  const [actionText, setActionText] = useState<string>("");
  const [copiedId, setCopiedId] = useState<number | null>(null);
  
  // Modals state
  const [isTunnelModalOpen, setIsTunnelModalOpen] = useState(false);
  const [isDomainModalOpen, setIsDomainModalOpen] = useState(false);
  const [creatingTunnel, setCreatingTunnel] = useState(false);
  const [creatingDomain, setCreatingDomain] = useState(false);

  // Form states
  const [tunnelForm, setTunnelForm] = useState({
    name: "",
    port: 3000,
    custom_subdomain: "",
  });
  const [domainInput, setDomainInput] = useState("");

  const fetchData = async () => {
    try {
      setLoading(true);
      const [tunnelsRes, statsRes, domainsRes] = await Promise.all([
        api.getTunnels(),
        api.getStats().catch(() => null),
        api.getDomains().catch(() => null),
      ]);

      setTunnels(tunnelsRes.data.tunnels);
      setMetrics(tunnelsRes.data.metrics);
      if (statsRes?.activities) {
        setActivities(statsRes.activities);
      }
      if (domainsRes?.domains) {
        setDomains(domainsRes.domains);
      }
    } catch (err) {
      console.error("Failed to load dashboard data:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();

    // Auto-refresh every 8 seconds to reflect live status
    const interval = setInterval(() => {
      api.getTunnels().then((res) => {
        setTunnels(res.data.tunnels);
        setMetrics(res.data.metrics);
      }).catch(() => {});
    }, 8000);

    return () => clearInterval(interval);
  }, []);

  const handleCopy = (id: number, url: string) => {
    navigator.clipboard.writeText(url);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  const handleStartTunnel = async (tunnel: Tunnel) => {
    setActionLoadingId(tunnel.id);
    setActionText("Starting cloudflared...");
    try {
      await api.startTunnel(tunnel.id);
      await fetchData();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to start tunnel");
    } finally {
      setActionLoadingId(null);
      setActionText("");
    }
  };

  const handleStopTunnel = async (tunnel: Tunnel) => {
    setActionLoadingId(tunnel.id);
    setActionText("Stopping process...");
    try {
      await api.stopTunnel(tunnel.id);
      await fetchData();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to stop tunnel");
    } finally {
      setActionLoadingId(null);
      setActionText("");
    }
  };

  const handleRestartTunnel = async (tunnel: Tunnel) => {
    setActionLoadingId(tunnel.id);
    setActionText("Restarting tunnel...");
    try {
      await api.restartTunnel(tunnel.id);
      await fetchData();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to restart tunnel");
    } finally {
      setActionLoadingId(null);
      setActionText("");
    }
  };

  const handleDeleteTunnel = async (id: number) => {
    if (!confirm("Are you sure you want to stop and delete this tunnel?")) return;
    try {
      await api.deleteTunnel(id);
      await fetchData();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to delete tunnel");
    }
  };

  const handleCreateTunnel = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreatingTunnel(true);
    try {
      await api.createTunnel({
        name: tunnelForm.name,
        port: Number(tunnelForm.port),
        custom_subdomain: tunnelForm.custom_subdomain || undefined,
      });
      setIsTunnelModalOpen(false);
      setTunnelForm({
        name: "",
        port: 3000,
        custom_subdomain: "",
      });
      await fetchData();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to create tunnel");
    } finally {
      setCreatingTunnel(false);
    }
  };

  const handleCreateDomain = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!domainInput.trim()) return;
    setCreatingDomain(true);
    try {
      await api.createDomain(domainInput.trim());
      setIsDomainModalOpen(false);
      setDomainInput("");
      await fetchData();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to add domain");
    } finally {
      setCreatingDomain(false);
    }
  };

  const handleDeleteDomain = async (id: number) => {
    if (!confirm("Remove this domain configuration?")) return;
    try {
      await api.deleteDomain(id);
      await fetchData();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to delete domain");
    }
  };

  // Menu items list
  const menuItems = [
    { id: "dashboard", label: "Dashboard", icon: LayoutDashboard },
    { id: "tunnels", label: "Tunnels", icon: Server },
    { id: "domains", label: "Domains", icon: Globe },
    { id: "settings", label: "Settings", icon: SettingsIcon },
  ] as const;

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 flex flex-row">
      {/* ======================================================== */}
      {/* SIDEBAR NAVIGATION */}
      {/* ======================================================== */}
      <aside
        className={`bg-white border-r border-slate-200/90 flex flex-col justify-between transition-all duration-300 sticky top-0 h-screen z-30 shadow-sm shrink-0 ${
          isCollapsed ? "w-20" : "w-64"
        }`}
      >
        {/* TOP OF SIDEBAR */}
        <div>
          {/* Header area with Logo and Toggle */}
          {!isCollapsed ? (
            <div className="flex items-center justify-between p-4 border-b border-slate-200/80">
              <div className="flex items-center gap-3 min-w-0">
                <div className="w-10 h-10 rounded-xl bg-[#100091] flex items-center justify-center shadow-md shadow-[#100091]/25 shrink-0">
                  <Radio className="w-5 h-5 text-white animate-pulse" />
                </div>
                <div className="min-w-0">
                  <div className="flex items-center gap-1.5">
                    <span className="font-extrabold text-base text-slate-900 tracking-tight">
                      TunnelDeck
                    </span>
                    <span className="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-indigo-50 text-[#100091] border border-indigo-200/80">
                      Lite
                    </span>
                  </div>
                  <p className="text-[11px] text-slate-400 font-medium truncate">
                    Cloudflared Engine
                  </p>
                </div>
              </div>

              {/* Half Hamburger Toggle Icon (visible when expanded) */}
              <button
                type="button"
                onClick={() => setIsCollapsed(true)}
                className="p-2 rounded-xl text-slate-400 hover:text-[#100091] hover:bg-indigo-50/80 transition cursor-pointer shrink-0"
                title="Collapse Sidebar"
              >
                <HalfHamburgerIcon className="w-5 h-5" />
              </button>
            </div>
          ) : (
            <div className="p-3 border-b border-slate-200/80 flex justify-center">
              {/* Toggled / Collapsed State: Logo icon with hoverable half-hamburger */}
              <button
                type="button"
                onClick={() => setIsCollapsed(false)}
                className="relative group w-11 h-11 rounded-xl bg-[#100091] flex items-center justify-center shadow-md shadow-[#100091]/25 cursor-pointer overflow-hidden transition-all duration-200"
                title="Expand Sidebar"
              >
                {/* Default Logo Icon */}
                <div className="transition-all duration-200 group-hover:opacity-0 group-hover:scale-75 flex items-center justify-center">
                  <Radio className="w-5 h-5 text-white animate-pulse" />
                </div>

                {/* Half Hamburger Icon Visible on Hover */}
                <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-200 bg-[#0b0068] text-white">
                  <HalfHamburgerIcon className="w-5 h-5" />
                </div>
              </button>
            </div>
          )}

          {/* MENU OPTIONS LIST */}
          <nav className="p-3 space-y-1.5">
            {menuItems.map((item) => {
              const Icon = item.icon;
              const isActive = activeTab === item.id;

              return (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => setActiveTab(item.id)}
                  className={`w-full flex items-center gap-3.5 px-3 py-2.5 rounded-xl text-sm transition font-medium cursor-pointer ${
                    isCollapsed ? "justify-center" : "justify-start"
                  } ${
                    isActive
                      ? "bg-[#100091] text-white font-semibold shadow-md shadow-[#100091]/25"
                      : "text-slate-600 hover:text-slate-900 hover:bg-slate-100/80"
                  }`}
                  title={isCollapsed ? item.label : undefined}
                >
                  <Icon
                    className={`w-4 h-4 shrink-0 ${
                      isActive ? "text-white" : "text-slate-500"
                    }`}
                  />
                  {!isCollapsed && <span>{item.label}</span>}
                </button>
              );
            })}
          </nav>
        </div>

        {/* BOTTOM OF SIDEBAR */}
        {!isCollapsed ? (
          <div className="p-3 border-t border-slate-200/80 bg-slate-50/70 flex items-center justify-between gap-2.5">
            <div className="flex items-center gap-2.5 min-w-0">
              <div className="w-8 h-8 rounded-full bg-[#100091] text-white font-bold text-xs flex items-center justify-center shadow-sm shrink-0">
                {user.name.charAt(0)}
              </div>
              <div className="min-w-0 text-left">
                <div className="font-semibold text-xs text-slate-800 truncate">
                  {user.name}
                </div>
                <div className="text-[11px] text-slate-500 truncate">{user.email}</div>
              </div>
            </div>

            <button
              type="button"
              onClick={onLogout}
              className="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-200 transition cursor-pointer shrink-0"
              title="Sign Out"
            >
              <LogOut className="w-4 h-4" />
            </button>
          </div>
        ) : (
          <div className="p-3 border-t border-slate-200/80 flex flex-col items-center gap-2.5 bg-slate-50/70">
            <div
              className="w-8 h-8 rounded-full bg-[#100091] text-white font-bold text-xs flex items-center justify-center shadow-sm"
              title={`${user.name} (${user.email})`}
            >
              {user.name.charAt(0)}
            </div>
            <button
              type="button"
              onClick={onLogout}
              className="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-200 transition cursor-pointer"
              title="Sign Out"
            >
              <LogOut className="w-4 h-4" />
            </button>
          </div>
        )}
      </aside>

      {/* ======================================================== */}
      {/* MAIN CONTENT AREA */}
      {/* ======================================================== */}
      <div className="flex-1 min-w-0 flex flex-col overflow-y-auto">
        {/* Top Header Bar */}
        <header className="border-b border-slate-200/90 bg-white/95 backdrop-blur-md sticky top-0 z-20 px-6 h-16 flex items-center justify-between shadow-sm">
          <div className="flex items-center gap-3">
            <h1 className="text-lg font-extrabold text-slate-900 tracking-tight capitalize">
              {activeTab} Overview
            </h1>
            <span className="text-slate-300">•</span>
            <div className="flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-xs font-medium text-slate-600">
              <Database className="w-3.5 h-3.5 text-[#100091]" />
              <span>SQLite Connected</span>
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
            </div>
          </div>

          <div className="flex items-center gap-3">
            <button
              onClick={fetchData}
              disabled={loading}
              className="px-3 py-1.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold border border-slate-300 transition flex items-center gap-2 cursor-pointer disabled:opacity-50 shadow-sm"
            >
              <RefreshCw className={`w-3.5 h-3.5 text-slate-500 ${loading ? "animate-spin" : ""}`} />
              <span>Sync</span>
            </button>

            <button
              onClick={() => setIsTunnelModalOpen(true)}
              className="px-3.5 py-1.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-semibold shadow-md shadow-[#100091]/25 transition flex items-center gap-2 cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>New Tunnel</span>
            </button>
          </div>
        </header>

        {/* Tab Content */}
        <main className="p-6 sm:p-8 space-y-7 flex-1">
          {/* 1. DASHBOARD TAB */}
          {activeTab === "dashboard" && (
            <div className="space-y-7">
              {/* Banner */}
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/90 shadow-sm">
                <div>
                  <h2 className="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2.5">
                    <span>Cloudflare Developer Tunnels</span>
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">
                      <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                      Live Infrastructure
                    </span>
                  </h2>
                  <p className="text-sm text-slate-500 mt-1">
                    Manage forwarding processes securely connected from localhost to Cloudflare edge network
                  </p>
                </div>
                <button
                  onClick={() => setIsTunnelModalOpen(true)}
                  className="px-4 py-2 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-semibold shadow-md shadow-[#100091]/25 transition flex items-center gap-2 cursor-pointer shrink-0"
                >
                  <Plus className="w-4 h-4" />
                  <span>Create Tunnel</span>
                </button>
              </div>

              {/* Metrics Grid */}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-sm">
                  <div className="flex items-center justify-between text-slate-500 mb-2">
                    <span className="text-xs font-semibold uppercase tracking-wider">Running Tunnels</span>
                    <ActivityIcon className="w-4 h-4 text-emerald-600" />
                  </div>
                  <div className="flex items-baseline gap-2">
                    <span className="text-3xl font-extrabold text-slate-900">{metrics.active}</span>
                    <span className="text-xs text-emerald-600 font-semibold">Active</span>
                  </div>
                </div>

                <div className="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-sm">
                  <div className="flex items-center justify-between text-slate-500 mb-2">
                    <span className="text-xs font-semibold uppercase tracking-wider">Total Configured</span>
                    <Layers className="w-4 h-4 text-[#100091]" />
                  </div>
                  <div className="flex items-baseline gap-2">
                    <span className="text-3xl font-extrabold text-slate-900">{metrics.total}</span>
                    <span className="text-xs text-slate-500 font-medium">Tunnels</span>
                  </div>
                </div>

                <div className="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-sm">
                  <div className="flex items-center justify-between text-slate-500 mb-2">
                    <span className="text-xs font-semibold uppercase tracking-wider">Idle Tunnels</span>
                    <Clock className="w-4 h-4 text-amber-500" />
                  </div>
                  <div className="flex items-baseline gap-2">
                    <span className="text-3xl font-extrabold text-slate-900">{metrics.paused}</span>
                    <span className="text-xs text-amber-600 font-semibold">Stopped</span>
                  </div>
                </div>

                <div className="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-sm">
                  <div className="flex items-center justify-between text-slate-500 mb-2">
                    <span className="text-xs font-semibold uppercase tracking-wider">Engine Status</span>
                    <Cpu className="w-4 h-4 text-[#100091]" />
                  </div>
                  <div className="flex items-baseline gap-2">
                    <span className="text-sm font-bold text-emerald-600">Cloudflared v2026.7</span>
                    <span className="text-xs text-slate-500 font-medium">Ready</span>
                  </div>
                </div>
              </div>

              {/* Quick Tunnels List */}
              <div className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-sm">
                <div className="px-6 py-4 border-b border-slate-200/80 bg-slate-50/70 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Server className="w-4 h-4 text-[#100091]" />
                    <h3 className="font-bold text-slate-900 text-sm">Quick Tunnels Access</h3>
                  </div>
                  <button
                    onClick={() => setActiveTab("tunnels")}
                    className="text-xs text-[#100091] font-semibold hover:underline"
                  >
                    View All Tunnels →
                  </button>
                </div>

                {loading ? (
                  <div className="py-12 text-center text-slate-500 text-sm">
                    <div className="w-6 h-6 border-2 border-[#100091] border-t-transparent rounded-full animate-spin mx-auto mb-2" />
                    <span>Synchronizing tunnel states...</span>
                  </div>
                ) : (
                  <div className="divide-y divide-slate-100">
                    {tunnels.slice(0, 3).map((tunnel) => (
                      <div
                        key={tunnel.id}
                        className="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-slate-50/70 transition"
                      >
                        <div className="space-y-1.5">
                          <div className="flex items-center gap-2.5">
                            <span className="font-bold text-slate-900 text-sm">{tunnel.name}</span>
                            <span
                              className={`text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase tracking-wider flex items-center gap-1 ${
                                tunnel.status === "running"
                                  ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
                                  : "bg-slate-100 text-slate-600 border border-slate-200"
                              }`}
                            >
                              <span
                                className={`w-1.5 h-1.5 rounded-full ${
                                  tunnel.status === "running"
                                    ? "bg-emerald-500 animate-pulse"
                                    : "bg-slate-400"
                                }`}
                              />
                              {tunnel.status}
                            </span>
                          </div>

                          <div className="flex items-center gap-2 text-xs">
                            <span className="font-mono text-slate-600 bg-slate-100 px-2 py-0.5 rounded">
                              Port {tunnel.port}
                            </span>
                            {tunnel.public_url && (
                              <span className="font-mono text-[#100091] bg-indigo-50 px-2 py-0.5 rounded truncate max-w-xs">
                                {tunnel.public_url}
                              </span>
                            )}
                          </div>
                        </div>

                        <div className="flex items-center gap-2">
                          {tunnel.status === "running" ? (
                            <button
                              onClick={() => handleStopTunnel(tunnel)}
                              disabled={actionLoadingId === tunnel.id}
                              className="px-3 py-1.5 rounded-lg text-xs font-semibold border bg-rose-50 hover:bg-rose-100 text-rose-700 border-rose-200 transition cursor-pointer"
                            >
                              Stop
                            </button>
                          ) : (
                            <button
                              onClick={() => handleStartTunnel(tunnel)}
                              disabled={actionLoadingId === tunnel.id}
                              className="px-3 py-1.5 rounded-lg text-xs font-semibold border bg-[#100091] hover:bg-[#0b0068] text-white transition cursor-pointer"
                            >
                              Start
                            </button>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Recent Activity Logs */}
              {activities.length > 0 && (
                <div className="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-sm">
                  <h4 className="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <ActivityIcon className="w-4 h-4 text-[#100091]" />
                    <span>Recent Activity Stream</span>
                  </h4>
                  <div className="space-y-2.5">
                    {activities.map((act) => (
                      <div
                        key={act.id}
                        className="text-xs flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-100 text-slate-700"
                      >
                        <div className="flex items-center gap-2">
                          <span className="w-1.5 h-1.5 rounded-full bg-[#100091]" />
                          <span>{act.description}</span>
                        </div>
                        <span className="text-[11px] text-slate-400 font-mono">
                          {new Date(act.created_at).toLocaleTimeString()}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}

          {/* 2. TUNNELS TAB */}
          {activeTab === "tunnels" && (
            <div className="space-y-6">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/90 shadow-sm">
                <div>
                  <h2 className="text-xl font-extrabold text-slate-900 tracking-tight">
                    Forwarding Tunnels Manager
                  </h2>
                  <p className="text-sm text-slate-500 mt-1">
                    Create and monitor persistent local-to-public Cloudflare edge tunnels
                  </p>
                </div>
                <button
                  onClick={() => setIsTunnelModalOpen(true)}
                  className="px-4 py-2 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-semibold shadow-md shadow-[#100091]/25 transition flex items-center gap-2 cursor-pointer shrink-0"
                >
                  <Plus className="w-4 h-4" />
                  <span>Create Tunnel</span>
                </button>
              </div>

              {/* Full Tunnels List */}
              <div className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-sm">
                <div className="px-6 py-4 border-b border-slate-200/80 bg-slate-50/70 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Server className="w-4 h-4 text-[#100091]" />
                    <h3 className="font-bold text-slate-900 text-sm">Configured Tunnels</h3>
                  </div>
                  <span className="text-xs font-medium text-slate-500">{tunnels.length} Registered</span>
                </div>

                {loading ? (
                  <div className="py-16 text-center text-slate-500 text-sm">
                    <div className="w-6 h-6 border-2 border-[#100091] border-t-transparent rounded-full animate-spin mx-auto mb-2" />
                    <span>Querying process table...</span>
                  </div>
                ) : tunnels.length === 0 ? (
                  <div className="py-16 text-center text-slate-500 text-sm">
                    <p>No tunnels registered yet.</p>
                    <button
                      onClick={() => setIsTunnelModalOpen(true)}
                      className="mt-3 text-[#100091] font-semibold hover:underline text-xs"
                    >
                      + Create your first tunnel
                    </button>
                  </div>
                ) : (
                  <div className="divide-y divide-slate-100">
                    {tunnels.map((tunnel) => {
                      const isOperating = actionLoadingId === tunnel.id;
                      const isRunning = tunnel.status === "running";
                      const isStarting = tunnel.status === "starting" || isOperating;

                      return (
                        <div
                          key={tunnel.id}
                          className="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-slate-50/70 transition"
                        >
                          <div className="space-y-1.5 flex-1 min-w-0">
                            <div className="flex items-center gap-2.5 flex-wrap">
                              <span className="font-bold text-slate-900 text-sm">{tunnel.name}</span>

                              {/* Status Badge */}
                              <span
                                className={`text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase tracking-wider flex items-center gap-1 ${
                                  isRunning
                                    ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
                                    : isStarting
                                    ? "bg-blue-50 text-blue-700 border border-blue-200"
                                    : "bg-slate-100 text-slate-600 border border-slate-200"
                                }`}
                              >
                                <span
                                  className={`w-1.5 h-1.5 rounded-full ${
                                    isRunning
                                      ? "bg-emerald-500 animate-pulse"
                                      : isStarting
                                      ? "bg-blue-500 animate-ping"
                                      : "bg-slate-400"
                                  }`}
                                />
                                {isOperating ? actionText : tunnel.status}
                              </span>

                              {tunnel.pid && (
                                <span className="text-[10px] text-slate-500 font-mono px-1.5 py-0.2 rounded bg-slate-100 border border-slate-200">
                                  PID: {tunnel.pid}
                                </span>
                              )}
                            </div>

                            <div className="flex flex-wrap items-center gap-3 text-xs">
                              {tunnel.public_url ? (
                                <div className="flex items-center gap-1.5 text-[#100091] font-mono bg-indigo-50/80 px-2.5 py-1 rounded-lg border border-indigo-100 font-medium">
                                  <Globe className="w-3.5 h-3.5 text-[#100091]" />
                                  <a
                                    href={tunnel.public_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="hover:underline flex items-center gap-1"
                                  >
                                    <span>{tunnel.public_url}</span>
                                    <ExternalLink className="w-3 h-3 text-slate-400" />
                                  </a>
                                  <button
                                    onClick={() => handleCopy(tunnel.id, tunnel.public_url!)}
                                    className="hover:text-slate-900 transition ml-1 text-slate-400"
                                    title="Copy Public URL"
                                  >
                                    {copiedId === tunnel.id ? (
                                      <Check className="w-3.5 h-3.5 text-emerald-600" />
                                    ) : (
                                      <Copy className="w-3.5 h-3.5" />
                                    )}
                                  </button>
                                </div>
                              ) : (
                                <span className="text-xs text-slate-400 italic">
                                  (Offline • Click Start to generate live Cloudflare URL)
                                </span>
                              )}

                              <span className="text-slate-300 font-bold">→</span>

                              <div className="text-slate-700 font-mono bg-slate-100 px-2.5 py-1 rounded-lg border border-slate-200">
                                http://127.0.0.1:{tunnel.port}
                              </div>
                            </div>
                          </div>

                          {/* Actions */}
                          <div className="flex items-center gap-2 shrink-0">
                            {isRunning ? (
                              <>
                                <button
                                  onClick={() => handleStopTunnel(tunnel)}
                                  disabled={isOperating}
                                  className="px-3 py-1.5 rounded-lg text-xs font-semibold border bg-rose-50 hover:bg-rose-100 text-rose-700 border-rose-200 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                                >
                                  <Pause className="w-3.5 h-3.5" />
                                  <span>Stop</span>
                                </button>

                                <button
                                  onClick={() => handleRestartTunnel(tunnel)}
                                  disabled={isOperating}
                                  className="px-2.5 py-1.5 rounded-lg text-xs font-semibold border bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-300 transition flex items-center gap-1 cursor-pointer disabled:opacity-50"
                                  title="Restart Tunnel"
                                >
                                  <RotateCw className="w-3.5 h-3.5" />
                                </button>
                              </>
                            ) : (
                              <button
                                onClick={() => handleStartTunnel(tunnel)}
                                disabled={isOperating}
                                className="px-3.5 py-1.5 rounded-lg text-xs font-semibold border bg-[#100091] hover:bg-[#0b0068] text-white border-transparent transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50 shadow-sm"
                              >
                                <Play className="w-3.5 h-3.5 fill-current" />
                                <span>Start Tunnel</span>
                              </button>
                            )}

                            <button
                              onClick={() => handleDeleteTunnel(tunnel.id)}
                              disabled={isOperating}
                              className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-200 transition cursor-pointer disabled:opacity-50"
                              title="Delete Tunnel"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            </div>
          )}

          {/* 3. DOMAINS TAB */}
          {activeTab === "domains" && (
            <div className="space-y-6">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/90 shadow-sm">
                <div>
                  <h2 className="text-xl font-extrabold text-slate-900 tracking-tight">
                    Custom Domains Management
                  </h2>
                  <p className="text-sm text-slate-500 mt-1">
                    Map custom domains and subdomains directly to your tunnels using Cloudflare DNS
                  </p>
                </div>
                <button
                  onClick={() => setIsDomainModalOpen(true)}
                  className="px-4 py-2 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-semibold shadow-md shadow-[#100091]/25 transition flex items-center gap-2 cursor-pointer shrink-0"
                >
                  <Plus className="w-4 h-4" />
                  <span>Add Domain</span>
                </button>
              </div>

              {/* Domains List */}
              <div className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-sm">
                <div className="px-6 py-4 border-b border-slate-200/80 bg-slate-50/70 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Globe className="w-4 h-4 text-[#100091]" />
                    <h3 className="font-bold text-slate-900 text-sm">Registered Domains</h3>
                  </div>
                  <span className="text-xs font-medium text-slate-500">{domains.length} Domains</span>
                </div>

                {domains.length === 0 ? (
                  <div className="py-16 text-center text-slate-500 text-sm space-y-3">
                    <p>No custom domains registered yet.</p>
                    <button
                      onClick={() => setIsDomainModalOpen(true)}
                      className="text-[#100091] font-semibold hover:underline text-xs"
                    >
                      + Connect your first custom domain
                    </button>
                  </div>
                ) : (
                  <div className="divide-y divide-slate-100">
                    {domains.map((dom) => (
                      <div
                        key={dom.id}
                        className="p-5 flex items-center justify-between gap-4 hover:bg-slate-50/70 transition"
                      >
                        <div className="space-y-1">
                          <div className="flex items-center gap-2.5">
                            <span className="font-bold text-slate-900 text-sm">{dom.domain}</span>
                            <span className="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                              {dom.validation_status}
                            </span>
                          </div>
                          <p className="text-xs text-slate-400">
                            {dom.tunnels_count || 0} active tunnel routes
                          </p>
                        </div>

                        <button
                          onClick={() => handleDeleteDomain(dom.id)}
                          className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-200 transition cursor-pointer"
                          title="Remove Domain"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Info Box */}
              <div className="p-5 rounded-2xl bg-indigo-50/60 border border-indigo-100 text-slate-700 text-xs space-y-2">
                <div className="font-semibold text-[#100091] flex items-center gap-1.5 text-sm">
                  <Shield className="w-4 h-4" />
                  <span>Cloudflare Nameserver Delegation</span>
                </div>
                <p className="text-slate-600 leading-relaxed">
                  To route tunnels to named custom domains (e.g. <code>api.yourdomain.com</code>), ensure your domain is added to your Cloudflare account. Cloudflared automatically provisions certificates and updates DNS records on tunnel startup.
                </p>
              </div>
            </div>
          )}

          {/* 4. SETTINGS TAB */}
          {activeTab === "settings" && (
            <div className="space-y-6 max-w-4xl">
              <div className="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-sm space-y-6">
                <div>
                  <h2 className="text-xl font-extrabold text-slate-900 tracking-tight">
                    Platform Settings
                  </h2>
                  <p className="text-sm text-slate-500 mt-1">
                    System configuration, binary paths, and credentials management
                  </p>
                </div>

                {/* Profile Card */}
                <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-3">
                  <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                    <UserIcon className="w-3.5 h-3.5 text-[#100091]" />
                    <span>Administrator Account</span>
                  </h4>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                      <span className="text-slate-400 block mb-0.5">Account Name</span>
                      <span className="font-semibold text-slate-900">{user.name}</span>
                    </div>
                    <div>
                      <span className="text-slate-400 block mb-0.5">Primary Email</span>
                      <span className="font-semibold text-slate-900">{user.email}</span>
                    </div>
                    <div>
                      <span className="text-slate-400 block mb-0.5">Role</span>
                      <span className="inline-flex px-2 py-0.5 rounded bg-indigo-50 text-[#100091] font-semibold text-[11px] border border-indigo-200">
                        Super Administrator
                      </span>
                    </div>
                    <div>
                      <span className="text-slate-400 block mb-0.5">Authentication Mode</span>
                      <span className="text-slate-700 font-medium">Direct SQLite (No OTP)</span>
                    </div>
                  </div>
                </div>

                {/* Engine Configuration */}
                <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-3">
                  <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                    <Sliders className="w-3.5 h-3.5 text-[#100091]" />
                    <span>Tunneling Engine Details</span>
                  </h4>
                  <div className="space-y-3 text-xs">
                    <div>
                      <span className="text-slate-400 block mb-1">Bundled Binary</span>
                      <code className="px-2.5 py-1 rounded bg-slate-100 border border-slate-200 text-slate-800 font-mono text-[11px] block overflow-x-auto">
                        src/backend/bin/windows/x64/cloudflared.exe (v2026.7)
                      </code>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                      <div>
                        <span className="text-slate-400 block mb-0.5">Process Timeout</span>
                        <span className="font-semibold text-slate-800">30 seconds</span>
                      </div>
                      <div>
                        <span className="text-slate-400 block mb-0.5">Auto-Restart</span>
                        <span className="font-semibold text-emerald-600">Enabled</span>
                      </div>
                      <div>
                        <span className="text-slate-400 block mb-0.5">Max Active Tunnels</span>
                        <span className="font-semibold text-slate-800">50</span>
                      </div>
                    </div>
                  </div>
                </div>

                {/* SQLite Database */}
                <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-2">
                  <h4 className="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                    <Database className="w-3.5 h-3.5 text-[#100091]" />
                    <span>Database Engine</span>
                  </h4>
                  <div className="text-xs space-y-1">
                    <div className="flex items-center gap-2">
                      <span className="text-slate-400">Driver:</span>
                      <span className="font-mono font-semibold text-slate-800">SQLite v3</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-slate-400">Storage File:</span>
                      <span className="font-mono text-slate-700">database/database.sqlite</span>
                    </div>
                    <div className="flex items-center gap-2 text-emerald-600 font-medium pt-1">
                      <CheckCircle2 className="w-3.5 h-3.5" />
                      <span>Migrations up to date • Healthy</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}
        </main>
      </div>

      {/* ======================================================== */}
      {/* MODAL: CREATE TUNNEL */}
      {/* ======================================================== */}
      {isTunnelModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
          <div className="bg-white border border-slate-200 rounded-2xl w-full max-w-md p-6 relative shadow-2xl">
            <button
              onClick={() => setIsTunnelModalOpen(false)}
              className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition cursor-pointer"
            >
              <X className="w-5 h-5" />
            </button>

            <h3 className="text-lg font-bold text-slate-900 mb-1">Create Cloudflare Tunnel</h3>
            <p className="text-xs text-slate-500 mb-5">
              Forward a local development server port to the internet
            </p>

            <form onSubmit={handleCreateTunnel} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Tunnel Name
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Local Next.js App"
                  value={tunnelForm.name}
                  onChange={(e) => setTunnelForm({ ...tunnelForm, name: e.target.value })}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091]"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Local Port
                </label>
                <input
                  type="number"
                  required
                  min={1}
                  max={65535}
                  value={tunnelForm.port}
                  onChange={(e) => setTunnelForm({ ...tunnelForm, port: Number(e.target.value) })}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091]"
                />
                <p className="text-[11px] text-slate-400 mt-1">
                  Target local server address: http://127.0.0.1:{tunnelForm.port}
                </p>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Custom Subdomain Slug (Optional)
                </label>
                <input
                  type="text"
                  placeholder="e.g. my-app"
                  value={tunnelForm.custom_subdomain}
                  onChange={(e) => setTunnelForm({ ...tunnelForm, custom_subdomain: e.target.value })}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091]"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-3">
                <button
                  type="button"
                  onClick={() => setIsTunnelModalOpen(false)}
                  className="px-4 py-2 rounded-xl text-slate-500 hover:text-slate-800 text-xs font-medium transition cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={creatingTunnel}
                  className="px-4 py-2 rounded-xl bg-[#100091] text-white text-xs font-semibold hover:bg-[#0b0068] transition shadow-md shadow-[#100091]/25 disabled:opacity-50 cursor-pointer"
                >
                  {creatingTunnel ? "Creating..." : "Save Tunnel"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ======================================================== */}
      {/* MODAL: ADD CUSTOM DOMAIN */}
      {/* ======================================================== */}
      {isDomainModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
          <div className="bg-white border border-slate-200 rounded-2xl w-full max-w-md p-6 relative shadow-2xl">
            <button
              onClick={() => setIsDomainModalOpen(false)}
              className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition cursor-pointer"
            >
              <X className="w-5 h-5" />
            </button>

            <h3 className="text-lg font-bold text-slate-900 mb-1">Connect Custom Domain</h3>
            <p className="text-xs text-slate-500 mb-5">
              Add a domain delegated to your Cloudflare account
            </p>

            <form onSubmit={handleCreateDomain} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Domain Name
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. dev.mycompany.com"
                  value={domainInput}
                  onChange={(e) => setDomainInput(e.target.value)}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091]"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-3">
                <button
                  type="button"
                  onClick={() => setIsDomainModalOpen(false)}
                  className="px-4 py-2 rounded-xl text-slate-500 hover:text-slate-800 text-xs font-medium transition cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={creatingDomain}
                  className="px-4 py-2 rounded-xl bg-[#100091] text-white text-xs font-semibold hover:bg-[#0b0068] transition shadow-md shadow-[#100091]/25 disabled:opacity-50 cursor-pointer"
                >
                  {creatingDomain ? "Adding..." : "Add Domain"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
