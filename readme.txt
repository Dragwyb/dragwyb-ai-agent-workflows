=== AI Agent Workflows – Visual Automation & Webhooks by Dragwyb ===
Contributors: dragwyb
Tags: ai agent, automation, workflows, webhooks, openai
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build autonomous AI workflows and visual automations connecting OpenAI, Claude, Gemini, Webhooks, WooCommerce, and forms without code.

== Description ==

**AI Agent Workflows by Dragwyb** is a visual workflow automation and autonomous AI agent platform built directly inside WordPress. It enables you to orchestrate multi-step automated pipelines connecting leading AI models, webhooks, form builders, and external business tools—all from an intuitive node-based canvas.

Connect autonomous agents to real-world tasks. Trigger workflows from form submissions, e-commerce orders, or incoming webhooks, execute intelligent multi-model prompts with built-in tool calling, route data through conditional branches, and sync results across services like Google Sheets, WhatsApp, Slack, and Telegram.

---

### Why Choose AI Agent Workflows?

* **Visual Flow Canvas:** Design complex, multi-branch automation routines using an intuitive drag-and-drop node graph builder.
* **Autonomous AI Agents & Tool Calling:** Deploy agents capable of structured JSON parsing, memory management, and executing multi-step WordPress functions dynamically.
* **Multi-LLM Provider Freedom:** Seamlessly switch between or combine OpenAI, Anthropic Claude, Google Gemini, DeepSeek, Groq, and OpenRouter in a single workflow.
* **Self-Hosted Privacy & Speed:** Run automations on your own WordPress server without third-party middleman per-task subscription fees or payload interception.
* **Enterprise-Grade Execution Tracking:** Inspect live execution logs, node snapshots, execution runtimes, and step-by-step payloads with re-entrancy protection.

---

### Core Automations & Trigger Integrations

=== Form Builder Triggers ===
* **Contact Form 7:** Trigger automations instantly upon successful CF7 submissions.
* **WPForms:** Ingest lead submissions and pass form fields directly into AI agents.
* **Elementor Forms & Atomic Forms:** Native integration for Elementor form submission triggers.

=== WooCommerce E-Commerce Automation ===
* Trigger multi-step flows on new orders, status changes, customer signups, and catalog stock updates.
* Analyze order notes and generate AI-driven customer summaries or notifications automatically.

=== Webhooks & Custom Ingress ===
* **Incoming Webhooks:** Accept payload triggers from external CRMs, payment gateways, and custom applications via dedicated webhook endpoints.
* **Chat Message Ingress:** Listen for incoming conversational triggers and pass them directly to autonomous agents.

---

### Multi-Model AI Capabilities

* **OpenAI:** Integrate GPT models for chat completions, content generation, and tool calling.
* **Anthropic Claude:** Leverage Claude models for complex analysis, summarization, and reasoning tasks.
* **Google Gemini:** Run fast multimodal prompts and text generation.
* **DeepSeek & Groq:** Execute high-speed open-source and reasoning models at ultra-low latency.
* **OpenRouter:** Access hundreds of open-source and commercial foundation models through a single connection.
* **Structured Output Parser:** Force AI models to return validated, reliable JSON schemas for automated processing.

---

### Actions & Third-Party Channels

* **Google Sheets:** Connect via secure OAuth to append rows, read ranges, and update spreadsheets dynamically.
* **WhatsApp Cloud API:** Send automated template messages and direct customer updates.
* **Telegram:** Dispatch bot notifications, alerts, and rich responses to private chats or channels.
* **Slack:** Post messages and structured webhook blocks directly into team channels.
* **Custom HTTP Requests:** Perform custom GET, POST, PUT, and DELETE API calls with custom headers and auth tokens.
* **WordPress Native Actions:** Automatically create, update, or publish Posts, Pages, Users, Comments, and Taxonomies.

---

### Logic & Flow Control Nodes

* **Conditional Logic Nodes:** Branch your workflows dynamically based on custom rules and payload variables.
* **Multi-Branch Routers:** Route data through distinct paths depending on conditions and user types.
* **Delay & Scheduling Nodes:** Control execution timing and prevent API rate-limit overages.

