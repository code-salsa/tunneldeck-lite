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
  AlertTriangle,
  Flame,
} from "lucide-react";
import { api, Activity, Tunnel, TunnelMetrics, User } from "@/lib/api";

interface DashboardProps {
  user: User;
  onLogout: () => void;
}

export default function Dashboard({ user, onLogout }: DashboardProps) {
  const [tunnels, setTunnels] = useState<Tunnel[]>([]);
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
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [creating, setCreating] = useState(false);

  // New tunnel form state
  const [formData, setFormData] = useState({
    name: "",
    port: 3000,
    custom_subdomain: "",
  });

  const fetchTunnelsAndStats = async () => {
    try {
      setLoading(true);
      const [tunnelsRes, statsRes] = await Promise.all([
        api.getTunnels(),
        api.getStats().catch(() => null),
      ]);

      setTunnels(tunnelsRes.data.tunnels);
      setMetrics(tunnelsRes.data.metrics);
      if (statsRes?.activities) {
        setActivities(statsRes.activities);
      }
    } catch (err) {
      console.error("Failed to load tunnels:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTunnelsAndStats();

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

  const handleStart = async (tunnel: Tunnel) => {
    setActionLoadingId(tunnel.id);
    setActionText("Starting cloudflared...");
    try {
      await api.startTunnel(tunnel.id);
      await fetchTunnelsAndStats();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to start tunnel");
    } finally {
      setActionLoadingId(null);
      setActionText("");
    }
  };

  const handleStop = async (tunnel: Tunnel) => {
    setActionLoadingId(tunnel.id);
    setActionText("Stopping process...");
    try {
      await api.stopTunnel(tunnel.id);
      await fetchTunnelsAndStats();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to stop tunnel");
    } finally {
      setActionLoadingId(null);
      setActionText("");
    }
  };

  const handleRestart = async (tunnel: Tunnel) => {
    setActionLoadingId(tunnel.id);
    setActionText("Restarting tunnel...");
    try {
      await api.restartTunnel(tunnel.id);
      await fetchTunnelsAndStats();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to restart tunnel");
    } finally {
      setActionLoadingId(null);
      setActionText("");
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Are you sure you want to stop and delete this tunnel?")) return;
    try {
      await api.deleteTunnel(id);
      await fetchTunnelsAndStats();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to delete tunnel");
    }
  };

  const handleCreateTunnel = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreating(true);
    try {
      await api.createTunnel({
        name: formData.name,
        port: Number(formData.port),
        custom_subdomain: formData.custom_subdomain || undefined,
      });
      setIsModalOpen(false);
      setFormData({
        name: "",
        port: 3000,
        custom_subdomain: "",
      });
      await fetchTunnelsAndStats();
    } catch (err: unknown) {
      alert(err instanceof Error ? err.message : "Failed to create tunnel");
    } finally {
      setCreating(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 flex flex-col">
      {/* Top Navbar */}
      <header className="border-b border-slate-200/90 bg-white/95 backdrop-blur-md sticky top-0 z-40 shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-[#100091] flex items-center justify-center shadow-md shadow-[#100091]/25">
              <Radio className="w-5 h-5 text-white animate-pulse" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <span className="font-extrabold text-base text-slate-900 tracking-tight">
                  TunnelDeck
                </span>
                <span className="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-indigo-50 text-[#100091] border border-indigo-200/80">
                  Lite
                </span>
              </div>
              <p className="text-[11px] text-slate-500 font-medium">Cloudflared Tunnel Engine</p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            {/* SQLite Badge */}
            <div className="hidden sm:flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-50 border border-slate-200 text-xs font-medium text-slate-700">
              <Database className="w-3.5 h-3.5 text-[#100091]" />
              <span>SQLite</span>
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
            </div>

            {/* User display */}
            <div className="flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200">
              <div className="w-7 h-7 rounded-full bg-[#100091] text-white font-bold text-xs flex items-center justify-center shadow-sm">
                {user.name.charAt(0)}
              </div>
              <div className="hidden md:block text-left text-xs">
                <div className="font-semibold text-slate-800">{user.name}</div>
                <div className="text-[11px] text-slate-500">{user.email}</div>
              </div>
            </div>

            {/* Logout button */}
            <button
              onClick={onLogout}
              className="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-200 transition cursor-pointer"
              title="Sign Out"
            >
              <LogOut className="w-4 h-4" />
            </button>
          </div>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-7">
        {/* Welcome Banner & Action Button */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-slate-200/90 shadow-sm">
          <div>
            <h2 className="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2.5">
              <span>Cloudflare Tunnel Manager</span>
              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">
                <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                Live Binary
              </span>
            </h2>
            <p className="text-sm text-slate-500 mt-1">
              Forward local ports instantly to the internet with high-speed Cloudflare tunnels
            </p>
          </div>
          <div className="flex items-center gap-3">
            <button
              onClick={fetchTunnelsAndStats}
              disabled={loading}
              className="px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold border border-slate-300 transition flex items-center gap-2 cursor-pointer disabled:opacity-50 shadow-sm"
            >
              <RefreshCw className={`w-3.5 h-3.5 text-slate-500 ${loading ? "animate-spin" : ""}`} />
              <span>Refresh</span>
            </button>

            <button
              onClick={() => setIsModalOpen(true)}
              className="px-4 py-2 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-semibold shadow-md shadow-[#100091]/25 transition flex items-center gap-2 cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>New Tunnel</span>
            </button>
          </div>
        </div>

        {/* Metrics Row */}
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
              <span className="text-xs font-semibold uppercase tracking-wider">Stopped Tunnels</span>
              <Clock className="w-4 h-4 text-amber-500" />
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-3xl font-extrabold text-slate-900">{metrics.paused}</span>
              <span className="text-xs text-amber-600 font-semibold">Idle</span>
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

        {/* Tunnels List */}
        <div className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-sm">
          <div className="px-6 py-4 border-b border-slate-200/80 bg-slate-50/70 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Server className="w-4 h-4 text-[#100091]" />
              <h3 className="font-bold text-slate-900 text-sm">Active & Configured Tunnels</h3>
            </div>
            <span className="text-xs font-medium text-slate-500">{tunnels.length} Registered</span>
          </div>

          {loading ? (
            <div className="py-16 text-center text-slate-500 text-sm">
              <div className="w-6 h-6 border-2 border-[#100091] border-t-transparent rounded-full animate-spin mx-auto mb-2" />
              <span>Checking tunnel process states...</span>
            </div>
          ) : tunnels.length === 0 ? (
            <div className="py-16 text-center text-slate-500 text-sm">
              <p>No tunnels registered yet.</p>
              <button
                onClick={() => setIsModalOpen(true)}
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
                              : tunnel.status === "starting" || isOperating
                              ? "bg-blue-50 text-blue-700 border border-blue-200"
                              : tunnel.status === "error"
                              ? "bg-rose-50 text-rose-700 border border-rose-200"
                              : "bg-slate-100 text-slate-600 border border-slate-200"
                          }`}
                        >
                          <span
                            className={`w-1.5 h-1.5 rounded-full ${
                              isRunning
                                ? "bg-emerald-500 animate-pulse"
                                : isStarting
                                ? "bg-blue-500 animate-ping"
                                : tunnel.status === "error"
                                ? "bg-rose-500"
                                : "bg-slate-400"
                            }`}
                          />
                          {isOperating ? actionText : tunnel.status}
                        </span>

                        {/* PID badge if running */}
                        {tunnel.pid && (
                          <span className="text-[10px] text-slate-500 font-mono px-1.5 py-0.2 rounded bg-slate-100 border border-slate-200">
                            PID: {tunnel.pid}
                          </span>
                        )}
                      </div>

                      <div className="flex flex-wrap items-center gap-3 text-xs">
                        {/* Public Endpoint */}
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

                        {/* Local Target Port */}
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
                            onClick={() => handleStop(tunnel)}
                            disabled={isOperating}
                            className="px-3 py-1.5 rounded-lg text-xs font-semibold border bg-rose-50 hover:bg-rose-100 text-rose-700 border-rose-200 transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
                          >
                            <Pause className="w-3.5 h-3.5" />
                            <span>Stop</span>
                          </button>

                          <button
                            onClick={() => handleRestart(tunnel)}
                            disabled={isOperating}
                            className="px-2.5 py-1.5 rounded-lg text-xs font-semibold border bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-300 transition flex items-center gap-1 cursor-pointer disabled:opacity-50"
                            title="Restart Tunnel"
                          >
                            <RotateCw className="w-3.5 h-3.5" />
                          </button>
                        </>
                      ) : (
                        <button
                          onClick={() => handleStart(tunnel)}
                          disabled={isOperating}
                          className="px-3.5 py-1.5 rounded-lg text-xs font-semibold border bg-[#100091] hover:bg-[#0b0068] text-white border-transparent transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50 shadow-sm"
                        >
                          <Play className="w-3.5 h-3.5 fill-current" />
                          <span>Start Tunnel</span>
                        </button>
                      )}

                      <button
                        onClick={() => handleDelete(tunnel.id)}
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

        {/* Recent Activities Section */}
        {activities.length > 0 && (
          <div className="bg-white border border-slate-200/90 rounded-2xl p-6 shadow-sm">
            <h4 className="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
              <ActivityIcon className="w-4 h-4 text-[#100091]" />
              <span>Recent Tunnel Activity Logs</span>
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
      </main>

      {/* Modal: New Tunnel */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
          <div className="bg-white border border-slate-200 rounded-2xl w-full max-w-md p-6 relative shadow-2xl">
            <button
              onClick={() => setIsModalOpen(false)}
              className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition"
            >
              <X className="w-5 h-5" />
            </button>

            <h3 className="text-lg font-bold text-slate-900 mb-1">Create Cloudflare Tunnel</h3>
            <p className="text-xs text-slate-500 mb-5">
              Set up a forwarding rule for your local application port
            </p>

            <form onSubmit={handleCreateTunnel} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Tunnel Name
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. My Next.js Web App"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
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
                  value={formData.port}
                  onChange={(e) => setFormData({ ...formData, port: Number(e.target.value) })}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091]"
                />
                <p className="text-[11px] text-slate-400 mt-1">
                  Target local server address: http://127.0.0.1:{formData.port}
                </p>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Custom Subdomain Slug (Optional)
                </label>
                <input
                  type="text"
                  placeholder="e.g. my-app"
                  value={formData.custom_subdomain}
                  onChange={(e) => setFormData({ ...formData, custom_subdomain: e.target.value })}
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091]"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-3">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 rounded-xl text-slate-500 hover:text-slate-800 text-xs font-medium transition cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={creating}
                  className="px-4 py-2 rounded-xl bg-[#100091] text-white text-xs font-semibold hover:bg-[#0b0068] transition shadow-md shadow-[#100091]/25 disabled:opacity-50 cursor-pointer"
                >
                  {creating ? "Creating..." : "Save Tunnel"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
