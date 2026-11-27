<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentType;
use App\Models\DocumentLocation;
use App\Models\EnrollmentRequirement;
use App\Models\Resource;
use App\Models\Student;
use App\Models\StudentDocumentRecord;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Notifications\StudentDocumentStatusNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;
use ZipArchive;

final class StudentDocumentService
{
    /**
     * Canonical document slot definitions used as defaults when a school has not customized labels.
     *
     * @var array<string, array{label: string, neutral_label: string, short_label: string, description: string, neutral_description: string, accepted: string, image_only?: bool}>
     */
    public const array SLOT_CATALOG = [
        'picture_1x1' => [
            'label' => '1x1 ID Photo',
            'neutral_label' => 'ID Photo',
            'short_label' => 'ID Photo',
            'description' => 'Recent clear 1x1 or 2x2 plain-background ID photo',
            'neutral_description' => 'Recent clear student identification photo',
            'accepted' => 'JPG, PNG, or WebP up to 5 MB',
            'image_only' => true,
        ],
        'birth_certificate' => [
            'label' => 'Birth Certificate (PSA)',
            'neutral_label' => 'Birth Certificate / Gov ID',
            'short_label' => 'Birth Cert',
            'description' => 'PSA / NSO Birth Certificate (clear scan or photo)',
            'neutral_description' => 'Official birth certificate or government-issued ID',
            'accepted' => 'PDF, JPG, PNG, or WebP up to 5 MB',
        ],
        'form_138' => [
            'label' => 'Form 138 (Report Card)',
            'neutral_label' => 'Latest Report Card',
            'short_label' => 'Form 138',
            'description' => 'Latest official high school or prior school report card (SF9)',
            'neutral_description' => 'Latest official school report card or grade sheet',
            'accepted' => 'PDF, JPG, PNG, or WebP up to 5 MB',
        ],
        'form_137' => [
            'label' => 'Form 137 (SF10)',
            'neutral_label' => 'Permanent Academic Record',
            'short_label' => 'Form 137',
            'description' => 'Permanent student academic record (SF10) from prior school',
            'neutral_description' => 'Permanent cumulative academic record',
            'accepted' => 'PDF, JPG, PNG, or WebP up to 5 MB',
        ],
        'good_moral_cert' => [
            'label' => 'Good Moral Certificate',
            'neutral_label' => 'Conduct / Reference Letter',
            'short_label' => 'Good Moral',
            'description' => 'Certificate of Good Moral Character from previous school',
            'neutral_description' => 'Character or conduct reference from previous institution',
            'accepted' => 'PDF, JPG, PNG, or WebP up to 5 MB',
        ],
        'transfer_credentials' => [
            'label' => 'Transfer Credentials',
            'neutral_label' => 'Transfer / Release Letter',
            'short_label' => 'Transfer Cred',
            'description' => 'Honorable Dismissal / Certificate of Transfer Credential (transferees)',
            'neutral_description' => 'Official transfer clearance from previous institution',
            'accepted' => 'PDF, JPG, PNG, or WebP up to 5 MB',
        ],
        'transcript_records' => [
            'label' => 'Transcript of Records (TOR)',
            'neutral_label' => 'Academic Transcript',
            'short_label' => 'Transcript',
            'description' => 'Official Transcript of Records for transferees or graduate entrants',
            'neutral_description' => 'Official academic transcript from prior institution',
            'accepted' => 'PDF, JPG, PNG, or WebP up to 5 MB',
        ],
    ];

    /**
     * Map student document keys to equivalent EnrollmentRequirement keys for two-way sync.
     *
     * @var array<string, list<string>>
     */
    private const array REQUIREMENT_KEY_ALIASES = [
        'picture_1x1' => ['picture_1x1', 'id_photo', 'photo', '1x1_picture'],
        'birth_certificate' => ['birth_certificate', 'psa_birth_certificate', 'birth_cert'],
        'form_138' => ['form_138', 'report_card', 'sf9'],
        'form_137' => ['form_137', 'sf10', 'permanent_record'],
        'good_moral_cert' => ['good_moral_cert', 'good_moral', 'good_moral_certificate'],
        'transfer_credentials' => ['transfer_credentials', 'honorable_dismissal', 'transfer_credential'],
        'transcript_records' => ['transcript_records', 'transcript', 'tor', 'transcript_of_records'],
    ];

    public function __construct(
        private readonly StudentConfigurationService $configuration,
    ) {}

    public function privateDisk(): string
    {
        return (string) config('filesystems.private_disk', 'private');
    }

    /**
     * Resolve the school-configured document checklist for a student type (or school default).
     *
     * @return list<array{key: string, label: string, short_label: string, description: string, accepted: string, required: bool, enabled: bool, image_only: bool}>
     */
    public function checklistFor(?string $studentType = null, ?int $schoolId = null, bool $forStudentPortal = false): array
    {
        $config = $this->configuration->get($schoolId);
        $resolvedType = $studentType ?: (string) ($config['default_type'] ?? StudentType::College->value);
        $isPhilippine = (bool) ($config['philippine_reporting'] ?? false);
        $configuredDocSlots = is_array($config['documents'] ?? null) ? $config['documents'] : [];

        $configuredChecklist = null;
        if (is_array($config['document_checklist'] ?? null)) {
            if (array_key_exists($resolvedType, $config['document_checklist']) && is_array($config['document_checklist'][$resolvedType])) {
                $configuredChecklist = $config['document_checklist'][$resolvedType];
            } elseif (array_key_exists('default', $config['document_checklist']) && is_array($config['document_checklist']['default'])) {
                $configuredChecklist = $config['document_checklist']['default'];
            }
        }

        if ($configuredChecklist !== null) {
            $slots = [];
            foreach ($configuredChecklist as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $key = (string) ($item['key'] ?? '');
                if ($key === '' || ! array_key_exists($key, self::SLOT_CATALOG)) {
                    continue;
                }
                if (array_key_exists('enabled', $item) && ! (bool) $item['enabled']) {
                    continue;
                }
                $docSlotOverride = is_array($configuredDocSlots[$key] ?? null) ? $configuredDocSlots[$key] : null;
                if ($forStudentPortal && is_array($docSlotOverride) && array_key_exists('visible', $docSlotOverride) && ! (bool) $docSlotOverride['visible']) {
                    continue;
                }
                $catalog = self::SLOT_CATALOG[$key];
                $slots[] = [
                    'key' => $key,
                    'label' => filled($item['label'] ?? null)
                        ? (string) $item['label']
                        : ($isPhilippine ? $catalog['label'] : $catalog['neutral_label']),
                    'short_label' => filled($item['short_label'] ?? null)
                        ? (string) $item['short_label']
                        : $catalog['short_label'],
                    'description' => filled($item['description'] ?? null)
                        ? (string) $item['description']
                        : ($isPhilippine ? $catalog['description'] : $catalog['neutral_description']),
                    'accepted' => $catalog['accepted'],
                    'required' => (bool) ($item['required'] ?? false),
                    'enabled' => true,
                    'image_only' => (bool) ($catalog['image_only'] ?? false),
                ];
            }

            return $slots;
        }

        if ($forStudentPortal) {
            $portalChecklist = $this->configuration->documentChecklist($schoolId);
            $slots = [];

            foreach ($portalChecklist as $key => $meta) {
                if (! array_key_exists($key, self::SLOT_CATALOG)) {
                    continue;
                }
                $catalog = self::SLOT_CATALOG[$key];
                $slots[] = [
                    'key' => $key,
                    'label' => (string) $meta['label'],
                    'short_label' => $catalog['short_label'],
                    'description' => (string) ($meta['description'] ?? ''),
                    'accepted' => (string) ($meta['accepted'] ?? $catalog['accepted']),
                    'required' => (bool) ($meta['required'] ?? false),
                    'enabled' => true,
                    'image_only' => (bool) ($catalog['image_only'] ?? false),
                ];
            }

            return $slots;
        }

        return $this->defaultChecklistForType($resolvedType, $isPhilippine, $configuredDocSlots);
    }

