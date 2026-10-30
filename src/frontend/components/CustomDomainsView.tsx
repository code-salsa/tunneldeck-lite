"use client";

import React, { useState, useEffect } from "react";
import {
  Globe,
  Search,
  Plus,
  ShieldCheck,
  Trash2,
  X,
  Info,
  Check,
  ExternalLink,
  Shield,
  RefreshCw,
  Cloud,
  Sparkles,
} from "lucide-react";
import { api, CustomDomain } from "@/lib/api";

export default function CustomDomainsView() {
  const [domains, setDomains] = useState<CustomDomain[]>([]);
  const [searchQuery, setSearchQuery] = useState("");
  const [loading, setLoading] = useState(true);

  // Add Domain Modal
  const [isAddModalOpen, setIsAddModalOpen] = useState(false);
  const [newDomainName, setNewDomainName] = useState("");
  const [addingDomain, setAddingDomain] = useState(false);

  // Step-by-Step Guide Modal
  const [isGuideModalOpen, setIsGuideModalOpen] = useState(false);

  // Delete Domain Confirmation Modal
  const [domainToDelete, setDomainToDelete] = useState<CustomDomain | null>(null);
  const [deletingDomain, setDeletingDomain] = useState(false);

  // Validation States & Modals:
  // 'none' | 'validating' | 'failed_checklist' | 'failed_dialog' | 'success' | 'revalidate_prompt'
  const [modalState, setModalState] = useState<
    "none" | "validating" | "failed_checklist" | "failed_dialog" | "success" | "revalidate_prompt"
  >("none");

  const [activeValidatingDomain, setActiveValidatingDomain] = useState<CustomDomain | null>(null);

  // Steps tracking for "Validating Domain" screen (Screenshots 3 & 5)
  const [step1Status, setStep1Status] = useState<"pending" | "checking" | "passed" | "failed">("pending");
  const [step2Status, setStep2Status] = useState<"pending" | "checking" | "passed" | "failed">("pending");
  const [step3Status, setStep3Status] = useState<"pending" | "checking" | "passed" | "failed">("pending");

  // Error details (Screenshot 4)
  const [failureMessage, setFailureMessage] = useState<string>("");
  const [authZone, setAuthZone] = useState<string | null>(null);
  const [loggingInCloudflare, setLoggingInCloudflare] = useState(false);

  const fetchDomains = async () => {
    try {
      setLoading(true);
      const res = await api.getDomains();
      setDomains(res.domains);
    } catch (err) {
      console.error("Failed to load custom domains:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDomains();
  }, []);

  const handleAddDomain = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newDomainName.trim()) return;
    setAddingDomain(true);
    try {
      const res = await api.createDomain(newDomainName.trim());
      setIsAddModalOpen(false);
      setNewDomainName("");
      await fetchDomains();

      // Launch validation for the newly added domain
      if (res.domain) {
        startDomainValidation(res.domain);
      }
    } catch (err) {
      alert(err instanceof Error ? err.message : "Failed to add domain");
    } finally {
      setAddingDomain(false);
    }
  };

  const confirmDeleteDomain = async () => {
    if (!domainToDelete) return;
    setDeletingDomain(true);
    try {
      await api.deleteDomain(domainToDelete.id);
      setDomainToDelete(null);
      await fetchDomains();
    } catch (err) {
      alert(err instanceof Error ? err.message : "Failed to delete domain");
    } finally {
      setDeletingDomain(false);
    }
  };

  // Run the step-by-step validation flow matching Screenshots 3, 4, and 5
  const startDomainValidation = async (domain: CustomDomain) => {
    setActiveValidatingDomain(domain);
    setModalState("validating");

    // Initialize step states
    setStep1Status("checking");
    setStep2Status("pending");
    setStep3Status("pending");

    // Step 1 delay to show verifying animation
    await new Promise((resolve) => setTimeout(resolve, 800));

    try {
      const res = await api.validateDomain(domain.id);

      if (!res.step1) {
        setStep1Status("failed");
        setFailureMessage(
          res.message || `No DNS records found for '${domain.domain}'. Please make sure the domain is registered.`
        );
        setModalState("failed_checklist");
        await new Promise((resolve) => setTimeout(resolve, 1200));
        setModalState("failed_dialog");
        await fetchDomains();
        return;
      }

      // Step 1 passed, proceed to Step 2
      setStep1Status("passed");
      setStep2Status("checking");
      await new Promise((resolve) => setTimeout(resolve, 900));

      if (res.step2) {
        // Step 2 passed, proceed to Step 3
        setStep2Status("passed");
        setStep3Status("checking");
        await new Promise((resolve) => setTimeout(resolve, 600));

        setStep3Status("passed");
        setModalState("success");
        await fetchDomains();
      } else {
        // Step 2 failed (Screenshot 3 & Screenshot 4)
        setStep2Status("failed");
        setStep3Status("pending");

        const authZoneName = res.auth_zone || "altrisense.online";
        setAuthZone(authZoneName);
        setFailureMessage(
          res.message ||
            `Domain '${domain.domain}' is not authorized in your current Cloudflare session. Your authenticated Cloudflare zone domain is '${authZoneName}'. Please login again and select the correct domain.`
        );

        // Show Screenshot 3 checklist failure state first
        setModalState("failed_checklist");

        // After a brief moment, display Screenshot 4 detailed modal
        await new Promise((resolve) => setTimeout(resolve, 1400));
        setModalState("failed_dialog");
        await fetchDomains();
      }
    } catch (err) {
      setStep1Status("failed");
      setStep2Status("failed");
      setFailureMessage(err instanceof Error ? err.message : "Failed to validate domain");
      setModalState("failed_dialog");
      await fetchDomains();
    }
  };

  // Launch Cloudflare OAuth Login (Screenshot 4 -> Button click)
  const handleCloudflareLogin = async () => {
    setLoggingInCloudflare(true);
    try {
      const res = await api.loginCloudflare();
      if (res.login_url) {
        window.open(res.login_url, "_blank");
      }
      // "Once user validated in cloudflare after login, It will show user again to validate."
      setModalState("revalidate_prompt");
    } catch (err) {
      alert(err instanceof Error ? err.message : "Failed to launch Cloudflare login");
    } finally {
      setLoggingInCloudflare(false);
    }
  };

  // Poll Cloudflare authentication status while in revalidate_prompt state
  useEffect(() => {
    if (modalState !== "revalidate_prompt" || !activeValidatingDomain) return;

    const intervalId = setInterval(async () => {
      try {
        const status = await api.getCloudflareStatus();
        if (
          status.has_cert &&
          status.auth_zone &&
          activeValidatingDomain.domain.toLowerCase().includes(status.auth_zone.toLowerCase())
        ) {
          // Cloudflare cert has been updated with the zone!
          clearInterval(intervalId);
          startDomainValidation(activeValidatingDomain);
        }
      } catch {
        // Silently continue polling
      }
    }, 2500);

    return () => clearInterval(intervalId);
  }, [modalState, activeValidatingDomain]);

  // Filtered domains by search
  const filteredDomains = domains.filter((d) => {
    const matchesSearch = d.domain.toLowerCase().includes(searchQuery.toLowerCase());
    return matchesSearch;
  });

  const formatDate = (dateStr: string | null | undefined) => {
    if (!dateStr) return "-";
    try {
      const date = new Date(dateStr);
      return date.toLocaleDateString("en-GB", {
        day: "numeric",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        hour12: true,
      });
    } catch {
      return dateStr;
    }
  };

  return (
    <div className="space-y-6 w-full">
      {/* ======================================================== */}
      {/* HEADER SECTION */}
      {/* ======================================================== */}
      <div className="w-full">
        <h1 className="text-3xl font-extrabold text-slate-900 tracking-tight font-sans">
          Custom Domains
        </h1>
        <p className="text-sm text-slate-500 mt-1">
          Configure and validate custom domains to route your local tunnels.
        </p>
      </div>

      {/* ======================================================== */}
      {/* MAIN TWO-COLUMN CONTENT GRID */}
      {/* ======================================================== */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start w-full">
        {/* LEFT COLUMN: Table & Search Bar */}
        <div className="lg:col-span-7 xl:col-span-7 2xl:col-span-7 space-y-4 w-full">
          {/* SEARCH AND ADD DOMAIN BAR (Screenshot 1) */}
          <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div className="relative flex-1">
              <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <Search className="w-4 h-4" />
              </div>
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search custom domains..."
                className="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200/90 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] shadow-2xs transition"
              />
            </div>

            <button
              type="button"
              onClick={() => setIsAddModalOpen(true)}
              className="px-5 py-2.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-sm font-semibold shadow-md shadow-[#100091]/25 transition flex items-center justify-center gap-2 cursor-pointer shrink-0"
            >
              <Plus className="w-4 h-4 stroke-[2.5]" />
              <span>Add Domain</span>
            </button>
          </div>

          {/* CONFIGURED DOMAINS TABLE CARD (Screenshot 1) */}
          <div className="bg-white border border-slate-200/90 rounded-2xl shadow-2xs overflow-hidden">
            {/* Table Card Header */}
            <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-white">
              <div className="flex items-center gap-2.5">
                <Globe className="w-4 h-4 text-blue-600 stroke-[2.2]" />
                <h3 className="font-extrabold text-slate-900 text-sm tracking-tight">
                  Configured Domains
                </h3>
              </div>
              <span className="text-xs font-semibold text-slate-400">
                {filteredDomains.length} Domains
              </span>
            </div>

            {/* Table Body */}
            {loading ? (
              <div className="py-16 text-center text-slate-500 text-sm">
                <div className="w-6 h-6 border-2 border-[#100091] border-t-transparent rounded-full animate-spin mx-auto mb-2" />
                <span>Loading domains...</span>
              </div>
            ) : filteredDomains.length === 0 ? (
              domains.length === 0 ? (
                /* Empty state card matching user request */
                <div className="p-8 sm:p-12 text-center max-w-lg mx-auto space-y-6">
                  {/* Globe Icon */}
                  <div className="w-16 h-16 rounded-2xl bg-indigo-50 border border-indigo-100 text-[#100091] flex items-center justify-center mx-auto shadow-2xs">
                    <Globe className="w-8 h-8 stroke-[1.8]" />
                  </div>

                  {/* Heading & Subtitle */}
                  <div className="space-y-2">
                    <h3 className="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight">
                      No domains added and verified yet.
                    </h3>
                    <p className="text-xs sm:text-sm text-slate-600 leading-relaxed">
                      First add your domain in Cloudflare (
                      <button
                        type="button"
                        onClick={() => setIsGuideModalOpen(true)}
                        className="text-[#100091] font-bold underline underline-offset-2 hover:text-[#0b0068] cursor-pointer inline-flex items-center gap-0.5"
                      >
                        follow a step by step guide
                        <ExternalLink className="w-3 h-3 inline ml-0.5" />
                      </button>
                      ) and then add a domain here.
                    </p>
                  </div>

                  {/* Open Guide button */}
                  <div>
                    <button
                      type="button"
                      onClick={() => setIsGuideModalOpen(true)}
                      className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-orange-50 hover:bg-orange-100/80 text-orange-700 border border-orange-200 text-xs font-semibold transition cursor-pointer shadow-2xs"
                    >
                      <Cloud className="w-4 h-4 fill-current text-orange-500" />
                      <span>Follow Step-by-Step Guide</span>
                    </button>
                  </div>

                  {/* Ready to add section */}
                  <div className="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <span className="text-xs text-slate-600 font-medium">
                      Ready to add a domain?
                    </span>
                    <button
                      type="button"
                      onClick={() => setIsAddModalOpen(true)}
                      className="px-5 py-2.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-bold shadow-md shadow-[#100091]/25 transition flex items-center gap-2 cursor-pointer"
                    >
                      <Plus className="w-4 h-4 stroke-[2.5]" />
                      <span>Add Domain</span>
                    </button>
                  </div>
                </div>
              ) : (
                <div className="py-16 text-center text-slate-500 text-sm space-y-3">
                  <p>No domains matching &quot;{searchQuery}&quot;</p>
                  <button
                    onClick={() => setSearchQuery("")}
                    className="text-[#100091] font-semibold hover:underline text-xs"
                  >
                    Clear search filter
                  </button>
                </div>
              )
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                  <thead>
                    <tr className="border-b border-slate-100 bg-slate-50/50 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                      <th className="py-3.5 px-6 font-bold">Domain</th>
                      <th className="py-3.5 px-6 font-bold">Status</th>
                      <th className="py-3.5 px-6 font-bold">Validated At</th>
                      <th className="py-3.5 px-6 font-bold text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 text-xs">
                    {filteredDomains.map((dom) => {
                      const isValidated = dom.validation_status === "validated";
                      const isFailed = dom.validation_status === "failed";

                      return (
                        <tr
                          key={dom.id}
                          className="hover:bg-slate-50/60 transition group"
                        >
                          {/* Domain Name */}
                          <td className="py-4 px-6 font-bold text-slate-900 text-sm">
                            {dom.domain}
                          </td>

                          {/* Status Badge (Matching Screenshot 1) */}
                          <td className="py-4 px-6">
                            {isValidated ? (
                              <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-600 border border-emerald-200">
                                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                                Validated
                              </span>
                            ) : isFailed ? (
                              <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-500 border border-rose-200">
                                <span className="w-1.5 h-1.5 rounded-full bg-rose-500" />
                                Failed
                              </span>
                            ) : (
                              <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-600 border border-amber-200">
                                <span className="w-1.5 h-1.5 rounded-full bg-amber-500" />
                                Pending
                              </span>
                            )}
                          </td>

                          {/* Validated At */}
                          <td className="py-4 px-6 text-slate-600 font-medium">
                            {isValidated ? formatDate(dom.validated_at) : "-"}
                          </td>

                          {/* Actions */}
                          <td className="py-4 px-6 text-right">
                            <div className="inline-flex items-center justify-end gap-3">
                              {/* If validated: show only text 'Valid Domain'; otherwise show Validate button */}
                              {isValidated ? (
                                <span className="text-xs font-medium text-emerald-600">
                                  Valid Domain
                                </span>
                              ) : (
                                <button
                                  type="button"
                                  onClick={() => startDomainValidation(dom)}
                                  className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-emerald-300 text-emerald-700 hover:bg-emerald-50 transition cursor-pointer shadow-2xs"
                                  title="Validate Domain with Cloudflare"
                                >
                                  <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                                  <span>Validate</span>
                                </button>
                              )}

                              {/* Delete Button */}
                              <button
                                type="button"
                                onClick={() => setDomainToDelete(dom)}
                                className="p-1.5 rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 border border-rose-200/70 transition cursor-pointer"
                                title="Delete Domain"
                              >
                                <Trash2 className="w-3.5 h-3.5" />
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

        {/* RIGHT COLUMN: STEP-BY-STEP FREE CLOUDFLARE GUIDE */}
        <div className="lg:col-span-5 xl:col-span-5 2xl:col-span-5 w-full space-y-4 lg:sticky lg:top-6">
          <div className="bg-white border border-slate-200/90 rounded-2xl shadow-2xs p-5 sm:p-6 space-y-5 w-full">
            {/* Guide Header */}
            <div className="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-orange-50 border border-orange-200/80 flex items-center justify-center text-orange-500 shadow-2xs">
                  <Cloud className="w-5 h-5 fill-current" />
                </div>
                <div>
                  <h3 className="font-extrabold text-slate-900 text-sm tracking-tight">
                    Connect Domain in Cloudflare
                  </h3>
                  <button
                    type="button"
                    onClick={() => setIsGuideModalOpen(true)}
                    className="text-[11px] text-[#100091] hover:underline font-semibold cursor-pointer text-left block"
                  >
                    View detailed guide modal →
                  </button>
                </div>
              </div>
              <span className="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200 shrink-0">
                Free Plan
              </span>
            </div>

            {/* Steps List */}
            <div className="space-y-4">
              {/* Step 1 */}
              <div className="flex gap-3 text-xs">
                <div className="w-6 h-6 rounded-full bg-[#100091] text-white font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                  1
                </div>
                <div className="space-y-0.5">
                  <h4 className="font-bold text-slate-900">Sign Up / Log In to Cloudflare</h4>
                  <p className="text-slate-500 text-[11px] leading-relaxed">
                    Create a free account at{" "}
                    <a
                      href="https://dash.cloudflare.com/sign-up"
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-[#100091] font-semibold hover:underline inline-flex items-center gap-0.5"
                    >
                      dash.cloudflare.com
                      <ExternalLink className="w-2.5 h-2.5 inline" />
                    </a>
                    . No credit card required.
                  </p>
                </div>
              </div>

              {/* Step 2 */}
              <div className="flex gap-3 text-xs">
                <div className="w-6 h-6 rounded-full bg-[#100091] text-white font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                  2
                </div>
                <div className="space-y-0.5">
                  <h4 className="font-bold text-slate-900">Add Your Domain</h4>
                  <p className="text-slate-500 text-[11px] leading-relaxed">
                    Click <strong>&quot;+ Add a Site&quot;</strong>, enter your root domain (e.g.{" "}
                    <code className="bg-slate-100 text-slate-800 px-1 py-0.5 rounded text-[10px] font-mono">
                      example.com
                    </code>
                    ), and choose the <strong>Free ($0/month)</strong> plan.
                  </p>
                </div>
              </div>

              {/* Step 3 */}
              <div className="flex gap-3 text-xs">
                <div className="w-6 h-6 rounded-full bg-[#100091] text-white font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                  3
                </div>
                <div className="space-y-0.5">
                  <h4 className="font-bold text-slate-900">Automatic DNS Records Scan</h4>
                  <p className="text-slate-500 text-[11px] leading-relaxed">
                    Cloudflare will automatically import your existing DNS records. Review them and click <strong>Continue</strong>.
                  </p>
                </div>
              </div>

              {/* Step 4 */}
              <div className="flex gap-3 text-xs">
                <div className="w-6 h-6 rounded-full bg-[#100091] text-white font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                  4
                </div>
                <div className="space-y-0.5">
                  <h4 className="font-bold text-slate-900">Update Nameservers at Registrar</h4>
                  <p className="text-slate-500 text-[11px] leading-relaxed">
                    Log in to your registrar (GoDaddy, Namecheap, Hostinger, etc.), open <strong>DNS / Nameservers</strong>, and replace them with the <strong>2 Cloudflare nameservers</strong> provided.
                  </p>
                </div>
              </div>

              {/* Step 5 */}
              <div className="flex gap-3 text-xs">
                <div className="w-6 h-6 rounded-full bg-[#100091] text-white font-bold text-[11px] flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                  5
                </div>
                <div className="space-y-0.5">
                  <h4 className="font-bold text-slate-900">Validate in TunnelDeck</h4>
                  <p className="text-slate-500 text-[11px] leading-relaxed">
                    Add the domain on the left and click <strong>Validate</strong>. Cloudflared will authenticate your zone and route tunnels automatically!
                  </p>
                </div>
              </div>
            </div>

            {/* Perks Callout */}
            <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 space-y-1.5 text-[11px]">
              <div className="flex items-center gap-1.5 font-bold text-slate-800">
                <Shield className="w-3.5 h-3.5 text-emerald-600" />
                <span>Free Plan Includes:</span>
              </div>
              <ul className="space-y-1 text-slate-500 text-[11px] pl-4 list-disc">
                <li>Automatic SSL/TLS encryption for all subdomains</li>
                <li>Unlimited bandwidth for Cloudflare Tunnels</li>
                <li>Global Edge CDN &amp; DDoS protection</li>
              </ul>
            </div>

            {/* Link Out to Cloudflare */}
            <a
              href="https://dash.cloudflare.com"
              target="_blank"
              rel="noopener noreferrer"
              className="w-full py-2.5 px-4 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white font-semibold text-xs transition flex items-center justify-center gap-2 shadow-md shadow-[#100091]/25 cursor-pointer"
            >
              <span>Open Cloudflare Dashboard</span>
              <ExternalLink className="w-3.5 h-3.5" />
            </a>
          </div>
        </div>
      </div>

      {/* ======================================================== */}
      {/* MODAL 1: ADD CUSTOM DOMAIN (Matching Screenshot 2) */}
      {/* ======================================================== */}
      {isAddModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150">
          <div className="bg-white border border-slate-200 rounded-2xl w-full max-w-lg p-7 relative shadow-2xl">
            {/* Top Header */}
            <div className="flex items-center justify-between mb-5">
              <div className="flex items-center gap-2">
                <Globe className="w-5 h-5 text-blue-600 stroke-[2.2]" />
                <h3 className="text-lg font-bold text-slate-900 tracking-tight">
                  Add Custom Domain
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setIsAddModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 transition cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleAddDomain} className="space-y-5">
              {/* Domain Input */}
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1.5">
                  Domain Name
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. example.com"
                  value={newDomainName}
                  onChange={(e) => setNewDomainName(e.target.value)}
                  className="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#100091]/20 focus:border-[#100091] transition"
                />
              </div>

              {/* How to connect with Cloudflare Box (Screenshot 2) */}
              <div className="p-4 rounded-xl bg-blue-50/60 border border-blue-100 text-xs text-slate-600 space-y-2.5">
                <div className="flex items-center gap-2 text-slate-900 font-bold">
                  <Info className="w-4 h-4 text-blue-600 shrink-0" />
                  <span>How to connect with Cloudflare:</span>
                </div>
                <ol className="space-y-1.5 pl-6 list-decimal text-slate-600 leading-relaxed text-[11px]">
                  <li>Log in to your domain registrar (e.g. GoDaddy, Namecheap).</li>
                  <li>
                    Go to DNS settings and locate the <strong>Nameservers</strong> section.
                  </li>
                  <li>
                    Change your nameservers to point to Cloudflare (you can obtain these from your
                    Cloudflare dashboard).
                  </li>
                  <li>
                    Add the domain name above and click <strong>Validate</strong> to verify nameserver
                    association.
                  </li>
                </ol>
              </div>

              {/* Footer Buttons */}
              <div className="flex items-center justify-end gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setIsAddModalOpen(false)}
                  className="px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={addingDomain}
                  className="px-6 py-2.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-semibold shadow-md shadow-[#100091]/20 transition disabled:opacity-50 cursor-pointer"
                >
                  {addingDomain ? "Adding..." : "Add Domain"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ======================================================== */}
      {/* MODAL: STEP-BY-STEP CLOUDFLARE GUIDE */}
      {/* ======================================================== */}
      {isGuideModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150">
          <div className="bg-white border border-slate-200 rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 sm:p-8 relative shadow-2xl space-y-6">
            {/* Modal Header */}
            <div className="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
              <div className="flex items-center gap-3">
                <div className="w-12 h-12 rounded-2xl bg-orange-50 border border-orange-200/80 flex items-center justify-center text-orange-500 shadow-2xs shrink-0">
                  <Cloud className="w-6 h-6 fill-current" />
                </div>
                <div>
                  <div className="flex items-center gap-2">
                    <h3 className="text-xl font-extrabold text-slate-900 tracking-tight">
                      Connect Domain in Cloudflare
                    </h3>
                    <span className="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                      100% Free
                    </span>
                  </div>
                  <p className="text-xs text-slate-500 mt-0.5">
                    Step-by-step guide to delegate your custom domain to Cloudflare for zero cost
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => setIsGuideModalOpen(false)}
                className="text-slate-400 hover:text-slate-600 transition cursor-pointer p-1 rounded-lg"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Steps Container */}
            <div className="space-y-4">
              {/* Step 1 */}
              <div className="p-4 rounded-2xl bg-slate-50/70 border border-slate-200/70 flex gap-3.5">
                <div className="w-7 h-7 rounded-full bg-[#100091] text-white font-extrabold text-xs flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                  1
                </div>
                <div className="space-y-1">
                  <h4 className="font-bold text-slate-900 text-sm">
                    Create a Free Cloudflare Account
                  </h4>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    Go to{" "}
                    <a
                      href="https://dash.cloudflare.com/sign-up"
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-[#100091] font-bold underline hover:text-[#0b0068] inline-flex items-center gap-0.5"
                    >
                      dash.cloudflare.com
                      <ExternalLink className="w-3 h-3 inline ml-0.5" />
                    </a>{" "}
                    and sign up. No credit card or payment info is ever required for the free tier.
                  </p>
                </div>
              </div>

              {/* Step 2 */}
              <div className="p-4 rounded-2xl bg-slate-50/70 border border-slate-200/70 flex gap-3.5">
                <div className="w-7 h-7 rounded-full bg-[#100091] text-white font-extrabold text-xs flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                  2
                </div>
                <div className="space-y-1">
                  <h4 className="font-bold text-slate-900 text-sm">
                    Add Your Domain &amp; Choose the Free Plan
                  </h4>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    In your Cloudflare dashboard, click <strong>&quot;+ Add a Site&quot;</strong>, enter your registered apex domain (e.g., <code className="bg-white border px-1.5 py-0.5 rounded text-slate-800 font-mono text-[11px]">yourdomain.com</code>), and select the <strong>Free ($0/month)</strong> plan at the bottom of the pricing options.
                  </p>
                </div>
              </div>

              {/* Step 3 */}
              <div className="p-4 rounded-2xl bg-slate-50/70 border border-slate-200/70 flex gap-3.5">
                <div className="w-7 h-7 rounded-full bg-[#100091] text-white font-extrabold text-xs flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                  3
                </div>
                <div className="space-y-1">
                  <h4 className="font-bold text-slate-900 text-sm">
                    Review Scanned DNS Records
                  </h4>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    Cloudflare automatically scans and imports all your domain&apos;s existing DNS records. Simply review them and click <strong>Continue</strong>.
                  </p>
                </div>
              </div>

              {/* Step 4 */}
              <div className="p-4 rounded-2xl bg-slate-50/70 border border-slate-200/70 flex gap-3.5">
                <div className="w-7 h-7 rounded-full bg-[#100091] text-white font-extrabold text-xs flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                  4
                </div>
                <div className="space-y-1">
                  <h4 className="font-bold text-slate-900 text-sm">
                    Update Nameservers at Your Domain Registrar
                  </h4>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    Cloudflare will display two assigned nameservers (e.g. <code className="bg-white border px-1.5 py-0.5 rounded text-slate-800 font-mono text-[11px]">xxx.ns.cloudflare.com</code>). Log in to wherever you bought the domain (GoDaddy, Namecheap, Hostinger, Squarespace, Porkbun, etc.), navigate to <strong>DNS / Custom Nameservers</strong>, and replace them with Cloudflare&apos;s two nameservers.
                  </p>
                </div>
              </div>

              {/* Step 5 */}
              <div className="p-4 rounded-2xl bg-slate-50/70 border border-slate-200/70 flex gap-3.5">
                <div className="w-7 h-7 rounded-full bg-[#100091] text-white font-extrabold text-xs flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
                  5
                </div>
                <div className="space-y-1">
                  <h4 className="font-bold text-slate-900 text-sm">
                    Add &amp; Validate Your Domain in TunnelDeck
                  </h4>
                  <p className="text-xs text-slate-600 leading-relaxed">
                    Once nameservers activate (usually 2–10 minutes), come back here, click <strong>Add Domain</strong>, and click <strong>Validate</strong>. Your custom domain is now linked and ready to route local tunnels!
                  </p>
                </div>
              </div>
            </div>

            {/* Perks Callout */}
            <div className="p-4 rounded-2xl bg-indigo-50/60 border border-indigo-100 flex items-start gap-3 text-xs text-slate-700">
              <Shield className="w-5 h-5 text-[#100091] shrink-0 mt-0.5" />
              <div className="space-y-1">
                <h5 className="font-bold text-slate-900">What makes Cloudflare Tunnels free?</h5>
                <p className="text-slate-600 text-[11px] leading-relaxed">
                  Cloudflare provides Argo Tunnels and DNS zone management at no charge on the Free tier. You get automatic free SSL/TLS certificates, DDoS protection, and global edge network routing for unlimited tunnels without opening router ports.
                </p>
              </div>
            </div>

            {/* Modal Actions */}
            <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-100">
              <a
                href="https://dash.cloudflare.com"
                target="_blank"
                rel="noopener noreferrer"
                className="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition flex items-center justify-center gap-2 cursor-pointer"
              >
                <span>Open Cloudflare Dashboard</span>
                <ExternalLink className="w-3.5 h-3.5" />
              </a>

              <button
                type="button"
                onClick={() => {
                  setIsGuideModalOpen(false);
                  setIsAddModalOpen(true);
                }}
                className="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white text-xs font-bold shadow-md shadow-[#100091]/25 transition flex items-center justify-center gap-2 cursor-pointer"
              >
                <Plus className="w-4 h-4 stroke-[2.5]" />
                <span>Ready to Add Domain</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ======================================================== */}
      {/* MODAL: CUSTOM DELETE DOMAIN CONFIRMATION */}
      {/* ======================================================== */}
      {domainToDelete && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-in fade-in duration-150">
          <div className="bg-white border border-slate-200 rounded-3xl w-full max-w-md p-7 relative shadow-2xl text-center space-y-4">
            <div className="w-14 h-14 rounded-2xl bg-rose-50 border border-rose-100 text-rose-500 flex items-center justify-center mx-auto shadow-2xs">
              <Trash2 className="w-6 h-6 stroke-[2.2]" />
            </div>

            <div className="space-y-1.5">
              <h3 className="text-xl font-extrabold text-slate-900 tracking-tight">
                Remove Custom Domain
              </h3>
              <p className="text-xs text-slate-500 leading-relaxed px-2">
                Are you sure you want to remove <strong className="text-slate-800">{domainToDelete.domain}</strong>? Any active routes or tunnel connections for this domain will be disconnected.
              </p>
            </div>

            <div className="pt-2 flex items-center justify-center gap-3">
              <button
                type="button"
                onClick={() => setDomainToDelete(null)}
                className="w-full py-2.5 px-4 rounded-xl border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-semibold transition cursor-pointer"
              >
                Cancel
              </button>

              <button
                type="button"
                onClick={confirmDeleteDomain}
                disabled={deletingDomain}
                className="w-full py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-md shadow-rose-600/20 transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-1.5"
              >
                {deletingDomain ? (
                  <>
                    <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    <span>Removing...</span>
                  </>
                ) : (
                  <>
                    <Trash2 className="w-3.5 h-3.5" />
                    <span>Remove Domain</span>
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ======================================================== */}
      {/* MODAL 2: VALIDATING DOMAIN (Matching Screenshots 3 & 5) */}
      {/* ======================================================== */}
      {(modalState === "validating" ||
        modalState === "failed_checklist" ||
        modalState === "success") && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150">
          <div className="bg-white border border-slate-200 rounded-3xl w-full max-w-md p-7 relative shadow-2xl">
            {/* Top Modal Header with border divider */}
            <div className="border-b border-slate-100 pb-3 mb-6">
              <h3 className="text-base font-bold text-slate-900 text-left">
                Validating Domain
              </h3>
            </div>

            {/* Circular Blue Spinner during validation, Green Tick on Success */}
            <div className="my-5 flex justify-center">
              {modalState === "success" ? (
                <div className="w-16 h-16 rounded-full border-2 border-emerald-500 bg-emerald-50 flex items-center justify-center text-emerald-600 animate-in zoom-in-75 duration-300 shadow-sm">
                  <Check className="w-9 h-9 stroke-[3]" />
                </div>
              ) : (
                <div className="w-14 h-14 border-4 border-blue-500 border-t-transparent rounded-full animate-spin" />
              )}
            </div>

            {/* Status Heading */}
            <h2 className="text-xl font-extrabold text-slate-900 mb-6 text-center">
              {modalState === "success"
                ? "Validation Successful!"
                : modalState === "failed_checklist"
                ? "Validation Failed"
                : "Validating Domain..."}
            </h2>

            {/* 3 Steps Checklist (Matching Screenshot 3 & 5) */}
            <div className="space-y-4 text-left max-w-xs mx-auto mb-6">
              {/* Step 1: Verifying domain registration */}
              <div className="flex items-center gap-3 text-xs font-medium">
                {step1Status === "passed" ? (
                  <div className="w-5 h-5 rounded-full border-2 border-emerald-500 flex items-center justify-center text-emerald-500 shrink-0">
                    <Check className="w-3.5 h-3.5 stroke-[2.5]" />
                  </div>
                ) : step1Status === "failed" ? (
                  <div className="w-5 h-5 rounded-full border-2 border-rose-500 flex items-center justify-center text-rose-500 shrink-0">
                    <X className="w-3.5 h-3.5 stroke-[2.5]" />
                  </div>
                ) : step1Status === "checking" ? (
                  <div className="w-5 h-5 border-2 border-blue-500 border-t-transparent rounded-full animate-spin shrink-0" />
                ) : (
                  <div className="w-5 h-5 rounded-full border-2 border-slate-300 shrink-0" />
                )}
                <span
                  className={
                    step1Status === "passed"
                      ? "text-emerald-600 font-semibold"
                      : step1Status === "failed"
                      ? "text-rose-600 font-semibold"
                      : step1Status === "checking"
                      ? "text-blue-600 font-semibold"
                      : "text-slate-400"
                  }
                >
                  Verifying domain registration
                </span>
              </div>

              {/* Step 2: Validating domain connection to cloudflare */}
              <div className="flex items-center gap-3 text-xs font-medium">
                {step2Status === "passed" ? (
                  <div className="w-5 h-5 rounded-full border-2 border-emerald-500 flex items-center justify-center text-emerald-500 shrink-0">
                    <Check className="w-3.5 h-3.5 stroke-[2.5]" />
                  </div>
                ) : step2Status === "failed" ? (
                  <div className="w-5 h-5 rounded-full border-2 border-rose-400 flex items-center justify-center text-rose-500 shrink-0">
                    <Check className="w-3.5 h-3.5 stroke-[2.5]" />
                  </div>
                ) : step2Status === "checking" ? (
                  <div className="w-5 h-5 border-2 border-blue-500 border-t-transparent rounded-full animate-spin shrink-0" />
                ) : (
                  <div className="w-5 h-5 rounded-full border-2 border-slate-300 shrink-0" />
                )}
                <span
                  className={
                    step2Status === "passed"
                      ? "text-emerald-600 font-semibold"
                      : step2Status === "failed"
                      ? "text-rose-500 font-semibold"
                      : step2Status === "checking"
                      ? "text-blue-600 font-semibold"
                      : "text-slate-400"
                  }
                >
                  Validating domain connection to cloudflare
                </span>
              </div>

              {/* Step 3: Domain Validated */}
              <div className="flex items-center gap-3 text-xs font-medium">
                {step3Status === "passed" ? (
                  <div className="w-5 h-5 rounded-full border-2 border-emerald-500 flex items-center justify-center text-emerald-500 shrink-0">
                    <Check className="w-3.5 h-3.5 stroke-[2.5]" />
                  </div>
                ) : (
                  <div className="w-5 h-5 rounded-full border-2 border-slate-200 flex items-center justify-center text-slate-300 shrink-0">
                    <Check className="w-3 h-3 text-slate-300" />
                  </div>
                )}
                <span
                  className={
                    step3Status === "passed"
                      ? "text-emerald-600 font-semibold"
                      : "text-slate-400"
                  }
                >
                  Domain Validated
                </span>
              </div>
            </div>

            {/* Done button on success */}
            {modalState === "success" && (
              <div className="pt-2">
                <button
                  type="button"
                  onClick={() => setModalState("none")}
                  className="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs transition cursor-pointer shadow-md"
                >
                  Close & Continue
                </button>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ======================================================== */}
      {/* MODAL 3: VALIDATION FAILED DIALOG (Matching Screenshot 4) */}
      {/* ======================================================== */}
      {modalState === "failed_dialog" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150">
          <div className="bg-white border border-slate-200 rounded-3xl w-full max-w-md p-8 relative shadow-2xl text-center">
            {/* Red Circle with X Icon (Screenshot 4) */}
            <div className="w-16 h-16 rounded-full border border-rose-300 bg-rose-50/60 flex items-center justify-center text-rose-500 mx-auto mb-4">
              <X className="w-8 h-8 stroke-[2]" />
            </div>

            {/* Heading */}
            <h2 className="text-2xl font-extrabold text-slate-900 mb-3 tracking-tight">
              Validation Failed
            </h2>

            {/* Description Text matching Screenshot 4 */}
            <p className="text-xs text-slate-600 leading-relaxed mb-7 px-3">
              {failureMessage ||
                `Domain '${activeValidatingDomain?.domain}' is not authorized in your current Cloudflare session. Your authenticated Cloudflare zone domain is '${authZone || "altrisense.online"}'. Please login again and select the correct domain.`}
            </p>

            {/* Action Buttons: Close (Grey) & Login to Cloudflare (Green) */}
            <div className="flex items-center justify-center gap-3">
              <button
                type="button"
                onClick={() => setModalState("none")}
                className="px-6 py-2.5 rounded-xl bg-[#5f6b7c] hover:bg-[#4f5968] text-white font-semibold text-xs transition cursor-pointer shadow-2xs"
              >
                Close
              </button>

              <button
                type="button"
                onClick={handleCloudflareLogin}
                disabled={loggingInCloudflare}
                className="px-6 py-2.5 rounded-xl bg-[#22c55e] hover:bg-[#16a34a] text-white font-semibold text-xs transition cursor-pointer disabled:opacity-50 flex items-center gap-1.5 shadow-md shadow-emerald-500/20"
              >
                {loggingInCloudflare ? (
                  <>
                    <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    <span>Opening...</span>
                  </>
                ) : (
                  <span>Login to Cloudflare</span>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ======================================================== */}
      {/* MODAL 4: POST-CLOUDFLARE LOGIN -> "SHOW USER AGAIN TO VALIDATE" */}
      {/* "Once user validated in cloudflare after login, It will show user again to validate." */}
      {/* ======================================================== */}
      {modalState === "revalidate_prompt" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150">
          <div className="bg-white border border-slate-200 rounded-3xl w-full max-w-md p-8 relative shadow-2xl text-center space-y-4">
            <div className="w-14 h-14 rounded-2xl bg-indigo-50 border border-indigo-100 text-[#100091] flex items-center justify-center mx-auto">
              <ShieldCheck className="w-8 h-8 text-[#100091]" />
            </div>

            <h3 className="text-xl font-extrabold text-slate-900">
              Cloudflare Authorization Opened
            </h3>

            <p className="text-xs text-slate-600 leading-relaxed px-2">
              Cloudflare authentication has been launched in your browser. Once you approve and select zone{" "}
              <strong className="text-slate-900">{activeValidatingDomain?.domain}</strong>, click below to validate your domain connection again.
            </p>

            <div className="flex items-center justify-center gap-2 text-xs font-semibold text-blue-600 bg-blue-50 py-2 px-3 rounded-xl border border-blue-100">
              <RefreshCw className="w-3.5 h-3.5 animate-spin text-blue-600" />
              <span>Listening for Cloudflare approval...</span>
            </div>

            <div className="pt-3 flex flex-col gap-2.5">
              <button
                type="button"
                onClick={() => {
                  if (activeValidatingDomain) {
                    startDomainValidation(activeValidatingDomain);
                  }
                }}
                className="w-full py-3 px-4 rounded-xl bg-[#100091] hover:bg-[#0b0068] text-white font-bold text-xs shadow-md shadow-[#100091]/25 transition cursor-pointer flex items-center justify-center gap-2"
              >
                <ShieldCheck className="w-4 h-4" />
                <span>Verify & Validate Domain Again</span>
              </button>

              <button
                type="button"
                onClick={() => setModalState("none")}
                className="w-full py-2 px-4 rounded-xl text-slate-500 hover:text-slate-800 text-xs font-semibold transition cursor-pointer"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
