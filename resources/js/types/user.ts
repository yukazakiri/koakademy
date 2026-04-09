export interface User {
    id?: number;
    name: string;
    email: string;
    avatar: string | null;
    role: string;
    permissions?: string[];
    school_id?: number | null;
    department_id?: number | null;
}

export interface Organization {
    id: number;
    name: string;
    code: string;
    description?: string | null;
    is_active: boolean;
}
