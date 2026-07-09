# UI/UX Guidelines

## Purpose

WindowShop interfaces should feel consistent across admin, merchant, customer, support, and future operational areas. Reuse established components before introducing variants.

## Design Tokens

- Define colors, spacing, typography, radii, shadows, and breakpoints as shared tokens.
- Use semantic colors such as primary, success, warning, danger, info, surface, and muted.
- Never use color as the only indicator of meaning.
- Prefer a consistent spacing scale instead of one-off pixel values.
- Maintain accessible contrast and visible keyboard focus.

## Buttons and Icons

- Use one primary action per section or modal.
- Use secondary buttons for alternatives and danger styling for destructive actions.
- Disable and show progress while an action is submitting.
- Pair unfamiliar icons with text or accessible labels.
- Use one icon family and consistent sizes.
- Confirm irreversible or high-impact actions.

## Forms and Validation

- Place persistent labels above fields; placeholders are examples, not labels.
- Mark required and optional fields clearly.
- Use the correct input type, autocomplete attributes, and mobile keyboard hints.
- Validate on the server; client validation improves feedback but is not authoritative.
- Show field errors beside the field and a summary when several fields fail.
- Preserve safe input after validation failure.
- Prevent duplicate submissions.

## Tables and Pagination

- Tables must have clear headings, meaningful empty states, and predictable actions.
- Align numbers consistently and format dates, money, and status through shared helpers.
- Provide search/filter feedback and a clear-filter action.
- Paginate every potentially unbounded list.
- Preserve filters and page state when returning from a detail screen where practical.
- On small screens, use responsive columns, cards, or controlled horizontal scrolling.

## Messages and Feedback

- Success messages state what completed: “Admin updated successfully.”
- Error messages explain what failed and the next safe action without exposing internals.
- Warning messages explain risk before the user commits.
- Inline feedback is preferred for field-level issues; toasts suit short global outcomes.
- Do not show raw exception, SQL, provider, or stack-trace messages.

## Badges

- Use badges for concise states, not actions.
- Map each enum status to a centralized label and semantic color.
- Always include text; color alone is insufficient.
- Unknown states render safely and visibly rather than assuming success.

## Modals

- Use modals for short, focused tasks and confirmations.
- Use full pages for complex forms or workflows.
- Trap focus, support Escape where safe, restore focus on close, and label the dialog.
- Destructive confirmation names the affected resource and consequence.

## Accessibility and Review

- Support keyboard navigation and meaningful focus order.
- Associate labels, errors, hints, and controls correctly.
- Provide alternative text for informative images.
- Test loading, empty, error, disabled, and permission-denied states.
- Review new UI against existing components before merging.

