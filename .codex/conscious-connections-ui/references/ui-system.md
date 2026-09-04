# Conscious Connections UI System

## Product Posture

Conscious Connections is a sexual-health learning platform. UI must feel safe, warm, and trustworthy, not clinical-cold or entertainment-only. Prefer plain language, visible readiness, and clear decision ownership. Admin and instructor tools are work surfaces; learner pages can be more encouraging and progress-driven.

## Core Tokens

- Typeface: use Tailwind `font-sans`, configured as Poppins, Figtree, then system sans.
- Primary gradient: `linear-gradient(135deg, #A30EB2 0%, #730DB1 50-55%, #3B0CB1 100%)`.
- Tailwind brand scale: use `brand-50` through `brand-950`; primary identity centers on `brand-500`, `brand-700`, and `brand-900`.
- Support colors:
  - Purple/indigo for learning, identity, and primary actions.
  - Amber for waiting, review, parent invitations, and attention.
  - Emerald/green for approved, success, healthy progress, or pass states.
  - Rose/red for rejection, danger, suspension, errors, or serious moderation.
  - Sky/blue sparingly for neutral information.
- Surfaces: `bg-gray-50` page background; `bg-white` primary panels; dark mode only where the layout already supports it.
- Radius: existing app favors `rounded-xl` and `rounded-2xl`. Use `rounded-lg` for dense controls, `rounded-xl` for rows/cards, `rounded-2xl` for larger panels and modals.
- Shadows: use `shadow-sm`, `shadow-soft`, `shadow-medium`, or `shadow-theme-md`. Avoid heavy decorative shadows on routine workspaces.
- Spacing: page main content commonly uses `p-4 md:p-6`; dashboard sections use `gap-4`, `gap-6`, and `space-y-6`.

## Role Shells

### Learner

Use `layouts.learner-app` for standard learner pages. Learner UI supports light and dark themes, global translation controls, gamification/shield toasts, chat popup, and responsive sidebar/header state.

Learner feel: warm, encouraging, rounded, progress-oriented. Use gradient welcome banners, soft tinted section backgrounds, horizontal module carousels, gamification panels, streak cards, badges, and empty states with clear next action.

Common learner patterns:

- Hero/welcome panels: gradient `#A30EB2 -> #730DB1 -> #3B0CB1`, white text, small uppercase eyebrow, concise progress copy.
- Sections: `p-5 border rounded-2xl` with tinted backgrounds like `bg-purple-50/40`, `bg-indigo-50/30`, `bg-amber-50/60` and dark equivalents when the page already includes dark classes.
- Section headings: left border accent (`pl-3 border-l-4 border-purple-400`) plus small supportive subtitle.
- CTAs: rounded buttons or pills with purple/indigo brand colors. Keep labels learner-friendly: `Start Learning`, `Explore Modules`, `Review Invitation`, `Message Parent`.
- Gamification: shields, XP, streaks, and achievements should be visible but not childish. Keep numbers clear and motivational.

Do not expose raw review states or backend terms to learners. Use age-appropriate, calm labels and show what they can do next.

### Instructor

Use `layouts.instructor-app`. Instructor UI is locked to light mode with `data-theme-lock="light"` and `instructor-theme-brand`. It uses a collapsible white sidebar, role-grouped navigation, compact cards, search, stats, pending requests, tables, and calendar widgets.

Instructor feel: calm, professional, task-focused, slightly lighter than admin. It can use brand gradients for active nav and primary actions, but most surfaces should be white/gray with subtle purple focus states.

Common instructor patterns:

- Sidebar width states: expanded `w-[280px]`, collapsed `w-[84px]`.
- Active nav: `bg-gradient-to-r from-brand-500 via-brand-700 to-brand-900 text-white shadow-sm`.
- Nav groups: uppercase small labels such as `MAIN`, `TEACHING`, `COMMUNICATION`, `FINANCE`, `ASSETS`.
- Search: icon-left input, `rounded-xl`, subtle border, purple focus ring.
- Sections: use `x-instructor.section-shell` when possible. It gives consistent title/subtitle/action rhythm.
- Stats: use `x-instructor.stat-card` where present.
- Actions: instructor approvals need distinct `Approve` and `Reject` controls; parent-gated rows should not imply instructor can decide.

Use utility copy: `Recent Activities`, `Pending Requests`, `Top Modules`, `Quiz Performance`, `Assessment Insights`. Avoid marketing hero language inside work tools.

### Admin

Use `layouts.admin`. Admin UI is also locked to light mode. It has a collapsible white sidebar, grouped operational navigation, top header, notifications, profile menu, and max-width work canvas.

