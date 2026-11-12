# UX and Design System

## 1. Brand direction

Product: PLAUD Companion  
Personality: calm, intelligent, dependable, professional.  
Suggested palette:
- Deep navy: `#101B33`
- Navy surface: `#172541`
- Emerald: `#18A879`
- Pale emerald: `#E8F7F1`
- Main background: `#F6F8FB`
- White: `#FFFFFF`
- Main text: `#172033`
- Secondary text: `#667085`
- Border: `#E5EAF0`
- Error: accessible red tone.

Validate all color combinations for accessibility. Do not rely on color alone to communicate state.

## 2. Navigation

Mobile bottom navigation:
- Home
- Transcripts
- AI
- Exports
- Workspace/Profile

Use a workspace switcher in the app bar where appropriate. Desktop can use a left sidebar.

## 3. Required screens

1. Splash and onboarding.
2. Sign in, register, password reset, email verification.
3. Dashboard.
4. Transcript library with search, filters and selection.
5. Import flow with file picker, validation and progress.
6. Transcript detail with metadata, segments and actions.
7. AI assistant and conversation history.
8. Provider/model settings.
9. Bulk export configuration.
10. Export queue/history and download.
11. Projects and folders.
12. Workspace switcher.
13. Members, roles and invitations.
14. Notifications.
15. Profile, privacy and account deletion.
16. Admin web screens.

## 4. Interaction requirements

- Show loading, empty, success and error states.
- Confirm destructive actions.
- Preserve selection when navigating back where sensible.
- Show clear progress for long-running jobs.
- Provide retry when safe.
- Do not show “Done” before the backend confirms completion.
- Use accessible labels and large enough touch targets.
- Support screen readers and dynamic text sizing.
- Respect platform conventions on Android and iOS.

## 5. Dashboard

Display:
- Total transcripts.
- Recent imports.
- Pending AI/export jobs.
- Recent activity.
- Quick import action.
- Recent transcripts.
- Workspace context.

Only show counts the user is authorized to see.

## 6. Transcript detail

Display:
- Title, date and source.
- Project and tags.
- Speakers and timestamped segments when available.
- Summary and action items if generated.
- Ask AI action.
- Export action.
- Edit metadata and delete actions according to role.

## 7. AI experience

- Provider selector shows only enabled providers.
- Explain that transcript content may be sent to the selected external AI provider.
- Show source transcript references and segment/timestamp links.
- Distinguish generated interpretation from transcript evidence.
- Show an insufficient-evidence message when appropriate.
- Provide stop/retry controls for supported tasks.

## 8. Export experience

- Selection count.
- Template selector.
- Optional document settings.
- Estimated item count.
- Job progress and per-item results.
- Download only when archive is verified and ready.
- Partial failure summary and retry.

## 9. Design implementation

Create shared design tokens and reusable widgets/components. Keep business logic outside UI components. Include localization-ready strings; initial language may be English, but avoid hardcoding text in a way that blocks future translation.
