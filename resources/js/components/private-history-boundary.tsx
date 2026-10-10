import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { home } from '@/routes';

export function PrivateHistoryBoundary({ children }: { children: ReactNode }) {
    useEffect(() => {
        const channel = typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel('erp-private-history');
        const stop = router.on('success', (event) => {
            if (event.detail.page.clearHistory) {
                channel?.postMessage('context-cleared');
            }
        });
        if (channel) {
            channel.onmessage = (event: MessageEvent<unknown>) => {
                if (event.data === 'context-cleared') {
                    router.clearHistory();
                    window.location.replace(home.url());
                }
            };
        }
        const refreshRestoredPage = (event: PageTransitionEvent) => {
            if (event.persisted) {
                window.location.reload();
            }
        };
        window.addEventListener('pageshow', refreshRestoredPage);
        return () => {
            stop();
            channel?.close();
            window.removeEventListener('pageshow', refreshRestoredPage);
        };
    }, []);
    return children;
}
