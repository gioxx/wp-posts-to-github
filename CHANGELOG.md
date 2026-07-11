# Changelog

All notable changes to this project are documented in this file.

## [1.5.1] - 2026-07-11
### Added
- Category and tag dropdown filters on the bulk export screen now show a live post count per term, computed against the currently active filters (e.g. selecting "Never exported" updates the counts shown next to each category/tag).

### Changed
- The single-commit batch export method now only kicks in when 2 or more posts are selected. Exporting exactly one post always uses the per-post commit flow, even when "Bulk export method" is enabled.

## [1.5.0] - 2026-07-11
### Added
- Single-commit bulk export: instead of one commit (and one push) per post, the plugin now prepares every selected post's Markdown locally and writes the whole batch to GitHub via the Git Data API in one commit and one push. Cuts a bulk export of N posts from ~2N GitHub API calls down to about 5, largely sidestepping rate limits.
- New "Bulk export method" setting (checked by default) to opt back into the previous one-commit-per-post behavior, e.g. to keep a dedicated commit per post in the repository history.

## [1.4.0] - 2026-07-11
### Added
- Automatic re-export of already-published posts when updated (separate opt-in setting from automatic export on publish).
- "Exported, no longer published" view: lists posts that were exported but are now a draft, pending, private, scheduled, or trashed, with a link to the post and to the file on GitHub. View-only by design — removal from the repository stays a manual, deliberate action.
- Automatic backoff and retry during bulk export when GitHub's rate limit is hit (reads `Retry-After` / `X-RateLimit-*` response headers).
- `uninstall.php` with a "Remove all plugin data on uninstall" setting (enabled by default; disable to keep settings and export history if you reinstall later).
- WP-CLI commands: `wp potogh export <post_id>` and `wp potogh bulk-export [--status=] [--dry-run]`.
- Git Updater compatibility (`GitHub Plugin URI` plugin header and a WordPress.org-style `readme.txt`) so the plugin can be updated directly from its GitHub repository.
- Trademark disclaimer and plugin credits on the Settings page.

### Removed
- Dropped the previously bundled Octodex mascot images; settings/export screens no longer reference third-party artwork.

## [1.3.0] - 2026-07-11
### Added
- Automatic export of newly published posts, scheduled in the background via WP-Cron so the Publish button is never delayed.

## [1.2.0] - 2026-07-10
### Added
- Unified bulk export into its own **Posts → Export to GitHub** screen with status/search/category/tag/month filters, WP-native pagination (shown above and below the table), a per-page preference remembered per user, and multi-page "select all matching filter" selection.
- Shift-click range selection and a "Reset filters" shortcut on the export table.
- Stoppable bulk export: a Stop control lets the current request finish and skips the rest instead of aborting mid-write.
- Live progress bar and scrolling log during bulk export; the page auto-refreshes on completion so status icons and stat tiles are always current.
- Clickable stat tiles above the export table (total published, never exported, exported, modified since export).
- GitHub connection test and automatic default-branch detection in Settings.
- Full English-by-default localization with a bundled Italian translation.

### Fixed
- Numerous issues found during live testing: stale status icon after a successful export, misaligned/invisible loading spinners, checkbox column alignment, disproportionate table column widths, exported-date shown in GMT instead of the site's timezone, hardcoded Italian strings in the export trace log.

## [1.1.0] - 2026-07-10
### Added
- Plugin metadata (author, license, plugin URI).
- Repository field accepts a full GitHub URL in addition to `owner/repo`.
- Inline help text under every settings field.

## [1.0.0] - 2026-07-08
### Added
- Initial release: export a single published post to GitHub from its edit screen, plus a bulk export admin page. Posts are converted to Markdown with YAML front matter and pushed via the GitHub Contents API.
