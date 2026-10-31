<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

final class APCampaign_Settings
{
    private const OPTION_KEY = 'apcampaign_settings_v1';

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        $settings = get_option(self::OPTION_KEY, []);
        return is_array($settings) ? $settings : [];
    }

    /**
     * @param array<string,mixed> $settings
     */
    public function save(array $settings): void
    {
        update_option(self::OPTION_KEY, $this->merge_defaults($settings), false);
    }

    /**
     * @return array<string,mixed>
     */
    public function defaults(): array
    {
        return [
            'shared_bridge' => [
                'prefer_apmailer' => true,
                'prefer_apai' => true,
                'prefer_apsms' => true,
                'prefer_apcrm_contacts' => true,
            ],
            'delivery_profiles' => [],
            'spam_profiles' => [],
            'ai_profiles' => [],
            'compliance' => [
                'default_unsubscribe_footer' => '',
                'default_physical_address' => '',
                'privacy_logging_mode' => 'metadata_only',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    private function merge_defaults(array $settings): array
    {
        return array_replace_recursive($this->defaults(), $settings);
    }
}

