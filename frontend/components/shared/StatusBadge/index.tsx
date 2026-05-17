import { Badge } from "@/components/ui/badge";
import { ARTICLE_STATUS_CONFIG } from "@/lib/constants/article-statuses";
import { cn } from "@/lib/utils";

interface StatusBadgeProps {
  status: string;
  className?: string;
}

export function StatusBadge({ status, className }: StatusBadgeProps) {
  const config = ARTICLE_STATUS_CONFIG[status] || {
    label: status,
    color: 'bg-slate-200 text-slate-800',
  };

  return (
    <Badge variant="outline" className={cn(config.color, "font-medium border-transparent", className)}>
      {config.label}
    </Badge>
  );
}
