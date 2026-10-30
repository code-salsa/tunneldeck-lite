"use client";

import React, { useState, useEffect } from "react";
import {
  Radio,
  Database,
  LogOut,
  Plus,
  RefreshCw,
  ExternalLink,
  Copy,
  Check,
  Play,
  Pause,
  Trash2,
  Server,
  Activity,
  Layers,
  ArrowUpRight,
  Shield,
  X,
} from "lucide-react";
import { api, authStorage, Tunnel, TunnelMetrics, User } from "@/lib/api";

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
  const [loading, setLoading] = useState(true);
  const [copiedId, setCopiedId] = useState<number | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [creating, setCreating] = useState(false);
  const [togglingId, setTogglingId] = useState<number | null>(null);

  // New tunnel form state
  const [formData, setFormData] = useState({
    name: "",
    subdomain: "",
    protocol: "http",
    port: 3000,
    target_host: "127.0.0.1",
  });

  const fetchTunnels = async () => {
    try {
      setLoading(true);
      const res = await api.getTunnels();
      setTunnels(res.data.tunnels);
      setMetrics(res.data.metrics);
    } catch (err) {
      console.error("Failed to load tunnels:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTunnels();
  }, []);

  const handleCopy = (id: number, url: string) => {
    navigator.clipboard.writeText(url);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  const handleToggle = async (tunnel: Tunnel) => {
    setTogglingId(tunnel.id);
    try {
      await api.toggleTunnel(tunnel.id);
      await fetchTunnels();
    } catch (err) {
      console.error("Toggle error:", err);
    } finally {
      setTogglingId(null);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm("Are you sure you want to delete this tunnel?")) return;
    try {
      await api.deleteTunnel(id);
      await fetchTunnels();
    } catch (err) {
      console.error("Delete error:", err);
    }
  };

  const handleCreateTunnel = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreating(true);
    try {
      await api.createTunnel({
        name: formData.name,
        subdomain: formData.subdomain || undefined,
        protocol: formData.protocol,
        port: Number(formData.port),
        target_host: formData.target_host,
      });
      setIsModalOpen(false);
      setFormData({
        name: "",
        subdomain: "",
        protocol: "http",
        port: 3000,
        target_host: "127.0.0.1",
      });
      await fetchTunnels();
    } catch (err) {
      alert(err instanceof Error ? err.message : "Failed to create tunnel");
    } finally {
      setCreating(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
      {/* Top Navbar */}
      <header className="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-md sticky top-0 z-40">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-cyan-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-cyan-500/20">
              <Radio className="w-5 h-5 text-white animate-pulse" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <span className="font-bold text-base text-white tracking-tight">TunnelDeck</span>
                <span className="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                  Lite
                </span>
              </div>
              <p className="text-[11px] text-slate-400">Laravel & Next.js Core</p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            {/* SQLite Badge */}
            <div className="hidden sm:flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-950 border border-slate-800 text-xs text-slate-300">
              <Database className="w-3.5 h-3.5 text-cyan-400" />
              <span>SQLite</span>
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
            </div>

            {/* User display */}
            <div className="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-800/50 border border-slate-700/60">
              <div className="w-6 h-6 rounded-full bg-gradient-to-tr from-cyan-400 to-blue-500 text-slate-950 font-bold text-xs flex items-center justify-center">
                {user.name.charAt(0)}
              </div>
              <div className="hidden md:block text-left text-xs">
                <div className="font-medium text-slate-200">{user.name}</div>
                <div className="text-[10px] text-slate-400">{user.email}</div>
              </div>
            </div>

            {/* Logout button */}
            <button
              onClick={onLogout}
              className="p-2 rounded-xl text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 border border-transparent hover:border-rose-500/20 transition cursor-pointer"
              title="Sign Out"
            >
              <LogOut className="w-4 h-4" />
            </button>
          </div>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        {/* Welcome Banner & Action Button */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 via-slate-900/90 to-slate-950 p-6 rounded-2xl border border-slate-800/80 shadow-xl">
          <div>
            <h2 className="text-2xl font-bold text-white tracking-tight">
              Tunnels Control Center
            </h2>
            <p className="text-sm text-slate-400 mt-1">
              Active developer tunnel instances securely forwarded from localhost
            </p>
          </div>
          <div className="flex items-center gap-3">
            <button
              onClick={fetchTunnels}
              disabled={loading}
              className="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-slate-700 transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${loading ? "animate-spin" : ""}`} />
              <span>Refresh</span>
            </button>

            <button
              onClick={() => setIsModalOpen(true)}
              className="px-4 py-2 rounded-xl bg-gradient-to-r from-cyan-400 to-blue-500 hover:opacity-90 text-slate-950 text-xs font-semibold shadow-lg shadow-cyan-500/20 transition flex items-center gap-2 cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>New Tunnel</span>
            </button>
          </div>
        </div>

        {/* Metrics Row */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="bg-slate-900/80 border border-slate-800/80 rounded-2xl p-5">
            <div className="flex items-center justify-between text-slate-400 mb-3">
              <span className="text-xs font-medium uppercase tracking-wider">Active Tunnels</span>
              <Activity className="w-4 h-4 text-emerald-400" />
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-3xl font-extrabold text-white">{metrics.active}</span>
              <span className="text-xs text-emerald-400 font-medium">Online</span>
            </div>
          </div>

          <div className="bg-slate-900/80 border border-slate-800/80 rounded-2xl p-5">
            <div className="flex items-center justify-between text-slate-400 mb-3">
              <span className="text-xs font-medium uppercase tracking-wider">Total Tunnels</span>
              <Layers className="w-4 h-4 text-cyan-400" />
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-3xl font-extrabold text-white">{metrics.total}</span>
              <span className="text-xs text-slate-500">Configured</span>
            </div>
          </div>

          <div className="bg-slate-900/80 border border-slate-800/80 rounded-2xl p-5">
            <div className="flex items-center justify-between text-slate-400 mb-3">
              <span className="text-xs font-medium uppercase tracking-wider">Total Requests</span>
              <ArrowUpRight className="w-4 h-4 text-indigo-400" />
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-3xl font-extrabold text-white">
                {metrics.total_requests.toLocaleString()}
              </span>
              <span className="text-xs text-indigo-400 font-medium">Forwarded</span>
            </div>
          </div>

          <div className="bg-slate-900/80 border border-slate-800/80 rounded-2xl p-5">
            <div className="flex items-center justify-between text-slate-400 mb-3">
              <span className="text-xs font-medium uppercase tracking-wider">SQLite Status</span>
              <Database className="w-4 h-4 text-sky-400" />
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-sm font-semibold text-emerald-400">Operational</span>
              <span className="text-xs text-slate-500">database.sqlite</span>
            </div>
          </div>
        </div>

        {/* Tunnels List */}
        <div className="bg-slate-900/80 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
          <div className="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Server className="w-4 h-4 text-cyan-400" />
              <h3 className="font-semibold text-white text-sm">Forwarding Tunnels</h3>
            </div>
            <span className="text-xs text-slate-400">{tunnels.length} Tunnels</span>
          </div>

          {loading ? (
            <div className="py-16 text-center text-slate-500 text-sm">
              <div className="w-6 h-6 border-2 border-cyan-400 border-t-transparent rounded-full animate-spin mx-auto mb-2" />
              <span>Fetching tunnels from SQLite...</span>
            </div>
          ) : tunnels.length === 0 ? (
            <div className="py-16 text-center text-slate-500 text-sm">
              <p>No tunnels registered yet.</p>
              <button
                onClick={() => setIsModalOpen(true)}
                className="mt-3 text-cyan-400 hover:underline text-xs"
              >
                + Create your first tunnel
              </button>
            </div>
          ) : (
            <div className="divide-y divide-slate-800/60">
              {tunnels.map((tunnel) => (
                <div
                  key={tunnel.id}
                  className="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-slate-800/30 transition"
                >
                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2.5">
                      <span className="font-semibold text-white text-sm">{tunnel.name}</span>
                      <span
                        className={`text-[10px] font-medium px-2 py-0.5 rounded-full uppercase tracking-wider flex items-center gap-1 ${
                          tunnel.status === "active"
                            ? "bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"
                            : "bg-amber-500/10 text-amber-400 border border-amber-500/20"
                        }`}
                      >
                        <span
                          className={`w-1.5 h-1.5 rounded-full ${
                            tunnel.status === "active"
                              ? "bg-emerald-400 animate-pulse"
                              : "bg-amber-400"
                          }`}
                        />
                        {tunnel.status}
                      </span>
                    </div>

                    <div className="flex flex-wrap items-center gap-3 text-xs">
                      {/* Public Endpoint */}
                      <div className="flex items-center gap-1.5 text-cyan-400 font-mono bg-slate-950/70 px-2.5 py-1 rounded-lg border border-slate-800">
                        <span>{tunnel.public_url}</span>
                        <button
                          onClick={() => handleCopy(tunnel.id, tunnel.public_url)}
                          className="hover:text-white transition ml-1 text-slate-400"
                          title="Copy Public URL"
                        >
                          {copiedId === tunnel.id ? (
                            <Check className="w-3.5 h-3.5 text-emerald-400" />
                          ) : (
                            <Copy className="w-3.5 h-3.5" />
                          )}
                        </button>
                      </div>

                      <span className="text-slate-600">→</span>

                      {/* Local Target */}
                      <div className="text-slate-400 font-mono bg-slate-950/70 px-2.5 py-1 rounded-lg border border-slate-800">
                        {tunnel.protocol.toUpperCase()}://{tunnel.target_host}:{tunnel.port}
                      </div>

                      <span className="text-slate-500 text-[11px]">
                        {tunnel.connections_count} requests
                      </span>
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-2 shrink-0">
                    <button
                      onClick={() => handleToggle(tunnel)}
                      disabled={togglingId === tunnel.id}
                      className={`px-3 py-1.5 rounded-lg text-xs font-medium border transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50 ${
                        tunnel.status === "active"
                          ? "bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border-amber-500/30"
                          : "bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border-emerald-500/30"
                      }`}
                    >
                      {tunnel.status === "active" ? (
                        <>
                          <Pause className="w-3.5 h-3.5" />
                          <span>Pause</span>
                        </>
                      ) : (
                        <>
                          <Play className="w-3.5 h-3.5" />
                          <span>Start</span>
                        </>
                      )}
                    </button>

                    <button
                      onClick={() => handleDelete(tunnel.id)}
                      className="p-1.5 rounded-lg text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 border border-transparent hover:border-rose-500/20 transition cursor-pointer"
                      title="Delete Tunnel"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </main>

      {/* Modal: New Tunnel */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 relative shadow-2xl">
            <button
              onClick={() => setIsModalOpen(false)}
              className="absolute top-4 right-4 text-slate-400 hover:text-white transition"
            >
              <X className="w-5 h-5" />
            </button>

            <h3 className="text-lg font-bold text-white mb-1">Create Forwarding Tunnel</h3>
            <p className="text-xs text-slate-400 mb-5">
              Expose a local port securely through a TunnelDeck domain
            </p>

            <form onSubmit={handleCreateTunnel} className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Tunnel Name
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. My Next.js Web App"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-cyan-500/50"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Subdomain (Optional)
                </label>
                <div className="flex items-center">
                  <input
                    type="text"
                    placeholder="my-app"
                    value={formData.subdomain}
                    onChange={(e) => setFormData({ ...formData, subdomain: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-l-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-cyan-500/50"
                  />
                  <span className="px-3 py-2 bg-slate-800 text-xs text-slate-400 border border-l-0 border-slate-700 rounded-r-xl">
                    .tunneldeck.online
                  </span>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Protocol
                  </label>
                  <select
                    value={formData.protocol}
                    onChange={(e) => setFormData({ ...formData, protocol: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/50"
                  >
                    <option value="http">HTTP</option>
                    <option value="https">HTTPS</option>
                    <option value="tcp">TCP</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-medium text-slate-300 mb-1">
                    Local Port
                  </label>
                  <input
                    type="number"
                    required
                    min={1}
                    max={65535}
                    value={formData.port}
                    onChange={(e) => setFormData({ ...formData, port: Number(e.target.value) })}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/50"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-slate-300 mb-1">
                  Local Host
                </label>
                <input
                  type="text"
                  value={formData.target_host}
                  onChange={(e) => setFormData({ ...formData, target_host: e.target.value })}
                  placeholder="127.0.0.1"
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500/50"
                />
              </div>

              <div className="flex items-center justify-end gap-3 pt-3">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="px-4 py-2 rounded-xl text-slate-400 hover:text-white text-xs font-medium transition cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={creating}
                  className="px-4 py-2 rounded-xl bg-gradient-to-r from-cyan-400 to-blue-500 text-slate-950 text-xs font-semibold hover:opacity-90 transition disabled:opacity-50 cursor-pointer"
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
