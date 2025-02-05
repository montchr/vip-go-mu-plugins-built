<?php

/**
 * Plugin Name: Query Monitor DB Connections
 * Description: Additional collector for Query Monitor for DB connection information.
 * Version: 1.0
 * Author: Automattic, Rebecca Hum
 */

if ( ! defined( 'VIP_GO_APP_ENVIRONMENT' ) ) {
	return;
}

add_action( 'plugins_loaded', 'register_qm_db_connections' );
function register_qm_db_connections() {
	if ( ! class_exists( 'QM_Collectors' ) ) {
		return;
	}

	require_once __DIR__ . '/class-qm-db-connections-collector.php';

	QM_Collectors::add( new QM_DB_Connections_Collector() );
	add_filter( 'qm/outputter/html', 'register_qm_db_connections_output', 120, 2 );
}

function register_qm_db_connections_output( array $output, \QM_Collectors $collectors ) {
	$collector = \QM_Collectors::get( 'qm-db-connections' );
	if ( $collector ) {
		require_once __DIR__ . '/class-qm-db-connections-output-html.php';

		$output['qm-db-connections'] = new QM_DB_Connections_Output( $collector );
	}
	return $output;
}
