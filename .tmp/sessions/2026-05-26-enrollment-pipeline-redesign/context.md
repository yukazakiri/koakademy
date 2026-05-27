# Task Context: Enrollment Pipeline Redesign

Session ID: 2026-05-26-enrollment-pipeline-redesign
Created: 2026-05-26T00:00:00Z
Status: in_progress

## Current Request
Redesign and restyle `resources/js/pages/administrators/system-management/enrollment-pipeline.tsx` to:
1. Hide the visual inspector completely
2. Show only the canvas with nodes in the workflow tab
3. Right-click on a node shows only essential actions (Edit, Duplicate, Set as entry, Set as completion, Connect, Disconnect, Delete)
4. Clicking "Edit" opens a modal with proper navigation and configuration for the node
5. Make the workflow more user-friendly and organized

## Context Files (Standards to Follow)
- No `.opencode/context` files found
- Project uses Laravel 12, Inertia React v3, Tailwind CSS v4, shadcn/ui components
- PHP 8.5, React 19, TypeScript

## Reference Files (Source Material to Look At)
- `resources/js/pages/administrators/system-management/enrollment-pipeline.tsx` — Main target file (~1800+ lines)
- `resources/js/pages/administrators/system-management/types.ts` — TypeScript types for pipeline nodes/actions/conditions
- `resources/js/components/ui/dialog.tsx` — Existing Radix dialog wrapper for modal
- `resources/js/components/ui/context-menu.tsx` — Existing context menu primitives
- `resources/js/components/ui/tabs.tsx` — Existing tabs API (base-ui/react-tabs)
- `resources/js/components/ui/card.tsx` — Card components
- `resources/js/components/ui/button.tsx` — Button components
- `resources/js/components/ui/input.tsx` — Input components
- `resources/js/components/ui/select.tsx` — Select components
- `resources/js/components/ui/switch.tsx` — Switch components
- `resources/js/components/ui/checkbox.tsx` — Checkbox components
- `resources/js/components/ui/badge.tsx` — Badge components
- `resources/js/components/ui/label.tsx` — Label components
- `resources/js/components/ui/separator.tsx` — Separator components

## External Docs Fetched
- Inertia React v3: useForm hook, Form component, client-side navigation
- Tailwind CSS v4: CSS-first config, @theme directive, utility classes, dark mode support
- shadcn/ui: Dialog, ContextMenu, Tabs components already installed and styled

## Components
1. **Canvas** — Full-width workflow canvas with draggable nodes and connection lines
2. **WorkflowCanvasNode** — Individual node component with right-click context menu
3. **NodeEditModal** — Modal dialog with sidebar tabs for node configuration
4. **ContextMenu** — Simplified right-click menu with essential actions only

## Constraints
- Must maintain all existing functionality (drag, connect, disconnect, organize, etc.)
- Must use existing shadcn/ui components (Dialog, ContextMenu, Tabs, etc.)
- Must follow existing code patterns in the project
- Must preserve all form handling with Inertia useForm
- Must keep all existing helper functions (updatePipelineStep, setStepField, etc.)
- Must support dark mode via Tailwind dark: variants
- Must not break existing tests

## Exit Criteria
- [ ] Visual inspector is completely hidden from workflow tab
- [ ] Canvas takes full width of the workflow tab
- [ ] Right-click on node shows: Edit, Duplicate, Set as entry, Set as completion, Connect, Disconnect, Delete
- [ ] Clicking Edit opens a modal with sidebar navigation
- [ ] Modal has organized tabs/sections for: Basic Info, Actions, Conditions, Connections, Permissions
- [ ] All existing functionality preserved (drag, connect, organize, add/remove steps, etc.)
- [ ] Code passes linting and formatting checks
- [ ] Existing tests still pass
