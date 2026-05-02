# Task Context: Faculty Structured Assignment Modal

Session ID: 2026-05-02-faculty-structured-assignment-modal
Created: 2026-05-02T00:00:00Z
Status: in_progress

## Current Request
Revamp `resources/js/pages/faculty/classes/show.tsx` so faculty create structured posts via a modal (Google Classroom style), with assignment creation fields for title, instruction, attachments, audience targeting (all students or selected students), total points, optional due date, and rubric criteria/levels that can be added dynamically.

## Context Files (Standards to Follow)
- /home/admin/.opencode/context/core/standards/code-quality.md
- /home/admin/.opencode/context/core/standards/navigation.md
- /home/admin/.opencode/context/development/navigation.md
- /home/admin/.opencode/context/ui/web/navigation.md
- /home/admin/.opencode/context/ui/web/react-patterns.md
- /home/admin/.opencode/context/ui/web/ui-styling-standards.md
- /home/admin/.opencode/context/ui/web/animation-components.md
- /home/admin/.opencode/context/ui/web/animation-forms.md
- /home/admin/.opencode/context/development/ui-navigation.md

## Reference Files (Source Material to Look At)
- resources/js/pages/faculty/classes/show.tsx
- resources/js/components/class/tabs/stream-tab.tsx
- resources/js/components/class/tabs/classwork-tab.tsx
- resources/js/types/class-detail-types.ts
- app/Http/Requests/StoreClassPostRequest.php
- app/Http/Controllers/FacultyClassController.php
- app/Models/ClassPost.php
- routes/web/faculty-portal.php
- database/migrations/2025_12_03_143028_create_class_posts_table.php
- database/migrations/2025_12_21_224035_add_action_center_fields_to_class_posts_table.php
- database/migrations/2025_12_21_232043_add_total_points_to_class_posts_table.php

## External Docs Fetched
- Inertia v2 React form helper and file upload guidance (`useForm`, progress, errors, multipart/form-data behavior)
- Inertia multipart limitation note for non-POST method uploads (method spoofing)
- Laravel 12 validation/file rules guidance for file and nested array validation

## Components
- Assignment composer modal UI
- Assignment payload model and validation UX
- Student assignment audience selector (all vs selected)
- Rubric builder (criteria + levels)
- Backend request/controller/model persistence updates
- Stream/Classwork rendering updates for new assignment structure
- Regression and feature tests

## Constraints
- Keep existing class post capabilities (announcement/quiz/activity) working
- Use Inertia React form patterns and multipart-safe submission
- Follow existing route/controller ownership/authorization checks
- No dependency changes without explicit approval
- Run targeted tests and formatting after code changes

## Exit Criteria
- [ ] Faculty can create assignment from modal with title, instruction, files, audience, points, optional due date
- [ ] Faculty can add one or more rubric criteria with points and one or more levels per criterion
- [ ] Assignment audience supports all students or specific student selections
- [ ] Assignment data persists and returns correctly in class posts payload
- [ ] Updated UI displays saved assignment metadata without breaking stream/classwork tabs
- [ ] Added/updated tests pass for validation and assignment creation flow
