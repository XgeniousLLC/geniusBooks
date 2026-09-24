export interface User {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
}

export interface Admin {
    id: number;
    name: string;
    email: string;
}

export interface Company {
    id: number;
    name: string;
    slug?: string;
    currency?: string;
    logo_path?: string | null;
}

export interface CompanyOption {
    id: number;
    name: string;
}

export interface ImportResult {
    imported: number;
    skipped: number;
    errors: { row: number; message: string }[];
}

export interface PageProps {
    auth: { user: User; roles: string[] };
    currentCompany?: Company | null;
    companies?: CompanyOption[];
    impersonating?: boolean;
    demo?: { email: string; password: string };
    flash?: {
        success?: string;
        error?: string;
        status?: string;
        importResult?: ImportResult | null;
    };
    errors?: Record<string, string>;
}

export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
