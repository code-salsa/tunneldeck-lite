"use client";

import { useEffect, useState } from "react";
import LoginForm from "@/components/LoginForm";
import Dashboard from "@/components/Dashboard";
import { api, authStorage, User } from "@/lib/api";

export default function Home() {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Check if user session exists in local storage
    const token = authStorage.getToken();
    const storedUser = authStorage.getUser();

    if (token && storedUser) {
      setUser(storedUser);
      // Verify token with backend
      api
        .getProfile()
        .then((res) => {
          setUser(res.user);
          authStorage.setUser(res.user);
        })
        .catch(() => {
          // Token is expired or invalid, reset to login
          authStorage.removeToken();
          setUser(null);
        })
        .finally(() => {
          setLoading(false);
        });
    } else {
      setLoading(false);
    }
  }, []);

  const handleLoginSuccess = (authenticatedUser: User) => {
    setUser(authenticatedUser);
  };

  const handleLogout = async () => {
    try {
      await api.logout();
    } catch {
      // Ignored
    } finally {
      authStorage.removeToken();
      setUser(null);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-950 flex items-center justify-center">
        <div className="flex flex-col items-center gap-3">
          <div className="w-8 h-8 border-2 border-cyan-400 border-t-transparent rounded-full animate-spin" />
          <span className="text-xs text-slate-400 font-mono tracking-wider">
            TUNNELDECK LITE INITIALIZING...
          </span>
        </div>
      </div>
    );
  }

  // By default show the login page if not authenticated
  if (!user) {
    return <LoginForm onLoginSuccess={handleLoginSuccess} />;
  }

  return <Dashboard user={user} onLogout={handleLogout} />;
}
