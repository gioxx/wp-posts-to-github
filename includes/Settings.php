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
        $ownerRepo = trim($input['owner_repo'] ?? '');
        $branch = trim($input['branch'] ?? '') ?: $defaults['branch'];
        $baseFolder = trim($input['base_folder'] ?? '', "/ \t\n\r\0\x0B") ?: $defaults['base_folder'];

        if ($ownerRepo !== '' && !preg_match('/^[\w.-]+\/[\w.-]+$/', $ownerRepo)) {
            $ownerRepo = '';
        }

        return [
            'token' => $token,
            'owner_repo' => $ownerRepo,
            'branch' => $branch,
            'base_folder' => $baseFolder,
        ];
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

    public function registerSetting(): void
    {
        register_setting('potogh_settings_group', self::OPTION_NAME, [
            'sanitize_callback' => [self::class, 'sanitize'],
            'default' => self::defaults(),
        ]);
    }

    public function renderPage(): void
    {
        $settings = self::get();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Post to GitHub MD', 'post-to-github-md'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('potogh_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="potogh_token"><?php esc_html_e('GitHub Personal Access Token', 'post-to-github-md'); ?></label></th>
                        <td><input type="password" id="potogh_token" name="<?php echo esc_attr(self::OPTION_NAME); ?>[token]" value="<?php echo esc_attr($settings['token']); ?>" class="regular-text" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="potogh_owner_repo"><?php esc_html_e('Owner/repo', 'post-to-github-md'); ?></label></th>
                        <td><input type="text" id="potogh_owner_repo" name="<?php echo esc_attr(self::OPTION_NAME); ?>[owner_repo]" value="<?php echo esc_attr($settings['owner_repo']); ?>" class="regular-text" placeholder="owner/repo"></td>
                    </tr>
                    <tr>
                        <th><label for="potogh_branch"><?php esc_html_e('Branch', 'post-to-github-md'); ?></label></th>
                        <td><input type="text" id="potogh_branch" name="<?php echo esc_attr(self::OPTION_NAME); ?>[branch]" value="<?php echo esc_attr($settings['branch']); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="potogh_base_folder"><?php esc_html_e('Base folder', 'post-to-github-md'); ?></label></th>
                        <td><input type="text" id="potogh_base_folder" name="<?php echo esc_attr(self::OPTION_NAME); ?>[base_folder]" value="<?php echo esc_attr($settings['base_folder']); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
