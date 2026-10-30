"use client";

import React, { useState, useEffect } from "react";
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

  useEffect(() => {
    if (typeof window !== "undefined") {
      const autofillDisabled = localStorage.getItem("tunneldeck_autofill_password_enabled") === "false";
      const savedPassword = localStorage.getItem("tunneldeck_saved_password") || "123456";

      if (autofillDisabled) {
        setPassword("");
      } else {
        setPassword(savedPassword);
      }
    }
  }, []);

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

  return (
    <div className="min-h-screen w-full flex items-center justify-center p-4 bg-slate-50 relative overflow-hidden">
      {/* Subtle light background gradients */}
      <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[400px] bg-gradient-to-tr from-[#100091]/5 via-indigo-100/40 to-blue-50 blur-[130px] pointer-events-none rounded-full" />
      <div className="absolute -bottom-20 -right-20 w-80 h-80 bg-blue-100/30 blur-[90px] pointer-events-none rounded-full" />

      <div className="w-full max-w-md relative z-10">
        {/* Brand Header */}
        <div className="text-center mb-7">
          {/* Main Logo */}
          <div className="flex items-center justify-center gap-3 mb-3.5">
            <div className="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-[#100091] text-white flex items-center justify-center shadow-lg shadow-[#100091]/25 shrink-0">
              <Radio className="w-6 h-6 animate-pulse" />
            </div>
            <div className="flex items-center gap-2">
              <span className="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 font-sans">
                TunnelDeck
              </span>
              <span className="px-2.5 py-0.5 rounded-full bg-indigo-50 text-[#100091] border border-indigo-200/90 text-xs font-extrabold uppercase tracking-wider shadow-2xs">
                Lite
              </span>
            </div>
          </div>

          {/* SQLite Active Pill */}
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-700 text-xs font-semibold mb-3">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
            <Database className="w-3.5 h-3.5 text-emerald-600" />
            <span>SQLite Active</span>
          </div>

          <h1 className="text-xl sm:text-2xl font-extrabold tracking-tight text-slate-900 mb-1.5">
            Sign in to <span className="text-[#100091]">TunnelDeck</span>
          </h1>
          <p className="text-sm text-slate-500">
            Developer tunnel manager & local port forwarder
          </p>
        </div>

        {/* White Card */}
        <div className="bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-8 shadow-xl shadow-slate-200/70 relative">
          {/* Error notification */}
          {error && (
            <div className="mb-5 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-start gap-2.5">
              <AlertCircle className="w-4 h-4 shrink-0 mt-0.5 text-rose-600" />
              <span>{error}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Email Field */}
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                Email Address
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                  <Mail className="w-4 h-4" />
                </div>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="admin@tunneldeck.online"
                  required
                  className="w-full pl-9 pr-3 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition"
                />
              </div>
            </div>

            {/* Password Field */}
            <div>
              <div className="flex items-center justify-between mb-1.5">
                <label className="block text-xs font-semibold text-slate-700">
                  Password
                </label>
                <span className="text-[11px] text-slate-500 font-medium">No OTP required</span>
              </div>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                  <Lock className="w-4 h-4" />
                </div>
                <input
                  type={showPassword ? "text" : "password"}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••"
                  required
                  className="w-full pl-9 pr-10 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition"
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
              className="w-full mt-2 py-2.5 px-4 rounded-xl font-semibold text-sm text-white bg-[#100091] hover:bg-[#0b0068] active:scale-[0.99] transition flex items-center justify-center gap-2 shadow-md shadow-[#100091]/25 disabled:opacity-50 cursor-pointer"
            >
              {loading ? (
                <>
                  <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
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
          <div className="mt-6 pt-5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
            <span className="flex items-center gap-1.5 font-medium">
              <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
              Direct login (no OTP)
            </span>
            <span className="flex items-center gap-1.5 font-medium text-slate-600">
              <Database className="w-3.5 h-3.5 text-[#100091]" />
              SQLite Database
            </span>
          </div>
        </div>

        {/* Footer info */}
        <p className="text-center text-xs text-slate-400 mt-6">
          TunnelDeck Lite • Laravel Backend + Next.js Frontend
        </p>
      </div>
    </div>
  );
}
