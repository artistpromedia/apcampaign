<?php
/**
 * apCampaign uninstall handler.
 *
 * Customer campaign and audience data is intentionally retained. Removing a
 * plugin must never silently erase marketing records. Site owners can remove
 * the retained tables manually after making a backup.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('apcampaign_license_cache');
delete_option('apcampaign_license_status');
delete_transient('apcampaign_license_update');

