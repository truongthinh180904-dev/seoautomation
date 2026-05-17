import { useMutation, useQueryClient } from '@tanstack/react-query';
import { post } from '@/lib/api/client';
import { ENDPOINTS } from '@/lib/api/endpoints';
import { useAuthStore, User } from '@/stores/authStore';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';

export interface LoginResponse {
  data: {
    token: string;
    user: User;
  };
}

export function useAuth() {
  const router = useRouter();
  const { setAuth, logout, isAuthenticated, user } = useAuthStore();
  const queryClient = useQueryClient();

  const loginMutation = useMutation({
    mutationFn: async (credentials: Record<string, string>) => {
      // The backend login endpoint might return `{ data: { token, user } }` or just `{ token, user }`.
      // Based on standard Laravel Resources, it usually returns `{ data: { token, user } }` 
      // or directly `{ token, user }` from a custom response.
      // We will cast to any to be safe or use LoginResponse if typed.
      const res = await post<any>(ENDPOINTS.AUTH.LOGIN, credentials);
      // Handle both cases
      if (res.data && res.data.token) {
        return res.data;
      }
      return res;
    },
    onSuccess: (data) => {
      setAuth(data.user, data.token);
      toast.success('Đăng nhập thành công');
      router.push('/dashboard');
    },
    onError: (error: any) => {
      if (!error.errors) {
        toast.error(error.message || 'Đăng nhập thất bại. Vui lòng kiểm tra lại thông tin.');
      }
    },
  });

  const logoutMutation = useMutation({
    mutationFn: async () => {
      await post(ENDPOINTS.AUTH.LOGOUT);
    },
    onSuccess: () => {
      logout();
      queryClient.clear();
      router.push('/login');
    },
    onError: () => {
      // Force local logout even if API fails
      logout();
      queryClient.clear();
      router.push('/login');
    }
  });

  return {
    login: loginMutation.mutateAsync,
    logout: logoutMutation.mutateAsync,
    isLoggingIn: loginMutation.isPending,
    isLoggingOut: logoutMutation.isPending,
    isAuthenticated,
    user,
    loginError: loginMutation.error,
  };
}
