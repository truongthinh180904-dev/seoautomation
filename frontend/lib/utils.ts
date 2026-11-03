import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function getErrorMessage(err: unknown, fallback: string = 'Đã có lỗi xảy ra'): string {
  if (err instanceof Error && err.message) {
    return err.message;
  }
  if (typeof err === 'object' && err !== null) {
    const errorObj = err as {
      message?: string;
      error?: string;
      response?: { data?: { message?: string; error?: string } };
    };
    return (
      errorObj.message ||
      errorObj.error ||
      errorObj.response?.data?.message ||
      errorObj.response?.data?.error ||
      fallback
    );
  }
  if (typeof err === 'string' && err.trim().length > 0) {
    return err;
  }
  return fallback;
}
