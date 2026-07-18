<?php

namespace POTOGH;

class Settings
{
    public const OPTION_NAME = 'potogh_settings';

    public static function defaults(): array
    {
        return [
            'token' => '',
            'owner_repo' => '',
            'branch' => 'main',
            'base_folder' => 'posts',
            'auto_export' => false,
            'auto_reexport' => false,
            'cleanup_on_uninstall' => true,
            'batch_commit' => true,
        ];
    }

    public static function sanitize(array $input): array
    {
        $defaults = self::defaults();

        $token = trim($input['token'] ?? '');
        $ownerRepo = self::extractOwnerRepo(trim($input['owner_repo'] ?? ''));
        $branch = trim($input['branch'] ?? '') ?: $defaults['branch'];
        $baseFolder = trim($input['base_folder'] ?? '', "/ \t\n\r\0\x0B") ?: $defaults['base_folder'];
        $autoExport = !empty($input['auto_export']);
        $autoReexport = !empty($input['auto_reexport']);
        $cleanupOnUninstall = !empty($input['cleanup_on_uninstall']);
        $batchCommit = !empty($input['batch_commit']);

        return [
            'token' => $token,
            'owner_repo' => $ownerRepo,
            'branch' => $branch,
            'base_folder' => $baseFolder,
            'auto_export' => $autoExport,
            'auto_reexport' => $autoReexport,
            'cleanup_on_uninstall' => $cleanupOnUninstall,
            'batch_commit' => $batchCommit,
        ];
    }

    private static function extractOwnerRepo(string $input): string
    {
        if ($input === '') {
            return '';
        }

        if (preg_match('#^https?://(www\.)?github\.com/([\w.-]+)/([\w.-]+?)(\.git)?/?$#', $input, $matches)) {
            $input = $matches[2] . '/' . $matches[3];
        }

        return preg_match('/^[\w.-]+\/[\w.-]+$/', $input) ? $input : '';
    }

    public static function isConfigured(): bool
    {
        $settings = get_option(self::OPTION_NAME, self::defaults());

        return !empty($settings['token']) && !empty($settings['owner_repo']);
    }

    public static function get(): array
    {
        return wp_parse_args(get_option(self::OPTION_NAME, []), self::defaults());
    }

    public function registerPage(): void
    {
        add_options_page(
            __('Export posts to GitHub: Settings', 'post-to-github-md'),
            __('Posts to GitHub', 'post-to-github-md'),
            'manage_options',
            'potogh-settings',
            [$this, 'renderPage']
        );
    }

    public static function pageUrl(): string
    {
        return admin_url('options-general.php?page=potogh-settings');
    }

    public function registerSetting(): void
    {
        register_setting('potogh_settings_group', self::OPTION_NAME, [
            'sanitize_callback' => [self::class, 'sanitize'],
            'default' => self::defaults(),
            'autoload' => false,
        ]);
    }

    public function renderPage(): void
    {
        ?>
        <div class="wrap potogh-settings">
            <h1><?php echo esc_html__('Export posts to GitHub: Settings', 'post-to-github-md'); ?></h1>
            <p>
                <?php
                printf(
                    /* translators: %s: link to the Export posts screen under the Posts menu */
                    esc_html__('Looking to export posts? Head over to %s.', 'post-to-github-md'),
                    '<a href="' . esc_url(ExportTab::pageUrl()) . '">' . esc_html__('Posts &rsaquo; Export to GitHub', 'post-to-github-md') . '</a>'
                );
                ?>
            </p>
            <?php $this->renderSettingsForm(); ?>
            <?php $this->renderCredits(); ?>
        </div>
        <?php
    }

    private function renderCredits(): void
    {
        ?>
        <div class="potogh-credits">
            <p class="description">
                <?php
                printf(
                    /* translators: 1: link to the author's site, 2: link to the plugin repository */
                    esc_html__('Posts to GitHub by %1$s, source on %2$s.', 'post-to-github-md'),
                    '<a href="https://gioxx.org" target="_blank" rel="noopener noreferrer">Gioxx</a>',
                    '<a href="https://github.com/gioxx/wp-posts-to-github" target="_blank" rel="noopener noreferrer">GitHub</a>'
                );
                ?>
            </p>
            <p class="description potogh-trademark-notice">
                <?php esc_html_e('All trademarks mentioned are the property of their respective owners. Third-party trademarks, product names, trade names, corporate names and companies mentioned may be trademarks of their respective owners or registered trademarks of other companies and have been used for explanatory purposes only and for the benefit of the owner, without any intent to infringe existing copyright.', 'post-to-github-md'); ?>
            </p>
        </div>
        <?php
    }

