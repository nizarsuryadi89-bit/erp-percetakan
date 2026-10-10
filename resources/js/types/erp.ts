export type Company = {
    id: number;
    uuid: string;
    name: string;
    phone?: string | null;
    address?: string | null;
};
export type Branch = {
    id: number;
    code: string;
    name: string;
    address?: string | null;
    is_active: boolean;
};
export type NavigationLink = { title: string; href: string; code: string };
export type Workspace = {
    tenant: Company | null;
    companies: Company[];
    branches: Branch[];
    branchId: number | null;
    navigation: NavigationLink[];
    settings: NavigationLink[];
    platform: NavigationLink[];
    roles: string[];
    switchCompanyUrl: string;
    switchBranchUrl: string;
    createCompanyUrl: string;
};
export type License = {
    package_name: string;
    price: string;
    status: string;
    activated_at: string | null;
    expires_at: string | null;
    support_ends_at: string | null;
};
export type ModuleDefinition = {
    name: string;
    description: string;
    dependencies: string[];
    steps: string[];
};
export type Catalog = Record<string, ModuleDefinition>;
export type TenantUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
};

export type ModuleEntitlement = {
    code: string;
    is_granted: boolean;
    is_enabled: boolean;
    is_usable: boolean;
    expires_at: string | null;
};
export type PlatformModule = {
    id: number;
    code: string;
    name: string;
    description: string;
    is_active: boolean;
    dependencies: { code: string; name: string }[];
};
