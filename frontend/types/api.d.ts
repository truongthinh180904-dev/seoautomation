interface ApiResponse<T> {
  data: T;
  message?: string;
}

interface ApiPaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
}
