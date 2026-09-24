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

export interface PageProps {
    auth: { user: User };
    flash?: { success?: string; error?: string; status?: string };
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
