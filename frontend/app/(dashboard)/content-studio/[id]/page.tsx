import { redirect } from 'next/navigation';

interface ContentStudioRedirectPageProps {
  params: Promise<{ id: string }>;
}

export default async function ContentStudioRedirectPage({
  params,
}: ContentStudioRedirectPageProps) {
  const { id } = await params;
  redirect(`/articles/${id}/edit`);
}
