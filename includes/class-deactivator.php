<?php
/**
 * Plugin deactivator. No-op by design — we keep all data on deactivate.
 * Full wipe happens only if the user opts in and uninstalls.
 *
 * @package Deliz\AI\Advisor
 */

namespace Deliz\AI\Advisor;

defined( 'ABSPATH' ) || exit;

class Deactivator {

	public static function deactivate(): void {
		// Intentionally empty.
	}
}
