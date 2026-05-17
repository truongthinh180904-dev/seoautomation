import { create } from 'zustand';

export interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  tenant_id: number;
  avatar_url: string | null;
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
    initialToken = localStorage.getItem('seo_auth_token');
    const userStr = localStorage.getItem('seo_auth_user');
    if (initialToken && userStr) {
      try {
        initialUser = JSON.parse(userStr);
        initialIsAuthenticated = true;
      } catch (e) {
        localStorage.removeItem('seo_auth_token');
        localStorage.removeItem('seo_auth_user');
      }
    }
  }

  return {
    user: initialUser,
    token: initialToken,
    isAuthenticated: initialIsAuthenticated,

    setAuth: (user: User, token: string) => {
      if (typeof window !== 'undefined') {
        localStorage.setItem('seo_auth_token', token);
        localStorage.setItem('seo_auth_user', JSON.stringify(user));
        document.cookie = `token=${token}; path=/; max-age=86400; SameSite=Lax`;
      }
      set({ user, token, isAuthenticated: true });
    },

    logout: () => {
      if (typeof window !== 'undefined') {
        localStorage.removeItem('seo_auth_token');
        localStorage.removeItem('seo_auth_user');
        document.cookie = 'token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
      }
      set({ user: null, token: null, isAuthenticated: false });
    },

    initialize: () => {
      if (typeof window !== 'undefined') {
        const token = localStorage.getItem('seo_auth_token');
        const userStr = localStorage.getItem('seo_auth_user');
        
        if (token && userStr) {
          try {
            const user = JSON.parse(userStr);
            set({ user, token, isAuthenticated: true });
          } catch (e) {
            localStorage.removeItem('seo_auth_token');
            localStorage.removeItem('seo_auth_user');
          }
        }
      }
    },
  };
});
