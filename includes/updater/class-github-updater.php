<?php
/**
 * GitHub-based self-updater.
 *
 * Compares the plugin header Version against the `tag_name` of the latest
 * GitHub release and offers an update via the native WP updates UI.
 *
 * @package Deliz\AI\Advisor\Updater
 */

namespace Deliz\AI\Advisor\Updater;

defined( 'ABSPATH' ) || exit;

class GithubUpdater {

	const GITHUB_REPO = 'omerelias/deliz-ai-advisor';
	const CACHE_KEY   = 'deliz_ai_gh_release';
	const CACHE_TTL   = 6 * HOUR_IN_SECONDS;

	public function register(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
	}

	/**
	 * Inject an update into the plugins-update transient if GitHub has a newer tag.
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = $this->latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$current    = $this->current_version();
		$new_version = ltrim( (string) ( $release['tag_name'] ?? '' ), 'vV' );
		if ( '' === $new_version || version_compare( $new_version, $current, '<=' ) ) {
			return $transient;
		}

		$zip_url = $release['zipball_url'] ?? '';
		// Prefer a release asset ending in .zip if present.
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( isset( $asset['browser_download_url'] ) && preg_match( '/\.zip$/i', $asset['browser_download_url'] ) ) {
					$zip_url = $asset['browser_download_url'];
					break;
				}
			}
		}

		$plugin_file = DELIZ_AI_PLUGIN_BASENAME;
		$obj = (object) array(
			'id'            => 'deliz-ai-advisor/' . $plugin_file,
			'slug'          => 'deliz-ai-advisor',
			'plugin'        => $plugin_file,
			'new_version'   => $new_version,
			'url'           => 'https://github.com/' . self::GITHUB_REPO,
			'package'       => $zip_url,
			'tested'        => get_bloginfo( 'version' ),
			'requires_php'  => '7.4',
			'compatibility' => new \stdClass(),
		);

		$transient->response[ $plugin_file ] = $obj;
		return $transient;
	}

	/**
	 * Provide plugin info for the "View details" modal.
	 */
	public function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || 'deliz-ai-advisor' !== $args->slug ) {
			return $result;
		}

		$release = $this->latest_release();
		if ( ! $release ) {
			return $result;
		}

		$new_version = ltrim( (string) ( $release['tag_name'] ?? '' ), 'vV' );

		return (object) array(
			'name'          => 'Deliz AI Advisor',
			'slug'          => 'deliz-ai-advisor',
			'version'       => $new_version,
			'author'        => '<a href="https://github.com/omerelias">omerelias</a>',
			'homepage'      => 'https://github.com/' . self::GITHUB_REPO,
			'download_link' => $release['zipball_url'] ?? '',
			'sections'      => array(
				'description' => 'AI-powered chat advisor for WooCommerce delicatessen shops.',
				'changelog'   => wpautop( esc_html( (string) ( $release['body'] ?? '' ) ) ),
			),
			'requires'     => '6.0',
			'requires_php' => '7.4',
		);
	}

	/**
	 * GitHub zips extract to `omerelias-deliz-ai-advisor-<hash>/` — rename to
	 * `deliz-ai-advisor/` so WP overwrites the plugin cleanly.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
		global $wp_filesystem;

		if ( ! is_string( $source ) || empty( $hook_extra['plugin'] ) ) {
			return $source;
		}
		if ( DELIZ_AI_PLUGIN_BASENAME !== $hook_extra['plugin'] ) {
			return $source;
		}

		$desired = trailingslashit( dirname( $source ) ) . 'deliz-ai-advisor/';
		if ( $source === $desired ) {
			return $source;
		}

		if ( $wp_filesystem && $wp_filesystem->move( $source, $desired, true ) ) {
			return $desired;
		}
		return $source;
	}

	/**
	 * Fetch the latest release JSON (cached).
	 *
	 * @return array<string,mixed>|null
	 */
	private function latest_release(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url = sprintf( 'https://api.github.com/repos/%s/releases/latest', self::GITHUB_REPO );
		$res = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'deliz-ai-advisor',
				),
			)
		);

		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			// Cache null briefly to avoid hammering GH on failure.
			set_transient( self::CACHE_KEY, array(), MINUTE_IN_SECONDS * 15 );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	private function current_version(): string {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data = get_plugin_data( DELIZ_AI_PLUGIN_FILE, false, false );
		return (string) ( $data['Version'] ?? DELIZ_AI_VERSION );
	}
}
