=== apCampaign ===
Contributors: artistpromusic
Tags: newsletter, email marketing, campaigns
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.12
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create newsletters, manage audiences, and send campaigns from WordPress.

== Description ==

apCampaign helps you create newsletters with a visual or HTML editor, manage contact lists, schedule campaigns, and process unsubscribe requests. Review the preflight checks and send a test before sending to your audience.

The free plugin sends through WordPress mail. Delivery depends on your site's mail configuration and hosting limits. A separate mail provider or SMTP plugin can be used; its terms and charges are set by that provider. Optional paid features are distributed separately by artistpro.

== Installation ==

1. Upload the apCampaign plugin folder and activate it in WordPress.
2. Configure your sender details and a physical mailing address in the campaign settings.
3. Make sure WordPress can send email, or configure a mail provider separately.
4. Create a contact list and a campaign, run the preflight checks, and send a test before sending to the list.

== Frequently Asked Questions ==

= How are newsletters sent? =

The free plugin uses WordPress mail. Your web host's email service or an independently configured mail provider handles delivery. Confirm that the sender address and mail service are configured for your site.

= Why has a scheduled campaign not sent? =

Scheduled processing uses WordPress scheduled events. Check that your site's scheduled events are running, that its mail service is configured, and that the campaign's preflight requirements are met.

= What information does apCampaign store? =

The plugin stores campaign content, contact-list information, delivery records, and unsubscribe or suppression records in your WordPress database. Review your site's privacy information and handle personal-data requests using your site's established procedures.

= What happens when I remove the plugin? =

Campaign and audience data is retained when the plugin is uninstalled. Make a backup before removing retained data manually.

== Changelog ==

= 1.0.12 =
* Improve scheduled delivery safety and reporting when storage is unavailable.

= 1.0.11 =
* Improve scheduled-send timing and report scheduling failures.

= 1.0.10 =
* Maintenance release.

= 1.0.9 =
* Saves campaign details and selected content together for existing drafts.

= 1.0.8 =
* Reports campaign save failures instead of creating incomplete records.
* Rejects malformed editor requests safely.

= 1.0.7 =
* Keeps incomplete send preparations from running and allows administrators to cancel stalled preparations.
* Stops queueing when recipient suppression cannot be verified.

= 1.0.6 =
* Preserves previously saved content if a campaign save fails.

= 1.0.5 =
* Reports an error when an unsubscribe cannot be saved.

= 1.0.4 =
* Shows preflight warnings and identifies site addresses that may make subscriber links unreachable.

= 1.0.3 =
* Isolates newsletter previews from scripts in campaign content.

= 1.0.2 =
* Provides a free newsletter core with separate optional paid features.
* Prevents sends when recipient suppression cannot be verified and reports the failure to administrators.
