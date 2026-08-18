<?php
/**
 * Live model catalogue sync.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pulls each provider's current model list twice a day so the dropdowns in
 * the admin reflect what the account can actually call today, not a list
 * that was hard-coded when the plugin shipped.
 */
class VMSAI_Model_Sync {

	/**
	 * Run a full sync across every configured text provider.
	 *
	 * @return array{synced:int,providers:array<string,int>,errors:array<string,string>}
	 */
	public static function run() {
		$engine    = vmsai()->text_engine();
		$providers = array();
		$errors    = array();
		$total     = 0;

		foreach ( $engine->providers() as $slug => $provider ) {
			if ( ! $provider->is_configured() ) {
				continue;
			}

			try {
				$models = $provider->list_models();
				VMSAI_Logger::debug( 'engine.models', sprintf( 'Syncing %s: found %d models.', $slug, count( (array) $models ) ) );
			} catch ( Throwable $e ) {
				$errors[ $slug ] = $e->getMessage();
				VMSAI_Logger::error( 'engine.models', sprintf( 'Sync failed for %s: %s', $slug, $e->getMessage() ) );
				continue;
			}

			if ( ! $models ) {
				$errors[ $slug ] = __( 'No models returned.', 'vm-social-ai-pro' );
				continue;
			}

			self::store( $slug, $models, 'text' );
			$providers[ $slug ] = count( $models );
			$total             += count( $models );
		}

		// Image models.
		$image_engine = vmsai()->image_engine();
		foreach ( $image_engine->providers() as $slug => $provider ) {
			if ( ! $provider->is_configured() ) {
				continue;
			}

			if ( method_exists( $provider, 'list_models' ) ) {
				try {
					$models = $provider->list_models();
					if ( $models ) {
						self::store( $slug, $models, 'image' );
					}
				} catch ( Throwable $e ) {
					// Soft fail for image models.
				}
			}
		}

		// Hardcoded static catalogues.
		self::store( 'pollinations', self::pollinations_models(), 'image' );

		// Video models.
		$video_engine = vmsai()->video_engine();
		foreach ( array_keys( $video_engine->providers() ) as $slug ) {
			try {
				$models = $video_engine->list_models( $slug );
				if ( $models ) {
					self::store( $slug, $models, 'video' );
					$providers[ $slug . ':video' ] = count( $models );
					$total += count( $models );
				}
			} catch ( Throwable $e ) {
				$errors[ $slug . ':video' ] = $e->getMessage();
			}
		}

		update_option(
			'vmsai_model_sync_state',
			array(
				'ran_at'    => time(),
				'total'     => $total,
				'providers' => $providers,
				'errors'    => $errors,
			),
			false
		);

		VMSAI_Logger::info( 'engine.models', sprintf( 'Synced %d models.', $total ), array( 'providers' => $providers ) );

		return array( 'synced' => $total, 'providers' => $providers, 'errors' => $errors );
	}

	/**
	 * Replace a provider's cached catalogue.
	 *
	 * @param string $provider Provider slug.
	 * @param array  $models   Model rows.
	 * @param string $modality text|image.
	 * @return void
	 */
	private static function store( $provider, array $models, $modality ) {
		if ( empty( $models ) ) {
			return;
		}

		global $wpdb;
		$table = VMSAI_Install::table( 'models' );
		$now   = current_time( 'mysql', true );

		$wpdb->delete( $table, array( 'provider' => $provider, 'modality' => $modality ), array( '%s', '%s' ) ); // phpcs:ignore

		foreach ( $models as $model ) {
			if ( empty( $model['id'] ) ) {
				continue;
			}

			$wpdb->insert( // phpcs:ignore
				$table,
				array(
					'provider'       => $provider,
					'model_id'       => substr( (string) $model['id'], 0, 190 ),
					'label'          => substr( (string) ( $model['label'] ?? $model['id'] ), 0, 190 ),
					'modality'       => $modality,
					'context_length' => (int) ( $model['context'] ?? 0 ),
					'is_free'        => ! empty( $model['free'] ) ? 1 : 0,
					'meta'           => null,
					'synced_at'      => $now,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Cached models for a provider.
	 *
	 * @param string $provider Provider slug.
	 * @param string $modality text|image.
	 * @return array
	 */
	public static function models( $provider, $modality = 'text' ) {
		global $wpdb;
		$table = VMSAI_Install::table( 'models' );

		return (array) $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT model_id, label, context_length, is_free FROM `$table` WHERE provider = %s AND modality = %s ORDER BY is_free DESC, label ASC", // phpcs:ignore
				$provider,
				$modality
			),
			ARRAY_A
		);
	}

	/**
	 * When the catalogue was last refreshed.
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( 'vmsai_model_sync_state', array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Pollinations publishes a small fixed set of renderers.
	 *
	 * @return array
	 */
	private static function pollinations_models() {
		return array(
			array( 'id' => 'flux', 'label' => 'Flux — balanced, best default', 'context' => 0, 'free' => true ),
			array( 'id' => 'flux-realism', 'label' => 'Flux Realism — photographic', 'context' => 0, 'free' => true ),
			array( 'id' => 'flux-anime', 'label' => 'Flux Anime — illustrated', 'context' => 0, 'free' => true ),
			array( 'id' => 'flux-3d', 'label' => 'Flux 3D — product renders', 'context' => 0, 'free' => true ),
			array( 'id' => 'turbo', 'label' => 'Turbo — fastest, high volume', 'context' => 0, 'free' => true ),
		);
	}
}
