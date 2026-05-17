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
};
