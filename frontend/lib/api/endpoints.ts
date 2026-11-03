export const ENDPOINTS = {
  AUTH: {
    LOGIN: '/auth/login',
    LOGOUT: '/auth/logout',
    ME: '/auth/me',
  },
  KEYWORDS: {
    LIST: '/keywords',
    IMPORT: '/keywords/import',
    BULK_DESTROY: '/keywords/bulk-destroy',
    DETAIL: (id: number) => `/keywords/${id}`,
  },
  ARTICLES: {
    LIST: '/articles',
    DETAIL: (id: number) => `/articles/${id}`,
  },
  WORDPRESS_SITES: {
    LIST: '/wordpress-sites',
    DETAIL: (id: number) => `/wordpress-sites/${id}`,
    TEST: (id: number) => `/wordpress-sites/${id}/test`,
  },
  BILLING: {
    PLANS: '/billing/plans',
    SUBSCRIPTION: '/billing/subscription',
    ORDERS: '/billing/orders',
    ORDER_DETAIL: (id: number) => `/billing/orders/${id}`,
    SUBMIT_PROOF: (id: number) => `/billing/orders/${id}/proof`,
    CANCEL_ORDER: (id: number) => `/billing/orders/${id}/cancel`,
    CREDIT_HISTORY: '/billing/credit-history',
    BALANCE: '/billing/balance',
  },
  ADMIN: {
    PAYMENT_ORDERS: '/admin/payment-orders',
    PAYMENT_ORDER_DETAIL: (id: number) => `/admin/payment-orders/${id}`,
    APPROVE_ORDER: (id: number) => `/admin/payment-orders/${id}/approve`,
    REJECT_ORDER: (id: number) => `/admin/payment-orders/${id}/reject`,
    WORKSPACES: '/admin/workspaces',
    WORKSPACE_DETAIL: (id: number) => `/admin/workspaces/${id}`,
    ADJUST_CREDIT: (id: number) => `/admin/workspaces/${id}/credit-adjust`,
    UPDATE_WORKSPACE_STATUS: (id: number) => `/admin/workspaces/${id}/status`,
  },
};
