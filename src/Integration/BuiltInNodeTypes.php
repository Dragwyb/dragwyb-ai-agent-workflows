<?php
/**
 * Registers the plugin's own built-in node types.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Integration;

use AIAWAB\Plugin\Integration\Actions\AiAgentAction;
use AIAWAB\Plugin\Integration\Actions\ClaudeMessagesAction;
use AIAWAB\Plugin\Integration\Actions\ConditionAction;
use AIAWAB\Plugin\Integration\Actions\GeminiGenerateContentAction;
use AIAWAB\Plugin\Integration\Actions\HttpRequestAction;
use AIAWAB\Plugin\Integration\Actions\DeepSeekChatAction;
use AIAWAB\Plugin\Integration\Actions\GroqChatAction;
use AIAWAB\Plugin\Integration\Actions\OpenAiChatAction;
use AIAWAB\Plugin\Integration\Actions\OpenRouterChatAction;
use AIAWAB\Plugin\Integration\Actions\RouterAction;
use AIAWAB\Plugin\Integration\Actions\SendEmailAction;
use AIAWAB\Plugin\Integration\Actions\SlackIncomingWebhookAction;
use AIAWAB\Plugin\Integration\Actions\StructuredOutputParserAction;
use AIAWAB\Plugin\Integration\Actions\TelegramSendMessageAction;
use AIAWAB\Plugin\Integration\Actions\WhatsAppCloudSendMessageAction;
use AIAWAB\Plugin\Integration\GoogleSheet\GoogleSheetsActionRegistrar;
use AIAWAB\Plugin\Integration\Triggers\CatalogHookTrigger;
use AIAWAB\Plugin\Integration\Triggers\ChatMessageReceivedTrigger;
use AIAWAB\Plugin\Integration\WordPress\WordPressActionRegistrar;
use AIAWAB\Plugin\Integration\Triggers\WooCommerceCatalogTrigger;
use AIAWAB\Plugin\Service\Agent\AgentAiClient;
use AIAWAB\Plugin\Service\Agent\AgentService;
use AIAWAB\Plugin\Service\ConnectionService;
use AIAWAB\Plugin\Service\GoogleOAuthService;
use AIAWAB\Plugin\Service\NodeTypeRegistry;

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

	private AgentService $agent;

	private AgentAiClient $ai_client;

	public function __construct(
		ConnectionService $connections,
		GoogleOAuthService $google_oauth,
		AgentService $agent,
		AgentAiClient $ai_client
	) {
		$this->connections  = $connections;
		$this->google_oauth = $google_oauth;
		$this->agent        = $agent;
		$this->ai_client    = $ai_client;
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

		$registry->registerTrigger( new ChatMessageReceivedTrigger() );

		$registry->registerAction( new HttpRequestAction( $this->connections ) );
		$registry->registerAction( new SendEmailAction() );
		$registry->registerAction( new SlackIncomingWebhookAction() );
		$registry->registerAction( new OpenAiChatAction( $this->ai_client ) );
		$registry->registerAction( new TelegramSendMessageAction( $this->connections ) );
		$registry->registerAction( new WhatsAppCloudSendMessageAction( $this->connections ) );
		$registry->registerAction( new GeminiGenerateContentAction( $this->ai_client ) );
		$registry->registerAction( new ClaudeMessagesAction( $this->ai_client ) );
		$registry->registerAction( new OpenRouterChatAction( $this->ai_client ) );
		$registry->registerAction( new GroqChatAction( $this->ai_client ) );
		$registry->registerAction( new DeepSeekChatAction( $this->ai_client ) );
		$registry->registerAction( new AiAgentAction( $this->agent ) );
		$registry->registerAction( new StructuredOutputParserAction() );
		$registry->registerAction( new RouterAction() );
		$registry->registerAction( new ConditionAction() );

		foreach ( GoogleSheetsActionRegistrar::all( $this->connections, $this->google_oauth ) as $action ) {
			$registry->registerAction( $action );
		}

		foreach ( WordPressActionRegistrar::all() as $action ) {
			$registry->registerAction( $action );
		}

		foreach ( IntegrationTriggerCatalog::definitions() as $definition ) {
			if ( ! $definition['active'] ) {
				continue;
			}

			if (
				WooCommerceCatalogTrigger::class === $definition['class']
				&& isset( $definition['definition'] )
				&& is_array( $definition['definition'] )
			) {
				$registry->registerTrigger( new WooCommerceCatalogTrigger( $definition['definition'] ) );
				continue;
			}

			$class = $definition['class'];
			$registry->registerTrigger( new $class() );
		}
	}
}
