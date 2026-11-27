import {
    bulkClassAction,
    bulkMove,
    dropEnrollment,
    enroll,
    parallelSections,
    previewScheduleImpact,
    promoteWaitlist,
    restoreEnrollment,
    searchStudents,
    show,
    updateEnrollment,
    updateScheduleWorkspace,
} from "@/actions/App/Http/Controllers/AdministratorClassWorkspaceController";
import type {
    ClassWorkspacePayload,
    EditableScheduleBlock,
    EnrollmentStatusValue,
    ParallelSectionsWorkspacePayload,
    ScheduleImpactResponse,
    StudentSearchCandidate,
    WorkspaceEnrollment,
} from "./types";

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";
}

function xsrfCookieToken(): string {
    if (typeof document === "undefined") return "";
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : "";
}

export class WorkspaceApiError extends Error {
    status: number;
    errors: Record<string, string[]>;

    constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
        super(message);
        this.name = "WorkspaceApiError";
        this.status = status;
        this.errors = errors;
    }
}

export async function requestJson<T>(url: string, options: RequestInit = {}): Promise<T> {
    const method = (options.method ?? "GET").toUpperCase();
    const headers: Record<string, string> = {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...(options.headers as Record<string, string> | undefined),
    };

    if (method !== "GET" && method !== "HEAD") {
        headers["Content-Type"] = "application/json";
        const metaToken = csrfToken();
        if (metaToken) {
            headers["X-CSRF-TOKEN"] = metaToken;
        }
        const xsrfToken = xsrfCookieToken();
        if (xsrfToken) {
            headers["X-XSRF-TOKEN"] = xsrfToken;
        }
    }

    const response = await fetch(url, {
        ...options,
        method,
        headers,
        credentials: "same-origin",
    });

    const text = await response.text();
    let payload: Record<string, unknown> = {};
    if (text) {
        try {
            payload = JSON.parse(text) as Record<string, unknown>;
        } catch {
            payload = { message: text };
        }
    }

    if (!response.ok) {
        const rawErrors = (payload.errors as Record<string, string[]> | undefined) ?? {};
        const firstFieldError = Object.values(rawErrors).flat()[0];
        const message = firstFieldError || (typeof payload.message === "string" ? payload.message : `Request failed (${response.status})`);
        throw new WorkspaceApiError(message, response.status, rawErrors);
    }

    return payload as T;
}

export function fetchClassWorkspace(classId: number, includeTrashed = false): Promise<ClassWorkspacePayload> {
    return requestJson<ClassWorkspacePayload>(show.url(classId, { query: includeTrashed ? { include_trashed: 1 } : undefined }));
}

export async function searchClassCandidates(classId: number, query: string, limit = 15): Promise<StudentSearchCandidate[]> {
    const response = await requestJson<{ data: StudentSearchCandidate[] }>(
        searchStudents.url(classId, { query: { q: query, limit } }),
    );
    return response.data ?? [];
}

