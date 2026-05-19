"use client";

import { Bell, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useNotificationActions, useNotifications } from '@/hooks/useNotifications';

export default function NotificationsPage() {
  const { data, isLoading } = useNotifications(false);
  const actions = useNotificationActions();
  const notifications = data?.data ?? [];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-extrabold tracking-tight text-slate-900">Notifications</h1>
        <p className="mt-1 text-slate-500">Email, Telegram và dashboard notifications thay cho Zalo OA.</p>
      </div>

      <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex items-start gap-4 border-b border-slate-100 pb-5">
          <div className="rounded-xl bg-blue-50 p-3 text-blue-600">
            <Bell className="h-6 w-6" />
          </div>
          <div>
            <h2 className="text-lg font-black text-slate-900">Notification system is enabled</h2>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Backend ghi notification generic và gửi qua email/Telegram nếu bật env tương ứng.</p>
          </div>
        </div>

        {isLoading ? (
          <div className="flex h-40 items-center justify-center">
            <Loader2 className="h-7 w-7 animate-spin text-blue-600" />
          </div>
        ) : notifications.length === 0 ? (
          <div className="py-10 text-center text-sm font-medium text-slate-500">Chưa có notification.</div>
        ) : (
          <div className="divide-y divide-slate-100">
            {notifications.map((notification) => (
              <div key={notification.id} className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <div className="flex items-center gap-2">
                    <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{notification.channel}</span>
                    {!notification.read_at && <span className="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">unread</span>}
                  </div>
                  <h2 className="mt-2 font-black text-slate-900">{notification.subject || notification.type}</h2>
                  {notification.message && <p className="mt-1 line-clamp-2 text-sm text-slate-500">{notification.message}</p>}
                </div>
                {!notification.read_at && (
                  <Button variant="outline" size="sm" onClick={() => actions.markRead.mutate(notification.id)}>
                    Mark read
                  </Button>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
