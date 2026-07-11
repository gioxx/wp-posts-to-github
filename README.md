# Post to GitHub Markdown

*[Leggi questo in italiano](README.it.md)*

A WordPress plugin that exports your published posts as Markdown files (with YAML front matter) to an existing GitHub repository. The goal is to build, over time, a text corpus of your writing that's useful for training or guiding a consistent writing style in tools like Claude.

The plugin does not create the repository, does not upload binary images, and only works on `post` content with a "published" status.

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- An existing GitHub repository (public or private)
- A GitHub Personal Access Token (PAT) with write access to the repository (classic `repo` scope, or a fine-grained token with read/write access to "Contents" for that repository)

## Installation

1. Copy the whole plugin folder to `wp-content/plugins/post-to-github-md/` on your WordPress site (the `vendor/` folder with dependencies is already included: no need to run Composer).
2. Go to **Plugins** in the WordPress dashboard and activate "Post to GitHub Markdown".

## Language

The plugin interface is in English by default; if your WordPress is set to Italian (`it_IT`), the plugin automatically loads the included Italian translation (`languages/post-to-github-md-it_IT.mo`).

## Configuration

Go to **Settings → Post to GitHub MD** and fill in:

| Field | Description | Example |
|---|---|---|
| **GitHub Personal Access Token** | The PAT with write access to the target repository. Never displayed in plain text elsewhere on the site. | `ghp_xxxxxxxxxxxxxxxxxxxx` |
| **Repository** | The target GitHub repository: enter either `owner/repo` or the full URL (`https://github.com/owner/repo`). The repository must already exist. | `yourname/your-repo` or `https://github.com/yourname/your-repo` |
| **Branch** | The branch files are committed to. Use the **"Detect from repository"** button to auto-fill it with the repository's actual default branch. | `main` |
| **Base folder** | The top-level repository folder exports are saved into. Left empty, it defaults to `posts`. | `posts` |
| **Automatic export** | When checked, newly published posts are exported automatically a few seconds after publishing, via WP-Cron, without delaying the Publish button. Off by default. Existing posts aren't affected retroactively — use the Export posts page for those. | — |
| **Automatic re-export** | When checked, already-published posts are re-exported automatically a few seconds after being updated, same background behavior as automatic export. Off by default. | — |
| **Bulk export method** | When checked (default), bulk export writes all selected posts to GitHub in a single commit and push instead of one commit per post — much faster and far less likely to hit rate limits. Uncheck to go back to a separate commit per post. | — |
| **Uninstall** | When checked (default), deleting the plugin removes its settings and per-post export history from the database. Uncheck to keep that data if you plan to reinstall later. | — |

Each field has a help text under the input describing the expected format. The **"Save Changes"** button stays disabled until you run **"Test connection"** successfully against the values currently in the form (read-only check, it never writes to the repository); editing the token, repository or branch again re-locks Save until you test once more.

Until the PAT and repository are configured, the plugin blocks every export attempt (both single-post and bulk) and shows an error message instead of calling GitHub. If automatic export is enabled but the connection isn't configured (or fails) when a post is published, the export is simply skipped — no error is shown to the author; the post stays "Never exported" and can be exported manually afterward.

### Where to create the Personal Access Token

On GitHub: **Settings → Developer settings → Personal access tokens** (also reachable via the direct link shown under the token field on the settings page). A classic token only needs the `repo` scope; for a fine-grained token, make sure "Contents" access is set to "Read and write" for the selected repository.

## Exporting a single post

1. Open an already-published post for editing.
2. In the sidebar you'll find the **"Export to GitHub"** box, showing the current status:
   - **Never exported** — the post has never been sent to the repository.
   - **Exported on [date]** — the last successful export, with date and time.
   - **Modified since last export** — the post was updated after the last export; re-exporting is recommended.
3. Click **"Export to GitHub"**. The operation happens via AJAX without reloading the page; the status updates once it completes.

If the post was already exported before, the plugin updates the same file on GitHub (same path, same commit history) instead of creating a new one.

## Exporting multiple posts at once (bulk export)

