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

type LoginPayload = {
  email: string;
  password: string;
};

type LoginResult = LoginResponse['data'];

type LoginRawResponse = LoginResponse | LoginResult;

function isWrappedLoginResponse(response: LoginRawResponse): response is LoginResponse {
  return 'data' in response && typeof response.data === 'object' && response.data !== null;
}

function isApiError(error: unknown): error is ApiError {
  return typeof error === 'object' && error !== null && 'message' in error;
}

export function useAuth() {
  const router = useRouter();
  const { setAuth, logout, isAuthenticated, user } = useAuthStore();
  const queryClient = useQueryClient();

  const loginMutation = useMutation({
    mutationFn: async (credentials: LoginPayload) => {
      const res = await post<LoginRawResponse>(ENDPOINTS.AUTH.LOGIN, credentials);
      return isWrappedLoginResponse(res) ? res.data : res;
    },
    onSuccess: (data) => {
      setAuth(data.user, data.token);
      toast.success('Đăng nhập thành công');
    },
    onError: (error: unknown) => {
      if (!isApiError(error) || !error.errors) {
        toast.error(isApiError(error) ? error.message : 'Đăng nhập thất bại. Vui lòng kiểm tra lại thông tin.');
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
