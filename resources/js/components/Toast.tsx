import { usePage } from '@inertiajs/react';
import { type PageProps } from '@/types';

export default function Toast() {
    const { flash } = usePage<PageProps>().props;

    const message = flash?.error ?? flash?.success;

    if (!message) {
        return null;
    }

    const isError = Boolean(flash?.error);

    return (
        <>
            <style>{`@keyframes xg-toast { 0%, 80% { opacity: 1; } 100% { opacity: 0; visibility: hidden; } }`}</style>
            <div
                key={message}
                role="status"
                aria-live="polite"
                style={{ animation: 'xg-toast 4s ease forwards' }}
                className={`fixed bottom-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-sm font-medium text-white max-w-sm pointer-events-none
                    ${isError ? 'bg-red-600' : 'bg-green-600'}`}
            >
                {message}
            </div>
        </>
    );
}
