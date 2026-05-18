import { create } from 'zustand';

export interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  tenant_id: number;
  avatar_url: string | null;
}

const AUTH_TOKEN_KEY = 'seo_auth_token';
const AUTH_USER_KEY = 'seo_auth_user';

function persistAuthCookie(token: string) {
  document.cookie = `token=${token}; path=/; max-age=86400; SameSite=Lax`;
}

function clearAuthCookie() {
  document.cookie = 'token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
}

interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  setAuth: (user: User, token: string) => void;
  logout: () => void;
  initialize: () => void;
}

export const useAuthStore = create<AuthState>((set) => {
  // Try to initialize from localStorage immediately if in browser
  let initialToken = null;
  let initialUser = null;
  let initialIsAuthenticated = false;

  if (typeof window !== 'undefined') {
    initialToken = localStorage.getItem(AUTH_TOKEN_KEY);
    const userStr = localStorage.getItem(AUTH_USER_KEY);
    if (initialToken && userStr) {
      try {
        initialUser = JSON.parse(userStr);
        initialIsAuthenticated = true;
        persistAuthCookie(initialToken);
      } catch {
        localStorage.removeItem(AUTH_TOKEN_KEY);
        localStorage.removeItem(AUTH_USER_KEY);
        clearAuthCookie();
      }
    }
  }

  return {
    user: initialUser,
    token: initialToken,
    isAuthenticated: initialIsAuthenticated,

    setAuth: (user: User, token: string) => {
      if (typeof window !== 'undefined') {
        localStorage.setItem(AUTH_TOKEN_KEY, token);
        localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
        persistAuthCookie(token);
      }
      set({ user, token, isAuthenticated: true });
    },

    logout: () => {
      if (typeof window !== 'undefined') {
        localStorage.removeItem(AUTH_TOKEN_KEY);
        localStorage.removeItem(AUTH_USER_KEY);
        clearAuthCookie();
      }
      set({ user: null, token: null, isAuthenticated: false });
    },

    initialize: () => {
      if (typeof window !== 'undefined') {
        const token = localStorage.getItem(AUTH_TOKEN_KEY);
        const userStr = localStorage.getItem(AUTH_USER_KEY);
        
        if (token && userStr) {
          try {
            const user = JSON.parse(userStr);
            persistAuthCookie(token);
            set({ user, token, isAuthenticated: true });
          } catch {
            localStorage.removeItem(AUTH_TOKEN_KEY);
            localStorage.removeItem(AUTH_USER_KEY);
            clearAuthCookie();
            set({ user: null, token: null, isAuthenticated: false });
          }
        } else {
          clearAuthCookie();
          set({ user: null, token: null, isAuthenticated: false });
        }
      }
    },
  };
});
