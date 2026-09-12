<?php
/**
 * Credential encryption.
 *
 * @package VM_Social_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Symmetric encryption for API keys and tokens, keyed off WordPress salts.
 *
 * If the site's salts are rotated the stored secrets become unreadable and the
 * admin will simply be asked to re-enter them. That is the intended trade-off:
 * secrets never sit in the database as plaintext.
 */
class VMSAI_Crypto {

	const CIPHER = 'aes-256-gcm';
	const PREFIX = 'vmsai1:';

	/**
	 * Generate a secure token for external portal access.
	 */
	public static function generate_portal_token( $queue_id, $ttl = 0 ) {
		$key = self::key();
		$exp = $ttl > 0 ? time() + (int) $ttl : 0;
		$body = (int) $queue_id . '|' . get_option( 'admin_email' ) . '|' . $exp;
		$sig = hash_hmac( 'sha256', $body, $key );
		return $exp > 0 ? $exp . '.' . $sig : $sig;
	}

	/**
	 * Verify an external portal token. Accepts legacy timeless tokens and
	 * new exp.sig tokens (rejects expired ones).
	 */
	public static function verify_portal_token( $queue_id, $token ) {
		$token = (string) $token;
		if ( false !== strpos( $token, '.' ) ) {
			list( $exp, $sig ) = explode( '.', $token, 2 );
			if ( ! ctype_digit( (string) $exp ) || (int) $exp < time() ) {
				return false;
			}
			$expected = hash_hmac( 'sha256', (int) $queue_id . '|' . get_option( 'admin_email' ) . '|' . (int) $exp, self::key() );
			return hash_equals( $expected, (string) $sig );
		}
		// Legacy timeless token (still honoured; new links carry expiry).
		$expected = hash_hmac( 'sha256', (int) $queue_id . '|' . get_option( 'admin_email' ) . '|0', self::key() );
		if ( hash_equals( $expected, $token ) ) {
			return true;
		}
		$very_legacy = hash_hmac( 'sha256', (int) $queue_id . '|' . get_option( 'admin_email' ), self::key() );
		return hash_equals( $very_legacy, $token );
	}

	/**
	 * Derive the 32-byte key.
	 *
	 * @return string
	 */
	private static function key() {
		$material = ( defined( 'VMSAI_ENCRYPTION_KEY' ) ? VMSAI_ENCRYPTION_KEY : '' )
			. ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' )
			. ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '' );

		if ( '' === $material ) {
			$material = get_option( 'siteurl' ) . ABSPATH;
		}

		return hash( 'sha256', 'vm-social-ai|' . $material, true );
	}

	/**
	 * Encrypt a plaintext string.
	 *
	 * @param string $plain Plaintext.
	 * @return string Portable ciphertext, or the plaintext if OpenSSL is missing.
	 */
	public static function encrypt( $plain ) {
		if ( '' === $plain || ! function_exists( 'openssl_encrypt' ) ) {
			return $plain;
		}

		$iv  = random_bytes( 12 );
		$tag = '';
		$out = openssl_encrypt( $plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $out ) {
			return $plain;
		}

		return self::PREFIX . base64_encode( $iv . $tag . $out ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/**
	 * Decrypt a ciphertext produced by encrypt().
	 *
	 * @param string $cipher Stored value.
	 * @return string Plaintext, or empty string on failure.
	 */
	public static function decrypt( $cipher ) {
		if ( '' === $cipher ) {
			return '';
		}
		if ( 0 !== strpos( $cipher, self::PREFIX ) ) {
			return $cipher; // Legacy plaintext value.
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$blob = base64_decode( substr( $cipher, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $blob || strlen( $blob ) < 29 ) {
			return '';
		}

		$iv   = substr( $blob, 0, 12 );
		$tag  = substr( $blob, 12, 16 );
		$data = substr( $blob, 28 );

		$out = openssl_decrypt( $data, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		return false === $out ? '' : $out;
	}
}
