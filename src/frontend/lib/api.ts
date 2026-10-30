const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000";

export interface User {
  id: number;
  name: string;
  email: string;
  created_at?: string;
}

export interface Tunnel {
  id: number;
  name: string;
  subdomain: string;
  protocol: "http" | "https" | "tcp";
  port: number;
  target_host: string;
  public_url: string;
  status: "active" | "paused" | "stopped";
  connections_count: number;
  last_active_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface TunnelMetrics {
  total: number;
  active: number;
  paused: number;
  total_requests: number;
}

export const authStorage = {
  getToken: (): string | null => {
    if (typeof window === "undefined") return null;
    return localStorage.getItem("tunneldeck_token");
  },
  setToken: (token: string) => {
    if (typeof window !== "undefined") {
      localStorage.setItem("tunneldeck_token", token);
    }
  },
  removeToken: () => {
    if (typeof window !== "undefined") {
      localStorage.removeItem("tunneldeck_token");
      localStorage.removeItem("tunneldeck_user");
    }
  },
  getUser: (): User | null => {
    if (typeof window === "undefined") return null;
    const data = localStorage.getItem("tunneldeck_user");
    if (!data) return null;
    try {
      return JSON.parse(data);
    } catch {
      return null;
    }
  },
  setUser: (user: User) => {
    if (typeof window !== "undefined") {
      localStorage.setItem("tunneldeck_user", JSON.stringify(user));
    }
  },
};

async function apiRequest<T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> {
  const token = authStorage.getToken();
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
    ...(options.headers as Record<string, string>),
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_URL}${endpoint}`, {
    ...options,
    headers,
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(data.message || `Request failed with status ${response.status}`);
  }

  return data as T;
}

export const api = {
  login: async (email: string, password: string) => {
    const data = await apiRequest<{
      status: string;
      token: string;
      user: User;
      message: string;
    }>("/api/login", {
      method: "POST",
      body: JSON.stringify({ email, password }),
    });
    authStorage.setToken(data.token);
    authStorage.setUser(data.user);
    return data;
  },

  logout: async () => {
    try {
      await apiRequest("/api/logout", { method: "POST" });
    } finally {
      authStorage.removeToken();
    }
  },

  getProfile: async () => {
    return apiRequest<{ status: string; user: User }>("/api/user");
  },

  getTunnels: async () => {
    return apiRequest<{
      status: string;
      data: {
        tunnels: Tunnel[];
        metrics: TunnelMetrics;
      };
    }>("/api/tunnels");
  },

  createTunnel: async (payload: {
    name: string;
    subdomain?: string;
    protocol: string;
    port: number;
    target_host?: string;
  }) => {
    return apiRequest<{
      status: string;
      tunnel: Tunnel;
      message: string;
    }>("/api/tunnels", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },

  toggleTunnel: async (id: number) => {
    return apiRequest<{
      status: string;
      tunnel: Tunnel;
      message: string;
    }>(`/api/tunnels/${id}/toggle`, {
      method: "PATCH",
    });
  },

  deleteTunnel: async (id: number) => {
    return apiRequest<{
      status: string;
      message: string;
    }>(`/api/tunnels/${id}`, {
      method: "DELETE",
    });
  },

  getHealth: async () => {
    return apiRequest<{
      status: string;
      database: string;
      version: string;
      app: string;
      timestamp: string;
    }>("/api/health");
  },
};

