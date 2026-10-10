import type { Workspace } from '@/types/erp';
import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            workspace: Workspace;
            flash: { success: string | null };
            [key: string]: unknown;
        };
    }
}
