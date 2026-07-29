<?php
/**
 * Minimal OAuth 1.0a request signer for the X (Twitter) API, user context.
 * Only what's needed here: build a signed Authorization header for a GET
 * or POST request, given a set of credentials and (for form-encoded
 * requests only) the params to sign alongside the URL.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Coin680X_OAuth {
    private $consumer_key;
    private $consumer_secret;
    private $access_token;
    private $access_token_secret;

    public function __construct($consumer_key, $consumer_secret, $access_token, $access_token_secret) {
        $this->consumer_key = $consumer_key;
        $this->consumer_secret = $consumer_secret;
        $this->access_token = $access_token;
        $this->access_token_secret = $access_token_secret;
    }

    private function percent_encode($value) {
        $encoded = rawurlencode((string) $value);
        // rawurlencode already matches RFC 3986 in modern PHP (encodes !*'())
        return $encoded;
    }

    public function auth_header($method, $url, $extra_params = array()) {
        $oauth = array(
            'oauth_consumer_key'     => $this->consumer_key,
            'oauth_nonce'            => wp_generate_password(32, false, false),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => (string) time(),
            'oauth_token'            => $this->access_token,
            'oauth_version'          => '1.0',
        );

        $all_params = array_merge($oauth, $extra_params);
        $sorted_keys = array_keys($all_params);
        sort($sorted_keys, SORT_STRING);

        $pairs = array();
        foreach ($sorted_keys as $key) {
            $pairs[] = $this->percent_encode($key) . '=' . $this->percent_encode($all_params[$key]);
        }
        $param_string = implode('&', $pairs);

        $base_string = strtoupper($method) . '&' . $this->percent_encode($url) . '&' . $this->percent_encode($param_string);
        $signing_key = $this->percent_encode($this->consumer_secret) . '&' . $this->percent_encode($this->access_token_secret);
        $signature = base64_encode(hash_hmac('sha1', $base_string, $signing_key, true));

        $oauth['oauth_signature'] = $signature;
        ksort($oauth, SORT_STRING);

        $header_parts = array();
        foreach ($oauth as $key => $value) {
            $header_parts[] = $this->percent_encode($key) . '="' . $this->percent_encode($value) . '"';
        }
        return 'OAuth ' . implode(', ', $header_parts);
    }
}
