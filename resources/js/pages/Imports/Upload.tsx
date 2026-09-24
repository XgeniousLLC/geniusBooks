import PortalLayout from '@/layouts/PortalLayout';
import { Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { type PageProps } from '@/types';

interface Props {
    type: 'customers' | 'products';
    title: string;
    columns: string[];
    sample: string[];
    indexUrl: string;
    templateUrl: string;
}

export default function ImportUpload({ type, title, columns, sample, indexUrl, templateUrl }: Props) {
    const { flash } = usePage<PageProps>().props;
    const result = flash?.importResult;

    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/portal/${type}/import`, { forceFormData: true });
    };

    return (
        <PortalLayout>
            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">{title}</h1>
                <p className="text-sm text-slate-500 mt-0.5">
                    Upload a CSV file. Imports are validated first — nothing is saved if any row is invalid.
                </p>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1.5">CSV file</label>
                                <input
                                    type="file"
                                    accept=".csv,text/csv"
                                    onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                                    className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                                />
                                {form.errors.file && <p className="mt-1 text-xs text-red-500">{form.errors.file}</p>}
                            </div>
                            <div className="flex items-center gap-3">
                                <button
                                    type="submit"
                                    disabled={form.processing || !form.data.file}
                                    className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {form.processing ? 'Importing...' : 'Upload & import'}
                                </button>
                                <a href={templateUrl} className="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                                    Download CSV template
                                </a>
                            </div>
                        </form>
                    </div>

                    {result && (
                        <div className={`rounded-xl border p-6 ${result.errors.length > 0 ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50'}`}>
                            {result.errors.length > 0 ? (
                                <>
                                    <h2 className="text-sm font-semibold text-red-800">Import failed — no rows were saved</h2>
                                    <ul className="mt-3 space-y-1 text-sm text-red-700">
                                        {result.errors.slice(0, 25).map((error, index) => (
                                            <li key={index}>Row {error.row}: {error.message}</li>
                                        ))}
                                        {result.errors.length > 25 && <li>… and {result.errors.length - 25} more</li>}
                                    </ul>
                                </>
                            ) : (
                                <h2 className="text-sm font-semibold text-green-800">
                                    Imported {result.imported} record{result.imported === 1 ? '' : 's'}
                                    {result.skipped > 0 && ` · skipped ${result.skipped} duplicate${result.skipped === 1 ? '' : 's'}`}.
                                </h2>
                            )}
                        </div>
                    )}
                </div>

                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 h-fit">
                    <h2 className="text-sm font-semibold text-slate-800 mb-3">Expected columns</h2>
                    <ul className="space-y-1 text-xs text-slate-600 font-mono">
                        {columns.map((column) => (
                            <li key={column}>{column}</li>
                        ))}
                    </ul>
                    <p className="mt-4 text-xs text-slate-500">
                        The first row must be a header. A sample row:
                    </p>
                    <p className="mt-1 text-xs text-slate-600 font-mono break-words">{sample.join(', ')}</p>
                    <Link href={indexUrl} className="mt-5 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-800">
                        &larr; Back to list
                    </Link>
                </div>
            </div>
        </PortalLayout>
    );
}
