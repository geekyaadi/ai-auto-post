<?php
/**
 * Helper class for interacting with the Google Indexing API.
 * Uses Service Account JSON key to sign JWT and request instant URL indexing.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class AAP_GSC_Helper {

    /**
     * Helper to base64url encode a string.
     */
    private static function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * Generates OAuth2 Access Token using Google Service Account JSON credentials.
     *
     * @return string|WP_Error Access token on success, WP_Error on failure.
     */
    public static function get_access_token() {
        $json_creds = get_option( 'aap_gsc_json', '' );
        if ( empty( $json_creds ) ) {
            return new WP_Error( 'missing_gsc_creds', 'Google Service Account JSON key is missing in settings.' );
        }

        $creds = json_decode( $json_creds, true );
        if ( ! is_array( $creds ) || empty( $creds['client_email'] ) || empty( $creds['private_key'] ) ) {
            return new WP_Error( 'invalid_gsc_creds', 'Google Service Account JSON key is invalid.' );
        }

        // Check if there is a cached token in transient
        $cached_token = get_transient( 'aap_gsc_access_token' );
        if ( $cached_token ) {
            return $cached_token;
        }

        $header = self::base64url_encode( json_encode( [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ] ) );

        $now = time();
        $claim = self::base64url_encode( json_encode( [
            'iss'   => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/indexing',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ] ) );

        $data_to_sign = $header . '.' . $claim;
        $signature    = '';

        $pkey = $creds['private_key'];
        if ( ! openssl_sign( $data_to_sign, $signature, $pkey, 'SHA256' ) ) {
            return new WP_Error( 'jwt_signing_failed', 'Failed to sign JWT with the private key. Make sure OpenSSL is enabled in PHP.' );
        }

        $jwt = $data_to_sign . '.' . self::base64url_encode( $signature );

        // Request token from Google Auth server
        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( empty( $data['access_token'] ) ) {
            $error_desc = $data['error_description'] ?? ( $data['error'] ?? 'Unknown authentication error' );
            return new WP_Error( 'oauth_token_failed', 'Google Auth Error: ' . $error_desc );
        }

        $access_token = $data['access_token'];
        $expires_in   = (int) ( $data['expires_in'] ?? 3500 );

        // Cache the token slightly shorter than expiry (e.g. 50 minutes)
        set_transient( 'aap_gsc_access_token', $access_token, $expires_in - 200 );

        return $access_token;
    }

    /**
     * Submits a URL to the Google Indexing API.
     *
     * @param string $url The post URL to index.
     * @param string $type The action type: 'URL_UPDATED' or 'URL_DELETED'. Default 'URL_UPDATED'.
     * @return array|WP_Error API response array on success, WP_Error on failure.
     */
    public static function submit_url( $url, $type = 'URL_UPDATED' ) {
        $token = self::get_access_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $api_url = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
        $body    = json_encode( [
            'url'  => esc_url_raw( $url ),
            'type' => $type,
        ] );

        $response = wp_remote_post( $api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code !== 200 ) {
            $err_msg = $data['error']['message'] ?? 'Google Indexing API call failed with response code ' . $code;
            return new WP_Error( 'gsc_api_error', $err_msg );
        }

        return $data;
    }
}
