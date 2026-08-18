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
		$url  = "https://{$host}/{$filename}";

		$content = file_get_contents( $local_path );
		if ( false === $content ) {
			return new WP_Error( 'read_failed', 'Could not read local file.' );
		}

		$response = self::s3_put( $url, $host, $content, $mime, $access_key, $secret_key );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Return the custom public URL if provided, otherwise the direct R2 URL.
		if ( $public_url ) {
			return untrailingslashit( $public_url ) . '/' . $filename;
		}

		return $url;
	}

	/**
	 * Simple S3-compatible PUT request with AWS Signature V4.
	 */
	private static function s3_put( $url, $host, $content, $mime, $key, $secret ) {
		$region = 'auto';
		$service = 's3';
		$method = 'PUT';
		$now = time();
		$amz_date = gmdate( 'Ymd\THis\Z', $now );
		$date_stamp = gmdate( 'Ymd', $now );

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$content_hash = hash( 'sha256', $content );

		$headers = array(
			'Host' => $host,
			'Content-Type' => $mime,
			'x-amz-content-sha256' => $content_hash,
			'x-amz-date' => $amz_date,
		);

		// Canonical Request
		$canonical_uri = $path;
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

		$response = wp_remote_request( $url, array(
			'method' => 'PUT',
			'headers' => $headers,
			'body' => $content,
			'timeout' => 60,
			'sslverify' => true,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'r2_upload_failed', 'R2 returned HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) );
		}

		return true;
	}
}
