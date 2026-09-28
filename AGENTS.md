# WindowShop Agent Instructions

## Before Making Changes

- Investigate the existing implementation before modifying code.
- Reuse existing services, patterns and business logic.
- Do not duplicate existing functionality.
- Check `git status` before making changes.
- Do not modify unrelated files.

## Scope

- Make only the changes required by the current task.
- Do not create migrations unless required.
- Do not change existing business rules unless explicitly requested.
- If the request says "investigate only", do not modify any files.

## Testing

After completing a change:

- Review `git diff`.
- Run the relevant tests.
- For UI changes, verify the affected page in the browser using Playwright when available.
- Check for browser/console errors when relevant.
- Do not create real orders or other consequential records during testing unless explicitly requested.

## Git

- Never commit unless explicitly requested.
- Never push unless explicitly requested.
- Never merge or create a pull request unless explicitly requested.
- Never discard existing user changes.

## Completion Report

After completing a task, report:

- What was changed
- Files changed
- Tests performed
- Test results
- Browser verification performed
- Anything that still needs manual testing

Never claim that something was tested if it was not actually tested.

## Project Decision / Outcome Logging

`Prompt_Outcome_Log.md` is the persistent project history for important
investigations, architectural decisions, frozen business rules, and completed
implementation outcomes.

### Before Starting Work

Before investigating or implementing a task:

1. Search `Prompt_Outcome_Log.md` for related previous decisions.
2. Treat recorded/frozen decisions as project constraints unless the current
   task explicitly changes them.
3. Do not silently contradict or replace an earlier recorded decision.
4. If the current task conflicts with an earlier decision, stop and report the
   conflict before implementing.

### When To Log

Update `Prompt_Outcome_Log.md` when a task produces any of the following:

- architecture investigation or technical audit
- important implementation decision
- frozen business rule
- new reusable project convention
- database/settings ownership decision
- integration approach
- significant feature implementation
- decision to intentionally postpone/defer functionality
- discovery that materially affects future development

Do NOT add routine noise such as:

- tiny CSS changes
- typo fixes
- simple copy changes
- ordinary bug fixes with no architectural impact
- test-only adjustments that introduce no new decision

### Investigation Logging

For investigation-only tasks, add an entry containing:

- date/time
- topic
- question investigated
- relevant existing implementation found
- findings
- decision/recommendation
- affected files/services/tables
- risks or dependencies
- whether any previous project decision was confirmed, changed, or superseded

If the investigation confirms an existing decision, reference the existing
decision instead of creating a contradictory duplicate.

### Implementation Logging

For significant implementation tasks, record:

- date/time
- task/feature
- original goal or concise prompt summary
- important decisions
- implementation outcome
- key files/services/tables involved
- tests/verification performed
- deferred/future work
- any new frozen rule

Keep entries concise but detailed enough that a future developer or AI agent
can understand WHY the implementation exists.

### Changing Previous Decisions

Never silently overwrite historical decisions.

If a previous decision changes:

1. Keep the old entry.
2. Add a new dated entry.
3. Clearly state:

   `Supersedes: <previous decision/topic/date>`

4. Explain why the decision changed.

The newest explicitly superseding decision becomes authoritative.

### Task Completion

Before reporting a significant task complete:

1. Review whether the task created or changed a project decision.
2. Update `Prompt_Outcome_Log.md` when required.
3. Include the log file in the changed-files report.
4. Do not commit or push unless explicitly requested.