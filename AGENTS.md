# Repository Agent Guidelines

Use these rules when working in this repository.

## Working Style

- Prefer minimal, focused changes.
- Follow YAGNI. Do not add abstractions, helpers, configuration, or broad cleanup unless the current task clearly needs them.
- Re-check the current code before changing it. Do not rely only on prior review notes.
- Preserve behavior that already works unless the requested fix requires changing it.
- Ask before choosing between materially different fixes.

## Formatting

- Do not reformat unrelated code.
- Preserve the surrounding indentation style. This codebase mixes tabs, two-space indents, and four-space indents.
- Avoid indentation-only changes. If a line does not need to change for the task, leave its whitespace alone.
- When editing an existing block, match the local style of that block instead of normalizing the file.
- Keep comments short and in English.

## Code Changes

- Keep patches small and easy to review.
- Reuse existing functions and patterns before adding new code.
- Avoid large refactors unless explicitly requested.
- For security fixes, prefer narrow input validation or escaping at the risky boundary first.
- Do not remove legacy plugins, processors, or configuration defaults without explicit approval.

## PHP Compatibility

- Use syntax compatible with PHP 8.1.
- Avoid introducing dependencies unless explicitly requested.
- Run `php8.1 -l` on changed PHP files when possible.
- Run `git diff --check` before finishing.

## Security Review

- Treat upload paths, shell command construction, remote URL fetching, dynamic includes, and `eval()` as high-risk areas.
- For file uploads, preserve existing allowed-extension behavior unless the task is specifically to tighten it.
- For shell commands, quote user-controlled arguments with `escapeshellarg()` and reject path traversal where applicable.
- For remote fetches, do not allow private, loopback, reserved, or localhost targets unless explicitly required.