== Installation ==

### Automatic Installation

1. Navigate to **Plugins → Add New** in your WordPress Admin Dashboard.
2. In the search field, enter **AI Agent Workflows** or **Dragwyb Workflows**.
3. Locate the plugin and click **Install Now**.
4. Click **Activate**.
5. Navigate to the **AI Workflows** menu in your WordPress sidebar to start building your first pipeline!

### Manual Installation via ZIP

1. Download the plugin ZIP archive.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Select the file and click **Install Now**.
4. Activate the plugin through the WordPress admin interface.

== Other Plugins by Dragwyb ==

* 🧩 **[Form Builder](https://wordpress.org/plugins/smart-form-builder-by-dragwyb/)** – The easiest & most powerful drag and drop form builder plugin for WordPress.
* 💬 **[Click To Chat](https://wordpress.org/plugins/dragwyb-click-to-chat/)** – Connect with your website visitors instantly through WhatsApp, Telegram, and social channels.
* 🎨 **[Contact form 7 Addon](https://wordpress.org/plugins/enhanced-addon-for-contact-form-7/)** – Enhances the functionality of Contact Form 7.
* 🎛️ **[Flipbox Addon for Elementor](https://wordpress.org/plugins/ultimate-flipbox-addon-for-elementor/)** – Create interactive, conversion-focused 3D flip boxes in Elementor.

== Frequently Asked Questions ==

= Does this plugin require external automation platforms like Zapier or Make? =

No. AI Agent Workflows runs directly on your WordPress installation, meaning all execution logic, webhooks, and routing happen on your server without third-party platform subscription fees.

= Which AI providers are supported? =

The plugin natively integrates with OpenAI, Anthropic (Claude), Google Gemini, DeepSeek, Groq, and OpenRouter.

= Do I need my own API keys? =

Yes. You provide your own API keys for the respective AI services or connection providers (e.g., OpenAI, Google OAuth, WhatsApp Cloud API). Credentials are stored securely and encrypted in your database.

= Can I trigger workflows from form plugins? =

Yes. Built-in triggers support Contact Form 7, WPForms, Elementor Forms, and Elementor Atomic Forms.

= Does it support conditional routing and multi-branch execution? =

Yes. You can add Condition and Router nodes to direct payloads through different pathways based on specific criteria or AI evaluation outcomes.

= How do I inspect workflow errors or logs? =

Navigate to **AI Workflows → Runs** to view a full history of all executions, step-by-step node durations, raw input/output payloads, and failure traces.

== Privacy ==

AI Agent Workflows processes and stores trigger event data, workflow states, and execution logs in your local WordPress MySQL database. 

When configuring AI and external integration nodes (e.g., OpenAI, Anthropic, Google Sheets, WhatsApp), the payloads passed through those specific nodes are transmitted to the respective third-party service APIs configured by the site administrator. 

Site administrators are responsible for ensuring that all API keys, data processing flows, and user privacy disclosures comply with regional laws and regulations (such as GDPR).

== Screenshots ==

1. Visual drag-and-drop workflow canvas.
2. Multi-model AI agent node setup.
3. WooCommerce and form trigger configuration.
4. Google Sheets, WhatsApp and Slack connections.
5. Real-time run logs and execution history.
6. Custom incoming webhooks manager.

== Changelog ==

= 0.1.2 =
* Security improvement.
* Update prefix and namespace.

= 0.1.1 =
* Fixed WordPress.org review compliance issues.
* Removed unnecessary `plugin.php` loading from the media helper.
* Improved REST API permissions for private chat endpoints.
* Restricted private chats to users with proper workflow permissions.
* Updated plugin metadata and WordPress compatibility information.
* Improved source/build documentation for generated assets.

= 0.1.0 =
* Update plugin name, plugin slug & prefix.
* Improve create, update and insert user validation and capability check.
* Use wp_iniline_script instead of direct script.

= 1.0.0 =
* Initial release.