    private function renderSettingsForm(): void
    {
        $settings = self::get();
        $repoLocked = $settings['owner_repo'] !== '';
        ?>
            <form method="post" action="options.php">
                <?php settings_fields('potogh_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="potogh_token"><?php esc_html_e('GitHub Personal Access Token', 'post-to-github-md'); ?></label></th>
                        <td>
                            <input type="password" id="potogh_token" name="<?php echo esc_attr(self::OPTION_NAME); ?>[token]" value="<?php echo esc_attr($settings['token']); ?>" class="regular-text" autocomplete="off">
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: 1: required token scope, 2: link to GitHub token settings page */
                                    esc_html__('A fine-grained or classic token with %1$s access to the target repository. Generate one at %2$s.', 'post-to-github-md'),
                                    '<code>repo</code>',
                                    '<a href="https://github.com/settings/tokens" target="_blank" rel="noopener noreferrer">github.com/settings/tokens</a>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="potogh_owner_repo"><?php esc_html_e('Repository', 'post-to-github-md'); ?></label></th>
                        <td>
                            <input type="text" id="potogh_owner_repo" name="<?php echo esc_attr(self::OPTION_NAME); ?>[owner_repo]" value="<?php echo esc_attr($settings['owner_repo']); ?>" class="regular-text" placeholder="owner/repo" <?php echo $repoLocked ? 'readonly' : ''; ?>>
                            <?php if ($repoLocked) : ?>
                                <button type="button" class="button" id="potogh-edit-repo"><?php esc_html_e('Change repository', 'post-to-github-md'); ?></button>
                            <?php endif; ?>
                            <button type="button" class="button" id="potogh-test-connection"><?php esc_html_e('Test connection', 'post-to-github-md'); ?></button>
                            <span id="potogh-test-connection-result"></span>
                            <p class="description">
                                <?php esc_html_e('Enter either "owner/repo" or the full GitHub URL (e.g. https://github.com/owner/repo).', 'post-to-github-md'); ?>
                                <?php if ($repoLocked) : ?>
                                    <?php esc_html_e('Locked to avoid accidental changes — click "Change repository" to edit it.', 'post-to-github-md'); ?>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="potogh_branch"><?php esc_html_e('Branch', 'post-to-github-md'); ?></label></th>
                        <td>
                            <input type="text" id="potogh_branch" name="<?php echo esc_attr(self::OPTION_NAME); ?>[branch]" value="<?php echo esc_attr($settings['branch']); ?>" class="regular-text">
                            <button type="button" class="button" id="potogh-detect-branch"><?php esc_html_e('Detect from repository', 'post-to-github-md'); ?></button>
                            <span id="potogh-detect-branch-result"></span>
                            <p class="description">
                                <?php esc_html_e('The branch posts will be committed to (e.g. main).', 'post-to-github-md'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="potogh_base_folder"><?php esc_html_e('Base folder', 'post-to-github-md'); ?></label></th>
                        <td>
                            <input type="text" id="potogh_base_folder" name="<?php echo esc_attr(self::OPTION_NAME); ?>[base_folder]" value="<?php echo esc_attr($settings['base_folder']); ?>" class="regular-text" placeholder="posts">
                            <p class="description">
                                <?php esc_html_e('Repository folder posts are exported into (e.g. posts). Left empty, it defaults to "posts".', 'post-to-github-md'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Automatic export', 'post-to-github-md'); ?></th>
                        <td>
                            <label for="potogh_auto_export">
                                <input type="checkbox" id="potogh_auto_export" name="<?php echo esc_attr(self::OPTION_NAME); ?>[auto_export]" value="1" <?php checked($settings['auto_export']); ?>>
                                <?php esc_html_e('Automatically export new posts to GitHub when published.', 'post-to-github-md'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Runs a few seconds after publishing via WP-Cron, without delaying the Publish button. Existing posts are not exported retroactively — use the Export posts page for those.', 'post-to-github-md'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Automatic re-export', 'post-to-github-md'); ?></th>
                        <td>
                            <label for="potogh_auto_reexport">
                                <input type="checkbox" id="potogh_auto_reexport" name="<?php echo esc_attr(self::OPTION_NAME); ?>[auto_reexport]" value="1" <?php checked($settings['auto_reexport']); ?>>
                                <?php esc_html_e('Automatically re-export already-published posts to GitHub when updated.', 'post-to-github-md'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Same background behavior as automatic export, triggered on every update to a published post instead of only its first publish.', 'post-to-github-md'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Bulk export method', 'post-to-github-md'); ?></th>
                        <td>
                            <label for="potogh_batch_commit">
                                <input type="checkbox" id="potogh_batch_commit" name="<?php echo esc_attr(self::OPTION_NAME); ?>[batch_commit]" value="1" <?php checked($settings['batch_commit']); ?>>
                                <?php esc_html_e('Combine bulk exports into a single commit (recommended).', 'post-to-github-md'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When checked, bulk export prepares all selected posts locally and writes them to GitHub in one commit and one push, instead of one commit per post. Much faster and far less likely to hit GitHub rate limits. Uncheck to go back to a separate commit per post, e.g. to keep one commit per post in your repository history.', 'post-to-github-md'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Uninstall', 'post-to-github-md'); ?></th>
                        <td>
                            <label for="potogh_cleanup_on_uninstall">
                                <input type="checkbox" id="potogh_cleanup_on_uninstall" name="<?php echo esc_attr(self::OPTION_NAME); ?>[cleanup_on_uninstall]" value="1" <?php checked($settings['cleanup_on_uninstall']); ?>>
                                <?php esc_html_e('Remove all plugin settings and export data when this plugin is deleted.', 'post-to-github-md'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Uncheck to keep your GitHub connection settings and per-post export history in the database if you delete the plugin (useful before reinstalling).', 'post-to-github-md'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit potogh-submit-row">
                    <?php wp_nonce_field('potogh_test_connection', 'potogh_test_connection_nonce'); ?>
                    <button type="submit" class="button button-primary" id="potogh-save-settings" <?php disabled(!self::isConfigured()); ?>>
                        <?php esc_html_e('Save Changes', 'post-to-github-md'); ?>
                    </button>
                </p>
            </form>
        <?php
    }

    public function handleAjaxTestConnection(): void
    {
        check_ajax_referer('potogh_test_connection', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'post-to-github-md')], 403);
        }

        $defaults = self::defaults();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below via self::sanitize().
        $sanitized = self::sanitize([
            'token' => wp_unslash($_POST['token'] ?? ''),
            'owner_repo' => wp_unslash($_POST['owner_repo'] ?? ''),
            'branch' => wp_unslash($_POST['branch'] ?? ''),
            'base_folder' => $defaults['base_folder'],
        ]);

        if ($sanitized['token'] === '' || $sanitized['owner_repo'] === '') {
            wp_send_json_error(['message' => __('Enter both a token and a valid repository before testing the connection.', 'post-to-github-md')], 400);
        }

        $client = new GithubClient($sanitized['token'], $sanitized['owner_repo'], $sanitized['branch']);
        $result = $client->testConnection();

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']], 400);
        }

        wp_send_json_success(['message' => $result['message']]);
    }

    public function handleAjaxDetectBranch(): void
    {
        check_ajax_referer('potogh_test_connection', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'post-to-github-md')], 403);
        }

        $defaults = self::defaults();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below via self::sanitize().
        $sanitized = self::sanitize([
            'token' => wp_unslash($_POST['token'] ?? ''),
            'owner_repo' => wp_unslash($_POST['owner_repo'] ?? ''),
            'branch' => $defaults['branch'],
            'base_folder' => $defaults['base_folder'],
        ]);

        if ($sanitized['token'] === '' || $sanitized['owner_repo'] === '') {
            wp_send_json_error(['message' => __('Enter both a token and a valid repository first.', 'post-to-github-md')], 400);
        }

        $client = new GithubClient($sanitized['token'], $sanitized['owner_repo'], $defaults['branch']);
        $result = $client->getDefaultBranch();

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']], 400);
        }

        wp_send_json_success(['branch' => $result['branch']]);
    }
}