    /**
     * Union of all active checklist columns across the school's enabled student types (used by the Compliance Matrix).
     *
     * @return list<array{key: string, label: string, short_label: string, description: string, accepted: string, required: bool, enabled: bool, image_only: bool}>
     */
    public function matrixColumnsForSchool(?int $schoolId = null, ?string $studentType = null): array
    {
        if (filled($studentType)) {
            return $this->checklistFor($studentType, $schoolId);
        }

        $config = $this->configuration->get($schoolId);
        $enabledTypes = $config['enabled_types'] ?? [StudentType::College->value];
        $merged = [];

        foreach ($enabledTypes as $type) {
            foreach ($this->checklistFor((string) $type, $schoolId) as $slot) {
                if (! isset($merged[$slot['key']])) {
                    $merged[$slot['key']] = $slot;
                } elseif ($slot['required']) {
                    $merged[$slot['key']]['required'] = true;
                }
            }
        }

        return array_values($merged);
    }

    /**
     * Build the full document dossier (fixed slots, dynamic vault, official PDFs, and compliance summary) for a student.
     *
     * @return array<string, mixed>
     */
    public function dossierFor(Student $student, bool $forStudentPortal = false): array
    {
        $student->loadMissing(['DocumentLocation', 'resources']);
        $recordsByKey = $this->recordsByKeyForStudent($student);
        $checklist = $this->checklistFor($student->student_type?->value, $student->school_id, $forStudentPortal);
        $checklistKeys = array_column($checklist, 'key');

        // Also include any legacy DocumentLocation slots that already have a file even if not in active checklist
        $docLocation = $student->DocumentLocation;
        $slots = $checklist;
        if (! $forStudentPortal) {
            foreach (DocumentLocation::DOCUMENT_FIELDS as $field) {
                if (in_array($field, $checklistKeys, true)) {
                    continue;
                }
                $legacyPath = $docLocation?->getAttribute($field);
                $hasLegacy = is_string($legacyPath) && mb_trim($legacyPath) !== '';
                $hasRecord = isset($recordsByKey[$field]) && $recordsByKey[$field]->status !== StudentDocumentRecord::StatusMissing;
                if ($hasLegacy || $hasRecord) {
                    $catalog = self::SLOT_CATALOG[$field];
                    $slots[] = [
                        'key' => $field,
                        'label' => $catalog['label'],
                        'short_label' => $catalog['short_label'],
                        'description' => $catalog['description'],
                        'accepted' => $catalog['accepted'],
                        'required' => false,
                        'enabled' => true,
                        'image_only' => (bool) ($catalog['image_only'] ?? false),
                    ];
                }
            }
        }

        $fixedSlots = [];
        $totalRequired = 0;
        $compliantCount = 0;
        $verifiedCount = 0;
        $physicalCount = 0;
        $waivedCount = 0;
        $pendingReviewCount = 0;
        $rejectedCount = 0;
        $missingCount = 0;

        foreach ($slots as $slot) {
            $key = $slot['key'];
            /** @var StudentDocumentRecord|null $record */
            $record = $recordsByKey[$key] ?? null;
            $legacyPath = $docLocation?->getAttribute($key);
            $storedPath = filled($record?->file_path) ? (string) $record->file_path : (is_string($legacyPath) && mb_trim($legacyPath) !== '' ? $legacyPath : null);
            $hasFile = $storedPath !== null;

            $status = $this->resolveEffectiveStatus($record, $hasFile);
            $isCompliant = in_array($status, StudentDocumentRecord::COMPLIANT_STATUSES, true);

            if ($slot['required']) {
                $totalRequired++;
                if ($isCompliant) {
                    $compliantCount++;
                } elseif ($status === StudentDocumentRecord::StatusPendingReview) {
                    $pendingReviewCount++;
                } elseif ($status === StudentDocumentRecord::StatusRejected) {
                    $rejectedCount++;
                } else {
                    $missingCount++;
                }
            } elseif ($status === StudentDocumentRecord::StatusPendingReview) {
                $pendingReviewCount++;
            } elseif ($status === StudentDocumentRecord::StatusRejected) {
                $rejectedCount++;
            }

            if ($status === StudentDocumentRecord::StatusVerified) {
                $verifiedCount++;
            } elseif ($status === StudentDocumentRecord::StatusPhysicalOnFile) {
                $physicalCount++;
            } elseif ($status === StudentDocumentRecord::StatusWaived) {
                $waivedCount++;
            }

            $downloadUrl = null;
            $previewUrl = null;
            if ($hasFile) {
                if ($forStudentPortal) {
                    $downloadUrl = route('student.documents.download', ['documentType' => $key]);
                    $previewUrl = route('student.documents.download', ['documentType' => $key, 'preview' => 1]);
                } else {
                    $downloadUrl = route('administrators.students.documents.fixed.show', [
                        'student' => $student->id,
                        'documentType' => $key,
                    ]);
                    $previewUrl = route('administrators.students.documents.fixed.show', [
                        'student' => $student->id,
                        'documentType' => $key,
                        'preview' => 1,
                    ]);
                }
            }

            $fixedSlots[] = [
                'key' => $key,
                'label' => $slot['label'],
                'short_label' => $slot['short_label'],
                'description' => $slot['description'],
                'accepted' => $slot['accepted'],
                'required' => $slot['required'],
                'image_only' => $slot['image_only'],
                'status' => $status,
                'is_compliant' => $isCompliant,
                'has_file' => $hasFile,
                'file_name' => $record?->file_name ?: ($hasFile ? basename($storedPath) : null),
                'mime_type' => $record?->mime_type ?: ($hasFile ? $this->guessMimeFromExtension($storedPath) : null),
                'file_size' => $record?->file_size,
                'uploaded_at' => $record?->uploaded_at?->toIso8601String(),
                'uploaded_at_label' => $record?->uploaded_at?->format('M j, Y g:i A'),
                'verified_at' => $record?->verified_at?->toIso8601String(),
                'verified_at_label' => $record?->verified_at?->format('M j, Y g:i A'),
                'verifier_name' => $record?->verifier?->name,
                'rejection_reason' => $record?->rejection_reason,
                'waiver_reason' => $record?->waiver_reason,
                'notes' => $record?->notes,
                'expires_at' => $record?->expires_at?->toDateString(),
                'history' => $record?->history ?? [],
                'download_url' => $downloadUrl,
                'preview_url' => $previewUrl,
            ];
        }

        $dynamicDocuments = $student->resources
            ->sortByDesc('created_at')
            ->values()
            ->map(function (Resource $resource) use ($student): array {
                return [
                    'id' => $resource->id,
                    'type' => $resource->type,
                    'file_name' => $resource->file_name,
                    'file_path' => $resource->file_path,
                    'mime_type' => $resource->mime_type,
                    'file_size' => $resource->file_size,
                    'created_at' => $resource->created_at?->toIso8601String(),
                    'created_at_label' => $resource->created_at?->format('M j, Y'),
                    'url' => route('administrators.students.documents.dynamic.show', [
                        'student' => $student->id,
                        'resource' => $resource->id,
                    ]),
                    'preview_url' => route('administrators.students.documents.dynamic.show', [
                        'student' => $student->id,
                        'resource' => $resource->id,
                        'preview' => 1,
                    ]),
                ];
            })
            ->all();

        $compliancePercent = $totalRequired > 0
            ? (int) round(($compliantCount / $totalRequired) * 100)
            : 100;

        $officialDocuments = $forStudentPortal ? [] : $this->officialDocumentsFor($student);

        return [
            'slots' => $fixedSlots,
            'dynamic_documents' => $dynamicDocuments,
            'official_documents' => $officialDocuments,
            'summary' => [
                'total_required' => $totalRequired,
                'compliant_count' => $compliantCount,
                'verified_count' => $verifiedCount,
                'physical_count' => $physicalCount,
                'waived_count' => $waivedCount,
                'pending_review_count' => $pendingReviewCount,
                'rejected_count' => $rejectedCount,
                'missing_count' => $missingCount,
                'dynamic_count' => count($dynamicDocuments),
                'compliance_percent' => $compliancePercent,
                'is_complete' => $totalRequired === 0 || $compliantCount >= $totalRequired,
            ],
        ];
    }

