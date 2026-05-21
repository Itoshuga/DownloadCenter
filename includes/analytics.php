<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', 'ctd_maybe_create_analytics_table' );

function ctd_get_analytics_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'ctd_document_events';
}

function ctd_maybe_create_analytics_table() {
	if ( CTD_VERSION === get_option( 'ctd_analytics_table_version' ) ) {
		return;
	}

	ctd_create_analytics_table();
}

function ctd_create_analytics_table() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table_name      = ctd_get_analytics_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		document_id bigint(20) unsigned NOT NULL,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		event_type varchar(20) NOT NULL DEFAULT 'open',
		occurred_at datetime NOT NULL,
		ip_address varchar(100) NOT NULL DEFAULT '',
		user_agent varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY document_id (document_id),
		KEY user_id (user_id),
		KEY event_type (event_type),
		KEY occurred_at (occurred_at)
	) {$charset_collate};";

	dbDelta( $sql );

	update_option( 'ctd_analytics_table_version', CTD_VERSION );
}

/**
 * @return array<string, string>
 */
function ctd_get_document_event_types() {
	return array(
		'open'     => __( 'Ouverture', 'centre-telechargement' ),
		'download' => __( 'Téléchargement', 'centre-telechargement' ),
	);
}

/**
 * @param mixed $event_type Event type candidate.
 * @return string
 */
function ctd_normalize_document_event_type( $event_type ) {
	$event_type = sanitize_key( (string) $event_type );
	$types      = ctd_get_document_event_types();

	return isset( $types[ $event_type ] ) ? $event_type : 'open';
}

/**
 * Records an opening or download event. Future front links can call this helper.
 *
 * @param int    $document_id Document post ID.
 * @param string $event_type Event type: open or download.
 * @param int    $user_id User ID. Defaults to current user.
 * @return int|false Inserted event ID or false.
 */
function ctd_log_document_event( $document_id, $event_type = 'open', $user_id = 0 ) {
	global $wpdb;

	$document_id = absint( $document_id );

	if ( ! $document_id || CTD_POST_TYPE !== get_post_type( $document_id ) ) {
		return false;
	}

	ctd_maybe_create_analytics_table();

	$user_id    = $user_id ? absint( $user_id ) : get_current_user_id();
	$event_type = ctd_normalize_document_event_type( $event_type );
	$ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	$inserted = $wpdb->insert(
		ctd_get_analytics_table_name(),
		array(
			'document_id' => $document_id,
			'user_id'     => $user_id,
			'event_type'  => $event_type,
			'occurred_at' => current_time( 'mysql' ),
			'ip_address'  => substr( $ip_address, 0, 100 ),
			'user_agent'  => substr( $user_agent, 0, 255 ),
		),
		array( '%d', '%d', '%s', '%s', '%s', '%s' )
	);

	if ( false === $inserted ) {
		return false;
	}

	return absint( $wpdb->insert_id );
}

/**
 * @param int    $document_id Document post ID.
 * @param int    $days Number of days to render.
 * @param string $event_type Event type.
 * @return array<int, array{date:string,label:string,count:int}>
 */
function ctd_get_document_daily_event_counts( $document_id, $days = 14, $event_type = 'open' ) {
	global $wpdb;

	ctd_maybe_create_analytics_table();

	$document_id = absint( $document_id );
	$days        = max( 1, min( 60, absint( $days ) ) );
	$event_type  = ctd_normalize_document_event_type( $event_type );
	$now         = current_time( 'timestamp' );
	$start       = strtotime( '-' . ( $days - 1 ) . ' days', $now );
	$items       = array();
	$index       = array();

	for ( $day = 0; $day < $days; $day++ ) {
		$timestamp = strtotime( '+' . $day . ' days', $start );
		$date      = wp_date( 'Y-m-d', $timestamp );

		$items[]        = array(
			'date'  => $date,
			'label' => wp_date( 'd/m', $timestamp ),
			'count' => 0,
		);
		$index[ $date ] = count( $items ) - 1;
	}

	$table_name = ctd_get_analytics_table_name();
	$start_date = wp_date( 'Y-m-d 00:00:00', $start );
	$rows       = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DATE(occurred_at) AS event_date, COUNT(id) AS event_count
			FROM {$table_name}
			WHERE document_id = %d
				AND event_type = %s
				AND occurred_at >= %s
			GROUP BY DATE(occurred_at)
			ORDER BY event_date ASC",
			$document_id,
			$event_type,
			$start_date
		),
		ARRAY_A
	);

	foreach ( $rows as $row ) {
		$date = isset( $row['event_date'] ) ? (string) $row['event_date'] : '';

		if ( isset( $index[ $date ] ) ) {
			$items[ $index[ $date ] ]['count'] = absint( $row['event_count'] );
		}
	}

	return $items;
}

/**
 * @param int $document_id Document post ID.
 * @param int $limit Number of events to return.
 * @return array<int, array{user_id:int,username:string,event_type:string,event_label:string,occurred_at:string,date_label:string}>
 */
function ctd_get_document_recent_events( $document_id, $limit = 30 ) {
	global $wpdb;

	ctd_maybe_create_analytics_table();

	$document_id  = absint( $document_id );
	$limit        = max( 1, min( 100, absint( $limit ) ) );
	$table_name   = ctd_get_analytics_table_name();
	$event_labels = array(
		'open'     => __( 'Ouvert', 'centre-telechargement' ),
		'download' => __( 'Téléchargé', 'centre-telechargement' ),
	);
	$rows         = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id, event_type, occurred_at
			FROM {$table_name}
			WHERE document_id = %d
			ORDER BY occurred_at DESC, id DESC
			LIMIT %d",
			$document_id,
			$limit
		),
		ARRAY_A
	);

	if ( empty( $rows ) ) {
		return array();
	}

	$events = array();

	foreach ( $rows as $row ) {
		$user_id    = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
		$user       = $user_id ? get_userdata( $user_id ) : false;
		$event_type = ctd_normalize_document_event_type( isset( $row['event_type'] ) ? $row['event_type'] : 'open' );
		$date       = isset( $row['occurred_at'] ) ? (string) $row['occurred_at'] : '';

		$events[] = array(
			'user_id'     => $user_id,
			'username'    => $user ? $user->user_login : ( $user_id ? __( 'Utilisateur supprimé', 'centre-telechargement' ) : __( 'Visiteur', 'centre-telechargement' ) ),
			'event_type'  => $event_type,
			'event_label' => $event_labels[ $event_type ],
			'occurred_at' => $date,
			'date_label'  => $date ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date ) : '',
		);
	}

	return $events;
}
