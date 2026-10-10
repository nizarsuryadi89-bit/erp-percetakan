import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
export function DeskprintConnection() {
    const [offline, setOffline] = useState(false);
    const [failed, setFailed] = useState(false);
    useEffect(() => {
        const changed = () => setOffline(!navigator.onLine);
        changed();
        window.addEventListener('online', changed); window.addEventListener('offline', changed);
        const stop = router.on('networkError', (event) => { event.preventDefault(); setFailed(true); });
        return () => { stop(); window.removeEventListener('online', changed); window.removeEventListener('offline', changed); };
    }, []);
    if (!offline && !failed) return null;
    return <div role="alert" className="rounded-lg border border-amber-400 bg-amber-50 p-4 text-sm text-amber-950 dark:bg-amber-950 dark:text-amber-100">
        <p>{offline ? 'Anda sedang offline.' : 'Respons server belum diterima.'} Isian tetap di layar. Periksa koneksi lalu coba tombol simpan kembali. Status tersimpan hanya berlaku setelah server mengonfirmasi.</p>
        {!offline && <button type="button" className="mt-2 min-h-11 underline" onClick={() => setFailed(false)}>Tutup pemberitahuan</button>}
    </div>;
}
