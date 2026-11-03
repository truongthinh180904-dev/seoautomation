import { redirect } from 'next/navigation';

export default function DeprecatedWorkspacesPage() {
  redirect('/admin/workspaces');
}