    /**
     * Lightweight summary for Student Directory / Compliance Matrix rows using already-eager-loaded relations.
     *
     * @param  Collection<int, StudentDocumentRecord>|null  $preloadedRecords
     * @return array<string, mixed>
     */
    public function rowSummaryFor(Student $student, ?Collection $preloadedRecords = null): array
    {
        $checklist = $this->checklistFor($student->student_type?->value, $student->school_id);
        $recordsByKey = ($preloadedRecords ?? $this->recordsByKeyForStudent($student))->keyBy('document_key');
        $docLocation = $student->DocumentLocation;

        $requiredTotal = 0;
        $compliant = 0;
        $pendingReview = 0;
        $rejected = 0;
        $missing = 0;
        $uploaded = 0;
        $slotStatuses = [];

        foreach ($checklist as $slot) {
            $key = $slot['key'];
            /** @var StudentDocumentRecord|null $record */
            $record = $recordsByKey->get($key);
            $legacyPath = $docLocation?->getAttribute($key);
            $hasFile = filled($record?->file_path) || (is_string($legacyPath) && mb_trim($legacyPath) !== '');
            $status = $this->resolveEffectiveStatus($record, $hasFile);
            $isCompliant = in_array($status, StudentDocumentRecord::COMPLIANT_STATUSES, true);

            if ($hasFile) {
                $uploaded++;
            }

            if ($slot['required']) {
                $requiredTotal++;
                if ($isCompliant) {
                    $compliant++;
                } elseif ($status === StudentDocumentRecord::StatusPendingReview) {
                    $pendingReview++;
                } elseif ($status === StudentDocumentRecord::StatusRejected) {
                    $rejected++;
                } else {
                    $missing++;
                }
            } elseif ($status === StudentDocumentRecord::StatusPendingReview) {
                $pendingReview++;
            } elseif ($status === StudentDocumentRecord::StatusRejected) {
                $rejected++;
            }

            $slotStatuses[$key] = [
                'key' => $key,
                'label' => $slot['label'],
                'short_label' => $slot['short_label'],
                'required' => $slot['required'],
                'status' => $status,
                'is_compliant' => $isCompliant,
                'has_file' => $hasFile,
                'file_name' => $record?->file_name ?: ($hasFile && is_string($legacyPath) ? basename($legacyPath) : null),
                'rejection_reason' => $record?->rejection_reason,
                'waiver_reason' => $record?->waiver_reason,
                'preview_url' => $hasFile
                    ? route('administrators.students.documents.fixed.show', ['student' => $student->id, 'documentType' => $key, 'preview' => 1])
                    : null,
                'download_url' => $hasFile
                    ? route('administrators.students.documents.fixed.show', ['student' => $student->id, 'documentType' => $key])
                    : null,
            ];
        }

        return [
            'required_total' => $requiredTotal,
            'compliant_count' => $compliant,
            'uploaded_count' => $uploaded,
            'pending_review_count' => $pendingReview,
            'rejected_count' => $rejected,
            'missing_count' => $missing,
            'compliance_percent' => $requiredTotal > 0 ? (int) round(($compliant / $requiredTotal) * 100) : 100,
            'is_complete' => $requiredTotal === 0 || $compliant >= $requiredTotal,
            'slots' => $slotStatuses,
        ];
    }

