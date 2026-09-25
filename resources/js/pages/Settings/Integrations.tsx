import PortalLayout from '@/layouts/PortalLayout';
import { useForm, router } from '@inertiajs/react';
import { type FormEvent } from 'react';

interface Integration {
    id: number;
    provider: string;
    status: string;
    last_sync_at: string | null;
    last_error: string | null;
}

interface Provider {
    provider: string;
    label: string;
    docs_url: string;
    api_base: string;
    integration: Integration | null;
}

interface Props {
    providers: Provider[];
}

export default function Integrations({ providers }: Props) {
    const form = useForm({ provider: 'xero', access_token: '', refresh_token: '', external_id: '' });

    const connect = (e: FormEvent) => {
        e.preventDefault();
        form.post('/portal/settings/integrations', { onSuccess: () => form.reset('access_token') });
    };

    const disconnect = (id: number) => {
        if (!window.confirm('Disconnect this integration?')) return;
        router.delete(`/portal/settings/integrations/${id}`);
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">Finance integrations</h1>
                <p className="text-sm text-slate-500 mt-0.5">Two-way sync with Xero, QuickBooks, HubSpot, FreshBooks and 6 more. Connect once, push/pull from portal or API.</p>
            </div>

            <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6 max-w-3xl">
                <h2 className="text-sm font-semibold text-slate-900 mb-3">Connect a provider</h2>
                <form onSubmit={connect} className="space-y-3">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <select className="rounded-lg border border-slate-200 px-3 py-2 text-sm" value={form.data.provider} onChange={(e) => form.setData('provider', e.target.value)}>
                            {providers.map((p) => (
                                <option key={p.provider} value={p.provider}>{p.label} {p.integration ? '— connected' : ''}</option>
                            ))}
                        </select>
                        <input className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Access token / API key" value={form.data.access_token} onChange={(e) => form.setData('access_token', e.target.value)} required />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Refresh token (optional)" value={form.data.refresh_token} onChange={(e) => form.setData('refresh_token', e.target.value)} />
                        <input className="rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="External company/org ID (optional)" value={form.data.external_id} onChange={(e) => form.setData('external_id', e.target.value)} />
                    </div>
                    <button disabled={form.processing} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">Connect / Update</button>
                    {form.errors.access_token && <p className="text-xs text-red-500">{form.errors.access_token}</p>}
                </form>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-4xl">
                {providers.map((p) => (
                    <div key={p.provider} className="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">{p.label}</h3>
                                <p className="text-xs text-slate-500 mt-0.5">{p.api_base}</p>
                                <a href={p.docs_url} target="_blank" rel="noreferrer" className="text-xs text-indigo-600 hover:underline">Docs</a>
                            </div>
                            <span className={`text-xs px-2 py-1 rounded-full ${p.integration?.status === 'connected' ? 'bg-green-50 text-green-700 border border-green-200' : p.integration?.status === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-slate-50 text-slate-600 border'}`}>
                                {p.integration?.status ?? 'disconnected'}
                            </span>
                        </div>
                        {p.integration ? (
                            <div className="mt-3 space-y-1 text-xs text-slate-600">
                                <div>Last sync: {p.integration.last_sync_at ?? 'Never'}</div>
                                {p.integration.last_error && <div className="text-red-600">Error: {p.integration.last_error}</div>}
                                <div className="flex gap-2 mt-3">
                                    <button onClick={() => disconnect(p.integration!.id)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">Disconnect</button>
                                    <a href={`/portal/settings/integrations/${p.integration.id}/logs`} className="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800">View logs</a>
                                </div>
                            </div>
                        ) : (
                            <p className="mt-3 text-xs text-slate-400">Not connected — use the form above to connect.</p>
                        )}
                    </div>
                ))}
            </div>

            <div className="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 max-w-4xl text-xs text-slate-600">
                <p className="font-semibold">Two-way sync</p>
                <p className="mt-1">Push local customers/invoices/payments to the provider, or pull remote records. Sync is available in portal (this page) and via <code>/api/v1</code> when you use an API application token. Sync logs are stored per integration for audit.</p>
            </div>
        </PortalLayout>
    );
}
