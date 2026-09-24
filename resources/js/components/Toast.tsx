import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { type PageProps } from '@/types';

interface ToastItem {
    id: number;
    message: string;
    type: 'success' | 'error';
}

let nextId = 0;

export default function Toast() {
    const { flash } = usePage<PageProps>().props;
    const [toasts, setToasts] = useState<ToastItem[]>([]);

    useEffect(() => {
        const items: ToastItem[] = [];
        if (flash?.success) items.push({ id: ++nextId, message: flash.success, type: 'success' });
        if (flash?.error) items.push({ id: ++nextId, message: flash.error, type: 'error' });

        if (items.length === 0) return;

        setToasts((prev) => [...prev, ...items]);

        const timer = setTimeout(() => {
            setToasts((prev) => prev.filter((t) => !items.find((i) => i.id === t.id)));
        }, 4000);

        return () => clearTimeout(timer);
    }, [flash?.success, flash?.error]);

    if (toasts.length === 0) return null;

    return (
        <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none">
            {toasts.map((t) => (
                <div
                    key={t.id}
                    className={`px-4 py-3 rounded-lg shadow-lg text-sm font-medium text-white max-w-sm pointer-events-auto
                        ${t.type === 'success' ? 'bg-green-600' : 'bg-red-600'}`}
                >
                    {t.message}
                </div>
            ))}
        </div>
    );
}
