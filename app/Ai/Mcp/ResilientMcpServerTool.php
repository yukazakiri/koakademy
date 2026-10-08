<?php

declare(strict_types=1);

namespace App\Ai\Mcp;

use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\McpServerTool;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * The framework's MCP server tool wrapper, with permission failures reported
 * to the model instead of aborting the turn.
 *
 * `McpServerTool` is used as the parent rather than reimplemented, so the
 * request binding, the structured-content handling, and support for dependency
 * injection in a tool's `handle()` all come from the SDK.
 *
 * The override exists because the AI SDK re-throws any exception a tool throws
 * (`InvokesTools::executeTool`), so an `AuthorizationException` from a tool's
 * own permission check would fail the whole chat turn. The KoAkademy MCP tools
 * deliberately deny by throwing, since that is correct for the MCP protocol
 * surface, so the denial has to be translated back into tool output the model
 * can read and explain: the agent instructions promise it will say the account
 * lacks the required permission rather than fill the gap with a guess.
 */
final class ResilientMcpServerTool extends McpServerTool
{
    public function handle(Request $request): string
    {
        try {
            return parent::handle($request);
        } catch (Throwable $e) {
            return (string) json_encode([
                'error' => true,
                'denied' => $e instanceof AuthorizationException,
                'tool' => $this->tool->name(),
                'message' => $e->getMessage(),
                'guidance' => $e instanceof AuthorizationException
                    ? 'This account is not permitted to perform that action. Report the missing permission; do not substitute a guess or another tool.'
                    : 'The tool could not be completed. Report the failure; do not retry repeatedly or substitute a guess.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        // Check if auto-approve is active via session or request parameter
        if (session('ai_auto_approve_actions', false) || request()->boolean('auto_approve', false)) {
            return false;
        }

        $toolClass = get_class($this->tool);
        $toolName = class_basename($toolClass);

        return match ($toolName) {
            'EnrollStudentSubjectTool' => Approval::required(
                'Enroll student into subject(s) or create official student enrollment record? This will modify academic registration.'
            ),
            'DropStudentSubjectEnrollmentTool' => Approval::required(
                'Drop student from enrolled subject? This will adjust academic units and fee assessment.'
            ),
            'AdvanceEnrollmentStepTool' => Approval::required(
                'Advance student enrollment step in the institutional registration pipeline?'
            ),
            'VerifyEnrollmentRequirementTool' => Approval::required(
                'Confirm verification of official enrollment document/requirement?'
            ),
            'UpdateSubjectEnrollmentGradeTool' => Approval::required(
                'Update subject grade on student record? This modifies official transcripts.'
            ),
            'UpdateEnrollmentRemarksTool' => Approval::required(
                'Update official administrative remarks on enrollment record?'
            ),
            'ManageStudentTool' => Approval::required(
                'Perform student record modification or creation?'
            ),
            'TransferStudentSectionTool' => Approval::required(
                'Transfer student to another class section?'
            ),
            'ManageClassScheduleTool' => Approval::required(
                'Modify class schedule or timetable assignment?'
            ),
            'ManageCurriculumSubjectTool' => Approval::required(
                'Modify curriculum subject catalog?'
            ),
            'ApplyApprovedCurriculumImportTool' => Approval::required(
                'Apply approved curriculum import to institution records?'
            ),
            default => false,
        };
    }
}
