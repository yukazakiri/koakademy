export type InventoryItem = {
    id: number;
    name: string;
    price: number;
    sku: string;
    category: string;
};

export type PaymentMethodOption = {
    value: string;
    label: string;
};

export type PaymentWorkspacePreference = {
    layout: "guided" | "spreadsheet";
    density: "comfortable" | "compact";
    history_visibility: "auto" | "open" | "hidden";
    default_payment_method: string;
};

export type FeeType = {
    id: string;
    label: string;
};

export type StudentOption = {
    id: number;
    full_name: string;
    email: string;
    course_code: string | null;
    formatted_academic_year: string | null;
};

export type UnpaidEnrollment = {
    id: number;
    enrollment_id: number;
    school_year: string;
    semester: number;
    total_amount?: number;
    paid?: number;
    balance: number;
};

export type StudentFinancialDetails = {
    id: number;
    full_name: string;
    student_id: number;
    course: string;
    year_level: number;
    outstanding_balance: number;
    unpaid_enrollments: UnpaidEnrollment[];
};

export type StudentTransactionHistory = {
    id: number;
    transaction_number: string | null;
    reference_number: string | null;
    date: string | null;
    time: string | null;
    amount: number;
    payment_method: string | null;
    status: string;
    cashier: string;
    remarks: string | null;
    settlements: Record<string, number>;
    receipt_url: string;
};

export type StudentTransactionHistoryResponse = {
    transactions: StudentTransactionHistory[];
    summary: { count: number; total_paid: number };
};

export const FEE_TYPES: FeeType[] = [
    { id: "registration_fee", label: "Registration Fee" },
    { id: "miscelanous_fee", label: "Miscellaneous Fee" },
    { id: "diploma_or_certificate", label: "Diploma / Certificate" },
    { id: "transcript_of_records", label: "Transcript of Records" },
    { id: "certification", label: "Certification" },
    { id: "special_exam", label: "Special Exam" },
    { id: "others", label: "Other Fees" },
];

export const defaultPaymentWorkspace: PaymentWorkspacePreference = {
    layout: "guided",
    density: "comfortable",
    history_visibility: "auto",
    default_payment_method: "Cash",
};

export function formatCurrency(amount: number, currency = "PHP"): string {
    return new Intl.NumberFormat(currency === "USD" ? "en-US" : "en-PH", {
        style: "currency",
        currency,
        minimumFractionDigits: 2,
    }).format(amount || 0);
}

export function makeClientRowId(): string {
    if (typeof crypto !== "undefined" && "randomUUID" in crypto) return crypto.randomUUID();

    return `row-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}