export function enrollStudentsInClass(
    classId: number,
    payload: {
        student_ids: Array<number | string>;
        force_overload?: boolean;
        override_reason?: string | null;
        allow_transfer_from_sibling?: boolean;
        allow_clash_override?: boolean;
        allow_hold_override?: boolean;
        sync_subject_enrollment?: boolean;
        remarks?: string | null;
        enrollment_status?: EnrollmentStatusValue;
    },
): Promise<{
    result: {
        enrolled: Array<Record<string, unknown>>;
        transferred: Array<Record<string, unknown>>;
        skipped: Array<Record<string, unknown>>;
        errors: Array<Record<string, unknown>>;
    };
    message: string;
}> {
    return requestJson(enroll.url(classId), {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function patchClassEnrollment(
    classId: number,
    enrollmentId: number,
    payload: {
        enrollment_status?: EnrollmentStatusValue;
        status_reason?: string | null;
        remarks?: string | null;
        prelim_grade?: number | null;
        midterm_grade?: number | null;
        finals_grade?: number | null;
        force_grade_override?: boolean;
        override_reason?: string | null;
    },
): Promise<{ enrollment: WorkspaceEnrollment; message: string }> {
    return requestJson(updateEnrollment.url({ class: classId, enrollment: enrollmentId }), {
        method: "PATCH",
        body: JSON.stringify(payload),
    });
}

export function deleteClassEnrollment(
    classId: number,
    enrollmentId: number,
    payload: { reason?: string | null; force_grade_override?: boolean } = {},
): Promise<{ enrollment: WorkspaceEnrollment; message: string }> {
    return requestJson(dropEnrollment.url({ class: classId, enrollment: enrollmentId }), {
        method: "DELETE",
        body: JSON.stringify(payload),
    });
}

export function postRestoreClassEnrollment(
    classId: number,
    enrollmentId: number,
    payload: { force_overload?: boolean; reason?: string | null } = {},
): Promise<{ enrollment: WorkspaceEnrollment; message: string }> {
    return requestJson(restoreEnrollment.url({ class: classId, enrollmentId }), {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function postBulkMoveStudents(
    classId: number,
    payload: {
        target_class_id: number;
        student_ids: number[];
        force_overload?: boolean;
        reason?: string | null;
    },
): Promise<{
    result: {
        moved_count: number;
        skipped_count: number;
        error_count: number;
        errors: Array<Record<string, unknown>>;
    };
    message: string;
}> {
    return requestJson(bulkMove.url(classId), {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function postScheduleImpactPreview(classId: number, schedules: EditableScheduleBlock[]): Promise<ScheduleImpactResponse> {
    return requestJson<ScheduleImpactResponse>(previewScheduleImpact.url(classId), {
        method: "POST",
        body: JSON.stringify({ schedules }),
    });
}

export function patchClassScheduleWorkspace(
    classId: number,
    payload: {
        schedules: EditableScheduleBlock[];
        schedule_revision: string;
        schedule_status: "draft" | "published";
        notify_enrolled: boolean;
    },
): Promise<{
    schedule_summary: ClassWorkspacePayload["schedule_summary"];
    resolutions: ClassWorkspacePayload["resolutions"];
    busy_overlay: ClassWorkspacePayload["busy_overlay"];
    schedule_revision: string;
    message: string;
}> {
    return requestJson(updateScheduleWorkspace.url(classId), {
        method: "PATCH",
        body: JSON.stringify(payload),
    });
}

export function fetchParallelSectionsWorkspace(
    classId: number,
    inspectStudentId?: number | null,
): Promise<ParallelSectionsWorkspacePayload> {
    return requestJson<ParallelSectionsWorkspacePayload>(
        parallelSections.url(classId, {
            query: inspectStudentId ? { inspect_student_id: inspectStudentId } : undefined,
        }),
    );
}

export function postPromoteWaitlist(
    classId: number,
    payload: { waitlist_id?: number; waitlist_ids?: number[]; force_overload?: boolean },
): Promise<{ message: string; [key: string]: unknown }> {
    return requestJson(promoteWaitlist.url(classId), {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function postBulkClassAction(payload: {
    class_ids: number[];
    action: "assign_faculty" | "set_capacity" | "publish_drafts" | "apply_pattern" | "enroll_cohort";
    faculty_id?: string | number | null;
    maximum_slots?: number;
    notify_enrolled?: boolean;
    pattern?: string;
    days?: string[];
    start_time?: string;
    end_time?: string;
    component_type?: "lecture" | "lab";
    delivery_mode?: string;
    student_identifiers?: Array<number | string>;
    force_overload?: boolean;
    override_reason?: string | null;
}): Promise<{ message: string; result?: Record<string, unknown>; [key: string]: unknown }> {
    return requestJson(bulkClassAction.url(), {
        method: "POST",
        body: JSON.stringify(payload),
    });
}
