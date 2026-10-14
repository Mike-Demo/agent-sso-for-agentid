<?php
/**
 * Standalone CLI test for AgentID_OIDC::verify_id_token().
 *
 * Generates a throwaway P-256 key pair, signs ES256 tokens with
 * OpenSSL, and checks that the verifier accepts a valid token and
 * fails closed on: tampered signature, wrong audience, expired
 * token, wrong nonce, and a human (non-agent) actor_type.
 *
 * Run: php tests/test-jwt-verify.php   (no WordPress needed)
 *
 * The class file only touches WP APIs inside the network methods
 * (discovery/jwks/exchange/userinfo); the verifier path used here is
 * pure. Minimal stubs below cover the few calls on that path
 * (WP_Error, __).
 */

error_reporting( E_ALL );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'AGENTID_ISSUER' ) ) {
	define( 'AGENTID_ISSUER', 'https://auth.agentid.com' );
}

// --- Minimal WP stubs -------------------------------------------------
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
	function __( $text, $domain = 'default' ) { // phpcs:ignore
		return $text;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-agentid-oidc.php';

// --- Key generation ---------------------------------------------------
$key_res = openssl_pkey_new(
	array(
		'curve_name'       => 'prime256v1',
		'private_key_type' => OPENSSL_KEYTYPE_EC,
	)
);
if ( ! $key_res ) {
	fwrite( STDERR, "FAIL: could not generate EC key\n" );
	exit( 1 );
}
$details = openssl_pkey_get_details( $key_res );
$x       = str_pad( $details['ec']['x'], 32, "\x00", STR_PAD_LEFT );
$y       = str_pad( $details['ec']['y'], 32, "\x00", STR_PAD_LEFT );
$kid     = 'test-key-1';
$jwks    = array(
	'keys' => array(
		array(
			'kty' => 'EC',
			'crv' => 'P-256',
			'kid' => $kid,
			'x'   => AgentID_OIDC::b64url_encode( $x ),
			'y'   => AgentID_OIDC::b64url_encode( $y ),
		),
	),
);

function der_to_raw_sig( $der ) {
	// Minimal DER ECDSA -> raw r||s for test signing output parsing.
	$offset = 2; // SEQUENCE tag + short length (our sigs are < 128 bytes total content? handle long form too).
	if ( ord( $der[1] ) & 0x80 ) {
		$len_bytes = ord( $der[1] ) & 0x7f;
		$offset    = 2 + $len_bytes;
	}
	$parts = array();
	for ( $i = 0; $i < 2; $i++ ) {
		// INTEGER tag.
		$offset++;
		$len     = ord( $der[ $offset ] );
		$offset++;
		$bytes   = substr( $der, $offset, $len );
		$offset += $len;
		$bytes   = ltrim( $bytes, "\x00" );
		$parts[] = str_pad( $bytes, 32, "\x00", STR_PAD_LEFT );
	}
	return $parts[0] . $parts[1];
}

function make_token( $key_res, $kid, array $claims ) {
	$head = AgentID_OIDC::b64url_encode( json_encode( array( 'alg' => 'ES256', 'kid' => $kid, 'typ' => 'JWT' ) ) );
	$body = AgentID_OIDC::b64url_encode( json_encode( $claims ) );
	$der  = '';
	openssl_sign( "$head.$body", $der, $key_res, OPENSSL_ALGO_SHA256 );
	return "$head.$body." . AgentID_OIDC::b64url_encode( der_to_raw_sig( $der ) );
}

$client_id = 'test-client-123';
$nonce     = 'nonce-abc';
$base      = array(
	'iss'        => 'https://auth.agentid.com',
	'aud'        => $client_id,
	'sub'        => 'agent-subject-1',
	'actor_type' => 'agent',
	'email'      => 'agent@example.agentmail.to',
	'exp'        => time() + 600,
	'iat'        => time(),
	'nonce'      => $nonce,
);

$failures = 0;
function check( $name, $condition ) {
	global $failures;
	if ( $condition ) {
		echo "PASS: $name\n";
	} else {
		echo "FAIL: $name\n";
		$failures++;
	}
}

// 1. Valid token verifies and returns claims.
$token  = make_token( $key_res, $kid, $base );
$result = AgentID_OIDC::verify_id_token( $token, $jwks, $client_id, $nonce );
check( 'valid token accepted', is_array( $result ) && $result['sub'] === 'agent-subject-1' );

// 2. Tampered signature rejected.
$parts         = explode( '.', $token );
$sig           = AgentID_OIDC::b64url_decode( $parts[2] );
$sig[0]        = chr( ord( $sig[0] ) ^ 0xff );
$tampered      = $parts[0] . '.' . $parts[1] . '.' . AgentID_OIDC::b64url_encode( $sig );
$result        = AgentID_OIDC::verify_id_token( $tampered, $jwks, $client_id, $nonce );
check( 'tampered signature rejected', is_wp_error( $result ) && $result->get_error_code() === 'agentid_jwt_signature' );

// 3. Wrong audience rejected.
$result = AgentID_OIDC::verify_id_token( $token, $jwks, 'other-client', $nonce );
check( 'wrong audience rejected', is_wp_error( $result ) && $result->get_error_code() === 'agentid_claim_aud' );

// 4. Expired token rejected.
$expired_claims        = $base;
$expired_claims['exp'] = time() - 3600;
$expired_token         = make_token( $key_res, $kid, $expired_claims );
$result                = AgentID_OIDC::verify_id_token( $expired_token, $jwks, $client_id, $nonce );
check( 'expired token rejected', is_wp_error( $result ) && $result->get_error_code() === 'agentid_claim_exp' );

// 5. Wrong nonce rejected.
$result = AgentID_OIDC::verify_id_token( $token, $jwks, $client_id, 'different-nonce' );
check( 'wrong nonce rejected', is_wp_error( $result ) && $result->get_error_code() === 'agentid_claim_nonce' );

// 6. Human actor rejected (fail closed on actor_type).
$human_claims               = $base;
$human_claims['actor_type'] = 'human';
$human_token                = make_token( $key_res, $kid, $human_claims );
$result                     = AgentID_OIDC::verify_id_token( $human_token, $jwks, $client_id, $nonce );
check( 'non-agent actor rejected', is_wp_error( $result ) && $result->get_error_code() === 'agentid_claim_actor' );

// 7. Missing actor_type rejected.
$no_actor = $base;
unset( $no_actor['actor_type'] );
$no_actor_token = make_token( $key_res, $kid, $no_actor );
$result         = AgentID_OIDC::verify_id_token( $no_actor_token, $jwks, $client_id, $nonce );
check( 'missing actor_type rejected', is_wp_error( $result ) && $result->get_error_code() === 'agentid_claim_actor' );

// 8. Unknown kid rejected.
$head2   = AgentID_OIDC::b64url_encode( json_encode( array( 'alg' => 'ES256', 'kid' => 'nope', 'typ' => 'JWT' ) ) );
$body2   = AgentID_OIDC::b64url_encode( json_encode( $base ) );
$der2    = '';
openssl_sign( "$head2.$body2", $der2, $key_res, OPENSSL_ALGO_SHA256 );
$token2  = "$head2.$body2." . AgentID_OIDC::b64url_encode( der_to_raw_sig( $der2 ) );
$result  = AgentID_OIDC::verify_id_token( $token2, $jwks, $client_id, $nonce );
check( 'unknown kid rejected', is_wp_error( $result ) && $result->get_error_code() === 'agentid_jwt_kid' );

// 10. Multiple audiences without azp rejected.
$multi_claims           = $base;
$multi_claims['aud']    = array( $client_id, 'someone-else' );
$multi_token            = make_token( $key_res, $kid, $multi_claims );
$result                 = AgentID_OIDC::verify_id_token( $multi_token, $jwks, $client_id, $nonce );
check( 'multi-audience without azp rejected', is_wp_error( $result ) && $result->get_error_code() === 'agentid_claim_azp' );

// 11. Multiple audiences with correct azp accepted.
$multi_ok_claims        = $multi_claims;
$multi_ok_claims['azp'] = $client_id;
$multi_ok_token         = make_token( $key_res, $kid, $multi_ok_claims );
$result                 = AgentID_OIDC::verify_id_token( $multi_ok_token, $jwks, $client_id, $nonce );
check( 'multi-audience with matching azp accepted', is_array( $result ) );

// 12. Multiple audiences with wrong azp rejected.
$multi_bad_claims        = $multi_claims;
$multi_bad_claims['azp'] = 'someone-else';
$multi_bad_token         = make_token( $key_res, $kid, $multi_bad_claims );
$result                  = AgentID_OIDC::verify_id_token( $multi_bad_token, $jwks, $client_id, $nonce );
check( 'multi-audience with wrong azp rejected', is_wp_error( $result ) && $result->get_error_code() === 'agentid_claim_azp' );

// 13. Missing iat rejected (iat is required on ID tokens).
$no_iat = $base;
unset( $no_iat['iat'] );
$no_iat_token = make_token( $key_res, $kid, $no_iat );
$result       = AgentID_OIDC::verify_id_token( $no_iat_token, $jwks, $client_id, $nonce );
check( 'missing iat rejected', is_wp_error( $result ) && $result->get_error_code() === 'agentid_claim_iat' );

// 14. Future nbf rejected.
$nbf_claims         = $base;
$nbf_claims['nbf']  = time() + 3600;
$nbf_token          = make_token( $key_res, $kid, $nbf_claims );
$result             = AgentID_OIDC::verify_id_token( $nbf_token, $jwks, $client_id, $nonce );
check( 'future nbf rejected', is_wp_error( $result ) && $result->get_error_code() === 'agentid_claim_nbf' );

// 15. Non-numeric iat rejected.
$bad_iat            = $base;
$bad_iat['iat']     = 'yesterday-ish';
$bad_iat_token      = make_token( $key_res, $kid, $bad_iat );
$result             = AgentID_OIDC::verify_id_token( $bad_iat_token, $jwks, $client_id, $nonce );
check( 'non-numeric iat rejected', is_wp_error( $result ) && $result->get_error_code() === 'agentid_claim_iat' );

// 16. Malformed (array-typed) audience value rejected.
$bad_aud          = $base;
$bad_aud['aud']   = array( array( 'nested' ) );
$bad_aud_token    = make_token( $key_res, $kid, $bad_aud );
$result           = AgentID_OIDC::verify_id_token( $bad_aud_token, $jwks, $client_id, $nonce );
check( 'malformed audience rejected', is_wp_error( $result ) && $result->get_error_code() === 'agentid_claim_aud' );

// 9. Real live AgentID JWKS shape: PEM builder handles any valid P-256 JWK.
$live_jwk = $jwks['keys'][0];
$pem      = AgentID_OIDC::jwk_ec_to_pem( $live_jwk );
check( 'JWK -> PEM builds', is_string( $pem ) && strpos( $pem, 'BEGIN PUBLIC KEY' ) !== false );

echo $failures === 0 ? "\nALL TESTS PASSED\n" : "\n$failures TEST(S) FAILED\n";
exit( $failures === 0 ? 0 : 1 );
