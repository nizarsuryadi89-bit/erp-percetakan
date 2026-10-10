import { usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2 } from 'lucide-react';
import type { ReactNode } from 'react';

export function ErpPage({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    const { flash, errors } = usePage().props;
    return (
        <main className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-8">
            <div>
                <p className="mb-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                    ERP Percetakan
                </p>
                <h1 className="text-2xl font-semibold tracking-tight">
                    {title}
                </h1>
                <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                    {description}
                </p>
            </div>
            {flash.success && (
                <div
                    role="status"
                    className="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                >
                    <CheckCircle2 className="size-4" />
                    {flash.success}
                </div>
            )}
            {Object.keys(errors).length > 0 && (
                <div
                    role="alert"
                    className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                >
                    <div className="mb-1 flex items-center gap-2 font-medium">
                        <AlertCircle className="size-4" />
                        Periksa kembali isian Anda
                    </div>
                    {Object.entries(errors).map(([key, error]) => (
                        <p key={key}>{error}</p>
                    ))}
                </div>
            )}
            {children}
        </main>
    );
}
export const panel = 'rounded-xl border bg-card p-5 shadow-sm';
export const field = 'h-10 w-full rounded-md border bg-background px-3 text-sm';
