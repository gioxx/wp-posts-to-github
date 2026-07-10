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
        ];
    }

    public static function sanitize(array $input): array
    {
        $defaults = self::defaults();

        $token = trim($input['token'] ?? '');
        $ownerRepo = self::extractOwnerRepo(trim($input['owner_repo'] ?? ''));
        $branch = trim($input['branch'] ?? '') ?: $defaults['branch'];
        $baseFolder = trim($input['base_folder'] ?? '', "/ \t\n\r\0\x0B") ?: $defaults['base_folder'];

        return [
            'token' => $token,
            'owner_repo' => $ownerRepo,
            'branch' => $branch,
            'base_folder' => $baseFolder,
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
            __('Post to GitHub MD', 'post-to-github-md'),
            __('Post to GitHub MD', 'post-to-github-md'),
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
            <h1><?php echo esc_html__('Post to GitHub MD', 'post-to-github-md'); ?></h1>
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
                    /* translators: %s: link to the plugin repository */
                    esc_html__('Post to GitHub Markdown by Gioxx, source on %s.', 'post-to-github-md'),
                    '<a href="https://github.com/gioxx/wp-post-to-github-md" target="_blank" rel="noopener noreferrer">GitHub</a>'
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
                                    /* translators: %s: link to GitHub token settings page */
                                    esc_html__('A fine-grained or classic token with %s access to the target repository. Generate one at %s.', 'post-to-github-md'),
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
                            <input type="text" id="potogh_owner_repo" name="<?php echo esc_attr(self::OPTION_NAME); ?>[owner_repo]" value="<?php echo esc_attr($settings['owner_repo']); ?>" class="regular-text" placeholder="owner/repo">
                            <p class="description">
                                <?php esc_html_e('Enter either "owner/repo" or the full GitHub URL (e.g. https://github.com/owner/repo).', 'post-to-github-md'); ?>
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
                </table>
                <p class="submit potogh-submit-row">
                    <?php wp_nonce_field('potogh_test_connection', 'potogh_test_connection_nonce'); ?>
                    <button type="button" class="button" id="potogh-test-connection">
                        <?php esc_html_e('Test connection', 'post-to-github-md'); ?>
                    </button>
                    <button type="submit" class="button button-primary" id="potogh-save-settings" disabled>
                        <?php esc_html_e('Save Changes', 'post-to-github-md'); ?>
                    </button>
                    <span id="potogh-test-connection-result"></span>
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
