<?php
/**
 * Optional trigger grouping for the builder palette.
 *
 * @package DRAGAIW\Plugin
 */

declare(strict_types=1);

namespace DRAGAIW\Plugin\Domain\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TriggerGroupInterface {

	public function group(): string;

	public function groupLabel(): string;

	public function app(): string;
}
