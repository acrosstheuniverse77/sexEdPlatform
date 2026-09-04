---
name: conscious-connections-ui
description: Use when Codex changes, reviews, or designs UI for the Conscious Connections Laravel sexual-health platform, especially Blade/Tailwind learner, instructor, admin, connector, moderation, learning-path, dashboard, gamification, enrollment, or safety workflow surfaces. Guides colors, fonts, layout rhythm, role-specific UX language, accessibility, and verification expectations for this project.
---

# Conscious Connections UI

Use this skill before editing or reviewing UI in `sexEdPlatform` so new work matches the existing product rather than drifting into a generic SaaS look.

## Workflow

1. Inspect the current target files first. Prefer `rg --files resources/views` and read the relevant layout, page, and component files.
2. Read [references/ui-system.md](references/ui-system.md) before making design or implementation decisions.
3. Match the role shell already in use:
   - Learner: `resources/views/layouts/learner-app.blade.php` plus learner components.
   - Instructor: `resources/views/layouts/instructor-app.blade.php` plus instructor components.
   - Admin: `resources/views/layouts/admin.blade.php` plus admin partials and shared UI components.
   - Connector: `resources/views/layouts/connector-app.blade.php` and community components when connector-scoped.
4. Preserve existing Blade, Alpine, Tailwind, Vite, and route/test contracts. Do not rename structural classes, data attributes, or text that tests likely assert unless the task requires it.
5. For safety-sensitive sexual-health surfaces, keep copy plain, calm, and action-oriented. Make parent, instructor, connector moderator, and platform admin responsibilities visibly distinct.
6. After edits, run the narrowest relevant feature tests and `npm.cmd run build` when Tailwind classes, Blade markup, or JS behavior changed.

## Quick Rules

- Use Poppins/Figtree through the existing Tailwind `font-sans`; do not introduce a new typeface.
- Use the brand gradient `#A30EB2 -> #730DB1 -> #3B0CB1` or Tailwind `brand-*` colors for primary identity.
- Keep role UI recognizable: playful progress for learners, calm operational workspace for instructors and admins.
- Prefer existing components in `resources/views/components/ui`, `components/learner`, `components/instructor`, and `components/community` before creating new ones.
- Use finite literal Tailwind classes. Avoid dynamic class interpolation that Vite cannot detect.
- Keep text inside controls short and scannable. Use icons for compact actions when the existing surface already uses them.
