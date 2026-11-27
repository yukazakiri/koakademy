import {
    batchBlueprint,
    preflight,
    quickSettings,
    sendScheduleNotices,
    verifyGrades,
} from "@/actions/App/Http/Controllers/AdministratorClassWorkspaceController";
import { requestJson } from "../workspace/api";
import type {
    BatchBlueprintRequest,
    BatchBlueprintResponse,
    ClassPolicySettings,
    PreflightRequest,
    PreflightResponse,
    QuickSettingsResponse,
    SendScheduleNoticesResponse,
    VerifyGradesRequest,
    VerifyGradesResponse,
} from "./types";

export function postClassPreflight(payload: PreflightRequest, signal?: AbortSignal): Promise<PreflightResponse> {
    return requestJson<PreflightResponse>(preflight.url(), {
        method: "POST",
        body: JSON.stringify(payload),
        signal,
    });
}

export function postBatchBlueprint(payload: BatchBlueprintRequest): Promise<BatchBlueprintResponse> {
    return requestJson<BatchBlueprintResponse>(batchBlueprint.url(), {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function postVerifyGrades(classId: number, payload: VerifyGradesRequest): Promise<VerifyGradesResponse> {
    return requestJson<VerifyGradesResponse>(verifyGrades.url(classId), {
        method: "POST",
        body: JSON.stringify(payload),
    });
}

export function postSendScheduleNotices(classId: number): Promise<SendScheduleNoticesResponse> {
    return requestJson<SendScheduleNoticesResponse>(sendScheduleNotices.url(classId), {
        method: "POST",
        body: JSON.stringify({}),
    });
}

export function patchQuickSettings(classId: number, settings: Partial<ClassPolicySettings>): Promise<QuickSettingsResponse> {
    return requestJson<QuickSettingsResponse>(quickSettings.url(classId), {
        method: "PATCH",
        body: JSON.stringify(settings),
    });
}
