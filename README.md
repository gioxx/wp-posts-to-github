# Post to GitHub Markdown

*[Leggi questo in italiano](README.it.md)*

A WordPress plugin that exports your published posts as Markdown files (with YAML front matter) to an existing private GitHub repository. The goal is to build, over time, a text corpus of your writing that's useful for training or guiding a consistent writing style in tools like Claude.

The plugin does not create the repository, does not upload binary images, and only works on `post` content with a "published" status.

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- An existing private GitHub repository
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
| **Repository** | The target GitHub repository: enter either `owner/repo` or the full URL (`https://github.com/owner/repo`). The repository must already exist. | `yourname/your-private-repo` or `https://github.com/yourname/your-private-repo` |
| **Branch** | The branch files are committed to. | `main` |
| **Base folder** | The top-level repository folder exports are saved into. | `posts` |

Each field has a help text under the input describing the expected format. After saving (or even before, on the values currently in the form), use the **"Test connection"** button to verify the token is valid and the repository/branch are reachable: the check is read-only and never writes to the repository.

Until the PAT and repository are configured, the plugin blocks every export attempt (both single-post and bulk) and shows an error message instead of calling GitHub.

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

1. Go to **Tools → Export to GitHub MD**.
2. You'll see a list of all published posts, with a status column identical to the one in the single-post box.
3. Select the posts you want to export (there's also a "select all" checkbox) and click **"Export selected"**.
4. The plugin exports one post at a time (to avoid timeouts on long lists) and updates each row's status as it goes. At the end, a summary shows the number of successfully exported posts and any errors with their reason.

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
- **GitHub rate limit**: if you export many posts in bulk and get a rate limit error, wait a few minutes and try again.
- **A draft post doesn't show the export button, or export fails with "Only published posts can be exported"**: by design, only posts with "published" status can be exported.

## Known limitations (v1)

- Only the `post` post type is supported (not pages or other custom content types).
- No automatic sync on publish: export must always be triggered manually (single post or bulk).
- No automatic repository creation: it must already exist before configuring the plugin.
- No image upload or management: they remain absolute links to the source site.
