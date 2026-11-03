interface Plan {
  id: number;
  code: 'free' | 'basic' | 'pro' | 'business';
  name: string;
  description: string;
  price_monthly_vnd: number;
  price_formatted: string;
  billing_interval: 'daily' | 'monthly' | 'yearly';
  credits_per_cycle: number;
  articles_limit_per_cycle: number;
  websites_limit: number;
  max_concurrent_tasks: number;
  max_retries_per_task: number;
  max_images_per_article: number;
  carryover: boolean;
  features: string[];
  is_active: boolean;
  sort_order: number;
}

type PaymentOrderStatusType =
  | 'pending_payment'
  | 'pending_verification'
  | 'approved'
  | 'rejected'
  | 'cancelled'
  | 'expired';

interface BankInfoSnapshot {
  bank_name: string;
  bank_code?: string;
  account_number: string;
  account_holder: string;
  branch?: string;
  transfer_syntax: string;
  amount_vnd: number;
  qr_payload?: string;
  instructions?: string;
}

interface PaymentTransaction {
  id: number;
  payment_order_id: number;
  workspace_id: number;
  transaction_code: string | null;
  amount_vnd: number;
  amount_formatted: string;
  direction: 'inbound' | 'refund';
  status: 'recorded' | 'verified' | 'voided';
  status_label: string;
  paid_at: string | null;
  created_at: string;
}

interface PaymentOrder {
  id: number;
  workspace_id: number;
  user_id: number;
  plan_id: number;
  reference_code: string;
  amount_vnd: number;
  amount_formatted: string;
  currency: string;
  payment_method: string;
  status: PaymentOrderStatusType;
  status_label: string;
  bank_info_snapshot: BankInfoSnapshot | null;
  proof_image_path: string | null;
  proof_image_url: string | null;
  customer_note: string | null;
  proof_submitted_at: string | null;
  reviewed_by: number | null;
  reviewed_at: string | null;
  admin_note: string | null;
  rejection_reason: string | null;
  expires_at: string | null;
  created_at: string;
  plan?: Plan;
  workspace?: {
    id: number;
    name: string;
    slug: string;
    status: string;
  };
  user?: {
    id: number;
    name: string;
    email: string;
  };
  reviewer?: {
    id: number;
    name: string;
    email: string;
  };
  transactions?: PaymentTransaction[];
}

interface CreditBalance {
  workspace_id?: number;
  available_credits: number;
  reserved_credits: number;
  total_credits: number;
  cycle_granted_credits: number;
  cycle_used_credits: number;
  period_start: string | null;
  period_end: string | null;
  plan_code?: string;
  plan_name?: string;
}

interface CreditTransactionItem {
  id: number;
  credit_account_id: number;
  workspace_id: number;
  credit_grant_id: number | null;
  type: string;
  type_label: string;
  amount: number;
  balance_before: number;
  balance_after: number;
  reference_type: string | null;
  reference_id: number | null;
  actor_id: number | null;
  description: string | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
}

interface SubscriptionCurrentResponse {
  subscription: {
    id: number;
    workspace_id: number;
    plan_id: number;
    status: string;
    status_label: string;
    current_period_start: string;
    current_period_end: string;
    last_credit_granted_at: string;
    next_credit_grant_at: string;
    created_at: string;
    plan?: Plan;
  } | null;
  plan: Plan;
  balance: CreditBalance;
}

interface AdminWorkspaceSummary {
  id: number;
  name: string;
  slug: string;
  status: 'active' | 'suspended';
  owner: {
    id: number;
    name: string;
    email: string;
  };
  current_plan: string;
  available_credits: number;
  websites_count: number;
  created_at: string;
}
