"use client";

import React, { useState } from 'react';
import { 
  CreditCard, 
  WalletCards, 
  Zap, 
  History, 
  FileText, 
  ShieldCheck, 
  Calendar, 
  Sparkles,
  RefreshCw,
  Clock
} from 'lucide-react';
import { 
  usePlans, 
  useCurrentSubscription, 
  usePaymentOrders, 
  useCreditHistory, 
  useCreatePaymentOrder 
} from '@/hooks/useBilling';
import { PricingTable } from '@/components/features/billing/PricingTable';
import { BankTransferCard } from '@/components/features/billing/BankTransferCard';
import { PaymentOrderList } from '@/components/features/billing/PaymentOrderList';
import { CreditHistoryTable } from '@/components/features/billing/CreditHistoryTable';
import { SubmitProofDialog } from '@/components/features/billing/SubmitProofDialog';
import { Card } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { toast } from 'sonner';
import { getErrorMessage } from '@/lib/utils';

export default function SubscriptionSettingsPage() {
  const [selectedPlanForOrder, setSelectedPlanForOrder] = useState<number | null>(null);
  const [activeProofOrder, setActiveProofOrder] = useState<PaymentOrder | null>(null);
  const [isProofDialogOpen, setIsProofDialogOpen] = useState(false);
  const [pageOrders, setPageOrders] = useState(1);
  const [pageCredits, setPageCredits] = useState(1);

  // Queries
  const { data: plans, isLoading: isLoadingPlans } = usePlans();
  const { data: subData, isLoading: isLoadingSub, refetch: refetchSub } = useCurrentSubscription();
  const { data: ordersData, isLoading: isLoadingOrders } = usePaymentOrders(pageOrders);
  const { data: creditData, isLoading: isLoadingCredits } = useCreditHistory(pageCredits);

  // Mutation
  const createOrderMutation = useCreatePaymentOrder();

  const currentPlan = subData?.plan;
  const balance = subData?.balance;
  const subscription = subData?.subscription;

  // Tìm đơn hàng đang chờ thanh toán hoặc chờ xác nhận gần nhất
  const pendingOrder = ordersData?.data?.find(
    (o) => o.status === 'pending_payment' || o.status === 'pending_verification'
  );

  const handleSelectPlan = async (plan: Plan) => {
    if (plan.code === 'free') {
      toast.info('Gói Miễn phí đã được kích hoạt mặc định.');
      return;
    }

    setSelectedPlanForOrder(plan.id);

    try {
      const newOrder = await createOrderMutation.mutateAsync({
        plan_id: plan.id,
        customer_note: `Đăng ký gói ${plan.name} qua cổng thanh toán chuyển khoản`,
      });

      toast.success(
        `Đã tạo đơn hàng #${newOrder.reference_code}. Vui lòng chuyển khoản theo thông tin bên dưới.`
      );
    } catch (err: unknown) {
      toast.error(getErrorMessage(err, 'Không thể tạo đơn hàng. Vui lòng thử lại sau.'));
    } finally {
      setSelectedPlanForOrder(null);
    }
  };

  const handleOpenProofDialog = (order: PaymentOrder) => {
    setActiveProofOrder(order);
    setIsProofDialogOpen(true);
  };

  return (
    <div className="space-y-8 pb-12">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
          <h1 className="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
            Gói dịch vụ & Ví Credit
          </h1>
          <p className="mt-1 text-xs text-slate-500 sm:text-sm">
            Quản lý gói đăng ký cước, nạp credit viết bài SEO và theo dõi lịch sử thanh toán chuyển khoản ngân hàng.
          </p>
        </div>
        <div className="flex items-center space-x-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => refetchSub()}
            disabled={isLoadingSub}
            className="h-8 text-xs font-medium border-slate-200"
          >
            <RefreshCw className={`mr-1.5 h-3.5 w-3.5 ${isLoadingSub ? 'animate-spin' : ''}`} />
            Làm mới
          </Button>
        </div>
      </div>

      {/* KPI Cards: Gói cước & Credit */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {/* Card 1: Gói hiện tại */}
        <Card className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Gói cước</span>
            <WalletCards className="h-5 w-5 text-blue-600" />
          </div>
          <div className="mt-3 flex items-baseline">
            <span className="text-2xl font-black capitalize text-slate-900">
              {currentPlan?.name ?? 'Free'}
            </span>
            <Badge variant="outline" className="ml-2 border-emerald-300 bg-emerald-50 text-emerald-700 text-[10px] font-semibold">
              {subscription?.status_label ?? 'Hoạt động'}
            </Badge>
          </div>
          <p className="mt-2 text-xs text-slate-500 flex items-center">
            <Calendar className="mr-1 h-3.5 w-3.5 text-slate-400" />
            {subscription?.current_period_end 
              ? `Hết hạn: ${new Date(subscription.current_period_end).toLocaleDateString('vi-VN')}`
              : 'Gói miễn phí vĩnh viễn'}
          </p>
        </Card>

        {/* Card 2: Credit khả dụng */}
        <Card className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Credit khả dụng</span>
            <Zap className="h-5 w-5 text-amber-500" />
          </div>
          <div className="mt-3">
            <span className="font-mono text-2xl font-black text-slate-900">
              {balance?.available_credits?.toLocaleString() ?? 0}
            </span>
            <span className="ml-1 text-xs text-slate-400 font-medium">credit</span>
          </div>
          <p className="mt-2 text-xs text-slate-500">
            Dùng để sinh bài viết, nghiên cứu đối thủ & Imagen 4
          </p>
        </Card>

        {/* Card 3: Credit đã dùng chu kỳ này */}
        <Card className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Tiêu thụ chu kỳ này</span>
            <FileText className="h-5 w-5 text-indigo-500" />
          </div>
          <div className="mt-3">
            <span className="font-mono text-2xl font-black text-indigo-700">
              {balance?.cycle_used_credits?.toLocaleString() ?? 0}
            </span>
            <span className="ml-1 text-xs text-slate-400 font-medium">credit</span>
          </div>
          <p className="mt-2 text-xs text-slate-500">
            Hạn mức chu kỳ: {balance?.cycle_granted_credits?.toLocaleString() ?? 0} credit
          </p>
        </Card>

        {/* Card 4: Đơn chờ xác nhận */}
        <Card className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-400">Đơn thanh toán</span>
            <Clock className="h-5 w-5 text-emerald-600" />
          </div>
          <div className="mt-3">
            <span className="text-2xl font-black text-slate-900">
              {pendingOrder ? 1 : 0}
            </span>
            <span className="ml-1 text-xs text-slate-400 font-medium">chờ xử lý</span>
          </div>
          <p className="mt-2 text-xs text-slate-500">
            {pendingOrder 
              ? `Mã đơn: #${pendingOrder.reference_code}`
              : 'Không có giao dịch đang treo'}
          </p>
        </Card>
      </div>

      {/* Hiển thị Card chuyển khoản nếu có đơn chờ thanh toán / chờ xác nhận */}
      {pendingOrder && (
        <div className="space-y-3">
          <div className="flex items-center space-x-2">
            <Sparkles className="h-4 w-4 text-amber-600" />
            <h2 className="text-sm font-bold uppercase tracking-wider text-slate-700">
              Đơn hàng cần thanh toán ngay
            </h2>
          </div>
          <BankTransferCard
            order={pendingOrder}
            onOpenSubmitProof={() => handleOpenProofDialog(pendingOrder)}
          />
        </div>
      )}

      {/* Tabs Chức Năng: Bảng giá, Lịch sử Đơn, Biến động Credit */}
      <Tabs defaultValue="pricing" className="space-y-6">
        <TabsList className="bg-slate-100 p-1 rounded-xl">
          <TabsTrigger value="pricing" className="text-xs font-semibold px-4 py-2 rounded-lg">
            <CreditCard className="mr-1.5 h-3.5 w-3.5" /> Bảng giá & Nâng cấp gói
          </TabsTrigger>
          <TabsTrigger value="orders" className="text-xs font-semibold px-4 py-2 rounded-lg">
            <FileText className="mr-1.5 h-3.5 w-3.5" /> Lịch sử đơn hàng ({ordersData?.meta?.total ?? 0})
          </TabsTrigger>
          <TabsTrigger value="credit_history" className="text-xs font-semibold px-4 py-2 rounded-lg">
            <History className="mr-1.5 h-3.5 w-3.5" /> Sổ phụ biến động Credit
          </TabsTrigger>
        </TabsList>

        {/* Tab 1: Bảng Giá */}
        <TabsContent value="pricing" className="space-y-4">
          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-6">
              <h2 className="text-lg font-bold text-slate-900">Chọn gói dịch vụ phù hợp</h2>
              <p className="text-xs text-slate-500 mt-1">
                Thanh toán an toàn qua hình thức chuyển khoản ngân hàng Việt Nam (VietQR 24/7). 
                Gói cước và số credit sẽ được kích hoạt ngay sau khi Admin xác nhận.
              </p>
            </div>

            <PricingTable
              plans={plans}
              currentPlanCode={currentPlan?.code}
              onSelectPlan={handleSelectPlan}
              isLoading={isLoadingPlans}
              isCreatingOrder={createOrderMutation.isPending}
              selectedPlanId={selectedPlanForOrder}
            />

            <div className="mt-8 rounded-xl bg-slate-50 border border-slate-200 p-4 text-xs text-slate-600 space-y-2">
              <div className="font-semibold text-slate-800 flex items-center">
                <ShieldCheck className="mr-1.5 h-4 w-4 text-blue-600" />
                Chính sách kích hoạt gói & hoàn trả minh bạch
              </div>
              <p>
                - Mỗi đơn hàng được gắn một mã tham chiếu duy nhất dạng <code>SEOPRO_xxxxx</code>. Quý khách vui lòng điền chính xác nội dung này khi chuyển tiền.
              </p>
              <p>
                - Credit định kỳ được cấp mới vào ngày đầu chu kỳ đăng ký. Credit không cộng dồn qua tháng nếu gói không bật chính sách carryover.
              </p>
              <p>
                - Trong trường hợp cần hỗ trợ khẩn cấp, vui lòng liên hệ bộ phận hỗ trợ kỹ thuật qua Hotline/Zalo hiển thị trên hệ thống.
              </p>
            </div>
          </div>
        </TabsContent>

        {/* Tab 2: Lịch sử đơn thanh toán */}
        <TabsContent value="orders" className="space-y-4">
          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-4 flex items-center justify-between">
              <div>
                <h2 className="text-base font-bold text-slate-900">Danh sách đơn hàng chuyển khoản</h2>
                <p className="text-xs text-slate-500 mt-0.5">
                  Theo dõi trạng thái duyệt đơn, xem mã tham chiếu và minh chứng thanh toán.
                </p>
              </div>
            </div>

            <PaymentOrderList
              orders={ordersData?.data ?? []}
              onSelectOrder={(order) => {
                setActiveProofOrder(order);
                // Nếu order đang pending thì mở form nộp proof
                if (order.status === 'pending_payment') {
                  setIsProofDialogOpen(true);
                } else {
                  toast.info(`Đơn hàng #${order.reference_code}: ${order.status_label}`);
                }
              }}
              onOpenSubmitProof={handleOpenProofDialog}
              isLoading={isLoadingOrders}
            />

            {/* Pagination */}
            {ordersData?.meta && ordersData.meta.last_page > 1 && (
              <div className="mt-4 flex items-center justify-end space-x-2">
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => setPageOrders((p) => Math.max(1, p - 1))}
                  disabled={pageOrders <= 1}
                  className="h-8 text-xs"
                >
                  Trang trước
                </Button>
                <span className="text-xs text-slate-600 font-medium">
                  Trang {pageOrders} / {ordersData.meta.last_page}
                </span>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => setPageOrders((p) => p + 1)}
                  disabled={pageOrders >= ordersData.meta.last_page}
                  className="h-8 text-xs"
                >
                  Trang sau
                </Button>
              </div>
            )}
          </div>
        </TabsContent>

        {/* Tab 3: Sổ phụ biến động Credit */}
        <TabsContent value="credit_history" className="space-y-4">
          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-4">
              <h2 className="text-base font-bold text-slate-900">Sổ cái lịch sử Credit</h2>
              <p className="text-xs text-slate-500 mt-0.5">
                Chi tiết mọi giao dịch nạp, trừ credit viết bài và điều chỉnh thủ công của workspace.
              </p>
            </div>

            <CreditHistoryTable
              transactions={creditData?.data ?? []}
              isLoading={isLoadingCredits}
            />

            {/* Pagination */}
            {creditData?.meta && creditData.meta.last_page > 1 && (
              <div className="mt-4 flex items-center justify-end space-x-2">
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => setPageCredits((p) => Math.max(1, p - 1))}
                  disabled={pageCredits <= 1}
                  className="h-8 text-xs"
                >
                  Trang trước
                </Button>
                <span className="text-xs text-slate-600 font-medium">
                  Trang {pageCredits} / {creditData.meta.last_page}
                </span>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => setPageCredits((p) => p + 1)}
                  disabled={pageCredits >= creditData.meta.last_page}
                  className="h-8 text-xs"
                >
                  Trang sau
                </Button>
              </div>
            )}
          </div>
        </TabsContent>
      </Tabs>

      {/* Dialog nộp ảnh minh chứng */}
      <SubmitProofDialog
        open={isProofDialogOpen}
        onOpenChange={setIsProofDialogOpen}
        order={activeProofOrder}
      />
    </div>
  );
}
