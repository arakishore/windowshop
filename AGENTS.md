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