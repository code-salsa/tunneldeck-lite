"use client";

import React, { useState } from "react";
import {
  Lock,
  Mail,
  ArrowRight,
  Eye,
  EyeOff,
  Database,
  CheckCircle2,
  AlertCircle,
  Radio,
  Sparkles,
} from "lucide-react";
import { api, User } from "@/lib/api";

interface LoginFormProps {
  onLoginSuccess: (user: User) => void;
}

export default function LoginForm({ onLoginSuccess }: LoginFormProps) {
  const [email, setEmail] = useState("admin@tunneldeck.online");
  const [password, setPassword] = useState("123456");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      const response = await api.login(email.trim(), password);
      onLoginSuccess(response.user);
    } catch (err: unknown) {
      if (err instanceof Error) {
        setError(err.message || "Failed to sign in. Please verify your credentials.");
      } else {
        setError("An unexpected error occurred. Please try again.");
      }
    } finally {
      setLoading(false);
    }
  };

  const handleFillCredentials = () => {
    setEmail("admin@tunneldeck.online");
    setPassword("123456");
    setError(null);
  };

  return (
    <div className="min-h-screen w-full flex items-center justify-center p-4 bg-slate-950 relative overflow-hidden">
      {/* Background glowing gradients */}
      <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[520px] h-[340px] bg-gradient-to-tr from-cyan-600/20 via-indigo-600/20 to-emerald-500/10 blur-[120px] pointer-events-none rounded-full" />
      <div className="absolute -bottom-24 -right-24 w-96 h-96 bg-blue-600/10 blur-[100px] pointer-events-none rounded-full" />

      <div className="w-full max-w-md relative z-10">
        {/* Brand Header */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center gap-2.5 px-3 py-1.5 rounded-full bg-slate-900/80 border border-slate-800 text-cyan-400 text-xs font-medium mb-4 backdrop-blur-md">
            <Radio className="w-3.5 h-3.5 animate-pulse text-cyan-400" />
            <span>TunnelDeck Lite</span>
            <span className="text-slate-500">•</span>
            <span className="text-emerald-400 flex items-center gap-1">
              <Database className="w-3 h-3" /> SQLite Active
            </span>
          </div>

          <h1 className="text-3xl font-extrabold tracking-tight text-white mb-2">
            Welcome to <span className="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-sky-300 to-indigo-400">TunnelDeck</span>
          </h1>
          <p className="text-sm text-slate-400">
            Sign in to manage and monitor your live secure tunnels
          </p>
        </div>

        {/* Card */}
        <div className="bg-slate-900/90 border border-slate-800/80 rounded-2xl p-6 sm:p-8 shadow-2xl backdrop-blur-xl relative">
          {/* Quick Demo Credentials Pill */}
          <div className="mb-6 p-3.5 rounded-xl bg-slate-950/70 border border-slate-800/90 flex items-start justify-between gap-3 text-xs">
            <div className="space-y-1 text-slate-300">
              <div className="flex items-center gap-1.5 text-cyan-400 font-semibold">
                <Sparkles className="w-3.5 h-3.5" />
                <span>Default Credentials</span>
              </div>
              <div className="font-mono text-slate-400 text-[11px]">
                <span>Email: </span>
                <span className="text-slate-200">admin@tunneldeck.online</span>
              </div>
              <div className="font-mono text-slate-400 text-[11px]">
                <span>Password: </span>
                <span className="text-slate-200">123456</span>
              </div>
            </div>
            <button
              type="button"
              onClick={handleFillCredentials}
              className="px-2.5 py-1 rounded-md bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 font-medium transition cursor-pointer text-[11px] shrink-0"
            >
              Autofill
            </button>
          </div>

          {/* Error notification */}
          {error && (
            <div className="mb-5 p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs flex items-start gap-2.5">
              <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
              <span>{error}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Email Field */}
            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1.5">
                Email Address
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                  <Mail className="w-4 h-4" />
                </div>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="admin@tunneldeck.online"
                  required
                  className="w-full pl-9 pr-3 py-2.5 bg-slate-950/60 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500 transition"
                />
              </div>
            </div>

            {/* Password Field */}
            <div>
              <div className="flex items-center justify-between mb-1.5">
                <label className="block text-xs font-medium text-slate-300">
                  Password
                </label>
                <span className="text-[11px] text-slate-500">No OTP required</span>
              </div>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                  <Lock className="w-4 h-4" />
                </div>
                <input
                  type={showPassword ? "text" : "password"}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••"
                  required
                  className="w-full pl-9 pr-10 py-2.5 bg-slate-950/60 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-cyan-500/50 focus:border-cyan-500 transition"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300 transition"
                  aria-label={showPassword ? "Hide password" : "Show password"}
                >
                  {showPassword ? (
                    <EyeOff className="w-4 h-4" />
                  ) : (
                    <Eye className="w-4 h-4" />
                  )}
                </button>
              </div>
            </div>

            {/* Submit Button */}
            <button
              type="submit"
              disabled={loading}
              className="w-full mt-2 py-2.5 px-4 rounded-xl font-medium text-sm text-slate-950 bg-gradient-to-r from-cyan-400 via-sky-300 to-indigo-300 hover:opacity-90 active:scale-[0.99] transition flex items-center justify-center gap-2 shadow-lg shadow-cyan-500/20 disabled:opacity-50 cursor-pointer"
            >
              {loading ? (
                <>
                  <div className="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin" />
                  <span>Verifying credentials...</span>
                </>
              ) : (
                <>
                  <span>Sign In</span>
                  <ArrowRight className="w-4 h-4" />
                </>
              )}
            </button>
          </form>

          {/* SQLite feature footnote */}
          <div className="mt-6 pt-5 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-500">
            <span className="flex items-center gap-1.5">
              <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" />
              Direct login (no OTP)
            </span>
            <span className="flex items-center gap-1.5">
              <Database className="w-3.5 h-3.5 text-cyan-400" />
              SQLite Database
            </span>
          </div>
        </div>

        {/* Footer info */}
        <p className="text-center text-xs text-slate-600 mt-6">
          TunnelDeck Lite • Laravel Backend + Next.js Frontend
        </p>
      </div>
    </div>
  );
}
