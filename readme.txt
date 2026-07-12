=== Post to GitHub Markdown ===
Contributors: gioxx
GitHub Plugin URI: https://github.com/gioxx/wp-post-to-github-md
Tags: github, markdown, export, backup, corpus
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.5.2
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export published WordPress posts as Markdown files (with YAML front matter) to a GitHub repository.

== Description ==
Post to GitHub Markdown exports your published posts as Markdown files, with YAML front matter, to an existing GitHub repository (public or private). The goal is to build, over time, a text corpus of your writing that's useful for training or guiding a consistent writing style in tools like Claude.

Features:
* Export a single post from its edit screen, with live status and trace log
* Bulk export from a dedicated Posts screen: filters, pagination, multi-page selection, stoppable progress
* Single commit and push for the whole bulk export batch by default (opt out for one commit per post), with automatic retry on GitHub rate limits either way
* Optional automatic export of newly published posts and automatic re-export of updated posts, both in the background via WP-Cron
* Read-only view of posts that were exported but are no longer published (draft, private, scheduled or trashed)
* Connection test and automatic branch detection against the configured GitHub repository
* WP-CLI commands for scripted single or bulk exports
* English by default, with an included Italian translation
* Git Updater compatible for seamless updates from GitHub

The plugin never creates the repository, never uploads binary images (they stay as absolute links to the source site), and only works on published `post` content.

== Installation ==
1. Copy the whole plugin folder to `wp-content/plugins/post-to-github-md/` (the `vendor/` folder with dependencies is already included: no need to run Composer).
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings → Post to GitHub MD** and configure a GitHub Personal Access Token and target repository.
4. Export posts individually from the post edit screen, in bulk from **Posts → Export to GitHub**, or automatically on publish via the "Automatic export" setting.

== Frequently Asked Questions ==
= Does this create the GitHub repository for me? =
No, the target repository must already exist before configuring the plugin.

= Does it upload images? =
No. Images in the post content remain absolute links to your site; nothing binary is uploaded to GitHub.

= What post types are supported? =
Only the `post` post type, with "published" status.

== Changelog ==
= 1.5.2 =
* Added a "Settings" quick link on the Plugins list screen.
* The author's name in the Settings page footer now links to gioxx.org.
* Settings page title changed to "Export posts to GitHub: Settings" for clarity.

= 1.5.1 =
* Category and tag filters on the bulk export screen now show a live post count per term, computed against the currently active filters.
* Bulk export now uses the single-commit batch method only when 2 or more posts are selected; exporting a single post always uses the per-post commit flow, regardless of the "Bulk export method" setting.
* Bulk export no longer force-reloads the page when done; status icons and stat tiles already update live, and successfully exported posts are automatically deselected.
* Fixed a fatal error on the export screen caused by the wrong taxonomy name being used for tag counts.

= 1.5.0 =
* Bulk export now writes the whole selected batch to GitHub in a single commit and push (via the Git Data API) instead of one commit per post, cutting API calls from ~2N to about 5 and largely avoiding rate limits. Opt-out setting included to keep one commit per post.

= 1.4.0 =
* Add optional automatic re-export of updated posts, a read-only view of exported-but-unpublished posts, automatic retry on GitHub rate limits, uninstall data cleanup (with an opt-out setting), and WP-CLI commands.

= 1.3.0 =
* Add optional automatic export of newly published posts, scheduled via WP-Cron so the Publish button is never delayed.

= 1.2.0 =
* Unify bulk export into its own Posts screen, with filters (status, search, category, tag, month), WP-native pagination, multi-page selection, a stoppable live progress/log panel, and export status stat tiles.
* Add GitHub connection test and automatic branch detection in Settings.
* Numerous UX refinements and bug fixes across the settings and export screens (spinners, icon/status sync, layout, accessibility).

= 1.1.0 =
* Add plugin metadata, repository URL parsing, status icons, and full English/Italian localization.

= 1.0.0 =
* Initial release: single-post and bulk export to a GitHub repository via the Contents API.

== Upgrade Notice ==
= 1.5.0 =
Bulk export now uses one commit for the whole batch by default; uncheck "Bulk export method" in Settings to keep one commit per post.
