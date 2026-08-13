=== Dragwyb AI Agent Workflows ===
Contributors: dragwyb
Tags: automation, workflow, ai, webhooks, woocommerce
Requires at least: 5.8
Tested up to: 7.0.2
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build visual automation workflows in WordPress with webhooks, form triggers, WooCommerce events, and AI agent actions.

== Description ==

Dragwyb AI Agent Workflows lets you design and run multi-step automations from your WordPress admin.

Use the visual builder to connect triggers (forms, WooCommerce events, inbound webhooks, chat messages) to actions (email, HTTP requests, Google Sheets, messaging services, and AI agents). Workflows can be activated, tested, and reviewed with run history from the admin screens.

### Features

* Visual workflow builder with a drag-and-drop canvas
* AI agent nodes that can call configured LLM providers (OpenAI, Anthropic, Google Gemini, OpenRouter, Groq, DeepSeek)
* Inbound webhooks with optional signing secrets and IP allow lists
* WooCommerce event triggers
* Form triggers for Contact Form 7, WPForms, and Elementor forms
* Action nodes for email, HTTP requests, Slack, Telegram, WhatsApp Cloud, and Google Sheets
* Connections manager for API keys and OAuth credentials
* Run history and per-node execution logs

### Getting started

1. Activate the plugin.
2. Open **Automation → Workflows** and create a workflow.
3. Add a trigger, then add one or more actions on the canvas.
4. Configure credentials under **Automation → Connections** when an action needs them.
5. Save the workflow and set it to Active.

== External services ==

This plugin sends data to third-party services only when a site administrator configures a connection and places the matching node in an active workflow.

API keys and OAuth tokens are stored in the WordPress database and are transmitted only as authorization material to the services you configure.

### AI Providers
When an AI node runs, the plugin sends prompts, conversation context, tool schemas, and model parameters to the selected provider.
* OpenAI: [Terms](https://openai.com/policies/terms-of-use) | [Privacy](https://openai.com/policies/privacy-policy)
* Google Gemini: [Terms](https://ai.google.dev/gemini-api/terms) | [Privacy](https://policies.google.com/privacy)
* Anthropic Claude: [Terms](https://www.anthropic.com/legal/commercial-terms) | [Privacy](https://www.anthropic.com/legal/privacy)
* OpenRouter: [Terms](https://openrouter.ai/terms) | [Privacy](https://openrouter.ai/privacy)
* Groq: [Terms](https://console.groq.com/docs/legal/services-agreement) | [Privacy](https://groq.com/privacy-policy)
* DeepSeek: [Terms](https://cdn.deepseek.com/policies/en-US/deepseek-open-platform-terms-of-service.html) | [Privacy](https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html)

### Google Workspace & Sheets
Google Sheets actions use Google OAuth2, Sheets, and Drive APIs.
* Google APIs: [Terms](https://developers.google.com/terms) | [Privacy](https://policies.google.com/privacy)

### Messaging
Notification nodes send message text and required identifiers to the selected provider.
* Slack: [Terms](https://slack.com/terms-of-service) | [Privacy](https://slack.com/privacy-policy)
* Telegram: [Terms](https://telegram.org/tos) | [Privacy](https://telegram.org/privacy)
* WhatsApp Cloud API (Meta): [Terms](https://www.whatsapp.com/legal/business-terms) | [Privacy](https://www.facebook.com/privacy/policy)

### Admin UI fonts
The builder may load the Inter font from Google Fonts.
* Google Fonts: [FAQ](https://developers.google.com/fonts/faq) | [Privacy](https://policies.google.com/privacy)

### Generic HTTP requests
The HTTP Request action sends the method, headers, and body you configure to a URL you choose. Review that destination’s terms and privacy policy before use.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/dragwyb-ai-agent-workflows`, or install the ZIP from Plugins → Add New.
2. Activate **Dragwyb AI Agent Workflows**.
3. Open **Automation → Workflows** to create your first workflow.

== Frequently Asked Questions ==

= Does uninstall remove my data? =

No by default. Data removal on uninstall is opt-in in plugin settings.

= What versions are required? =

PHP 7.4+ and WordPress 5.8+.

= Can workflows create WordPress users? =

User create/update/delete actions only run when the current request has the matching WordPress capability (`create_users`, `edit_users`, or `delete_users`). Unauthenticated public triggers cannot create privileged users.

== Changelog ==

= 0.1.0=
* Update plugin name, plugin slug & prefix.
* Added default password required for create user and add currnet user can promoter user capability check.
* Use wp_iniline_script

= 0.0.0 =
* Initial Release