Admin feel: command center, moderation-first, high-trust, scan-friendly. Admin pages can use richer brand panels and charts, but should prioritize queues, readiness, approvals, and safety operations over decorative analytics.

Common admin patterns:

- Sidebar width states: expanded `w-[290px]`, collapsed `w-[90px]`.
- Active nav: inline or Tailwind brand gradient from vivid purple through indigo.
- Badges: small rose notification badges for pending/unread counts; keep count slots stable to avoid layout shifts.
- Dashboard cards: `rounded-2xl border border-brand-200/80 bg-gradient-to-br from-brand-50 via-white to-brand-100/70 shadow-soft ring-1 ring-brand-200/40`.
- Operational panels: white cards with brand-tinted headers, clear titles, short subtitles, and obvious `Open`/review links.
- Tables: dense, readable, hover rows, small uppercase metadata, stable action columns.
- Modals: use existing modal shells/partials where available, especially moderation and approval flows.

Admin copy must be plain-language. Avoid raw enums and database names. Prefer `Needs Attention`, `Waiting for instructor approval`, `Waiting for parent approval`, `Not ready yet`, `Live`, `In Review`, `Archived`, `Blocked / Rejected`.

### Connector And Community

Connector UI is organization-scoped. Community feed V1 is adult-facing and connector-scoped; do not create minor participation, DMs, nested replies, or global sharing unless a separate safety design exists.

Community patterns:

- Use `resources/views/components/community/*` for feed sidebar, post cards, composer, badges, reaction rows, safety reminders, and right panels.
- Keep safety reminders visible and calm.
- Use education-focused reactions: Learned, Helpful, Question, Support, Bookmark.
- Distinguish connector moderation from platform-admin escalation.

## Components And Interaction

Prefer these existing component families:

- Shared UI: `components/ui/button`, `card`, `badge`, `alert`, `empty-state`, `progress-bar`, `spinner`, `skeleton`.
- Learner: `components/learner/gamification-panel`, `module-card-active`, `module-card-recommended`, `mini-calendar`, `streak-card`.
- Instructor: `components/instructor/hero-banner`, `section-shell`, `stat-card`, `quick-actions`, `module-carousel`, `mini-calendar-shell`.
- Community: `components/community/post-card`, `post-composer`, `reaction-row`, `status-badge`, `safety-reminder`.

Use Alpine for local UI state because layouts already register sidebar stores and page widgets use `x-data`, `x-show`, `x-cloak`, and transitions. Keep mobile backdrops and sidebars accessible with click-away behavior and stable z-indexes.

Use SVG icons already present in the codebase unless the project has a local icon component for the surface. Keep icons simple outline style, usually `w-4 h-4` or `w-5 h-5`.

## Forms, States, And Copy

- Use labels and helper text that explain the user decision, not the database field.
- Show readiness before an action fails, especially publishing, approvals, and moderation.
- Keep parent approval and instructor approval visually distinct: different labels, colors/icons, and available actions.
- Use empty states with one next action. Do not leave blank dashboards.
- For moderation/safety: state severity and next step clearly. Do not soften blocking risks.
- For learner-facing sexual-health content: use inclusive, neutral, non-judgmental language.

## Tailwind And Build Rules

- Use literal Tailwind class strings so Vite can detect them.
- Avoid Blade interpolation inside class names such as `lg:grid-cols-{{ $count }}`. Map to a finite class string instead.
- Preserve structural classes and `data-testid` attributes used by tests.
- If a class must be present for server-render assertions, put it in static HTML, not only inside Alpine `:class`.
- Do not introduce new CSS files unless the pattern is reusable across several views. Prefer Blade component classes and Tailwind utilities.

## Accessibility And Responsiveness

- Maintain visible focus states. Global focus uses `ring-brand-purple-500` currently; use brand/purple focus rings consistently.
- Keep button text short enough to fit on mobile; allow wrapping where needed rather than overflow.
- Use `truncate`, `min-w-0`, stable icon sizes, and fixed badge slots in nav/table rows.
- Tables need horizontal overflow wrappers on smaller screens.
- Maintain dark-mode classes on learner pages; avoid adding dark-only styling to instructor/admin unless their layouts change.

## Verification

After UI changes:

- Run focused Laravel feature tests for the touched role or workflow.
- Run `npm.cmd run build` when Blade/Tailwind/JS changed.
- For learner activity/game UI, also run existing activity JS/PHP tests when relevant.
- Inspect generated diffs carefully because this repo often has unrelated dirty files and Vite may update `public/build` assets.
