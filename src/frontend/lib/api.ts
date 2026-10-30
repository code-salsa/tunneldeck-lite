const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000";

export interface User {
  id: number;
  name: string;
  email: string;
  created_at?: string;
}

export interface Tunnel {
  id: number;
  user_id?: number | null;
  name: string;
  port: number;
  status: "stopped" | "starting" | "running" | "error";
  public_url: string | null;
  pid: number | null;
  process_uuid?: string | null;
  custom_subdomain?: string | null;
  custom_domain_id?: number | null;
  is_root_domain?: boolean;
  health?: "healthy" | "warning" | "failed";
  last_started_at?: string | null;
  last_stopped_at?: string | null;
  connections_count?: number;
  created_at: string;
  updated_at: string;
}

export interface TunnelMetrics {
  total: number;
  active: number;
  paused: number;
  total_requests: number;
}

export interface Activity {
  id: number;
  description: string;
  type: string;
  created_at: string;
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

  getStats: async () => {
    return apiRequest<{
      status: string;
      total: number;
      running: number;
      stopped: number;
      last_started: string;
      activities: Activity[];
    }>("/api/stats");
  },

  createTunnel: async (payload: {
    name: string;
    port: number;
    custom_subdomain?: string;
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

  startTunnel: async (id: number) => {
    return apiRequest<{
      status: string;
      tunnel: Tunnel;
      message: string;
    }>(`/api/tunnels/${id}/start`, {
      method: "POST",
    });
  },

  stopTunnel: async (id: number) => {
    return apiRequest<{
      status: string;
      tunnel: Tunnel;
      message: string;
    }>(`/api/tunnels/${id}/stop`, {
      method: "POST",
    });
  },

  restartTunnel: async (id: number) => {
    return apiRequest<{
      status: string;
      tunnel: Tunnel;
      message: string;
    }>(`/api/tunnels/${id}/restart`, {
      method: "POST",
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

  getTunnelStatus: async (id: number) => {
    return apiRequest<{
      status: string;
      tunnel_status: string;
      health: string;
      public_url: string | null;
    }>(`/api/tunnels/${id}/status`);
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
