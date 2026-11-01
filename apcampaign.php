<?php
/**
 * Plugin Name: apCampaign
 * Plugin URI: https://artistprowp.com/apcampaign/
 * Description: Create, schedule, and track newsletters with visual and HTML editors, audience management, preflight checks, and unsubscribe support.
 * Version: 1.0.16
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: artistpro
 * Author URI: https://artistprowp.com/
 * Text Domain: apcampaign
  * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('APCAMPAIGN_VERSION', '1.0.16');
define('APCAMPAIGN_PLUGIN_FILE', __FILE__);
define('APCAMPAIGN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('APCAMPAIGN_PLUGIN_URL', plugin_dir_url(__FILE__));


require_once APCAMPAIGN_PLUGIN_DIR . 'includes/class-apcampaign-contract.php';
require_once APCAMPAIGN_PLUGIN_DIR . 'includes/class-apcampaign-schema.php';
require_once APCAMPAIGN_PLUGIN_DIR . 'includes/class-apcampaign-renderer.php';
require_once APCAMPAIGN_PLUGIN_DIR . 'includes/class-apcampaign-spam-score.php';
require_once APCAMPAIGN_PLUGIN_DIR . 'includes/class-apcampaign-preflight.php';
require_once APCAMPAIGN_PLUGIN_DIR . 'includes/class-apcampaign-lifecycle.php';
require_once APCAMPAIGN_PLUGIN_DIR . 'includes/class-apcampaign-delivery.php';
require_once APCAMPAIGN_PLUGIN_DIR . 'includes/class-apcampaign-admin.php';
require_once APCAMPAIGN_PLUGIN_DIR . 'includes/class-apcampaign-app.php';
require_once APCAMPAIGN_PLUGIN_DIR . 'includes/class-apcampaign-plugin.php';
require_once APCAMPAIGN_PLUGIN_DIR . 'includes/class-apcampaign-cli.php';

register_activation_hook(__FILE__, static function (bool $network_wide = false): void {
    if (is_multisite() && $network_wide) {
        foreach (get_sites(['fields' => 'ids']) as $site_id) {
            switch_to_blog((int) $site_id);
            APCampaign_Schema::install();
            restore_current_blog();
        }
    } else {
        APCampaign_Schema::install();
    }
    APCampaign_App::register_routes();
    flush_rewrite_rules(false);
});

register_deactivation_hook(__FILE__, static function (): void {
    flush_rewrite_rules(false);
});

add_action('plugins_loaded', static function (): void {
    if ((string) get_option('apcampaign_schema_version', '') !== APCAMPAIGN_VERSION) {
        APCampaign_Schema::install();
    }
    APCampaign_Plugin::instance()->boot();
});

if (!function_exists('apcampaign_component')) {
    /**
     * @return array<string,mixed>
     */
    function apcampaign_component(): array
    {
        return APCampaign_Contract::component();
    }
}

if (!function_exists('apcampaign_score_campaign')) {
    /**
     * @param array<string,mixed> $campaign
     * @return array<string,mixed>
     */
    function apcampaign_score_campaign(array $campaign): array
    {
        return class_exists('APCampaign_Intelligence') ? APCampaign_Intelligence::score_campaign($campaign) : ['available' => false];
    }
}

if (!function_exists('apcampaign_spam_score')) {
    /**
     * @return array<string,mixed>
     */
    function apcampaign_spam_score(string $subject, string $html = '', string $preheader = ''): array
    {
        return APCampaign_Spam_Score::score($subject, $html, $preheader);
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('apcampaign', 'APCampaign_CLI');
}
