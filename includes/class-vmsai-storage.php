<?php
/**
 * Remote Storage Handler (R2/S3).
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles offloading images to Cloudflare R2 or other S3-compatible services.
 */
class VMSAI_Storage {

	/**
	 * Upload a local file to remote storage.
	 *
	 * @param string $local_path Absolute path to file.
	 * @param string $filename   Target filename.
	 * @param string $mime       Mime type.
	 * @return string|WP_Error   The public URL or an error.
	 */
	public static function upload( $local_path, $filename, $mime = 'image/jpeg' ) {
		$type = VMSAI_Settings::get( 'remote_storage', 'off' );

		if ( 'r2' !== $type ) {
			return new WP_Error( 'storage_disabled', 'Remote storage is not enabled.' );
		}

		$account_id = VMSAI_Settings::credential( 'r2_account_id' );
		$bucket     = VMSAI_Settings::credential( 'r2_bucket' );
		$access_key = VMSAI_Settings::credential( 'r2_key' );
		$secret_key = VMSAI_Settings::credential( 'r2_secret' );
		$public_url = VMSAI_Settings::credential( 'r2_public_url' );

		if ( ! $account_id || ! $bucket || ! $access_key || ! $secret_key ) {
			return new WP_Error( 'missing_credentials', 'R2 credentials are incomplete.' );
		}

		$host = "{$bucket}.{$account_id}.r2.cloudflarestorage.com";

		// Path-segment encoding: filenames can contain spaces or Unicode,
		// which otherwise break both the request URL and the returned
		// public URL.
		$encoded = rawurlencode( $filename );
		$url     = "https://{$host}/{$encoded}";

		if ( ! file_exists( $local_path ) || ! is_readable( $local_path ) ) {
			return new WP_Error( 'read_failed', 'Could not read local file.' );
		}

		$response = self::s3_put( $url, $host, $local_path, $mime, $access_key, $secret_key );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Return the custom public URL if provided, otherwise the direct R2 URL.
		if ( $public_url ) {
			return untrailingslashit( $public_url ) . '/' . $encoded;
		}

		return $url;
	}

	/**
	 * Simple S3-compatible PUT request with AWS Signature V4. The body is
	 * streamed from disk — a video can far exceed available memory.
	 */
	private static function s3_put( $url, $host, $path, $mime, $key, $secret ) {
		$region = 'auto';
		$service = 's3';
		$method = 'PUT';
		$now = time();
		$amz_date = gmdate( 'Ymd\THis\Z', $now );
		$date_stamp = gmdate( 'Ymd', $now );

		$url_path    = wp_parse_url( $url, PHP_URL_PATH );
		$content_hash = hash_file( 'sha256', $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$headers = array(
			'Host' => $host,
			'Content-Type' => $mime,
			'x-amz-content-sha256' => $content_hash,
			'x-amz-date' => $amz_date,
		);

		// Canonical Request
		$canonical_uri = $url_path;
		$canonical_querystring = '';
		$canonical_headers = "content-type:{$mime}\nhost:{$host}\nx-amz-content-sha256:{$content_hash}\nx-amz-date:{$amz_date}\n";
		$signed_headers = 'content-type;host;x-amz-content-sha256;x-amz-date';

		$canonical_request = "{$method}\n{$canonical_uri}\n{$canonical_querystring}\n{$canonical_headers}\n{$signed_headers}\n{$content_hash}";

		// String to Sign
		$algorithm = 'AWS4-HMAC-SHA256';
		$credential_scope = "{$date_stamp}/{$region}/{$service}/aws4_request";
		$string_to_sign = "{$algorithm}\n{$amz_date}\n{$credential_scope}\n" . hash( 'sha256', $canonical_request );

		// Calculate Signature
		$k_date = hash_hmac( 'sha256', $date_stamp, 'AWS4' . $secret, true );
		$k_region = hash_hmac( 'sha256', $region, $k_date, true );
		$k_service = hash_hmac( 'sha256', $service, $k_region, true );
		$k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );
		$signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );

		$headers['Authorization'] = "{$algorithm} Credential={$key}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";

		// Streamed upload — keeps multi-hundred-MB videos out of memory.
		$response = VMSAI_Http::send_file( 'PUT', $url, $path, $headers, 'storage.r2', 300 );

		if ( ! $response['ok'] ) {
			return new WP_Error( 'r2_upload_failed', $response['error'] ?: 'R2 upload failed.' );
		}

		return true;
	}
}
