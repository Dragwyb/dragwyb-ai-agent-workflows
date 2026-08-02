=== Dragwyb AgentFlow: Visual workflow builder and automation ===
Contributors: dragwyb
Tags: automation, workflow, ai, webhooks, woocommerce
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build automated workflows, connect webhooks, and create autonomous AI agents. Integrate OpenAI, Google Sheets, and WooCommerce inside WordPress.

== Description ==

Dragwyb AgentFlow is your native WordPress solution for creating complex automated workflows, routing webhooks, and deploying autonomous AI agents.

Whether you want to trigger actions from a WooCommerce order, process data from Elementor forms, or connect your site to external APIs like Google Sheets, this drag-and-drop workflow automation tool makes it possible directly inside your WordPress dashboard.

### Top Features & Integrations

*   Visual Workflow Builder: Drag-and-drop canvas to design custom automations and logic flows.
*   AI Agent Orchestration: Connect top-tier Large Language Models (LLMs) including OpenAI, Anthropic Claude, Google Gemini, DeepSeek, Groq, and OpenRouter.
*   Inbound & Outbound Webhooks: Effortlessly receive data from external services or push WordPress data to any API.
*   WooCommerce Automation: Trigger workflows based on store events (e.g., new orders, updated catalogs).
*   Form Integrations: Seamlessly capture submissions from Contact Form 7, WPForms, and Elementor forms.
*   External Service Actions: Send data to Google Sheets, post messages to Slack and Telegram, or send WhatsApp notifications.

*(Note: This early release [v0.0.0] contains the core plugin bootstrap—activation, deactivation, and safe uninstall. The workflow execution engine, visual builder, and advanced integrations are currently in active development. Check docs/internal/roadmap.md in our repository for updates.)*

== External services ==

This plugin acts as an automation builder and relies on various third-party external services to execute workflows, process AI commands, and deliver messages. Data is only sent to these services if the site administrator actively configures a connection and uses the corresponding node in a workflow.

API keys and OAuth tokens are stored securely in your WordPress database and are only transmitted as authorization headers to the services you explicitly configure.

### AI Providers
When an AI node is used, the plugin sends your defined prompts, conversation context, tool/function schemas, and model parameters to the selected provider.
*   OpenAI: [Terms of Service](https://openai.com/policies/terms-of-use) | [Privacy Policy](https://openai.com/policies/privacy-policy)
*   Google Gemini: [Terms of Service](https://ai.google.dev/gemini-api/terms) | [Usage Policies](https://ai.google.dev/gemini-api/docs/usage-policies)
*   Anthropic Claude: [Commercial Terms](https://www.anthropic.com/legal/commercial-terms) | [Privacy Policy](https://www.anthropic.com/legal/privacy)
*   OpenRouter: Routes prompts to underlying models. [Terms of Service](https://openrouter.ai/terms) | [Privacy Policy](https://openrouter.ai/privacy)
*   Groq: [Services Agreement](https://console.groq.com/docs/legal/services-agreement) | [Privacy Policy](https://groq.com/privacy-policy)
*   DeepSeek: [Terms of Service](https://cdn.deepseek.com/policies/en-US/deepseek-open-platform-terms-of-service.html) | [Privacy Policy](https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html)

### Google Workspace & Sheets
When using Google Sheets actions, the plugin connects to Google's OAuth2, Sheets, and Drive APIs. Spreadsheet values, sheet metadata, and authorized OAuth tokens are transmitted.
*   Google APIs: [Terms of Service](https://developers.google.com/terms) | [Privacy Policy](https://policies.google.com/privacy)

### Messaging & Notifications
When using notification nodes, the plugin sends message text and required identifiers (e.g., Slack webhook URL, Telegram chat ID, WhatsApp phone number ID).
*   Slack: [Terms of Service](https://slack.com/terms-of-service) | [Privacy Policy](https://slack.com/privacy-policy)
*   Telegram: [Terms of Service](https://telegram.org/tos) | [Privacy Policy](https://telegram.org/privacy)
*   WhatsApp Cloud API (Meta): [Business Terms](https://www.whatsapp.com/legal/business-terms) | [Privacy Policy](https://www.facebook.com/privacy/policy)

### Admin UI Assets
The visual workflow builder interface loads the "Outfit" font from Google Fonts to render the canvas typography.
*   Google Fonts: [Terms & FAQ](https://developers.google.com/fonts/faq) | [Privacy Policy](https://policies.google.com/privacy)

### Generic HTTP Requests
If you use the HTTP Request action node, the plugin will send the headers, method, and body data you define to an external URL of your choosing. It is the site administrator's responsibility to review the terms and privacy policies of any third-party endpoints called via this node.

== Source Code & Development ==

Dragwyb AgentFlow is an open-source project. You can view the full source code, report issues, and submit pull requests on GitHub.

*   GitHub Repository: [https://github.com/Dragwyb/dragwyb-agentflow](https://github.com/Dragwyb/dragwyb-agentflow)

**Build Files & UI Source**
The React-based visual workflow builder is bundled within the plugin. 
*   The compiled build files are located in: `assets/builder/`
*   The uncompiled source files for the visual builder can be found in: `assets/builder/src/`

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/dragwyb-agentflow` directory, or search for "Dragwyb AgentFlow" in the WordPress plugins screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Navigate to the new Automation menu to start building your first workflow.

== Frequently Asked Questions ==

= Does this workflow builder delete my data when I uninstall it? =

No, not by default. Data removal on uninstall is opt-in via a setting. If you never enable it, your workflow configurations, webhook logs, and AI agent settings are left in place when the plugin is deleted.

= What PHP and WordPress versions are required? =

PHP 7.4 or higher and WordPress 5.8 or higher.

= Can I use this plugin to connect WooCommerce to Google Sheets? =

Yes, our automation builder includes specific triggers for WooCommerce and actions for Google Sheets, allowing you to automatically log new orders or customer data without needing external services like Zapier.

= Which AI providers are supported for building AI Agents? =

The plugin provides a unified AI client that supports major providers. This allows you to easily orchestrate OpenAI, Google Gemini, Claude, DeepSeek, and Groq directly within your automated workflows.

== Changelog ==

= 0.0.0 =
* Initial Release