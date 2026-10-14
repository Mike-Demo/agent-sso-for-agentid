<?php
/**
 * Uninstall cleanup for Agent SSO for AgentID.
 *
 * Removes the plugin's options and cached transients. User accounts
 * created for agents are intentionally left in place — they belong to
 * the site — but their AgentID identity meta is removed so a later
 * reinstall starts from a clean slate.
 *
 * @package AgentSSOForAgentID
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'agentid_sso_options' );
delete_transient( 'agentid_sso_discovery' );
delete_transient( 'agentid_sso_jwks' );

$agentid_meta_keys = array(
	'agentid_is_agent',
	'agentid_sub',
	'agentid_actor_type',
	'agentid_owner_sub',
	'agentid_owner_email',
	'agentid_owner_name',
	'agentid_owner_user_id',
	'agentid_linked_sub',
	'agentid_last_login',
	'agentid_disabled',
);
foreach ( $agentid_meta_keys as $agentid_meta_key ) {
	delete_metadata( 'user', 0, $agentid_meta_key, '', true );
}
