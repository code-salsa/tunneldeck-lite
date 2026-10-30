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
  Key,
  Eye,
  EyeOff,
  Save,
  PlusCircle,
  Pencil,
  Clipboard,
} from "lucide-react";
import { api, Activity, CustomDomain, Tunnel, TunnelMetrics, User } from "@/lib/api";
import CustomDomainsView from "./CustomDomainsView";

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

  // Custom Delete Tunnel Modal
  const [tunnelToDelete, setTunnelToDelete] = useState<Tunnel | null>(null);
  const [deletingTunnel, setDeletingTunnel] = useState(false);

  // Edit Tunnel Modal
  const [editingTunnel, setEditingTunnel] = useState<Tunnel | null>(null);
  const [editForm, setEditForm] = useState({ name: "", port: 3000 });
  const [savingEdit, setSavingEdit] = useState(false);

  // Toast Notification
  const [toastMessage, setToastMessage] = useState<string | null>(null);

  // Form states
  const [tunnelForm, setTunnelForm] = useState<{
    name: string;
    port: number | string;
    custom_subdomain: string;
  }>({
    name: "",
    port: "",
    custom_subdomain: "",
  });
  const [domainInput, setDomainInput] = useState("");

  // Password Change State (Platform Settings)
  const [currentPasswordDisplay, setCurrentPasswordDisplay] = useState("123456");
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [autofillToggle, setAutofillToggle] = useState(true);
  const [savingPassword, setSavingPassword] = useState(false);
  const [passwordStatusMsg, setPasswordStatusMsg] = useState<{
    type: "success" | "error";
    text: string;
  } | null>(null);

  useEffect(() => {
    if (typeof window !== "undefined") {
      const savedPw = localStorage.getItem("tunneldeck_saved_password") || "123456";
      setCurrentPasswordDisplay(savedPw);
      const autofillEnabled = localStorage.getItem("tunneldeck_autofill_password_enabled") !== "false";
      setAutofillToggle(autofillEnabled);
    }
  }, []);

  const handleSavePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    setPasswordStatusMsg(null);

    if (newPassword.length < 6) {
      setPasswordStatusMsg({
        type: "error",
        text: "New password must be at least 6 characters long.",
      });
      return;
    }

    if (newPassword !== confirmPassword) {
      setPasswordStatusMsg({
        type: "error",
        text: "New password and confirmation do not match.",
      });
      return;
    }

    setSavingPassword(true);
    try {
      await api.updatePassword(newPassword, currentPasswordDisplay);

      // Save in localStorage for UI & login page autofill
      if (typeof window !== "undefined") {
        localStorage.setItem("tunneldeck_saved_password", newPassword);
        localStorage.setItem("tunneldeck_autofill_password_enabled", autofillToggle ? "true" : "false");
      }

      setCurrentPasswordDisplay(newPassword);
      setNewPassword("");
      setConfirmPassword("");
      setPasswordStatusMsg({
        type: "success",
        text: "Password updated successfully! Login page autofill preferences saved.",
      });
    } catch (err) {
      setPasswordStatusMsg({
        type: "error",
        text: err instanceof Error ? err.message : "Failed to update password.",
      });
    } finally {
      setSavingPassword(false);
    }
  };

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

  const confirmDeleteTunnel = async () => {
    if (!tunnelToDelete) return;
    setDeletingTunnel(true);
    try {
      await api.deleteTunnel(tunnelToDelete.id);
      setTunnelToDelete(null);
      await fetchData();
      setToastMessage("Tunnel removed successfully.");
      setTimeout(() => {
        setToastMessage((prev) => (prev === "Tunnel removed successfully." ? null : prev));
      }, 4000);
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to delete tunnel");
    } finally {
      setDeletingTunnel(false);
    }
  };

  const handleCreateTunnel = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!tunnelForm.name.trim() || !tunnelForm.port) return;
    setCreatingTunnel(true);
    try {
      await api.createTunnel({
        name: tunnelForm.name.trim(),
        port: Number(tunnelForm.port),
      });
      setIsTunnelModalOpen(false);
      setTunnelForm({
        name: "",
        port: "",
        custom_subdomain: "",
      });
      await fetchData();

      // Trigger toast notification matching user request
      setToastMessage("Tunnel added successfully.");
      setTimeout(() => {
        setToastMessage((prev) => (prev === "Tunnel added successfully." ? null : prev));
      }, 4000);
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to create tunnel");
    } finally {
      setCreatingTunnel(false);
    }
  };

  const handleOpenEditTunnel = (tunnel: Tunnel) => {
    setEditingTunnel(tunnel);
    setEditForm({
      name: tunnel.name,
      port: tunnel.port,
    });
  };

  const handleSaveEditTunnel = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingTunnel || !editForm.name.trim() || !editForm.port) return;
    setSavingEdit(true);
    try {
      await api.updateTunnel(editingTunnel.id, {
        name: editForm.name.trim(),
        port: Number(editForm.port),
      });
      setEditingTunnel(null);
      await fetchData();
      setToastMessage("Tunnel updated successfully.");
      setTimeout(() => {
        setToastMessage((prev) => (prev === "Tunnel updated successfully." ? null : prev));
      }, 4000);
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to update tunnel");
    } finally {
      setSavingEdit(false);
    }
  };

  const formatCreatedDate = (dateStr?: string | null) => {
    if (!dateStr) return "31 Oct 2026";
    try {
      const date = new Date(dateStr);
      return date.toLocaleDateString("en-GB", {
        day: "numeric",
        month: "short",
        year: "numeric",
      });
    } catch {
      return dateStr;
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
              {/* Header */}
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
                  type="button"
                  onClick={() => setIsTunnelModalOpen(true)}
                  className="px-4 py-2.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-semibold shadow-md shadow-[#100091]/25 transition flex items-center gap-2 cursor-pointer shrink-0"
                >
                  <Plus className="w-4 h-4" />
                  <span>Add New Tunnel</span>
                </button>
              </div>

              {/* Tunnels Table Container matching Screenshot 2 */}
              <div className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-sm">
                {loading ? (
                  <div className="py-16 text-center text-slate-500 text-sm">
                    <div className="w-6 h-6 border-2 border-[#100091] border-t-transparent rounded-full animate-spin mx-auto mb-2" />
                    <span>Querying process table...</span>
                  </div>
                ) : tunnels.length === 0 ? (
                  <div className="py-16 text-center text-slate-500 text-sm">
                    <p className="font-medium text-slate-700">No tunnels registered yet.</p>
                    <p className="text-xs text-slate-400 mt-1">Create your first forwarding tunnel to expose local ports.</p>
                    <button
                      type="button"
                      onClick={() => setIsTunnelModalOpen(true)}
                      className="mt-4 px-4 py-2 rounded-xl bg-[#100091] text-white text-xs font-semibold hover:bg-[#0b0068] shadow-sm transition cursor-pointer"
                    >
                      + Add New Tunnel
                    </button>
                  </div>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                      <thead>
                        <tr className="border-b border-slate-200/80 bg-slate-50/70">
                          <th className="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            STATUS
                          </th>
                          <th className="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            TUNNEL NAME
                          </th>
                          <th className="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            LOCAL URL
                          </th>
                          <th className="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            PUBLIC URL
                          </th>
                          <th className="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            CREATED DATE
                          </th>
                          <th className="py-3.5 px-6 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">
                            ACTIONS
                          </th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100">
                        {tunnels.map((tunnel) => {
                          const isOperating = actionLoadingId === tunnel.id;
                          const isRunning = tunnel.status === "running";
                          const isStarting = tunnel.status === "starting" || isOperating;

                          return (
                            <tr
                              key={tunnel.id}
                              className="hover:bg-slate-50/80 transition group"
                            >
                              {/* 1. STATUS */}
                              <td className="py-4 px-6 whitespace-nowrap">
                                {isRunning ? (
                                  <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                    <span>Running</span>
                                  </span>
                                ) : isStarting ? (
                                  <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                    <span className="w-1.5 h-1.5 rounded-full bg-blue-500 animate-ping" />
                                    <span>{actionText || "Starting..."}</span>
                                  </span>
                                ) : (
                                  <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100/90 text-slate-600 border border-slate-200">
                                    <span className="w-1.5 h-1.5 rounded-full bg-slate-400" />
                                    <span>Stopped</span>
                                  </span>
                                )}
                              </td>

                              {/* 2. TUNNEL NAME */}
                              <td className="py-4 px-6 font-bold text-slate-900 text-sm whitespace-nowrap">
                                {tunnel.name}
                              </td>

                              {/* 3. LOCAL URL */}
                              <td className="py-4 px-6 whitespace-nowrap">
                                <div className="inline-flex items-center gap-2">
                                  <span className="px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-xs font-mono text-slate-700">
                                    http://localhost:{tunnel.port}
                                  </span>
                                  <button
                                    type="button"
                                    onClick={() =>
                                      handleCopy(tunnel.id, `http://localhost:${tunnel.port}`)
                                    }
                                    className="p-1 text-slate-400 hover:text-slate-600 transition cursor-pointer"
                                    title="Copy Local URL"
                                  >
                                    {copiedId === tunnel.id ? (
                                      <Check className="w-4 h-4 text-emerald-600" />
                                    ) : (
                                      <Clipboard className="w-4 h-4" />
                                    )}
                                  </button>
                                </div>
                              </td>

                              {/* 4. PUBLIC URL */}
                              <td className="py-4 px-6 whitespace-nowrap">
                                {isRunning && tunnel.public_url ? (
                                  <div className="inline-flex items-center gap-2">
                                    <a
                                      href={tunnel.public_url}
                                      target="_blank"
                                      rel="noreferrer"
                                      className="text-xs font-mono text-[#100091] hover:underline flex items-center gap-1 font-medium max-w-xs truncate"
                                    >
                                      <span>{tunnel.public_url}</span>
                                      <ExternalLink className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                    </a>
                                    <button
                                      type="button"
                                      onClick={() =>
                                        handleCopy(tunnel.id + 100000, tunnel.public_url!)
                                      }
                                      className="p-1 text-slate-400 hover:text-slate-600 transition cursor-pointer shrink-0"
                                      title="Copy Public URL"
                                    >
                                      {copiedId === tunnel.id + 100000 ? (
                                        <Check className="w-3.5 h-3.5 text-emerald-600" />
                                      ) : (
                                        <Copy className="w-3.5 h-3.5" />
                                      )}
                                    </button>
                                  </div>
                                ) : (
                                  <span className="text-xs text-slate-500 font-normal">
                                    Not Running
                                  </span>
                                )}
                              </td>

                              {/* 5. CREATED DATE */}
                              <td className="py-4 px-6 text-xs text-slate-600 whitespace-nowrap">
                                {formatCreatedDate(tunnel.created_at)}
                              </td>

                              {/* 6. ACTIONS */}
                              <td className="py-4 px-6 text-right whitespace-nowrap">
                                <div className="inline-flex items-center justify-end gap-2">
                                  {isRunning ? (
                                    <button
                                      type="button"
                                      onClick={() => handleStopTunnel(tunnel)}
                                      disabled={isOperating}
                                      className="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold flex items-center gap-1.5 shadow-2xs transition cursor-pointer disabled:opacity-50"
                                      title="Stop Tunnel"
                                    >
                                      <Pause className="w-3 h-3 fill-current" />
                                      <span>Stop</span>
                                    </button>
                                  ) : (
                                    <button
                                      type="button"
                                      onClick={() => handleStartTunnel(tunnel)}
                                      disabled={isOperating}
                                      className="px-3.5 py-1.5 rounded-lg bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold flex items-center gap-1.5 shadow-2xs transition cursor-pointer disabled:opacity-50"
                                      title="Start Tunnel"
                                    >
                                      <Play className="w-3 h-3 fill-current" />
                                      <span>Start</span>
                                    </button>
                                  )}

                                  {/* Edit Button */}
                                  <button
                                    type="button"
                                    onClick={() => handleOpenEditTunnel(tunnel)}
                                    disabled={isOperating}
                                    className="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500 hover:text-slate-700 transition cursor-pointer disabled:opacity-50"
                                    title="Edit Tunnel"
                                  >
                                    <Pencil className="w-4 h-4" />
                                  </button>

                                  {/* Delete Button */}
                                  <button
                                    type="button"
                                    onClick={() => setTunnelToDelete(tunnel)}
                                    disabled={isOperating}
                                    className="p-1.5 rounded-lg border border-slate-200 hover:bg-rose-50 text-rose-500 hover:text-rose-600 transition cursor-pointer disabled:opacity-50"
                                    title="Delete Tunnel"
                                  >
                                    <Trash2 className="w-4 h-4 stroke-[1.8]" />
                                  </button>
                                </div>
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* 3. DOMAINS TAB */}
          {activeTab === "domains" && (
            <CustomDomainsView />
          )}

          {/* 4. SETTINGS TAB */}
          {activeTab === "settings" && (
            <div className="space-y-6 w-full max-w-7xl">
              <div>
                <h2 className="text-3xl font-extrabold text-slate-900 tracking-tight font-sans">
                  Platform Settings
                </h2>
                <p className="text-sm text-slate-500 mt-1">
                  System configuration, binary paths, credentials &amp; security management
                </p>
              </div>

              <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start w-full">
                {/* Left Column: Platform Configuration (7 cols on lg/xl) */}
                <div className="lg:col-span-7 xl:col-span-7 space-y-6 w-full">
                  <div className="bg-white p-6 rounded-2xl border border-slate-200/90 shadow-2xs space-y-6">
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

                {/* Right Sidebar: Change Password (5 cols on lg/xl) */}
                <div className="lg:col-span-5 xl:col-span-5 w-full space-y-4 lg:sticky lg:top-6">
                  <div className="bg-white border border-slate-200/90 rounded-2xl shadow-2xs p-6 space-y-5 w-full">
                    {/* Header */}
                    <div className="flex items-center gap-3 border-b border-slate-100 pb-4">
                      <div className="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[#100091] shadow-2xs">
                        <Key className="w-5 h-5" />
                      </div>
                      <div>
                        <h3 className="font-extrabold text-slate-900 text-sm tracking-tight">
                          Change Password
                        </h3>
                        <p className="text-[11px] text-slate-400">
                          Update your login credentials
                        </p>
                      </div>
                    </div>

                    {/* Show current password box */}
                    <div className="p-4 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1.5">
                      <div className="flex items-center justify-between text-xs">
                        <span className="text-slate-500 font-medium">Your current password:</span>
                        <button
                          type="button"
                          onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                          className="text-[#100091] hover:text-[#0b0068] text-[11px] font-semibold transition cursor-pointer flex items-center gap-1"
                        >
                          {showCurrentPassword ? (
                            <>
                              <EyeOff className="w-3.5 h-3.5" />
                              <span>Hide</span>
                            </>
                          ) : (
                            <>
                              <Eye className="w-3.5 h-3.5" />
                              <span>Show</span>
                            </>
                          )}
                        </button>
                      </div>
                      <div className="font-mono text-sm font-bold text-slate-900 bg-white border border-slate-200 px-3 py-1.5 rounded-lg flex items-center justify-between">
                        <span>{showCurrentPassword ? currentPasswordDisplay : "••••••••"}</span>
                        <span className="text-[10px] text-slate-400 font-sans font-medium uppercase tracking-wider bg-slate-100 px-1.5 py-0.5 rounded">
                          Active
                        </span>
                      </div>
                    </div>

                    {/* Status notification */}
                    {passwordStatusMsg && (
                      <div
                        className={`p-3 rounded-xl text-xs flex items-start gap-2 ${
                          passwordStatusMsg.type === "success"
                            ? "bg-emerald-50 border border-emerald-200 text-emerald-700"
                            : "bg-rose-50 border border-rose-200 text-rose-700"
                        }`}
                      >
                        {passwordStatusMsg.type === "success" ? (
                          <CheckCircle2 className="w-4 h-4 shrink-0 mt-0.5 text-emerald-600" />
                        ) : (
                          <X className="w-4 h-4 shrink-0 mt-0.5 text-rose-600" />
                        )}
                        <span>{passwordStatusMsg.text}</span>
                      </div>
                    )}

                    {/* Change password form */}
                    <form onSubmit={handleSavePassword} className="space-y-4">
                      {/* Enter new password */}
                      <div>
                        <label className="block text-xs font-bold text-slate-700 mb-1.5">
                          Enter New Password
                        </label>
                        <div className="relative">
                          <input
                            type={showNewPassword ? "text" : "password"}
                            required
                            minLength={6}
                            value={newPassword}
                            onChange={(e) => setNewPassword(e.target.value)}
                            placeholder="Enter at least 6 characters"
                            className="w-full px-3.5 pr-10 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition"
                          />
                          <button
                            type="button"
                            onClick={() => setShowNewPassword(!showNewPassword)}
                            className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition"
                          >
                            {showNewPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                          </button>
                        </div>
                      </div>

                      {/* Confirm new password */}
                      <div>
                        <label className="block text-xs font-bold text-slate-700 mb-1.5">
                          Confirm New Password
                        </label>
                        <div className="relative">
                          <input
                            type={showConfirmPassword ? "text" : "password"}
                            required
                            minLength={6}
                            value={confirmPassword}
                            onChange={(e) => setConfirmPassword(e.target.value)}
                            placeholder="Re-enter your new password"
                            className="w-full px-3.5 pr-10 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition"
                          />
                          <button
                            type="button"
                            onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                            className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition"
                          >
                            {showConfirmPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                          </button>
                        </div>
                      </div>

                      {/* Toggle for login page autofill */}
                      <div className="pt-1 pb-1">
                        <label className="flex items-start gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200/80 cursor-pointer select-none hover:bg-slate-100/60 transition">
                          <div className="relative inline-flex items-center shrink-0 mt-0.5">
                            <input
                              type="checkbox"
                              checked={autofillToggle}
                              onChange={(e) => setAutofillToggle(e.target.checked)}
                              className="sr-only peer"
                            />
                            <div className="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#100091]"></div>
                          </div>
                          <div className="space-y-0.5 text-left">
                            <span className="block text-xs font-bold text-slate-800">
                              Autofill in login page password field
                            </span>
                            <span className="block text-[11px] text-slate-500 leading-relaxed">
                              When enabled, your password is automatically filled when visiting the login page.
                            </span>
                          </div>
                        </label>
                      </div>

                      {/* Save button */}
                      <button
                        type="submit"
                        disabled={savingPassword}
                        className="w-full py-2.5 px-4 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white font-bold text-xs shadow-md shadow-[#100091]/25 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                      >
                        {savingPassword ? (
                          <>
                            <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                            <span>Saving Changes...</span>
                          </>
                        ) : (
                          <>
                            <Save className="w-4 h-4" />
                            <span>Save Password</span>
                          </>
                        )}
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          )}
        </main>
      </div>

      {/* ======================================================== */}
      {/* MODAL: ADD NEW TUNNEL (Matching Screenshot 1) */}
      {/* ======================================================== */}
      {isTunnelModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150">
          <div className="bg-white border border-slate-200 rounded-2xl w-full max-w-md p-6 relative shadow-2xl space-y-5">
            {/* Modal Header */}
            <div className="flex items-center justify-between pb-4 border-b border-slate-100">
              <div className="flex items-center gap-2.5">
                <PlusCircle className="w-5 h-5 text-blue-600 shrink-0" />
                <h3 className="text-base font-bold text-slate-900 tracking-tight">
                  Add New Tunnel
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setIsTunnelModalOpen(false)}
                className="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer"
                title="Close"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Modal Form */}
            <form onSubmit={handleCreateTunnel} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Tunnel Name
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Dev Server"
                  value={tunnelForm.name}
                  onChange={(e) => setTunnelForm({ ...tunnelForm, name: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-white border border-blue-400 rounded-xl text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-100 transition"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Port Number
                </label>
                <input
                  type="number"
                  required
                  min={1}
                  max={65535}
                  placeholder="e.g. 3000, 8080"
                  value={tunnelForm.port || ""}
                  onChange={(e) =>
                    setTunnelForm({ ...tunnelForm, port: e.target.value ? Number(e.target.value) : "" })
                  }
                  className="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100 transition"
                />
              </div>

              {/* Modal Footer */}
              <div className="border-t border-slate-100 pt-4 mt-6 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setIsTunnelModalOpen(false)}
                  className="px-5 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={creatingTunnel}
                  className="px-5 py-2 rounded-xl bg-[#2563eb] hover:bg-[#1d4ed8] text-white text-xs font-bold shadow-sm transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-1.5"
                >
                  {creatingTunnel ? (
                    <>
                      <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                      <span>Adding...</span>
                    </>
                  ) : (
                    <span>Add Tunnel</span>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ======================================================== */}
      {/* MODAL: EDIT TUNNEL */}
      {/* ======================================================== */}
      {editingTunnel && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150">
          <div className="bg-white border border-slate-200 rounded-2xl w-full max-w-md p-6 relative shadow-2xl space-y-5">
            <div className="flex items-center justify-between pb-4 border-b border-slate-100">
              <div className="flex items-center gap-2.5">
                <Pencil className="w-5 h-5 text-blue-600 shrink-0" />
                <h3 className="text-base font-bold text-slate-900 tracking-tight">
                  Edit Tunnel
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setEditingTunnel(null)}
                className="p-1 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer"
                title="Close"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveEditTunnel} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Tunnel Name
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Dev Server"
                  value={editForm.name}
                  onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                  className="w-full px-3.5 py-2.5 bg-white border border-blue-400 rounded-xl text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-100 transition"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Port Number
                </label>
                <input
                  type="number"
                  required
                  min={1}
                  max={65535}
                  placeholder="e.g. 3000, 8080"
                  value={editForm.port || ""}
                  onChange={(e) =>
                    setEditForm({ ...editForm, port: Number(e.target.value) })
                  }
                  className="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100 transition"
                />
              </div>

              <div className="border-t border-slate-100 pt-4 mt-6 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setEditingTunnel(null)}
                  className="px-5 py-2 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={savingEdit}
                  className="px-5 py-2 rounded-xl bg-[#2563eb] hover:bg-[#1d4ed8] text-white text-xs font-bold shadow-sm transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-1.5"
                >
                  {savingEdit ? (
                    <>
                      <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                      <span>Saving...</span>
                    </>
                  ) : (
                    <span>Save Changes</span>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ======================================================== */}
      {/* MODAL: CUSTOM DELETE TUNNEL CONFIRMATION */}
      {/* ======================================================== */}
      {tunnelToDelete && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150">
          <div className="bg-white border border-slate-200 rounded-3xl w-full max-w-md p-7 relative shadow-2xl text-center space-y-4">
            <div className="w-14 h-14 rounded-2xl bg-rose-50 border border-rose-100 text-rose-500 flex items-center justify-center mx-auto shadow-2xs">
              <Trash2 className="w-6 h-6 stroke-[2.2]" />
            </div>

            <div className="space-y-1.5">
              <h3 className="text-xl font-extrabold text-slate-900 tracking-tight">
                Delete Tunnel
              </h3>
              <p className="text-xs text-slate-500 leading-relaxed px-2">
                Are you sure you want to stop and delete tunnel <strong className="text-slate-800">{tunnelToDelete.name}</strong> (Port {tunnelToDelete.port})? The local process and public URL will be stopped immediately.
              </p>
            </div>

            <div className="pt-2 flex items-center justify-center gap-3">
              <button
                type="button"
                onClick={() => setTunnelToDelete(null)}
                className="w-full py-2.5 px-4 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition cursor-pointer"
              >
                Cancel
              </button>

              <button
                type="button"
                onClick={confirmDeleteTunnel}
                disabled={deletingTunnel}
                className="w-full py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-md shadow-rose-600/20 transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-1.5"
              >
                {deletingTunnel ? (
                  <>
                    <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    <span>Deleting...</span>
                  </>
                ) : (
                  <>
                    <Trash2 className="w-3.5 h-3.5" />
                    <span>Delete Tunnel</span>
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ======================================================== */}
      {/* FLOATING TOAST NOTIFICATION */}
      {/* ======================================================== */}
      {toastMessage && (
        <div className="fixed top-6 right-6 z-50 flex items-center gap-3 px-4 py-3 bg-white border border-emerald-200 text-slate-800 rounded-2xl shadow-xl shadow-slate-900/10 animate-in slide-in-from-top-3 fade-in duration-200">
          <div className="w-7 h-7 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
            <CheckCircle2 className="w-4 h-4" />
          </div>
          <span className="text-xs sm:text-sm font-semibold text-slate-800">{toastMessage}</span>
          <button
            type="button"
            onClick={() => setToastMessage(null)}
            className="ml-2 text-slate-400 hover:text-slate-600 transition cursor-pointer"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

    </div>
  );
}
