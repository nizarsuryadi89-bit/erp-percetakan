import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardList,
    GitBranch,
    LayoutGrid,
    Layers,
    Factory,
    Wallet,
    ChartNoAxesCombined,
    Users,
    Settings2,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
    SidebarGroupLabel,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavigationLink } from '@/types/erp';

const icons = {
    dashboard: LayoutGrid,
    POS: ClipboardList,
    PRODUCTION: Factory,
    INVENTORY: Layers,
    FINANCE: Wallet,
    CRM: Users,
    HRM: Users,
    ANALYTICS: ChartNoAxesCombined,
    AI_ASSISTANT: Settings2,
    platform: Settings2,
    transaksi: ClipboardList,
    bom: Layers,
    routing: GitBranch,
    produksi: Factory,
    keuangan: Wallet,
    laba_rugi: ChartNoAxesCombined,
    perusahaan: Building2,
    cabang: GitBranch,
    pengguna: Users,
    modul: Settings2,
};
function links(items: NavigationLink[]) {
    return items.map((item) => ({
        ...item,
        icon: icons[item.code as keyof typeof icons] ?? LayoutGrid,
    }));
}
export function AppSidebar() {
    const { workspace } = usePage().props;
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()}>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={links(workspace.platform)} />
                <NavMain items={links(workspace.navigation)} />
                {workspace.settings.length > 0 && (
                    <SidebarGroup>
                        <SidebarGroupLabel>Pengaturan</SidebarGroupLabel>
                        <NavMain items={links(workspace.settings)} />
                    </SidebarGroup>
                )}
            </SidebarContent>
            <SidebarFooter>
                <div className="px-3 py-2 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
                    ERP Percetakan
                    <span className="mt-1 block">
                        Transaksi · Produksi · Keuangan
                    </span>
                </div>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
