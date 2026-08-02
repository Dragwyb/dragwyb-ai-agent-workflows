=== AI Agent & Workflow Automation Builder ===
Contributors: 
Tags: automation, workflow, ai, webhooks, woocommerce
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build automated workflows, connect webhooks, and create autonomous AI agents. Integrate OpenAI, Google Sheets, and WooCommerce inside WordPress.

== Description ==

**AI Agent & Workflow Automation Builder** is your native WordPress solution for creating complex **automated workflows**, routing **webhooks**, and deploying autonomous **AI agents**.

Whether you want to trigger actions from a **WooCommerce** order, process data from **Elementor** forms, or connect your site to external APIs like **Google Sheets**, this drag-and-drop **workflow automation** tool makes it possible directly inside your WordPress dashboard.

### 🚀 Top Features & Integrations

*   **Visual Workflow Builder:** Drag-and-drop canvas to design custom automations and logic flows.
*   **AI Agent Orchestration:** Connect top-tier Large Language Models (LLMs) including **OpenAI**, Anthropic Claude, **Google Gemini**, DeepSeek, Groq, and OpenRouter.
*   **Inbound & Outbound Webhooks:** Effortlessly receive data from external services or push WordPress data to any API.
*   **WooCommerce Automation:** Trigger workflows based on store events (e.g., new orders, updated catalogs).
*   **Form Integrations:** Seamlessly capture submissions from **Contact Form 7**, **WPForms**, and Elementor forms.
*   **External Service Actions:** Send data to **Google Sheets**, post messages to Slack and Telegram, or send WhatsApp notifications.

*(Note: This early release [v0.0.0] contains the core plugin bootstrap—activation, deactivation, and safe uninstall. The workflow execution engine, visual builder, and advanced integrations are currently in active development. Check `docs/internal/roadmap.md` in our repository for updates.)*

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ai-agent-workflow-automation` directory, or search for "**AI Agent & Workflow Automation Builder**" in the WordPress plugins screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Navigate to the new Automation menu to start building your first **workflow**.

== Frequently Asked Questions ==

= Does this workflow builder delete my data when I uninstall it? =

No, not by default. Data removal on uninstall is opt-in via a setting. If you never enable it, your **workflow** configurations, **webhook** logs, and **AI agent** settings are left in place when the plugin is deleted.

= What PHP and WordPress versions are required? =

PHP 7.4 or higher and WordPress 5.8 or higher.

= Can I use this plugin to connect WooCommerce to Google Sheets? =

Yes, our **automation builder** includes specific triggers for **WooCommerce** and actions for **Google Sheets**, allowing you to automatically log new orders or customer data without needing external services like Zapier.

= Which AI providers are supported for building AI Agents? =

The plugin utilizes and uses WordPress connectors AI to provide a unified AI client that supports major providers. This allows you to easily orchestrate **OpenAI**, **Google Gemini**, Claude, DeepSeek, and Groq directly within your **automated workflows**.

== Changelog ==

= 0.0.0 =
* Initial Release