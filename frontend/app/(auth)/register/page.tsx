'use client';

import { Suspense, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import Link from 'next/link';
import { post } from '@/lib/api/client';
import { useAuthStore, User } from '@/stores/authStore';
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
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';

const registerSchema = z.object({
  name: z.string().min(2, 'Họ tên phải có ít nhất 2 ký tự'),
  email: z.string().email('Email không hợp lệ'),
  workspace_name: z.string().min(2, 'Tên workspace phải có ít nhất 2 ký tự').optional(),
  password: z.string().min(8, 'Mật khẩu phải có ít nhất 8 ký tự'),
  password_confirmation: z.string().min(8, 'Xác nhận mật khẩu phải có ít nhất 8 ký tự'),
}).refine((data) => data.password === data.password_confirmation, {
  message: 'Mật khẩu xác nhận không khớp',
  path: ['password_confirmation'],
});

type RegisterFormValues = z.infer<typeof registerSchema>;

interface RegisterApiResponse {
  data?: {
    token: string;
    user: User;
  };
  token?: string;
  user?: User;
  message?: string;
}

function RegisterFormContent() {
  const router = useRouter();
  const setAuth = useAuthStore((state) => state.setAuth);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const form = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      name: '',
      email: '',
      workspace_name: '',
      password: '',
      password_confirmation: '',
    },
  });

  const onSubmit = async (values: RegisterFormValues) => {
    setIsSubmitting(true);
    try {
      const response = await post<RegisterApiResponse>('/auth/register', {
        name: values.name,
        email: values.email,
        workspace_name: values.workspace_name || `${values.name}'s Workspace`,
        password: values.password,
        password_confirmation: values.password_confirmation,
      });

      const user = response.data?.user || response.user;
      const token = response.data?.token || response.token;

      if (user && token) {
        setAuth(user, token);
        toast.success('Đăng ký tài khoản SaaS thành công!');
        router.replace('/dashboard');
      } else {
        toast.success('Đăng ký thành công! Vui lòng đăng nhập.');
        router.replace('/login');
      }
    } catch (err: unknown) {
      const errorObj = err as { message?: string; errors?: Record<string, string[]> };
      if (errorObj.errors) {
        Object.entries(errorObj.errors).forEach(([field, messages]) => {
          form.setError(field as keyof RegisterFormValues, {
            type: 'server',
            message: messages[0],
          });
        });
      } else {
        toast.error(errorObj.message || 'Không thể tạo tài khoản. Vui lòng thử lại.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Card className="w-full max-w-md shadow-sm">
      <CardHeader className="space-y-1 text-center">
        <CardTitle className="text-2xl font-bold">Đăng ký tài khoản</CardTitle>
        <CardDescription>
          Khởi tạo Workspace SEO tự động cho website của bạn
        </CardDescription>
      </CardHeader>
      <CardContent>
        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Họ và tên</FormLabel>
                  <FormControl>
                    <Input placeholder="Nguyễn Văn A" {...field} disabled={isSubmitting} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="email"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Email</FormLabel>
                  <FormControl>
                    <Input type="email" placeholder="email@example.com" {...field} disabled={isSubmitting} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="workspace_name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Tên Workspace (Tùy chọn)</FormLabel>
                  <FormControl>
                    <Input placeholder="SEO Team của tôi" {...field} disabled={isSubmitting} />
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
                    <Input type="password" placeholder="Tối thiểu 8 ký tự" {...field} disabled={isSubmitting} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="password_confirmation"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Xác nhận mật khẩu</FormLabel>
                  <FormControl>
                    <Input type="password" placeholder="Nhập lại mật khẩu" {...field} disabled={isSubmitting} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <Button type="submit" className="w-full bg-blue-600 hover:bg-blue-700" disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {isSubmitting ? 'Đang tạo tài khoản...' : 'Đăng ký ngay'}
            </Button>

            <div className="text-center pt-2 text-xs text-slate-500">
              Đã có tài khoản?{' '}
              <Link href="/login" className="font-semibold text-blue-600 hover:underline">
                Đăng nhập
              </Link>
            </div>
          </form>
        </Form>
      </CardContent>
    </Card>
  );
}

export default function RegisterPage() {
  return (
    <Suspense
      fallback={
        <div className="flex h-64 items-center justify-center">
          <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
        </div>
      }
    >
      <RegisterFormContent />
    </Suspense>
  );
}
