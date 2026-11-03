"use client";

import React from 'react';
import { Check, Sparkles, Zap, ArrowRight, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

interface PricingTableProps {
  plans?: Plan[];
  currentPlanCode?: string;
  onSelectPlan: (plan: Plan) => void;
  isLoading?: boolean;
  isCreatingOrder?: boolean;
  selectedPlanId?: number | null;
}

export function PricingTable({
  plans = [],
  currentPlanCode = 'free',
  onSelectPlan,
  isLoading = false,
  isCreatingOrder = false,
  selectedPlanId = null,
}: PricingTableProps) {
  if (isLoading) {
    return (
      <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <Skeleton className="h-6 w-24 mb-2" />
            <Skeleton className="h-4 w-40 mb-6" />
            <Skeleton className="h-10 w-32 mb-6" />
            <div className="space-y-3 mb-8">
              <Skeleton className="h-4 w-full" />
              <Skeleton className="h-4 w-5/6" />
              <Skeleton className="h-4 w-4/6" />
            </div>
            <Skeleton className="h-10 w-full mt-auto" />
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
      {plans.map((plan) => {
        const isCurrent = currentPlanCode.toLowerCase() === plan.code.toLowerCase();
        const isPro = plan.code === 'pro';
        const isFree = plan.code === 'free';
        const isProcessing = isCreatingOrder && selectedPlanId === plan.id;

        return (
          <Card
            key={plan.id}
            className={`relative flex flex-col rounded-2xl border bg-white p-6 shadow-sm transition-all duration-200 hover:shadow-md ${
              isPro
                ? 'border-blue-500 ring-2 ring-blue-500/20'
                : 'border-slate-200'
            }`}
          >
            {isPro && (
              <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                <Badge className="bg-blue-600 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-white shadow-sm">
                  <Sparkles className="mr-1 h-3.5 w-3.5 inline" /> Phổ biến nhất
                </Badge>
              </div>
            )}

            <div className="mb-4">
              <div className="flex items-center justify-between">
                <h3 className="text-xl font-bold text-slate-900">{plan.name}</h3>
                {isCurrent && (
                  <Badge variant="outline" className="border-emerald-500 bg-emerald-50 text-emerald-700 font-medium">
                    Đang sử dụng
                  </Badge>
                )}
              </div>
              <p className="mt-1 text-xs text-slate-500 line-clamp-2 min-h-[32px]">{plan.description}</p>
            </div>

            <div className="mb-6 border-b border-slate-100 pb-6">
              <div className="flex items-baseline">
                {isFree ? (
                  <span className="text-3xl font-extrabold text-slate-900">0 đ</span>
                ) : (
                  <>
                    <span className="text-3xl font-extrabold text-slate-900">
                      {new Intl.NumberFormat('vi-VN').format(plan.price_monthly_vnd)} đ
                    </span>
                    <span className="ml-1 text-xs font-medium text-slate-500">/tháng</span>
                  </>
                )}
              </div>
              <p className="mt-2 text-xs font-medium text-blue-600 flex items-center">
                <Zap className="mr-1 h-3.5 w-3.5" />
                {plan.credits_per_cycle.toLocaleString()} credit / chu kỳ
              </p>
            </div>

            <ul className="mb-8 space-y-3 text-xs text-slate-600 flex-1">
              <li className="flex items-start">
                <Check className="mr-2 h-4 w-4 shrink-0 text-emerald-600 mt-0.5" />
                <span>Giới hạn <strong>{plan.articles_limit_per_cycle}</strong> bài viết / tháng</span>
              </li>
              <li className="flex items-start">
                <Check className="mr-2 h-4 w-4 shrink-0 text-emerald-600 mt-0.5" />
                <span>Kết nối tối đa <strong>{plan.websites_limit}</strong> website WordPress</span>
              </li>
              <li className="flex items-start">
                <Check className="mr-2 h-4 w-4 shrink-0 text-emerald-600 mt-0.5" />
                <span>Tối đa <strong>{plan.max_concurrent_tasks}</strong> tác vụ chạy đồng thời</span>
              </li>
              <li className="flex items-start">
                <Check className="mr-2 h-4 w-4 shrink-0 text-emerald-600 mt-0.5" />
                <span>Tối đa <strong>{plan.max_images_per_article}</strong> ảnh mỗi bài viết</span>
              </li>
              {plan.features?.map((feature, idx) => (
                <li key={idx} className="flex items-start">
                  <Check className="mr-2 h-4 w-4 shrink-0 text-emerald-600 mt-0.5" />
                  <span>{feature}</span>
                </li>
              ))}
            </ul>

            <div className="mt-auto">
              {isCurrent ? (
                <Button
                  disabled
                  variant="outline"
                  className="w-full bg-slate-50 text-slate-500 border-slate-200 cursor-not-allowed"
                >
                  Gói hiện tại
                </Button>
              ) : isFree ? (
                <Button
                  disabled
                  variant="outline"
                  className="w-full bg-slate-50 text-slate-500 border-slate-200"
                >
                  Gói mặc định
                </Button>
              ) : (
                <Button
                  onClick={() => onSelectPlan(plan)}
                  disabled={isCreatingOrder}
                  className={`w-full font-semibold transition-all ${
                    isPro
                      ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-sm'
                      : 'bg-slate-900 hover:bg-slate-800 text-white'
                  }`}
                >
                  {isProcessing ? (
                    <>
                      <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                      Đang tạo đơn...
                    </>
                  ) : (
                    <>
                      Nâng cấp ngay <ArrowRight className="ml-1.5 h-4 w-4" />
                    </>
                  )}
                </Button>
              )}
            </div>
          </Card>
        );
      })}
    </div>
  );
}
