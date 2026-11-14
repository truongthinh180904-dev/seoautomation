import { redirect } from 'next/navigation';

export default function DeprecatedPaymentOrdersPage() {
  redirect('/admin/payment-orders');
}
