<?php
/**
 * Registers the plugin's own built-in node types.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration;

use WorkflowAutomate\Plugin\Integration\Actions\ClaudeMessagesAction;
use WorkflowAutomate\Plugin\Integration\Actions\GeminiGenerateContentAction;
use WorkflowAutomate\Plugin\Integration\Actions\GoogleSheetsAppendRowAction;
use WorkflowAutomate\Plugin\Integration\Actions\HttpRequestAction;
use WorkflowAutomate\Plugin\Integration\Actions\OpenAiChatAction;
use WorkflowAutomate\Plugin\Integration\Actions\SendEmailAction;
use WorkflowAutomate\Plugin\Integration\Actions\SlackIncomingWebhookAction;
use WorkflowAutomate\Plugin\Integration\Actions\TelegramSendMessageAction;
use WorkflowAutomate\Plugin\Integration\Actions\WhatsAppCloudSendMessageAction;
use WorkflowAutomate\Plugin\Integration\Triggers\CatalogHookTrigger;
use WorkflowAutomate\Plugin\Integration\Triggers\ContactForm7SubmittedTrigger;
use WorkflowAutomate\Plugin\Integration\Triggers\ElementorFormSubmittedTrigger;
use WorkflowAutomate\Plugin\Integration\Triggers\WooCommerceOrderCompletedTrigger;
use WorkflowAutomate\Plugin\Integration\Triggers\WpFormsSubmittedTrigger;
use WorkflowAutomate\Plugin\Service\ConnectionService;
use WorkflowAutomate\Plugin\Service\NodeTypeRegistry;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listens on the public `wfa/nodes/register` action to add this plugin's
 * own built-in trigger/action node types.
 *
 * Optional co-plugin integrations are registered only when that plugin is
 * active so the palette stays relevant to the site.
 */
class BuiltInNodeTypes {

	private ConnectionService $connections;

	public function __construct( ConnectionService $connections ) {
		$this->connections = $connections;
	}

	/**
	 * @param NodeTypeRegistry $registry The registry being populated.
	 *
	 * @return void
	 */
	public function register( NodeTypeRegistry $registry ): void {
		foreach ( WordPressHookCatalog::definitions() as $definition ) {
			$registry->registerTrigger( new CatalogHookTrigger( $definition ) );
		}

		$registry->registerAction( new HttpRequestAction( $this->connections ) );
		$registry->registerAction( new SendEmailAction() );
		$registry->registerAction( new SlackIncomingWebhookAction() );
		$registry->registerAction( new OpenAiChatAction( $this->connections ) );
		$registry->registerAction( new TelegramSendMessageAction( $this->connections ) );
		$registry->registerAction( new WhatsAppCloudSendMessageAction( $this->connections ) );
		$registry->registerAction( new GeminiGenerateContentAction( $this->connections ) );
		$registry->registerAction( new ClaudeMessagesAction( $this->connections ) );
		$registry->registerAction( new GoogleSheetsAppendRowAction( $this->connections ) );

		if ( self::isWooCommerceActive() ) {
			$registry->registerTrigger( new WooCommerceOrderCompletedTrigger() );
		}

		if ( self::isElementorProActive() ) {
			$registry->registerTrigger( new ElementorFormSubmittedTrigger() );
		}

		if ( self::isContactForm7Active() ) {
			$registry->registerTrigger( new ContactForm7SubmittedTrigger() );
		}

		if ( self::isWpFormsActive() ) {
			$registry->registerTrigger( new WpFormsSubmittedTrigger() );
		}
	}

	/**
	 * @return bool
	 */
	private static function isWooCommerceActive(): bool {
		return class_exists( '\WooCommerce', false ) && function_exists( 'WC' );
	}

	/**
	 * @return bool
	 */
	private static function isElementorProActive(): bool {
		return defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\ElementorPro\Plugin', false );
	}

	/**
	 * @return bool
	 */
	private static function isContactForm7Active(): bool {
		return defined( 'WPCF7_VERSION' ) || class_exists( '\WPCF7_ContactForm', false );
	}

	/**
	 * @return bool
	 */
	private static function isWpFormsActive(): bool {
		return function_exists( 'wpforms' ) || defined( 'WPFORMS_VERSION' );
	}
}
