<?php
/**
 * apCampaign admin bridge.
 *
 * @package apCampaign
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('APCampaign_Admin')) {
    final class APCampaign_Admin
    {
        public static function boot(): void
        {
            add_action('admin_menu', [self::class, 'register_menu']);
        }

        public static function register_menu(): void
        {
            $capability = 'manage_options';

            add_menu_page(
                __('apCampaign', 'apcampaign'),
                __('apCampaign', 'apcampaign'),
                $capability,
                'apcampaign',
                [self::class, 'render_overview'],
                'dashicons-email-alt2',
                56
            );

            add_submenu_page('apcampaign', __('Overview', 'apcampaign'), __('Overview', 'apcampaign'), $capability, 'apcampaign', [self::class, 'render_overview']);
            add_submenu_page('apcampaign', __('Campaign Studio', 'apcampaign'), __('Campaign Studio', 'apcampaign'), 'edit_posts', 'apcampaign-studio', [self::class, 'open_studio']);
        }

        public static function render_overview(): void
        {
            self::guard();
            $component = function_exists('apcampaign_component') ? apcampaign_component() : [];
            $counts = class_exists('APCampaign_Lifecycle') ? APCampaign_Lifecycle::counts() : [];

            echo '<div class="wrap"><h1>' . esc_html__('apCampaign', 'apcampaign') . '</h1>';
            echo '<p>' . esc_html__('Create, test, schedule, track, and improve email campaigns directly from WordPress.', 'apcampaign') . '</p>';
            echo '<p><strong>' . esc_html__('Current status:', 'apcampaign') . '</strong> ' . esc_html((string) ($component['status'] ?? 'unknown')) . '</p>';
            echo '<p><a class="button button-primary" href="' . esc_url(APCampaign_App::url()) . '">' . esc_html__('Open Campaign Studio', 'apcampaign') . '</a></p>';
            echo '<h2>' . esc_html__('Stored Records', 'apcampaign') . '</h2>';
            echo '<table class="widefat striped" style="max-width: 980px;"><thead><tr><th>' . esc_html__('Surface', 'apcampaign') . '</th><th>' . esc_html__('Rows', 'apcampaign') . '</th></tr></thead><tbody>';
            if (!$counts) {
                echo '<tr><td colspan="2">' . esc_html__('No schema counts available yet.', 'apcampaign') . '</td></tr>';
            }
            foreach ($counts as $key => $count) {
                echo '<tr><td>' . esc_html(self::humanize((string) $key)) . '</td><td>' . esc_html((string) $count) . '</td></tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        }

        public static function open_studio(): void
        {
            wp_safe_redirect(APCampaign_App::url());
            exit;
        }

        public static function render_services(): void
        {
            self::guard();
            $domain = class_exists('APCampaign_Contract') ? APCampaign_Contract::domain() : [];

            echo '<div class="wrap"><h1>' . esc_html__('apCampaign Services', 'apcampaign') . '</h1>';
            echo '<p>' . esc_html__('This screen confirms the standalone campaign services loaded successfully.', 'apcampaign') . '</p>';
            echo '<table class="widefat striped" style="max-width: 1100px;"><tbody>';
            self::detail_row(__('Composer modes', 'apcampaign'), implode(', ', (array) ($domain['composer_modes'] ?? [])));
            self::detail_row(__('Lifecycle states', 'apcampaign'), implode(', ', (array) ($domain['campaign_lifecycle'] ?? [])));
            self::detail_row(__('Renderer loaded', 'apcampaign'), class_exists('APCampaign_Renderer') ? 'yes' : 'no');
            self::detail_row(__('Spam scorer loaded', 'apcampaign'), class_exists('APCampaign_Spam_Score') ? 'yes' : 'no');
            self::detail_row(__('Intelligence loaded', 'apcampaign'), class_exists('APCampaign_Intelligence') ? 'yes' : 'no');
            self::detail_row(__('Preflight loaded', 'apcampaign'), class_exists('APCampaign_Preflight') ? 'yes' : 'no');
            self::detail_row(__('Lifecycle loaded', 'apcampaign'), class_exists('APCampaign_Lifecycle') ? 'yes' : 'no');
            echo '</tbody></table>';
            echo '</div>';
        }

        private static function detail_row(string $label, string $value): void
        {
            echo '<tr><th scope="row" style="width: 220px;">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
        }

        private static function humanize(string $value): string
        {
            return ucwords(str_replace(['_', '-'], ' ', $value));
        }

        private static function guard(): void
        {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to access apCampaign.', 'apcampaign'));
            }
        }
    }
}
