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
  Trash2,
  Server,
  Activity,
  Layers,
  ArrowUpRight,
  X,
  Globe,
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
              <p className="text-[11px] text-slate-500 font-medium">Laravel & Next.js Core</p>
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
            <h2 className="text-2xl font-extrabold text-slate-900 tracking-tight">
              Tunnels Control Center
            </h2>
            <p className="text-sm text-slate-500 mt-1">
              Active developer tunnel instances securely forwarded from localhost
            </p>
          </div>
          <div className="flex items-center gap-3">
            <button
              onClick={fetchTunnels}
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
              <span className="text-xs font-semibold uppercase tracking-wider">Active Tunnels</span>
              <Activity className="w-4 h-4 text-emerald-600" />
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-3xl font-extrabold text-slate-900">{metrics.active}</span>
              <span className="text-xs text-emerald-600 font-semibold">Online</span>
            </div>
          </div>

          <div className="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-semibold uppercase tracking-wider">Total Tunnels</span>
              <Layers className="w-4 h-4 text-[#100091]" />
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-3xl font-extrabold text-slate-900">{metrics.total}</span>
              <span className="text-xs text-slate-500 font-medium">Configured</span>
            </div>
          </div>

          <div className="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-semibold uppercase tracking-wider">Total Requests</span>
              <ArrowUpRight className="w-4 h-4 text-[#100091]" />
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-3xl font-extrabold text-slate-900">
                {metrics.total_requests.toLocaleString()}
              </span>
              <span className="text-xs text-[#100091] font-semibold">Forwarded</span>
            </div>
          </div>

          <div className="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-semibold uppercase tracking-wider">SQLite Status</span>
              <Database className="w-4 h-4 text-[#100091]" />
            </div>
            <div className="flex items-baseline gap-2">
              <span className="text-sm font-bold text-emerald-600">Operational</span>
              <span className="text-xs text-slate-500 font-medium">database.sqlite</span>
            </div>
          </div>
        </div>

        {/* Tunnels List */}
        <div className="bg-white border border-slate-200/90 rounded-2xl overflow-hidden shadow-sm">
          <div className="px-6 py-4 border-b border-slate-200/80 bg-slate-50/70 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <Server className="w-4 h-4 text-[#100091]" />
              <h3 className="font-bold text-slate-900 text-sm">Forwarding Tunnels</h3>
            </div>
            <span className="text-xs font-medium text-slate-500">{tunnels.length} Registered</span>
          </div>

          {loading ? (
            <div className="py-16 text-center text-slate-500 text-sm">
              <div className="w-6 h-6 border-2 border-[#100091] border-t-transparent rounded-full animate-spin mx-auto mb-2" />
              <span>Fetching tunnels from SQLite...</span>
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
              {tunnels.map((tunnel) => (
                <div
                  key={tunnel.id}
                  className="p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-slate-50/70 transition"
                >
                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2.5">
                      <span className="font-bold text-slate-900 text-sm">{tunnel.name}</span>
                      <span
                        className={`text-[10px] font-semibold px-2 py-0.5 rounded-full uppercase tracking-wider flex items-center gap-1 ${
                          tunnel.status === "active"
                            ? "bg-emerald-50 text-emerald-700 border border-emerald-200"
                            : "bg-amber-50 text-amber-700 border border-amber-200"
                        }`}
                      >
                        <span
                          className={`w-1.5 h-1.5 rounded-full ${
                            tunnel.status === "active"
                              ? "bg-emerald-500 animate-pulse"
                              : "bg-amber-500"
                          }`}
                        />
                        {tunnel.status}
                      </span>
                    </div>

                    <div className="flex flex-wrap items-center gap-3 text-xs">
                      {/* Public Endpoint */}
                      <div className="flex items-center gap-1.5 text-[#100091] font-mono bg-indigo-50/80 px-2.5 py-1 rounded-lg border border-indigo-100 font-medium">
                        <Globe className="w-3.5 h-3.5 text-[#100091]" />
                        <span>{tunnel.public_url}</span>
                        <button
                          onClick={() => handleCopy(tunnel.id, tunnel.public_url)}
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

                      <span className="text-slate-400 font-bold">→</span>

                      {/* Local Target */}
                      <div className="text-slate-700 font-mono bg-slate-100 px-2.5 py-1 rounded-lg border border-slate-200">
                        {tunnel.protocol.toUpperCase()}://{tunnel.target_host}:{tunnel.port}
                      </div>

                      <span className="text-slate-500 text-[11px] font-medium">
                        {tunnel.connections_count} requests forwarded
                      </span>
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-2 shrink-0">
                    <button
                      onClick={() => handleToggle(tunnel)}
                      disabled={togglingId === tunnel.id}
                      className={`px-3 py-1.5 rounded-lg text-xs font-semibold border transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50 ${
                        tunnel.status === "active"
                          ? "bg-amber-50 hover:bg-amber-100/80 text-amber-800 border-amber-200"
                          : "bg-emerald-50 hover:bg-emerald-100/80 text-emerald-800 border-emerald-200"
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
                      className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-200 transition cursor-pointer"
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
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
          <div className="bg-white border border-slate-200 rounded-2xl w-full max-w-md p-6 relative shadow-2xl">
            <button
              onClick={() => setIsModalOpen(false)}
              className="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition"
            >
              <X className="w-5 h-5" />
            </button>

            <h3 className="text-lg font-bold text-slate-900 mb-1">Create Forwarding Tunnel</h3>
            <p className="text-xs text-slate-500 mb-5">
              Expose a local port securely through a TunnelDeck domain
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
                  Subdomain (Optional)
                </label>
                <div className="flex items-center">
                  <input
                    type="text"
                    placeholder="my-app"
                    value={formData.subdomain}
                    onChange={(e) => setFormData({ ...formData, subdomain: e.target.value })}
                    className="w-full px-3 py-2 bg-white border border-slate-300 rounded-l-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091]"
                  />
                  <span className="px-3 py-2 bg-slate-100 text-xs font-medium text-slate-600 border border-l-0 border-slate-300 rounded-r-xl">
                    .tunneldeck.online
                  </span>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Protocol
                  </label>
                  <select
                    value={formData.protocol}
                    onChange={(e) => setFormData({ ...formData, protocol: e.target.value })}
                    className="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091]"
                  >
                    <option value="http">HTTP</option>
                    <option value="https">HTTPS</option>
                    <option value="tcp">TCP</option>
                  </select>
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
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Local Host
                </label>
                <input
                  type="text"
                  value={formData.target_host}
                  onChange={(e) => setFormData({ ...formData, target_host: e.target.value })}
                  placeholder="127.0.0.1"
                  className="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091]"
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
