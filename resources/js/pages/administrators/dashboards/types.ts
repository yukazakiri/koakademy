import type { User } from "@/types/user";

export type AdminStatTone = "success" | "warning" | "info" | "neutral";

export type AdminStat = {
    label: string;
    value: number | string;
    description: string;
    format?: "number" | "percent";
    series?: Array<{ date: string; value: number }>;
    tone: AdminStatTone;
    trend?: number;
};

export type QuickAction = {
    title: string;
    description: string;
    href: string;
    disabled: boolean;
    disabledTooltip?: string;
};

export type RecentActivityItem = {
    actor: string;
    action: string;
    time: string;
    status: "success" | "info" | "warning" | "error" | "neutral";
};

export type BeginnerTip = {
    title: string;
    content: string;
};

export type PipelineItem = {
    status: string;
    count: number;
    color?: string;
};

export type StudentTypeItem = {
    type: string;
    label: string;
    count: number;
    percentage: number;
};

export type TopCourse = {
    code: string;
    title: string;
    student_count: number;
};

export type RecentStudent = {
    id: number;
    student_id: string | null;
    name: string;
    type: string | null;
    status: string | null;
    course: string | null;
    registered_at: string;
};

export type FinanceSnapshot = {
    total_revenue: number;
    total_collectibles: number;
    total_assessed: number;
    collection_rate: number;
    fully_paid_count: number;
    outstanding_count: number;
    today_collection: number;
    today_transactions: number;
};

export type ActionQueueItem = {
    label: string;
    value: number;
    description: string;
    href: string;
    tone: AdminStatTone;
};

export type TrendPoint = {
    date: string;
    month: string;
    enrollments: number;
};

export type AdminAnalytics = {
    last_updated_at: string;
    enrollment_trends: TrendPoint[];
    enrollment_status: PipelineItem[];
    application_vs_enrollment: {
        applicants: number;
        enrolled: number;
        on_leave: number;
        conversion_rate: number;
    };
    student_types: StudentTypeItem[];
    gender_distribution: { gender: string; count: number }[];
    year_level_distribution: { year_level: string; count: number }[];
    top_courses: TopCourse[];
    recent_students: RecentStudent[];
};

export type AdminData = {
    current_period: {
        school_year: string;
        semester: number;
        label: string;
    };
    stats: AdminStat[];
    quick_actions: QuickAction[];
    recent_activity: RecentActivityItem[];
    beginner_tips: BeginnerTip[];
    executive_summary: {
        kpis: AdminStat[];
        last_updated_at: string;
    };
    enrollment_health: {
        pending: number;
        enrolled_this_period: number;
        conversion_rate: number;
        applicants: number;
        enrolled: number;
        on_leave: number;
        pipeline: PipelineItem[];
        trends: TrendPoint[];
    };
    student_demographics: {
        total: number;
        by_type: StudentTypeItem[];
        by_gender: { gender: string; count: number }[];
        by_year_level: { year_level: string; count: number }[];
        top_courses: TopCourse[];
    };
    finance_snapshot: FinanceSnapshot;
    operations: {
        total_faculty: number;
        active_classes: number;
        total_users: number;
        unassigned_classes: number;
        action_queue: ActionQueueItem[];
    };
    recent_records: {
        students: RecentStudent[];
        activity: RecentActivityItem[];
    };
    analytics: AdminAnalytics;
};

export interface DashboardViewProps {
    user: User;
    admin_data: AdminData;
    currency: string;
}
