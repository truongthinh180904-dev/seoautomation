import { create } from 'zustand';

export interface WorkspaceInfo {
  id: number;
  name: string;
  slug: string;
  status: string;
  role?: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  tenant_id: number;
  avatar_url: string | null;
  is_platform_admin?: boolean;
  default_workspace_id?: number | null;
  workspaces?: WorkspaceInfo[];
}

const AUTH_TOKEN_KEY = 'seo_auth_token';
const AUTH_USER_KEY = 'seo_auth_user';
const AUTH_WORKSPACE_KEY = 'seo_active_workspace_id';

function persistAuthCookie(token: string) {
  const isSecure = typeof window !== 'undefined' && window.location.protocol === 'https:';
  document.cookie = `token=${token}; path=/; max-age=86400; SameSite=Lax${isSecure ? '; Secure' : ''}`;
}

function clearAuthCookie() {
  document.cookie = 'token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
}

interface AuthState {
  user: User | null;
  token: string | null;
  activeWorkspaceId: number | null;
  isAuthenticated: boolean;
  setAuth: (user: User, token: string) => void;
  setActiveWorkspaceId: (workspaceId: number | null) => void;
  logout: () => void;
  initialize: () => void;
}

export const useAuthStore = create<AuthState>((set) => {
  // Try to initialize from localStorage immediately if in browser
  let initialToken = null;
  let initialUser: User | null = null;
  let initialWorkspaceId: number | null = null;
  let initialIsAuthenticated = false;

  if (typeof window !== 'undefined') {
    initialToken = localStorage.getItem(AUTH_TOKEN_KEY);
    const userStr = localStorage.getItem(AUTH_USER_KEY);
    const wsStr = localStorage.getItem(AUTH_WORKSPACE_KEY);
    if (wsStr) {
      const parsedWs = parseInt(wsStr, 10);
      if (!Number.isNaN(parsedWs)) {
        initialWorkspaceId = parsedWs;
      }
    }
    if (initialToken && userStr) {
      try {
        initialUser = JSON.parse(userStr);
        initialIsAuthenticated = true;
        persistAuthCookie(initialToken);
        if (!initialWorkspaceId && initialUser?.default_workspace_id) {
          initialWorkspaceId = initialUser.default_workspace_id;
        }
      } catch {
        localStorage.removeItem(AUTH_TOKEN_KEY);
        localStorage.removeItem(AUTH_USER_KEY);
        localStorage.removeItem(AUTH_WORKSPACE_KEY);
        clearAuthCookie();
      }
    }
  }

  return {
    user: initialUser,
    token: initialToken,
    activeWorkspaceId: initialWorkspaceId,
    isAuthenticated: initialIsAuthenticated,

    setAuth: (user: User, token: string) => {
      const workspaceId = user.default_workspace_id ?? (user.workspaces?.[0]?.id ?? null);
      if (typeof window !== 'undefined') {
        localStorage.setItem(AUTH_TOKEN_KEY, token);
        localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
        if (workspaceId) {
          localStorage.setItem(AUTH_WORKSPACE_KEY, workspaceId.toString());
        }
        persistAuthCookie(token);
      }
      set({ user, token, activeWorkspaceId: workspaceId, isAuthenticated: true });
    },

    setActiveWorkspaceId: (workspaceId: number | null) => {
      if (typeof window !== 'undefined') {
        if (workspaceId) {
          localStorage.setItem(AUTH_WORKSPACE_KEY, workspaceId.toString());
        } else {
          localStorage.removeItem(AUTH_WORKSPACE_KEY);
        }
      }
      set({ activeWorkspaceId: workspaceId });
    },

    logout: () => {
      if (typeof window !== 'undefined') {
        localStorage.removeItem(AUTH_TOKEN_KEY);
        localStorage.removeItem(AUTH_USER_KEY);
        localStorage.removeItem(AUTH_WORKSPACE_KEY);
        clearAuthCookie();
      }
      set({ user: null, token: null, activeWorkspaceId: null, isAuthenticated: false });
    },

    initialize: () => {
      if (typeof window !== 'undefined') {
        const token = localStorage.getItem(AUTH_TOKEN_KEY);
        const userStr = localStorage.getItem(AUTH_USER_KEY);
        const wsStr = localStorage.getItem(AUTH_WORKSPACE_KEY);
        let workspaceId: number | null = null;
        if (wsStr) {
          const parsed = parseInt(wsStr, 10);
          if (!Number.isNaN(parsed)) workspaceId = parsed;
        }
        
        if (token && userStr) {
          try {
            const user: User = JSON.parse(userStr);
            if (!workspaceId && user.default_workspace_id) {
              workspaceId = user.default_workspace_id;
            }
            persistAuthCookie(token);
            set({ user, token, activeWorkspaceId: workspaceId, isAuthenticated: true });
          } catch {
            localStorage.removeItem(AUTH_TOKEN_KEY);
            localStorage.removeItem(AUTH_USER_KEY);
            localStorage.removeItem(AUTH_WORKSPACE_KEY);
            clearAuthCookie();
            set({ user: null, token: null, activeWorkspaceId: null, isAuthenticated: false });
          }
        } else {
          clearAuthCookie();
          set({ user: null, token: null, activeWorkspaceId: null, isAuthenticated: false });
        }
      }
    },
  };
});
