# Agent Instructions

## Token efficiency

- Keep work concise and use the lowest-token approach that can safely complete the request.
- Do not perform token-heavy exploration, browser experimentation, broad searches, extensive visual inspection, or repeated verification unless the user explicitly requests it.
- If token-heavy work becomes urgently necessary to complete the task safely, explain why and ask the user for permission before doing it.
- Prefer direct source inspection, a focused edit, and one proportional verification command.
- Stop once the requested outcome is implemented and verified; do not continue with optional experiments.

### Examples

- For a PDF font-size request, inspect the PDF generator, update its typography, and run the build. Do not open unrelated browser tabs or repeatedly generate PDFs unless the user asks for visual testing.
- For a small UI bug, inspect the relevant component and its direct dependencies. Do not scan the entire repository.
- If a change cannot be validated without a lengthy browser or integration workflow, ask: "This needs a token-heavy end-to-end check. May I run it?"
