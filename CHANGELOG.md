# Changelog

All notable changes to this project are documented in this file.

## [1.5.8] - 2026-07-18
### Fixed
- Addressed findings from the WordPress Plugin Check tool ahead of directory submission: missing `translators:` comments, unordered translation placeholders, unescaped pagination output, missing `ABSPATH` guard in `includes/functions.php`, deprecated `load_plugin_textdomain()` call, and an outdated readme "Tested up to" header (now 7.0, tested against 7.0.2).
- Excluded `README.it.md` from the release zip built by the GitHub Actions workflow (unexpected file in plugin root per directory guidelines); it remains in the GitHub repository.

## [1.5.7] - 2026-07-14
### Added
- Failed exports (automatic or manual, single-post or batch) now record the error message on the post (`_potogh_last_error`), surfaced as a warning next to the export status in the post edit metabox, the bulk export table, and the "Exported, no longer published" view. Previously, a failed background auto-(re)export was completely silent.
- "Exported, no longer published" view: added two actions per row, "Delete from GitHub" (removes the file from the repository via a dedicated commit, using the GitHub Contents API) and "Ignore" (clears the local export record without touching GitHub, e.g. when the post is intentionally being reworked before republishing).

### Fixed
- "Invalid request. \"sha\" wasn't supplied" error from GitHub when re-exporting a post whose local `_potogh_sha` meta was missing or out of sync. The plugin now falls back to looking up the file's current sha directly from GitHub (via the Contents API) before writing, instead of only trusting the cached post meta.

## [1.5.6] - 2026-07-12
### Changed
- Bulk export screen: "posts per page" is now set via the native WordPress Screen Options panel instead of a custom dropdown, persisted the standard WordPress way (per-user meta tied to the screen option).
- Bulk export screen: pagination now happens at the database level (`WP_Query` with `posts_per_page`/`paged`) for the filter combinations that don't require computing export status per post (no status filter, "Never exported", and the orphaned-posts view), instead of always loading every matching post into memory and slicing in PHP. The "Exported" and "Modified since export" filters keep the previous full-fetch behavior since their status can't be expressed as a database query.

## [1.5.5] - 2026-07-12
### Changed
- Plugin renamed from "WordPress Posts to GitHub" to "Posts to GitHub", ahead of submission to the official WordPress.org plugin directory, to comply with the WordPress trademark policy (third-party plugins may not use "WordPress" in the plugin name). Internal identifiers (text domain, PHP prefixes, plugin folder name, option names) are unchanged, so existing installs are unaffected.

## [1.5.4] - 2026-07-12
### Changed
- "Test connection" moved next to the Repository field instead of the bottom submit row.
- The Repository field is now locked (read-only) once a repository is configured, to avoid accidental edits; a "Change repository" button unlocks it for editing.

## [1.5.3] - 2026-07-12
### Changed
- "Save Changes" on the Settings page is no longer forced to re-run "Test connection" on every visit. It only requires a successful test the first time you configure the token/repository, or after editing token, repository or branch — toggling other settings no longer re-locks it.

## [1.5.2] - 2026-07-12
### Added
- "Settings" quick link on the Plugins list screen, next to Activate/Deactivate.
- The author's name in the Settings page footer now links to gioxx.org.

### Changed
- Settings page title changed to "Export posts to GitHub: Settings" for clarity.
- Plugin renamed from "Post to GitHub Markdown" to "WordPress Posts to GitHub" (the previous name read as ambiguous, as if posting *to* GitHub rather than exporting WordPress posts as Markdown). The GitHub repository moved from `wp-post-to-github-md` to `wp-posts-to-github` (GitHub redirects the old URL automatically). Internal identifiers (text domain, PHP prefixes, plugin folder name, option names) are unchanged, so existing installs are unaffected.

## [1.5.1] - 2026-07-11
### Added
- Category and tag dropdown filters on the bulk export screen now show a live post count per term, computed against the currently active filters (e.g. selecting "Never exported" updates the counts shown next to each category/tag).

### Changed
- The single-commit batch export method now only kicks in when 2 or more posts are selected. Exporting exactly one post always uses the per-post commit flow, even when "Bulk export method" is enabled.
- Bulk export no longer force-reloads the page when it finishes; row status icons and the stat tiles were already updated live, so the reload was redundant. Instead, successfully exported posts are automatically unchecked from the selection when the run completes (failed or skipped posts stay selected).

### Fixed
- Fatal error on the export screen caused by `termCounts()` using the wrong taxonomy name for tags (`tag` instead of `post_tag`).

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
