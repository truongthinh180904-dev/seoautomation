'use client';

import { Suspense, useEffect, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { useAuth } from '@/hooks/useAuth';

import { Button } from '@/components/ui/button';
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useAuthStore } from '@/stores/authStore';
import { Loader2 } from 'lucide-react';
import Link from 'next/link';

const loginSchema = z.object({
  email: z.string().email('Email không hợp lệ'),
  password: z.string().min(8, 'Mật khẩu phải có ít nhất 8 ký tự'),
});

type LoginFormValues = z.infer<typeof loginSchema>;

function isValidationError(error: unknown): error is ApiValidationError {
  return typeof error === 'object' && error !== null && 'errors' in error;
}

function getSafeRedirectUrl(target: string | null): string {
  if (!target) return '/dashboard';
  if (target.startsWith('/') && !target.startsWith('//') && !target.includes('://')) {
    return target;
  }
  return '/dashboard';
}

function LoginFormContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { login, isLoggingIn } = useAuth();

  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const initialize = useAuthStore((state) => state.initialize);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    initialize();
    const frame = requestAnimationFrame(() => setMounted(true));

    return () => cancelAnimationFrame(frame);
  }, [initialize]);

  useEffect(() => {
    if (mounted && isAuthenticated) {
      router.replace(getSafeRedirectUrl(searchParams.get('redirect')));
    }
  }, [mounted, isAuthenticated, router, searchParams]);

  const form = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      email: '',
      password: '',
    },
  });

  const onSubmit = async (data: LoginFormValues) => {
    try {
      await login(data);
      router.replace(getSafeRedirectUrl(searchParams.get('redirect')));
    } catch (error: unknown) {
      if (isValidationError(error)) {
        Object.entries(error.errors).forEach(([key, messages]) => {
          form.setError(key as keyof LoginFormValues, {
            type: 'server',
            message: messages[0],
          });
        });
      }
    }
  };

  if (!mounted || isAuthenticated) {
    return null;
  }

  return (
    <Card>
      <CardHeader className="space-y-1 text-center">
        <CardTitle className="text-2xl font-bold">Đăng nhập</CardTitle>
        <CardDescription>
          Nhập email và mật khẩu của bạn để tiếp tục
        </CardDescription>
      </CardHeader>
      <CardContent>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <FormField
              control={form.control}
              name="email"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Email</FormLabel>
                  <FormControl>
                    <Input placeholder="admin@example.com" {...field} disabled={isLoggingIn} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="password"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Mật khẩu</FormLabel>
                  <FormControl>
                    <Input type="password" placeholder="********" {...field} disabled={isLoggingIn} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <Button type="submit" className="w-full" disabled={isLoggingIn}>
              {isLoggingIn && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {isLoggingIn ? 'Đang đăng nhập...' : 'Đăng nhập'}
            </Button>
            <div className="flex items-center justify-between pt-2 text-xs text-slate-500">
              <Link href="/forgot-password" className="hover:text-blue-600 transition-colors">
                Quên mật khẩu?
              </Link>
              <Link href="/register" className="font-semibold text-blue-600 hover:underline">
                Tạo tài khoản mới
              </Link>
            </div>
          </form>
        </Form>
      </CardContent>
    </Card>
  );
}

export default function LoginPage() {
  return (
    <Suspense
      fallback={
        <div className="flex h-64 items-center justify-center">
          <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
        </div>
      }
    >
      <LoginFormContent />
    </Suspense>
  );
}