    public function uploadFixed(
        Student $student,
        string $documentKey,
        UploadedFile $file,
        ?User $actor = null,
        bool $autoVerify = false,
    ): StudentDocumentRecord {
        if (! in_array($documentKey, DocumentLocation::DOCUMENT_FIELDS, true)) {
            throw ValidationException::withMessages(['document_type' => 'Invalid document type.']);
        }

        $disk = $this->privateDisk();
        $path = $file->store("students/{$student->id}/documents", $disk);

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['file' => 'Unable to store uploaded document.']);
        }

        /** @var list<array{0: string, 1: string|null}> $filesToDelete */
        $filesToDelete = [];

        try {
            $record = DB::transaction(function () use ($student, $documentKey, $file, $path, $disk, $actor, $autoVerify, &$filesToDelete): StudentDocumentRecord {
                Student::withoutSchoolScope()->withTrashed()->whereKey($student->id)->lockForUpdate()->first();
                $student->refresh()->load('DocumentLocation');
                $oldPath = $student->DocumentLocation?->getAttribute($documentKey);

                if (! $student->DocumentLocation) {
                    $docLocation = DocumentLocation::create([$documentKey => $path]);
                    $student->forceFill(['document_location_id' => $docLocation->id])->save();
                } else {
                    if (is_string($oldPath) && mb_trim($oldPath) !== '' && $oldPath !== $path) {
                        $filesToDelete[] = [$oldPath, null];
                    }
                    $student->DocumentLocation->update([$documentKey => $path]);
                }

                $record = $this->firstOrNewRecord($student, $documentKey, lockForUpdate: true);
                if (filled($record->file_path) && $record->file_path !== $path && $record->file_path !== $oldPath) {
                    $filesToDelete[] = [(string) $record->file_path, $record->disk];
                }

                $history = is_array($record->history) ? $record->history : [];

                if (filled($record->file_path)) {
                    $history[] = [
                        'event' => 'replaced',
                        'previous_file_name' => $record->file_name,
                        'previous_status' => $record->status,
                        'actor' => $actor?->name ?? 'System',
                        'at' => now()->toIso8601String(),
                    ];
                } else {
                    $history[] = [
                        'event' => 'uploaded',
                        'file_name' => $file->getClientOriginalName(),
                        'actor' => $actor?->name ?? 'Student',
                        'at' => now()->toIso8601String(),
                    ];
                }

                $wasPhysicalOnFile = $record->exists
                    && $record->status === StudentDocumentRecord::StatusPhysicalOnFile;

                $nextStatus = $autoVerify
                    ? StudentDocumentRecord::StatusVerified
                    : ($wasPhysicalOnFile
                        ? StudentDocumentRecord::StatusPhysicalOnFile
                        : StudentDocumentRecord::StatusPendingReview);

                $record->fill([
                    'school_id' => $student->school_id,
                    'status' => $nextStatus,
                    'file_path' => $path,
                    'disk' => $disk,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => $actor?->id,
                    'uploaded_at' => now(),
                    'verified_by' => $autoVerify
                        ? $actor?->id
                        : ($wasPhysicalOnFile ? $record->verified_by : null),
                    'verified_at' => $autoVerify
                        ? now()
                        : ($wasPhysicalOnFile ? $record->verified_at : null),
                    'rejection_reason' => null,
                    'waiver_reason' => null,
                    'history' => array_slice($history, -20),
                ]);
                $record->save();

                $this->syncToEnrollmentRequirements($student, $documentKey, $record, $actor);

                return $record;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFile($path, $disk);

            throw $exception;
        }

        foreach ($filesToDelete as [$stalePath, $staleDisk]) {
            $this->deleteStoredFile($stalePath, $staleDisk);
        }

        return $record;
    }

    public function removeFixed(Student $student, string $documentKey, ?User $actor = null): void
    {
        if (! in_array($documentKey, DocumentLocation::DOCUMENT_FIELDS, true)) {
            abort(404, 'Invalid document type.');
        }

        /** @var list<array{0: string, 1: string|null}> $filesToDelete */
        $filesToDelete = [];

        DB::transaction(function () use ($student, $documentKey, $actor, &$filesToDelete): void {
            Student::withoutSchoolScope()->withTrashed()->whereKey($student->id)->lockForUpdate()->first();
            $student->refresh()->load('DocumentLocation');
            $oldPath = $student->DocumentLocation?->getAttribute($documentKey);

            if ($student->DocumentLocation) {
                if (is_string($oldPath) && mb_trim($oldPath) !== '') {
                    $filesToDelete[] = [$oldPath, null];
                }
                $student->DocumentLocation->update([$documentKey => null]);
            }

            if ($documentKey === 'picture_1x1' && $student->profile_url) {
                if ($student->profile_url !== $oldPath) {
                    $filesToDelete[] = [(string) $student->profile_url, null];
                }
                $student->update(['profile_url' => null]);
            }

            if (Schema::hasTable('student_document_records')) {
                $record = StudentDocumentRecord::query()
                    ->where('student_id', $student->id)
                    ->where('document_key', $documentKey)
                    ->lockForUpdate()
                    ->first();

                if ($record) {
                    if (filled($record->file_path) && $record->file_path !== $oldPath) {
                        $filesToDelete[] = [(string) $record->file_path, $record->disk];
                    }
                    $history = is_array($record->history) ? $record->history : [];
                    $history[] = [
                        'event' => 'removed',
                        'previous_file_name' => $record->file_name,
                        'actor' => $actor?->name ?? 'System',
                        'at' => now()->toIso8601String(),
                    ];

                    $record->fill([
                        'status' => StudentDocumentRecord::StatusMissing,
                        'file_path' => null,
                        'disk' => null,
                        'file_name' => null,
                        'mime_type' => null,
                        'file_size' => null,
                        'verified_by' => null,
                        'verified_at' => null,
                        'rejection_reason' => null,
                        'waiver_reason' => null,
                        'history' => array_slice($history, -20),
                    ])->save();

                    $this->syncToEnrollmentRequirements($student, $documentKey, $record, $actor);
                } elseif (is_string($oldPath) && mb_trim($oldPath) !== '') {
                    $missingPlaceholder = new StudentDocumentRecord([
                        'school_id' => $student->school_id,
                        'student_id' => $student->id,
                        'document_key' => $documentKey,
                        'status' => StudentDocumentRecord::StatusMissing,
                    ]);
                    $this->syncToEnrollmentRequirements($student, $documentKey, $missingPlaceholder, $actor);
                }
            }
        });

        foreach ($filesToDelete as [$stalePath, $staleDisk]) {
            $this->deleteStoredFile($stalePath, $staleDisk);
        }
    }

    /**
     * Review a fixed document slot: verify, reject, physical, waive, or reset.
     */
    public function review(
        Student $student,
        string $documentKey,
        string $action,
        ?User $actor = null,
        ?string $reason = null,
        ?string $notes = null,
        ?string $expiresAt = null,
        bool $notifyStudent = true,
        bool &$notified = false,
    ): StudentDocumentRecord {
        if (! in_array($documentKey, DocumentLocation::DOCUMENT_FIELDS, true)) {
            throw ValidationException::withMessages(['document_type' => 'Invalid document type.']);
        }

        $notified = false;

        $record = DB::transaction(function () use ($student, $documentKey, $action, $actor, $reason, $notes, $expiresAt): StudentDocumentRecord {
            Student::withoutSchoolScope()->withTrashed()->whereKey($student->id)->lockForUpdate()->first();
            $student->refresh()->load('DocumentLocation');
            $record = $this->firstOrNewRecord($student, $documentKey, lockForUpdate: true);

            $legacyPath = $student->DocumentLocation?->getAttribute($documentKey);
            if (! filled($record->file_path) && is_string($legacyPath) && mb_trim($legacyPath) !== '') {
                $record->file_path = $legacyPath;
                $record->file_name = basename($legacyPath);
            }

            $history = is_array($record->history) ? $record->history : [];

            $status = match ($action) {
                'verify' => StudentDocumentRecord::StatusVerified,
                'reject' => StudentDocumentRecord::StatusRejected,
                'physical' => StudentDocumentRecord::StatusPhysicalOnFile,
                'waive' => StudentDocumentRecord::StatusWaived,
                'reset' => filled($record->file_path) ? StudentDocumentRecord::StatusPendingReview : StudentDocumentRecord::StatusMissing,
                default => throw ValidationException::withMessages(['action' => 'Unsupported review action.']),
            };

            $history[] = [
                'event' => $action,
                'status' => $status,
                'reason' => $reason,
                'notes' => $notes,
                'actor' => $actor?->name ?? 'System',
                'at' => now()->toIso8601String(),
            ];

            $record->fill([
                'school_id' => $student->school_id,
                'status' => $status,
                'verified_by' => in_array($status, StudentDocumentRecord::COMPLIANT_STATUSES, true) ? $actor?->id : null,
                'verified_at' => in_array($status, StudentDocumentRecord::COMPLIANT_STATUSES, true) ? now() : null,
                'rejection_reason' => $status === StudentDocumentRecord::StatusRejected ? ($reason ?: 'Please upload a clearer or valid copy.') : null,
                'waiver_reason' => $status === StudentDocumentRecord::StatusWaived ? ($reason ?: 'Waived by registrar.') : null,
                'notes' => $notes !== null ? $notes : $record->notes,
                'expires_at' => $expiresAt !== null ? ($expiresAt === '' ? null : $expiresAt) : $record->expires_at,
                'history' => array_slice($history, -20),
            ]);
            $record->save();

            $this->syncToEnrollmentRequirements($student, $documentKey, $record, $actor);

            return $record;
        });

        if ($action === 'reject' && $notifyStudent) {
            $label = $this->labelFor($documentKey, $student->student_type?->value, $student->school_id);
            $notified = $this->notifyStudent($student, new StudentDocumentStatusNotification(
                student: $student,
                eventType: 'rejected',
                documentLabels: [$label],
                reason: $record->rejection_reason,
            ));
        }

        return $record;
    }

    /**
     * Two-way sync: when an EnrollmentRequirement is reviewed inside Enrollments, mirror it back to StudentDocumentRecord.
     */
    public function syncFromEnrollmentRequirement(EnrollmentRequirement $requirement, ?User $actor = null): void
    {
        if (! Schema::hasTable('student_document_records')) {
            return;
        }

        $documentKey = $this->documentKeyFromRequirementKey($requirement->requirement_key);
        if ($documentKey === null) {
            return;
        }

        $enrollment = $requirement->enrollment;
        if (! $enrollment) {
            return;
        }

        $student = Student::query()
            ->whereKey($enrollment->student_id)
            ->first();

        if (! $student instanceof Student) {
            return;
        }

        DB::transaction(function () use ($student, $documentKey, $requirement, $enrollment, $actor): void {
            Student::withoutSchoolScope()->withTrashed()->whereKey($student->id)->lockForUpdate()->first();
            $student->refresh()->load('DocumentLocation');
            $record = $this->firstOrNewRecord($student, $documentKey, lockForUpdate: true);

            $nextStatus = match ($requirement->status) {
                EnrollmentRequirement::Verified => StudentDocumentRecord::StatusVerified,
                EnrollmentRequirement::Waived => StudentDocumentRecord::StatusWaived,
                default => filled($record->file_path) || filled($student->DocumentLocation?->getAttribute($documentKey))
                    ? StudentDocumentRecord::StatusPendingReview
                    : StudentDocumentRecord::StatusMissing,
            };

            $history = is_array($record->history) ? $record->history : [];
            $history[] = [
                'event' => 'synced_from_enrollment',
                'status' => $nextStatus,
                'enrollment_id' => $enrollment->id,
                'actor' => $actor?->name ?? 'System',
                'at' => now()->toIso8601String(),
            ];

            $verifiedBy = match ($nextStatus) {
                StudentDocumentRecord::StatusVerified => $requirement->verified_by ?? $actor?->id,
                StudentDocumentRecord::StatusWaived => $requirement->waived_by ?? $actor?->id,
                default => null,
            };

            $verifiedAt = match ($nextStatus) {
                StudentDocumentRecord::StatusVerified => $requirement->verified_at ?? now(),
                StudentDocumentRecord::StatusWaived => $requirement->waived_at ?? now(),
                default => null,
            };

            $record->fill([
                'school_id' => $student->school_id,
                'status' => $nextStatus,
                'verified_by' => $verifiedBy,
                'verified_at' => $verifiedAt,
                'waiver_reason' => $nextStatus === StudentDocumentRecord::StatusWaived ? $requirement->waiver_reason : null,
                'history' => array_slice($history, -20),
            ])->save();
        });
    }

    /**
     * Send missing-document reminders to a student.
     *
     * @param  list<string>|null  $specificDocumentKeys
     */
    public function remindMissing(
        Student $student,
        ?array $specificDocumentKeys = null,
        ?string $customMessage = null,
    ): bool {
        $summary = $this->rowSummaryFor($student);
        $missingLabels = [];

        foreach ($summary['slots'] as $key => $slot) {
            if ($specificDocumentKeys !== null && $specificDocumentKeys !== [] && ! in_array($key, $specificDocumentKeys, true)) {
                continue;
            }
            if (! $slot['is_compliant'] && ($slot['required'] || ($specificDocumentKeys !== null && $specificDocumentKeys !== []))) {
                $missingLabels[] = (string) $slot['label'];
            }
        }

        if ($missingLabels === []) {
            return false;
        }

        return $this->notifyStudent($student, new StudentDocumentStatusNotification(
            student: $student,
            eventType: 'reminder',
            documentLabels: $missingLabels,
            customMessage: $customMessage,
        ));
    }

    /**
     * Locate a stored fixed document file across candidate disks.
     *
     * @return array{disk: string, path: string, filename: string}|null
     */
    public function resolveFixedFile(Student $student, string $documentType): ?array
    {
        if (! in_array($documentType, DocumentLocation::DOCUMENT_FIELDS, true)) {
            return null;
        }

        $record = Schema::hasTable('student_document_records')
            ? StudentDocumentRecord::query()
                ->where('student_id', $student->id)
                ->where('document_key', $documentType)
                ->first()
            : null;

        $path = filled($record?->file_path)
            ? (string) $record->file_path
            : $student->DocumentLocation?->getAttribute($documentType);

        if (! is_string($path) || mb_trim($path) === '' || str_contains($path, '..')) {
            return null;
        }

        $disk = $this->resolveExistingDisk($path, $record?->disk);
        if ($disk === null) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $studentNumber = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($student->student_id ?? $student->id)) ?: (string) $student->id;
        $filename = "{$studentNumber}_{$documentType}".($extension !== '' ? ".{$extension}" : '');

        return [
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
        ];
    }

    /**
     * Locate a stored dynamic resource file across candidate disks.
     *
     * @return array{disk: string, path: string, filename: string}|null
     */
    public function resolveDynamicFile(Student $student, Resource $resource): ?array
    {
        if ($resource->resourceable_type !== Student::class || (int) $resource->resourceable_id !== (int) $student->id) {
            return null;
        }

        $path = $resource->file_path;
        if (! is_string($path) || mb_trim($path) === '' || str_contains($path, '..')) {
            return null;
        }

        $disk = $this->resolveExistingDisk($path, $resource->disk);
        if ($disk === null) {
            return null;
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'filename' => $resource->file_name ?: basename($path),
        ];
    }

    /**
     * Create a temporary ZIP archive containing all available files for a student dossier.
     */
    public function createDossierZip(Student $student): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $student->loadMissing(['DocumentLocation', 'resources']);
        $tmpPath = tempnam(sys_get_temp_dir(), 'student_dossier_').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $added = 0;

        foreach (DocumentLocation::DOCUMENT_FIELDS as $field) {
            $resolved = $this->resolveFixedFile($student, $field);
            if ($resolved === null) {
                continue;
            }
            $contents = Storage::disk($resolved['disk'])->get($resolved['path']);
            if (is_string($contents)) {
                $zip->addFromString('required/'.$field.'_'.$resolved['filename'], $contents);
                $added++;
            }
        }

        foreach ($student->resources as $resource) {
            $resolved = $this->resolveDynamicFile($student, $resource);
            if ($resolved === null) {
                continue;
            }
            $contents = Storage::disk($resolved['disk'])->get($resolved['path']);
            if (is_string($contents)) {
                $zip->addFromString('additional/'.$resource->id.'_'.$resolved['filename'], $contents);
                $added++;
            }
        }

        $zip->close();

        if ($added === 0) {
            @unlink($tmpPath);

            return null;
        }

        return $tmpPath;
    }

    public function labelFor(string $documentKey, ?string $studentType = null, ?int $schoolId = null): string
    {
        foreach ($this->checklistFor($studentType, $schoolId) as $slot) {
            if ($slot['key'] === $documentKey) {
                return $slot['label'];
            }
        }

        return self::SLOT_CATALOG[$documentKey]['label'] ?? str_replace('_', ' ', ucfirst($documentKey));
    }

    /**
     * @return list<array{key: string, label: string, description: string, url: string, type: string}>
     */
    public function officialDocumentsFor(Student $student): array
    {
        $docs = [
            [
                'key' => 'soa',
                'label' => 'Statement of Account (SOA)',
                'description' => 'Official tuition balance, payment history, and installment ledger PDF.',
                'url' => route('administrators.students.tuition.soa', ['student' => $student->id]),
                'type' => 'financial',
            ],
        ];

        $latestEnrollment = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->latest('id')
            ->first();

        if ($latestEnrollment instanceof StudentEnrollment) {
            $docs[] = [
                'key' => 'enrollment_record',
                'label' => 'Latest Enrollment Dossier',
                'description' => "S.Y. {$latestEnrollment->school_year} · Term {$latestEnrollment->semester} ({$latestEnrollment->status})",
                'url' => route('administrators.enrollments.show', $latestEnrollment->id),
                'type' => 'registrar',
            ];
        }

        $docs[] = [
            'key' => 'registrar_reports',
            'label' => 'Registrar Certificates (COR / Registration / Grades)',
            'description' => 'Generate official A4 Certificate of Enrollment, Registration Form, or Grade Report.',
            'url' => route('administrators.registrar.reports.index', ['student_id' => $student->id]),
            'type' => 'registrar',
        ];

        return $docs;
    }

    /**
     * Resolve distinct (school_id, student_type) cohorts and their applicable checklists for a scoped Student query.
     *
     * @param  Builder<Student>  $scopeQuery
     * @return list<array{school_id: int|null, student_type: string|null, checklist: list<array{key: string, label: string, short_label: string, description: string, accepted: string, required: bool, enabled: bool, image_only: bool}>}>
     */
    public function resolveCohortGroups(Builder $scopeQuery): array
    {
        $rows = (clone $scopeQuery)
            ->toBase()
            ->select(['students.school_id', 'students.student_type'])
            ->distinct()
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $groupSchoolId = $row->school_id !== null ? (int) $row->school_id : null;
            $groupType = is_string($row->student_type) && $row->student_type !== '' ? $row->student_type : null;

            $groups[] = [
                'school_id' => $groupSchoolId,
                'student_type' => $groupType,
                'checklist' => $this->checklistFor($groupType, $groupSchoolId),
            ];
        }

        return $groups;
    }

    /**
     * Compute KPI totals and per-column compliance metrics in SQL without hydrating the full student roster.
     *
     * @param  Builder<Student>  $scopeQuery
     * @param  list<array{school_id: int|null, student_type: string|null, checklist: list<array{key: string, label: string, short_label: string, description: string, accepted: string, required: bool, enabled: bool, image_only: bool}>}>  $cohortGroups
     * @param  list<array{key: string, label: string, short_label: string, description: string, accepted: string, required: bool, enabled: bool, image_only: bool}>  $matrixColumns
     * @return array{kpis: array{total_students: int, fully_compliant: int, pending_review: int, missing_required: int, rejected_count: int}, column_stats: array<string, array<string, mixed>>}
     */
    public function cohortComplianceStats(Builder $scopeQuery, array $cohortGroups, array $matrixColumns): array
    {
        $hasDocRecordsTable = Schema::hasTable('student_document_records');

        $columnStats = [];
        foreach ($matrixColumns as $col) {
            $columnStats[$col['key']] = [
                'key' => $col['key'],
                'label' => $col['label'],
                'short_label' => $col['short_label'],
                'required' => $col['required'],
                'compliant' => 0,
                'pending_review' => 0,
                'rejected' => 0,
                'missing' => 0,
                'applicable' => 0,
                'compliance_percent' => 100,
            ];
        }

        $kpis = [
            'total_students' => 0,
            'fully_compliant' => 0,
            'pending_review' => 0,
            'missing_required' => 0,
            'rejected_count' => 0,
        ];

        $matrixKeys = array_keys($columnStats);

        foreach ($cohortGroups as $group) {
            $checklist = $group['checklist'];
            $applicableKeys = array_values(array_intersect(
                array_column($checklist, 'key'),
                DocumentLocation::DOCUMENT_FIELDS,
            ));
            $requiredKeys = array_values(array_intersect(
                array_column(array_filter($checklist, static fn (array $slot): bool => $slot['required']), 'key'),
                DocumentLocation::DOCUMENT_FIELDS,
            ));

            $aggQuery = (clone $scopeQuery)
                ->toBase()
                ->leftJoin('document_locations as dl', 'dl.id', '=', 'students.document_location_id');

            if ($group['school_id'] === null) {
                $aggQuery->whereNull('students.school_id');
            } else {
                $aggQuery->where('students.school_id', $group['school_id']);
            }

            if ($group['student_type'] === null) {
                $aggQuery->whereNull('students.student_type');
            } else {
                $aggQuery->where('students.student_type', $group['student_type']);
            }

            $aggQuery->selectRaw('COUNT(*) as total_students');

            [$allCompliantSql, $allCompliantBindings] = $this->sqlAllSlotsCompliant($requiredKeys, $hasDocRecordsTable);
            $aggQuery->selectRaw("COALESCE(SUM(CASE WHEN ({$allCompliantSql}) THEN 1 ELSE 0 END), 0) as fully_compliant", $allCompliantBindings);

            [$anyPendingSql, $anyPendingBindings] = $this->sqlAnySlotPendingReview($applicableKeys, $hasDocRecordsTable, true);
            $aggQuery->selectRaw("COALESCE(SUM(CASE WHEN ({$anyPendingSql}) THEN 1 ELSE 0 END), 0) as pending_review", $anyPendingBindings);

            [$anyMissingSql, $anyMissingBindings] = $this->sqlAnySlotMissing($requiredKeys, $hasDocRecordsTable, true);
            $aggQuery->selectRaw("COALESCE(SUM(CASE WHEN ({$anyMissingSql}) THEN 1 ELSE 0 END), 0) as missing_required", $anyMissingBindings);

            [$anyRejectedSql, $anyRejectedBindings] = $this->sqlAnySlotRejected($applicableKeys, $hasDocRecordsTable);
            $aggQuery->selectRaw("COALESCE(SUM(CASE WHEN ({$anyRejectedSql}) THEN 1 ELSE 0 END), 0) as rejected_count", $anyRejectedBindings);

            $groupMatrixKeys = array_values(array_intersect($matrixKeys, $applicableKeys));
            foreach ($groupMatrixKeys as $colKey) {
                [$cSql, $cBind] = $this->sqlSlotCompliant($colKey, $hasDocRecordsTable);
                $aggQuery->selectRaw("COALESCE(SUM(CASE WHEN ({$cSql}) THEN 1 ELSE 0 END), 0) as col_{$colKey}_compliant", $cBind);

                [$pSql, $pBind] = $this->sqlSlotPendingReview($colKey, $hasDocRecordsTable, true);
                $aggQuery->selectRaw("COALESCE(SUM(CASE WHEN ({$pSql}) THEN 1 ELSE 0 END), 0) as col_{$colKey}_pending", $pBind);

                [$rSql, $rBind] = $this->sqlSlotRejected($colKey, $hasDocRecordsTable);
                $aggQuery->selectRaw("COALESCE(SUM(CASE WHEN ({$rSql}) THEN 1 ELSE 0 END), 0) as col_{$colKey}_rejected", $rBind);

                [$mSql, $mBind] = $this->sqlSlotMissing($colKey, $hasDocRecordsTable, true);
                $aggQuery->selectRaw("COALESCE(SUM(CASE WHEN ({$mSql}) THEN 1 ELSE 0 END), 0) as col_{$colKey}_missing", $mBind);
            }

            $row = $aggQuery->first();
            if ($row === null) {
                continue;
            }

            $groupTotal = (int) ($row->total_students ?? 0);
            $kpis['total_students'] += $groupTotal;
            $kpis['fully_compliant'] += (int) ($row->fully_compliant ?? 0);
            $kpis['pending_review'] += (int) ($row->pending_review ?? 0);
            $kpis['missing_required'] += (int) ($row->missing_required ?? 0);
            $kpis['rejected_count'] += (int) ($row->rejected_count ?? 0);

            foreach ($groupMatrixKeys as $colKey) {
                $columnStats[$colKey]['applicable'] += $groupTotal;
                $columnStats[$colKey]['compliant'] += (int) ($row->{"col_{$colKey}_compliant"} ?? 0);
                $columnStats[$colKey]['pending_review'] += (int) ($row->{"col_{$colKey}_pending"} ?? 0);
                $columnStats[$colKey]['rejected'] += (int) ($row->{"col_{$colKey}_rejected"} ?? 0);
                $columnStats[$colKey]['missing'] += (int) ($row->{"col_{$colKey}_missing"} ?? 0);
            }
        }

        foreach ($columnStats as &$stat) {
            $stat['compliance_percent'] = $stat['applicable'] > 0
                ? (int) round(($stat['compliant'] / $stat['applicable']) * 100)
                : 100;
        }
        unset($stat);

        return [
            'kpis' => $kpis,
            'column_stats' => $columnStats,
        ];
    }

    /**
     * Push compliance & missing_doc filters directly into SQL so pagination runs before row hydration.
     *
     * @param  Builder<Student>  $query
     * @param  list<array{school_id: int|null, student_type: string|null, checklist: list<array{key: string, label: string, short_label: string, description: string, accepted: string, required: bool, enabled: bool, image_only: bool}>}>  $cohortGroups
     */
    public function applyComplianceFilters(Builder $query, array $cohortGroups, string $compliance, string $missingDoc): void
    {
        if ($compliance === 'all' && $missingDoc === '') {
            return;
        }

        if ($cohortGroups === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $hasDocRecordsTable = Schema::hasTable('student_document_records');

        $query->where(function (Builder $outer) use ($cohortGroups, $compliance, $missingDoc, $hasDocRecordsTable): void {
            foreach ($cohortGroups as $group) {
                $outer->orWhere(function (Builder $groupQuery) use ($group, $compliance, $missingDoc, $hasDocRecordsTable): void {
                    if ($group['school_id'] === null) {
                        $groupQuery->whereNull('students.school_id');
                    } else {
                        $groupQuery->where('students.school_id', $group['school_id']);
                    }

                    if ($group['student_type'] === null) {
                        $groupQuery->whereNull('students.student_type');
                    } else {
                        $groupQuery->where('students.student_type', $group['student_type']);
                    }

                    $checklist = $group['checklist'];
                    $applicableKeys = array_values(array_intersect(
                        array_column($checklist, 'key'),
                        DocumentLocation::DOCUMENT_FIELDS,
                    ));
                    $requiredKeys = array_values(array_intersect(
                        array_column(array_filter($checklist, static fn (array $slot): bool => $slot['required']), 'key'),
                        DocumentLocation::DOCUMENT_FIELDS,
                    ));

                    if ($compliance === 'pending_review') {
                        [$sql, $bindings] = $this->sqlAnySlotPendingReview($applicableKeys, $hasDocRecordsTable, false);
                        $groupQuery->whereRaw($sql, $bindings);
                    } elseif ($compliance === 'missing') {
                        [$sql, $bindings] = $this->sqlAnySlotMissing($requiredKeys, $hasDocRecordsTable, false);
                        $groupQuery->whereRaw($sql, $bindings);
                    } elseif ($compliance === 'rejected') {
                        [$sql, $bindings] = $this->sqlAnySlotRejected($applicableKeys, $hasDocRecordsTable);
                        $groupQuery->whereRaw($sql, $bindings);
                    } elseif ($compliance === 'complete') {
                        [$sql, $bindings] = $this->sqlAllSlotsCompliant($requiredKeys, $hasDocRecordsTable);
                        $groupQuery->whereRaw($sql, $bindings);
                    }

                    if ($missingDoc !== '') {
                        if (! in_array($missingDoc, $applicableKeys, true)) {
                            $groupQuery->whereRaw('1 = 0');
                        } else {
                            [$sql, $bindings] = $this->sqlSlotCompliant($missingDoc, $hasDocRecordsTable);
                            $groupQuery->whereRaw("NOT ({$sql})", $bindings);
                        }
                    }
                });
            }
        });
    }

    /**
     * Count Pending Review document slots for active in-scope students using their applicable checklists
     * (matching the Documents Hub queue).
     */
    public function countPendingReviewDocuments(): int
    {
        $scopeQuery = Student::query();
        $cohortGroups = $this->resolveCohortGroups($scopeQuery);
        if ($cohortGroups === []) {
            return 0;
        }

        $hasDocRecordsTable = Schema::hasTable('student_document_records');
        $total = 0;

        foreach ($cohortGroups as $group) {
            $applicableKeys = array_values(array_intersect(
                array_column($group['checklist'], 'key'),
                DocumentLocation::DOCUMENT_FIELDS,
            ));

            if ($applicableKeys === []) {
                continue;
            }

            $aggQuery = (clone $scopeQuery)
                ->toBase()
                ->leftJoin('document_locations as dl', 'dl.id', '=', 'students.document_location_id');

            if ($group['school_id'] === null) {
                $aggQuery->whereNull('students.school_id');
            } else {
                $aggQuery->where('students.school_id', $group['school_id']);
            }

            if ($group['student_type'] === null) {
                $aggQuery->whereNull('students.student_type');
            } else {
                $aggQuery->where('students.student_type', $group['student_type']);
            }

            foreach ($applicableKeys as $key) {
                [$sql, $bindings] = $this->sqlSlotPendingReview($key, $hasDocRecordsTable, true);
                $aggQuery->selectRaw("COALESCE(SUM(CASE WHEN ({$sql}) THEN 1 ELSE 0 END), 0) as pending_{$key}", $bindings);
            }

            $row = $aggQuery->first();
            if ($row === null) {
                continue;
            }

            foreach ($applicableKeys as $key) {
                $total += (int) ($row->{"pending_{$key}"} ?? 0);
            }
        }

        return $total;
    }

    /**
     * @param  array<string, array{label?: string, description?: string, visible?: bool, required?: bool}>  $configuredDocSlots
     * @return list<array{key: string, label: string, short_label: string, description: string, accepted: string, required: bool, enabled: bool, image_only: bool}>
     */
    private function defaultChecklistForType(string $studentType, bool $isPhilippine, array $configuredDocSlots = []): array
    {
        $requiredByType = match (true) {
            ! $isPhilippine => [
                'picture_1x1' => true,
                'birth_certificate' => true,
                'transcript_records' => true,
                'good_moral_cert' => false,
            ],
            $studentType === StudentType::SeniorHighSchool->value => [
                'picture_1x1' => true,
                'birth_certificate' => true,
                'form_138' => true,
                'form_137' => true,
                'good_moral_cert' => true,
            ],
            $studentType === StudentType::TESDA->value => [
                'picture_1x1' => true,
                'birth_certificate' => true,
                'form_138' => false,
                'good_moral_cert' => false,
            ],
            default => [
                'picture_1x1' => true,
                'birth_certificate' => true,
                'form_138' => true,
                'good_moral_cert' => true,
                'form_137' => false,
                'transfer_credentials' => false,
                'transcript_records' => false,
            ],
        };

        $slots = [];
        foreach ($requiredByType as $key => $required) {
            $catalog = self::SLOT_CATALOG[$key];
            $docSlotOverride = $configuredDocSlots[$key] ?? null;
            if (is_array($docSlotOverride) && array_key_exists('visible', $docSlotOverride) && ! (bool) $docSlotOverride['visible']) {
                continue;
            }

            $label = is_array($docSlotOverride) && filled($docSlotOverride['label'] ?? null)
                ? (string) $docSlotOverride['label']
                : ($isPhilippine ? $catalog['label'] : $catalog['neutral_label']);
            $description = is_array($docSlotOverride) && filled($docSlotOverride['description'] ?? null)
                ? (string) $docSlotOverride['description']
                : ($isPhilippine ? $catalog['description'] : $catalog['neutral_description']);

            $slots[] = [
                'key' => $key,
                'label' => $label,
                'short_label' => $catalog['short_label'],
                'description' => $description,
                'accepted' => $catalog['accepted'],
                'required' => is_array($docSlotOverride) && array_key_exists('required', $docSlotOverride)
                    ? (bool) $docSlotOverride['required']
                    : $required,
                'enabled' => true,
                'image_only' => (bool) ($catalog['image_only'] ?? false),
            ];
        }

        return $slots;
    }

    /** @return Collection<string, StudentDocumentRecord> */
    private function recordsByKeyForStudent(Student $student): Collection
    {
        if (! Schema::hasTable('student_document_records')) {
            return collect();
        }

        if ($student->relationLoaded('documentRecords')) {
            return $student->documentRecords->keyBy('document_key');
        }

        return StudentDocumentRecord::query()
            ->with('verifier:id,name')
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('document_key');
    }

    private function firstOrNewRecord(Student $student, string $documentKey, bool $lockForUpdate = false): StudentDocumentRecord
    {
        $record = StudentDocumentRecord::query()
            ->where('student_id', $student->id)
            ->where('document_key', $documentKey)
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();

        if ($record instanceof StudentDocumentRecord) {
            return $record;
        }

        return new StudentDocumentRecord([
            'school_id' => $student->school_id,
            'student_id' => $student->id,
            'document_key' => $documentKey,
            'status' => StudentDocumentRecord::StatusMissing,
        ]);
    }

    private function resolveEffectiveStatus(?StudentDocumentRecord $record, bool $hasFile): string
    {
        if ($record instanceof StudentDocumentRecord) {
            if ($record->status === StudentDocumentRecord::StatusMissing && $hasFile) {
                return StudentDocumentRecord::StatusPendingReview;
            }

            return $record->status;
        }

        return $hasFile
            ? StudentDocumentRecord::StatusPendingReview
            : StudentDocumentRecord::StatusMissing;
    }

    private function syncToEnrollmentRequirements(
        Student $student,
        string $documentKey,
        StudentDocumentRecord $record,
        ?User $actor = null,
    ): void {
        if (! Schema::hasTable('enrollment_requirements')) {
            return;
        }

        $aliases = self::REQUIREMENT_KEY_ALIASES[$documentKey] ?? [$documentKey];

        $enrollmentIds = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->whereNotIn('status', ['cancelled', 'rejected', 'withdrawn'])
            ->pluck('id');

        if ($enrollmentIds->isEmpty()) {
            return;
        }

        $requirements = EnrollmentRequirement::query()
            ->whereIn('student_enrollment_id', $enrollmentIds)
            ->whereIn('requirement_key', $aliases)
            ->get();

        foreach ($requirements as $requirement) {
            if (in_array($record->status, [StudentDocumentRecord::StatusVerified, StudentDocumentRecord::StatusPhysicalOnFile], true)) {
                $requirement->update([
                    'status' => EnrollmentRequirement::Verified,
                    'evidence_path' => $record->file_path ?? $requirement->evidence_path,
                    'verified_by' => $actor?->id ?? $record->verified_by,
                    'verified_at' => $record->verified_at ?? now(),
                ]);
            } elseif ($record->status === StudentDocumentRecord::StatusWaived) {
                $requirement->update([
                    'status' => EnrollmentRequirement::Waived,
                    'waived_by' => $actor?->id ?? $record->verified_by,
                    'waived_at' => $record->verified_at ?? now(),
                    'waiver_reason' => $record->waiver_reason ?: 'Waived in Student Documents',
                ]);
            } elseif (in_array($record->status, [StudentDocumentRecord::StatusRejected, StudentDocumentRecord::StatusPendingReview, StudentDocumentRecord::StatusMissing], true)) {
                $requirement->update([
                    'status' => EnrollmentRequirement::Pending,
                    'verified_by' => null,
                    'verified_at' => null,
                    'waived_by' => null,
                    'waived_at' => null,
                    'waiver_reason' => null,
                ]);
            }
        }
    }

    private function documentKeyFromRequirementKey(string $requirementKey): ?string
    {
        $normalized = mb_strtolower(mb_trim($requirementKey));
        foreach (self::REQUIREMENT_KEY_ALIASES as $docKey => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $docKey;
            }
        }

        return null;
    }

    private function notifyStudent(Student $student, StudentDocumentStatusNotification $notification): bool
    {
        $student->loadMissing('user');

        if ($student->user instanceof User) {
            $student->user->notify($notification);

            return true;
        }

        if (filled($student->email)) {
            Notification::route('mail', $student->email)->notify($notification);

            return true;
        }

        return false;
    }

    private function resolveExistingDisk(string $path, ?string $preferredDisk = null): ?string
    {
        $candidateDisks = array_values(array_unique(array_filter([
            $preferredDisk,
            $this->privateDisk(),
            'private',
            is_string(config('filesystems.default')) ? (string) config('filesystems.default') : null,
            'public',
        ])));

        foreach ($candidateDisks as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    private function deleteStoredFile(?string $path, ?string $preferredDisk = null): void
    {
        if (! is_string($path) || mb_trim($path) === '' || filter_var($path, FILTER_VALIDATE_URL) || str_starts_with($path, '/')) {
            return;
        }

        $candidateDisks = array_values(array_unique(array_filter([
            $preferredDisk,
            $this->privateDisk(),
            'private',
            is_string(config('filesystems.default')) ? (string) config('filesystems.default') : null,
            'public',
        ])));

        foreach ($candidateDisks as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);

                break;
            }
        }
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function sqlSlotCompliant(string $key, bool $hasDocRecordsTable): array
    {
        if (! $hasDocRecordsTable) {
            return ['0 = 1', []];
        }

        return [
            'EXISTS (SELECT 1 FROM student_document_records sdr WHERE sdr.student_id = students.id AND sdr.document_key = ? AND sdr.status IN (?, ?, ?))',
            [
                $key,
                StudentDocumentRecord::StatusVerified,
                StudentDocumentRecord::StatusWaived,
                StudentDocumentRecord::StatusPhysicalOnFile,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function sqlSlotHasFile(string $key, bool $hasDocRecordsTable, bool $hasJoinedDocLocation): array
    {
        $legacySql = $hasJoinedDocLocation
            ? "(dl.{$key} IS NOT NULL AND TRIM(dl.{$key}) <> '')"
            : "EXISTS (SELECT 1 FROM document_locations dl WHERE dl.id = students.document_location_id AND dl.{$key} IS NOT NULL AND TRIM(dl.{$key}) <> '')";

        if (! $hasDocRecordsTable) {
            return [$legacySql, []];
        }

        $recordFileSql = "EXISTS (SELECT 1 FROM student_document_records sdr WHERE sdr.student_id = students.id AND sdr.document_key = ? AND sdr.file_path IS NOT NULL AND TRIM(sdr.file_path) <> '')";

        return ["({$recordFileSql} OR {$legacySql})", [$key]];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function sqlSlotNonMissingRecord(string $key, bool $hasDocRecordsTable): array
    {
        if (! $hasDocRecordsTable) {
            return ['0 = 1', []];
        }

        return [
            'EXISTS (SELECT 1 FROM student_document_records sdr WHERE sdr.student_id = students.id AND sdr.document_key = ? AND sdr.status <> ?)',
            [$key, StudentDocumentRecord::StatusMissing],
        ];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function sqlSlotPendingReview(string $key, bool $hasDocRecordsTable, bool $hasJoinedDocLocation): array
    {
        [$hasFileSql, $hasFileBindings] = $this->sqlSlotHasFile($key, $hasDocRecordsTable, $hasJoinedDocLocation);

        if (! $hasDocRecordsTable) {
            return [$hasFileSql, $hasFileBindings];
        }

        [$nonMissingSql, $nonMissingBindings] = $this->sqlSlotNonMissingRecord($key, true);
        $explicitPendingSql = 'EXISTS (SELECT 1 FROM student_document_records sdr WHERE sdr.student_id = students.id AND sdr.document_key = ? AND sdr.status = ?)';

        return [
            "({$explicitPendingSql} OR (NOT ({$nonMissingSql}) AND ({$hasFileSql})))",
            [$key, StudentDocumentRecord::StatusPendingReview, ...$nonMissingBindings, ...$hasFileBindings],
        ];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function sqlSlotRejected(string $key, bool $hasDocRecordsTable): array
    {
        if (! $hasDocRecordsTable) {
            return ['0 = 1', []];
        }

        return [
            'EXISTS (SELECT 1 FROM student_document_records sdr WHERE sdr.student_id = students.id AND sdr.document_key = ? AND sdr.status = ?)',
            [$key, StudentDocumentRecord::StatusRejected],
        ];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function sqlSlotMissing(string $key, bool $hasDocRecordsTable, bool $hasJoinedDocLocation): array
    {
        [$hasFileSql, $hasFileBindings] = $this->sqlSlotHasFile($key, $hasDocRecordsTable, $hasJoinedDocLocation);

        if (! $hasDocRecordsTable) {
            return ["NOT ({$hasFileSql})", $hasFileBindings];
        }

        [$nonMissingSql, $nonMissingBindings] = $this->sqlSlotNonMissingRecord($key, true);

        return [
            "(NOT ({$nonMissingSql}) AND NOT ({$hasFileSql}))",
            [...$nonMissingBindings, ...$hasFileBindings],
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return array{0: string, 1: list<mixed>}
     */
    private function sqlAllSlotsCompliant(array $keys, bool $hasDocRecordsTable): array
    {
        if ($keys === []) {
            return ['1 = 1', []];
        }

        $clauses = [];
        $bindings = [];
        foreach ($keys as $key) {
            [$sql, $bind] = $this->sqlSlotCompliant($key, $hasDocRecordsTable);
            $clauses[] = "({$sql})";
            $bindings = [...$bindings, ...$bind];
        }

        return [implode(' AND ', $clauses), $bindings];
    }

    /**
     * @param  list<string>  $keys
     * @return array{0: string, 1: list<mixed>}
     */
    private function sqlAnySlotPendingReview(array $keys, bool $hasDocRecordsTable, bool $hasJoinedDocLocation): array
    {
        if ($keys === []) {
            return ['0 = 1', []];
        }

        $clauses = [];
        $bindings = [];
        foreach ($keys as $key) {
            [$sql, $bind] = $this->sqlSlotPendingReview($key, $hasDocRecordsTable, $hasJoinedDocLocation);
            $clauses[] = "({$sql})";
            $bindings = [...$bindings, ...$bind];
        }

        return [implode(' OR ', $clauses), $bindings];
    }

    /**
     * @param  list<string>  $keys
     * @return array{0: string, 1: list<mixed>}
     */
    private function sqlAnySlotMissing(array $keys, bool $hasDocRecordsTable, bool $hasJoinedDocLocation): array
    {
        if ($keys === []) {
            return ['0 = 1', []];
        }

        $clauses = [];
        $bindings = [];
        foreach ($keys as $key) {
            [$sql, $bind] = $this->sqlSlotMissing($key, $hasDocRecordsTable, $hasJoinedDocLocation);
            $clauses[] = "({$sql})";
            $bindings = [...$bindings, ...$bind];
        }

        return [implode(' OR ', $clauses), $bindings];
    }

    /**
     * @param  list<string>  $keys
     * @return array{0: string, 1: list<mixed>}
     */
    private function sqlAnySlotRejected(array $keys, bool $hasDocRecordsTable): array
    {
        if ($keys === []) {
            return ['0 = 1', []];
        }

        $clauses = [];
        $bindings = [];
        foreach ($keys as $key) {
            [$sql, $bind] = $this->sqlSlotRejected($key, $hasDocRecordsTable);
            $clauses[] = "({$sql})";
            $bindings = [...$bindings, ...$bind];
        }

        return [implode(' OR ', $clauses), $bindings];
    }

    private function guessMimeFromExtension(string $path): ?string
    {
        return match (mb_strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => null,
        };
    }
}
