<?php
/**
 * Registers the plugin's own built-in node types.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration;

use WorkflowAutomate\Plugin\Integration\Actions\AiAgentAction;
use WorkflowAutomate\Plugin\Integration\Actions\ClaudeMessagesAction;
use WorkflowAutomate\Plugin\Integration\Actions\ConditionAction;
use WorkflowAutomate\Plugin\Integration\Actions\GeminiGenerateContentAction;
use WorkflowAutomate\Plugin\Integration\Actions\HttpRequestAction;
use WorkflowAutomate\Plugin\Integration\Actions\OpenAiChatAction;
use WorkflowAutomate\Plugin\Integration\Actions\RouterAction;
use WorkflowAutomate\Plugin\Integration\Actions\SendEmailAction;
use WorkflowAutomate\Plugin\Integration\Actions\SlackIncomingWebhookAction;
use WorkflowAutomate\Plugin\Integration\Actions\TelegramSendMessageAction;
use WorkflowAutomate\Plugin\Integration\Actions\WhatsAppCloudSendMessageAction;
use WorkflowAutomate\Plugin\Integration\GoogleSheet\GoogleSheetsActionRegistrar;
use WorkflowAutomate\Plugin\Integration\Triggers\CatalogHookTrigger;
use WorkflowAutomate\Plugin\Service\ConnectionService;
use WorkflowAutomate\Plugin\Service\GoogleOAuthService;
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

	private GoogleOAuthService $google_oauth;

	public function __construct( ConnectionService $connections, GoogleOAuthService $google_oauth ) {
		$this->connections  = $connections;
		$this->google_oauth = $google_oauth;
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
		$registry->registerAction( new AiAgentAction( $this->connections ) );
		$registry->registerAction( new RouterAction() );
		$registry->registerAction( new ConditionAction() );

		foreach ( GoogleSheetsActionRegistrar::all( $this->connections, $this->google_oauth ) as $action ) {
			$registry->registerAction( $action );
		}

		foreach ( IntegrationTriggerCatalog::definitions() as $definition ) {
			if ( ! $definition['active'] ) {
				continue;
			}

			$class = $definition['class'];
			$registry->registerTrigger( new $class() );
		}
	}
}
