=== Workflow Automate ===
Contributors: 
Tags: automation, workflow, integrations
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build and run visual, multi-step automation workflows in WordPress.

== Description ==

Workflow Automate lets you connect WordPress events to actions using a visual, drag-and-drop workflow builder. This early release contains only the plugin's core bootstrap (activation, deactivation, and safe uninstall); the workflow builder, execution engine, and integrations are being added incrementally — see the plugin's `docs/internal/roadmap.md` for development-only progress tracking (not included in the distributed plugin).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/workflow-automate` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the "Plugins" screen in WordPress.

== Frequently Asked Questions ==

= Does this plugin delete my data when I uninstall it? =

No, not by default. Data removal on uninstall is opt-in via a setting; if you never enable it, your data is left in place when the plugin is deleted.

= What PHP and WordPress versions are required? =

PHP 7.4 or higher and WordPress 5.8 or higher.

== Changelog ==

= 0.1.0 =
* Initial scaffolding: plugin bootstrap, activation/deactivation, opt-in uninstall data removal.