1. Go to **Posts → Export to GitHub**.
2. You'll see a paginated list of published posts, with Categories and Tags columns (each value links back into the filters) and a status column identical to the one in the single-post box.
3. Use the filters above the table to narrow down the list: status, search, category, tag and publish month all sit on one row, plus a per-page dropdown (10/25/50/100, remembered for next time). Filtering reloads the list, same as WordPress' own post list, with pagination shown both above and below the table.
4. Select the posts you want to export, or check the header checkbox to select everything on the current page. If more posts match your filters than are on screen, a **"Select all N items matching this filter"** link appears to extend the selection across every page; **"Clear selection"** resets it.
5. Click **"Export selected"**. With the default **"Bulk export method"** setting, the plugin first converts every selected post to Markdown locally (no calls to GitHub yet, shown post by post in the progress bar and log), then writes all of them to the repository in **one single commit and push** — a single "Bulk export: N posts" commit listing every included post, instead of one commit per post. This is much faster and rarely gets anywhere near GitHub's rate limits. If you'd rather keep a dedicated commit per post, uncheck that setting in Settings — export then falls back to one commit per post, same as before, with automatic wait-and-retry if GitHub's rate limit is hit mid-run.
6. A **"Stop"** control appears above the log while an export is running: it lets the request currently in flight finish, then skips the rest instead of aborting mid-write. With the single-commit method, anything already prepared before you stopped is still included in that one commit.

## Posts exported but no longer published

The stat tiles above the table include **"Exported, no longer published"**: posts that were exported to GitHub at some point but are now a draft, pending, private, scheduled, or trashed in WordPress. Selecting that tile switches to a read-only list with a link to the post and a link to the corresponding file on GitHub. The plugin never deletes files from the repository automatically — if you want the file gone, do it manually on GitHub.

## WP-CLI

If [WP-CLI](https://wp-cli.org/) is available, the plugin registers two commands:

```
wp potogh export <post_id>
wp potogh bulk-export [--status=<never_exported|exported|modified_since_export>] [--dry-run]
```

`bulk-export` without `--status` considers every published post; `--dry-run` lists what would be exported without exporting it.

## Where files end up on GitHub

Each post is saved as:

```
{base_folder}/{publish_year}/{post-slug}.md
```

For example, with base folder `posts`, a post published in 2026 with slug `how-to-configure-wordpress` becomes `posts/2026/how-to-configure-wordpress.md`. The year is the post's **publish** year, not the last modification date.

Each file starts with a YAML front matter followed by the content converted to Markdown:

```yaml
---
title: "Post title"
slug: how-to-configure-wordpress
date: 2026-07-08T10:30:00+02:00
modified: 2026-07-08T11:00:00+02:00
wp_id: 1234
categories: ["WordPress", "Tutorial"]
tags: ["plugin", "github"]
permalink: https://yoursite.com/how-to-configure-wordpress/
---

# Post title

Post content in Markdown...
```

Images in the content remain as absolute links to your site (`![alt](https://yoursite.com/wp-content/uploads/...)`): they are not uploaded or duplicated to GitHub.

The commit message generated for each export follows the format `Export post: {title} (#{id})`, so you can easily follow the export history in the repository.

## Troubleshooting

- **"Configure the PAT and repository in the plugin settings first"**: the token or repository aren't set yet (or the format entered is invalid). Check the plugin settings, using the "Test connection" button to pinpoint the issue if needed.
- **Authentication error / repository not found**: verify the PAT is valid, not expired, and has the correct permissions on the specified repository. The "Test connection" button distinguishes between the two cases.
- **Conflict (409) during export**: this means the file on GitHub was modified or renamed directly in the repository after the last export from WordPress, and the reference saved by the plugin no longer matches the file's real state. Check the repository contents before re-exporting; if needed, manually verify the file on GitHub.
- **GitHub rate limit**: bulk export waits and retries automatically once when GitHub reports a rate limit. If a post still fails afterward, wait a few minutes and export it again.
- **A draft post doesn't show the export button, or export fails with "Only published posts can be exported"**: by design, only posts with "published" status can be exported.

## Known limitations (v1)

- Only the `post` post type is supported (not pages or other custom content types).
- No automatic repository creation: it must already exist before configuring the plugin.
- No image upload or management: they remain absolute links to the source site.
- No automatic deletion of files from GitHub when a post is unpublished or trashed (see "Posts exported but no longer published" above).
