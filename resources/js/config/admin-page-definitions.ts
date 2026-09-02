export type AdminSkeletonVariant = "dashboard" | "list" | "detail" | "form" | "settings" | "analytics" | "special";

export interface AdminPageDefinition {
    routePattern: RegExp;
    component: string;
    skeleton: string;
    variant: AdminSkeletonVariant;
    loadingProps: Record<string, unknown>;
    fixture: AdminSkeletonVariant;
}

type PageDefinitionInput = Omit<AdminPageDefinition, "skeleton" | "loadingProps" | "fixture">;

function pageDefinition(input: PageDefinitionInput): AdminPageDefinition {
    const skeleton = `admin-${input.component.toLowerCase().replace(/[^a-z0-9]+/g, "-")}`;

    return {
        ...input,
        skeleton,
        loadingProps: {
            __adminLoading: true,
        },
        fixture: input.variant,
    };
}

const definitions: PageDefinitionInput[] = [
    { routePattern: /^\/administrators\/classes\/[^/]+\/edit$/, component: "administrators/classes/create", variant: "form" },
    { routePattern: /^\/administrators\/classes\/(?!create$)[^/]+$/, component: "administrators/classes/show", variant: "detail" },
    { routePattern: /^\/administrators\/classes\/create$/, component: "administrators/classes/create", variant: "form" },
    { routePattern: /^\/administrators\/classes$/, component: "administrators/classes/index", variant: "list" },
    { routePattern: /^\/administrators\/curriculum$/, component: "administrators/curriculum/programs", variant: "list" },
    { routePattern: /^\/administrators\/curriculum\/programs\/[^/]+$/, component: "administrators/curriculum/programs/show", variant: "detail" },
    { routePattern: /^\/administrators\/curriculum\/programs$/, component: "administrators/curriculum/programs", variant: "list" },
    { routePattern: /^\/administrators\/departments\/[^/]+\/edit$/, component: "administrators/departments/edit", variant: "form" },
    { routePattern: /^\/administrators\/departments\/create$/, component: "administrators/departments/edit", variant: "form" },
    { routePattern: /^\/administrators\/departments$/, component: "administrators/departments/index", variant: "list" },
    {
        routePattern: /^\/administrators\/enrollments\/[^/]+\/assessment-preview$/,
        component: "administrators/enrollments/assessment-preview",
        variant: "detail",
    },
    { routePattern: /^\/administrators\/enrollments\/[^/]+\/edit$/, component: "administrators/enrollments/edit", variant: "form" },
    {
        routePattern: /^\/administrators\/enrollments\/(?!create$|applicants$)[^/]+$/,
        component: "administrators/enrollments/show",
        variant: "detail",
    },
    { routePattern: /^\/administrators\/enrollments\/create$/, component: "administrators/enrollments/create", variant: "form" },
    { routePattern: /^\/administrators\/enrollments\/applicants$/, component: "administrators/enrollments/applicants", variant: "list" },
    { routePattern: /^\/administrators\/enrollments$/, component: "administrators/enrollments/index", variant: "list" },
    { routePattern: /^\/administrators\/faculties\/[^/]+\/edit$/, component: "administrators/faculties/edit", variant: "form" },
    { routePattern: /^\/administrators\/faculties\/(?!create$)[^/]+$/, component: "administrators/faculties/show", variant: "detail" },
    { routePattern: /^\/administrators\/faculties\/create$/, component: "administrators/faculties/create", variant: "form" },
    { routePattern: /^\/administrators\/faculties$/, component: "administrators/faculties/index", variant: "list" },
    { routePattern: /^\/administrators\/finance\/payments\/[^/]+$/, component: "administrators/finance/receipt", variant: "detail" },
    { routePattern: /^\/administrators\/finance\/receipt\/[^/]+$/, component: "administrators/finance/receipt", variant: "detail" },
    {
        routePattern: /^\/administrators\/finance\/tuition-adjustments\/imports\/[^/]+$/,
        component: "administrators/finance/tuition-adjustment-spreadsheet-import",
        variant: "detail",
    },
    {
        routePattern: /^\/administrators\/finance\/tuition-adjustment-spreadsheet-import\/[^/]+$/,
        component: "administrators/finance/tuition-adjustment-spreadsheet-import",
        variant: "detail",
    },
    {
        routePattern: /^\/administrators\/finance\/tuition-update-requests\/[^/]+$/,
        component: "administrators/finance/tuition-update-requests/show",
        variant: "detail",
    },
    { routePattern: /^\/administrators\/finance\/payments\/create$/, component: "administrators/finance/create-payment", variant: "form" },
    { routePattern: /^\/administrators\/finance\/create-payment$/, component: "administrators/finance/create-payment", variant: "form" },
    { routePattern: /^\/administrators\/finance\/tuition-adjustments$/, component: "administrators/finance/tuition-adjustments", variant: "list" },
    {
        routePattern: /^\/administrators\/finance\/tuition-update-requests$/,
        component: "administrators/finance/tuition-update-requests/index",
        variant: "list",
    },
    { routePattern: /^\/administrators\/finance\/invoices$/, component: "administrators/finance/invoices", variant: "list" },
    { routePattern: /^\/administrators\/finance\/payments$/, component: "administrators/finance/payments", variant: "list" },
    { routePattern: /^\/administrators\/finance\/reports$/, component: "administrators/finance/reports", variant: "analytics" },
    { routePattern: /^\/administrators\/finance(?:\/dashboard)?$/, component: "administrators/finance/dashboard", variant: "dashboard" },
    { routePattern: /^\/administrators\/help-tickets\/[^/]+$/, component: "administrators/help/show", variant: "detail" },
    { routePattern: /^\/administrators\/help\/[^/]+$/, component: "administrators/help/show", variant: "detail" },
    { routePattern: /^\/administrators\/help(?:-tickets)?$/, component: "administrators/help/index", variant: "list" },
    { routePattern: /^\/administrators\/inventory\/items\/[^/]+\/edit$/, component: "administrators/inventory/items/edit", variant: "form" },
    { routePattern: /^\/administrators\/inventory\/items\/create$/, component: "administrators/inventory/items/edit", variant: "form" },
    { routePattern: /^\/administrators\/inventory\/items$/, component: "administrators/inventory/items/index", variant: "list" },
    {
        routePattern: /^\/administrators\/inventory\/borrowings\/[^/]+\/edit$/,
        component: "administrators/inventory/borrowings/edit",
        variant: "form",
    },
    { routePattern: /^\/administrators\/inventory\/borrowings\/create$/, component: "administrators/inventory/borrowings/edit", variant: "form" },
    { routePattern: /^\/administrators\/inventory\/borrowings$/, component: "administrators/inventory/borrowings/index", variant: "list" },
    { routePattern: /^\/administrators\/inventory\/ledger$/, component: "administrators/inventory/ledger/index", variant: "list" },
    { routePattern: /^\/administrators\/inventory$/, component: "administrators/inventory/index", variant: "dashboard" },
    { routePattern: /^\/administrators\/library\/authors\/[^/]+\/edit$/, component: "administrators/library/authors/edit", variant: "form" },
    { routePattern: /^\/administrators\/library\/authors\/create$/, component: "administrators/library/authors/edit", variant: "form" },
    { routePattern: /^\/administrators\/library\/authors$/, component: "administrators/library/authors/index", variant: "list" },
    { routePattern: /^\/administrators\/library\/books\/[^/]+\/edit$/, component: "administrators/library/books/edit", variant: "form" },
    { routePattern: /^\/administrators\/library\/books\/create$/, component: "administrators/library/books/edit", variant: "form" },
    { routePattern: /^\/administrators\/library\/books$/, component: "administrators/library/books/index", variant: "list" },
    {
        routePattern: /^\/administrators\/library\/borrow-records\/[^/]+\/edit$/,
        component: "administrators/library/borrow-records/edit",
        variant: "form",
    },
    { routePattern: /^\/administrators\/library\/borrow-records\/create$/, component: "administrators/library/borrow-records/edit", variant: "form" },
    { routePattern: /^\/administrators\/library\/borrow-records$/, component: "administrators/library/borrow-records/index", variant: "list" },
    { routePattern: /^\/administrators\/library\/categories\/[^/]+\/edit$/, component: "administrators/library/categories/edit", variant: "form" },
    { routePattern: /^\/administrators\/library\/categories\/create$/, component: "administrators/library/categories/edit", variant: "form" },
    { routePattern: /^\/administrators\/library\/categories$/, component: "administrators/library/categories/index", variant: "list" },
    {
        routePattern: /^\/administrators\/library\/research-papers\/[^/]+\/edit$/,
        component: "administrators/library/research-papers/edit",
        variant: "form",
    },
    {
        routePattern: /^\/administrators\/library\/research-papers\/create$/,
        component: "administrators/library/research-papers/edit",
        variant: "form",
    },
    { routePattern: /^\/administrators\/library\/research-papers$/, component: "administrators/library/research-papers/index", variant: "list" },
    { routePattern: /^\/administrators\/library$/, component: "administrators/library/index", variant: "dashboard" },
    { routePattern: /^\/administrators\/students\/[^/]+\/documents$/, component: "administrators/students/documents/index", variant: "detail" },
    { routePattern: /^\/administrators\/students\/[^/]+\/edit$/, component: "administrators/students/edit", variant: "form" },
    { routePattern: /^\/administrators\/students\/(?!create$|documents$)[^/]+$/, component: "administrators/students/show", variant: "detail" },
    { routePattern: /^\/administrators\/students\/documents$/, component: "administrators/students/documents/list", variant: "list" },
    { routePattern: /^\/administrators\/students\/create$/, component: "administrators/students/create", variant: "form" },
    { routePattern: /^\/administrators\/students$/, component: "administrators/students/index", variant: "list" },
    { routePattern: /^\/administrators\/users\/[^/]+\/edit$/, component: "administrators/users/edit", variant: "form" },
    { routePattern: /^\/administrators\/users\/create$/, component: "administrators/users/create", variant: "form" },
    { routePattern: /^\/administrators\/users$/, component: "administrators/users/index", variant: "list" },
    { routePattern: /^\/administrators\/roles\/[^/]+\/edit$/, component: "administrators/roles/edit", variant: "form" },
    { routePattern: /^\/administrators\/roles$/, component: "administrators/roles/index", variant: "list" },
    { routePattern: /^\/administrators\/feature-toggles$/, component: "administrators/feature-toggles/index", variant: "settings" },
    { routePattern: /^\/administrators\/audit-logs$/, component: "administrators/audit-logs/index", variant: "list" },
    { routePattern: /^\/administrators\/registrar\/analytics$/, component: "administrators/registrar/analytics", variant: "analytics" },
    { routePattern: /^\/administrators\/registrar\/reports$/, component: "administrators/registrar/reports", variant: "analytics" },
    { routePattern: /^\/administrators\/scheduling-analytics$/, component: "administrators/scheduling-analytics", variant: "analytics" },
    { routePattern: /^\/administrators\/module-marketplace$/, component: "administrators/module-marketplace/index", variant: "list" },
    { routePattern: /^\/administrators\/notifications\/inbox$/, component: "notifications/index", variant: "special" },
    { routePattern: /^\/administrators\/notifications$/, component: "NotificationCenter/Index", variant: "special" },
    { routePattern: /^\/administrators\/announcements$/, component: "Announcement/Index", variant: "special" },
    { routePattern: /^\/administrators\/settings\/newsletter$/, component: "administrators/system-management/newsletter", variant: "settings" },
    { routePattern: /^\/administrators\/settings$/, component: "profile", variant: "settings" },
    { routePattern: /^\/administrators\/system-management\/school$/, component: "administrators/system-management/school", variant: "settings" },
    {
        routePattern: /^\/administrators\/system-management\/enrollment-pipeline$/,
        component: "administrators/system-management/enrollment-pipeline",
        variant: "settings",
    },
    { routePattern: /^\/administrators\/system-management\/seo$/, component: "administrators/system-management/seo", variant: "settings" },
    {
        routePattern: /^\/administrators\/system-management\/analytics$/,
        component: "administrators/system-management/analytics",
        variant: "analytics",
    },
    {
        routePattern: /^\/administrators\/system-management\/brand(?:\/appearance)?$/,
        component: "administrators/system-management/brand",
        variant: "settings",
    },
    {
        routePattern: /^\/administrators\/system-management\/socialite$/,
        component: "administrators/system-management/socialite",
        variant: "settings",
    },
    { routePattern: /^\/administrators\/system-management\/mail$/, component: "administrators/system-management/mail", variant: "settings" },
    {
        routePattern: /^\/administrators\/system-management\/newsletter$/,
        component: "administrators/system-management/newsletter",
        variant: "settings",
    },
    { routePattern: /^\/administrators\/system-management\/api$/, component: "administrators/system-management/api", variant: "settings" },
    {
        routePattern: /^\/administrators\/system-management\/notifications$/,
        component: "administrators/system-management/notifications",
        variant: "settings",
    },
    {
        routePattern: /^\/administrators\/system-management\/finance-documents$/,
        component: "administrators/system-management/finance-documents",
        variant: "settings",
    },
    {
        routePattern: /^\/administrators\/system-management\/tuition-payment-schedule$/,
        component: "administrators/system-management/tuition-payment-schedule",
        variant: "settings",
    },
    { routePattern: /^\/administrators\/system-management\/grading$/, component: "administrators/system-management/grading", variant: "settings" },
    {
        routePattern: /^\/administrators\/system-management\/identifiers$/,
        component: "administrators/system-management/identifiers",
        variant: "settings",
    },
    {
        routePattern: /^\/administrators\/system-management\/faculty-fields$/,
        component: "administrators/system-management/faculty-fields",
        variant: "settings",
    },
    { routePattern: /^\/administrators\/system-management\/pulse$/, component: "administrators/system-management/pulse", variant: "analytics" },
    { routePattern: /^\/administrators\/system-management$/, component: "administrators/system-management/index", variant: "settings" },
    { routePattern: /^\/administrators\/dashboard$/, component: "administrators/dashboard", variant: "dashboard" },
    { routePattern: /a^/, component: "administrators/curriculum/programs/index", variant: "list" },
];

export const ADMIN_PAGE_DEFINITIONS = definitions.map(pageDefinition);

export function resolveAdminPageDefinition(href: string): AdminPageDefinition | null {
    let pathname = href;

    try {
        pathname = new URL(href, "http://admin.local").pathname;
    } catch {
        pathname = href.split("?")[0].split("#")[0];
    }

    return ADMIN_PAGE_DEFINITIONS.find((definition) => definition.routePattern.test(pathname)) ?? null;
}

export function resolveAdminPageDefinitionByComponent(component: string): AdminPageDefinition | null {
    return ADMIN_PAGE_DEFINITIONS.find((definition) => definition.component === component) ?? null;
}

export function getAdminPageDefinitions(): readonly AdminPageDefinition[] {
    return ADMIN_PAGE_DEFINITIONS;
}
