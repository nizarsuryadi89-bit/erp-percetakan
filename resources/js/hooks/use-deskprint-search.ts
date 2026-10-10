import { useEffect, useState } from 'react';
export function useDeskprintSearch<T>(url: string | null) {
    const [attempt, setAttempt] = useState(0);
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    useEffect(() => {
        const controller = new AbortController(); setData(null); setError('');
        if (!url) { setLoading(false); return; }
        setLoading(true);
        const timer = setTimeout(() => {
            fetch(url, { signal: controller.signal, cache: 'no-store', headers: { Accept: 'application/json' } })
                .then(async (response) => { if (!response.ok) throw new Error('Pencarian gagal. Periksa akses dan koneksi, lalu coba lagi.'); return response.json() as Promise<T>; })
                .then((result) => { if (!controller.signal.aborted) setData(result); })
                .catch((reason: Error) => { if (!controller.signal.aborted) setError(reason.message); })
                .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        }, 250);
        return () => { controller.abort(); clearTimeout(timer); };
    }, [url, attempt]);
    return { data, loading, error, retry: () => setAttempt((value) => value + 1) };
}
