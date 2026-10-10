import { router, usePage } from '@inertiajs/react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItem[];
}) {
    const { workspace } = usePage().props;
    return (
        <header className="flex min-h-16 flex-wrap items-center justify-between gap-3 border-b border-sidebar-border/50 px-4 py-3">
            <div className="flex items-center gap-2">
                <SidebarTrigger />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {workspace.tenant && (
                <div className="flex flex-wrap items-center gap-2 text-xs">
                    <label className="sr-only" htmlFor="active-company">
                        Perusahaan aktif
                    </label>
                    <select
                        id="active-company"
                        value={workspace.tenant.id}
                        onChange={(e) =>
                            router.post(workspace.switchCompanyUrl, {
                                tenant_id: Number(e.target.value),
                            })
                        }
                        className="max-w-44 rounded-md border bg-background px-2 py-1.5"
                    >
                        {workspace.companies.map((company) => (
                            <option key={company.id} value={company.id}>
                                {company.name}
                            </option>
                        ))}
                    </select>
                    {workspace.branches.length > 0 && (
                        <>
                            <label className="sr-only" htmlFor="active-branch">
                                Cabang aktif
                            </label>
                            <select
                                id="active-branch"
                                value={workspace.branchId ?? ''}
                                onChange={(e) =>
                                    router.post(workspace.switchBranchUrl, {
                                        branch_id: Number(e.target.value),
                                    })
                                }
                                className="max-w-40 rounded-md border bg-background px-2 py-1.5"
                            >
                                {workspace.branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                        </>
                    )}
                </div>
            )}
        </header>
    );
}
