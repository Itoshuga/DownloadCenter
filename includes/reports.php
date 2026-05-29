<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', 'ctd_ensure_stats_report_scheduled' );
add_action( 'admin_post_ctd_send_stats_report', 'ctd_handle_manual_stats_report' );
add_action( 'wp_ajax_ctd_send_stats_report', 'ctd_handle_ajax_stats_report' );
add_action( CTD_REPORT_CRON_HOOK, 'ctd_send_scheduled_stats_report' );
add_action( 'add_option_' . CTD_REPORT_SETTINGS_OPTION, 'ctd_reschedule_stats_report', 10, 0 );
add_action( 'update_option_' . CTD_REPORT_SETTINGS_OPTION, 'ctd_reschedule_stats_report', 10, 0 );

/**
 * @return DateTimeZone
 */
function ctd_get_report_timezone() {
	return new DateTimeZone( 'Europe/Paris' );
}

/**
 * @return string
 */
function ctd_get_report_current_mysql() {
	return ( new DateTimeImmutable( 'now', ctd_get_report_timezone() ) )->format( 'Y-m-d H:i:s' );
}

/**
 * @param int    $timestamp Timestamp.
 * @param string $format Date format.
 * @return string
 */
function ctd_format_report_timestamp( $timestamp, $format = '' ) {
	$format = $format ? $format : get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

	return wp_date( $format, absint( $timestamp ), ctd_get_report_timezone() );
}

/**
 * @param string $mysql_datetime MySQL datetime in Europe/Paris local time.
 * @return string
 */
function ctd_format_report_mysql_datetime( $mysql_datetime ) {
	if ( ! $mysql_datetime ) {
		return '';
	}

	try {
		$date = new DateTimeImmutable( (string) $mysql_datetime, ctd_get_report_timezone() );
	} catch ( Exception $exception ) {
		return '';
	}

	return ctd_format_report_timestamp( $date->getTimestamp() );
}

/**
 * @return array<string, string>
 */
function ctd_get_report_frequencies() {
	return array(
		'manual'  => __( 'Manuel uniquement', 'centre-telechargement' ),
		'weekly'  => __( 'Hebdomadaire - tous les lundis', 'centre-telechargement' ),
		'monthly' => __( 'Mensuel - tous les 1er du mois', 'centre-telechargement' ),
	);
}

/**
 * @return array<string, string>
 */
function ctd_get_report_settings_defaults() {
	$site_name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$admin_email = (string) get_option( 'admin_email' );

	return array(
		'sender_name'     => $site_name ? $site_name : __( 'Centre de Téléchargement', 'centre-telechargement' ),
		'sender_email'    => is_email( $admin_email ) ? $admin_email : '',
		'recipient_email' => is_email( $admin_email ) ? $admin_email : '',
		'frequency'       => 'manual',
	);
}

/**
 * @return array<string, string>
 */
function ctd_get_report_settings() {
	$settings = get_option( CTD_REPORT_SETTINGS_OPTION, array() );

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return ctd_sanitize_report_settings( wp_parse_args( $settings, ctd_get_report_settings_defaults() ) );
}

/**
 * @param mixed $settings Settings candidate.
 * @return array<string, string>
 */
function ctd_sanitize_report_settings( $settings ) {
	$defaults    = ctd_get_report_settings_defaults();
	$frequencies = ctd_get_report_frequencies();

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	$frequency = isset( $settings['frequency'] ) ? sanitize_key( (string) $settings['frequency'] ) : $defaults['frequency'];

	if ( ! isset( $frequencies[ $frequency ] ) ) {
		$frequency = $defaults['frequency'];
	}

	return array(
		'sender_name'     => ctd_sanitize_mail_name_setting( $settings['sender_name'] ?? $defaults['sender_name'], $defaults['sender_name'] ),
		'sender_email'    => ctd_sanitize_email_setting( $settings['sender_email'] ?? $defaults['sender_email'], $defaults['sender_email'] ),
		'recipient_email' => ctd_sanitize_email_setting( $settings['recipient_email'] ?? $defaults['recipient_email'], $defaults['recipient_email'] ),
		'frequency'       => $frequency,
	);
}

/**
 * @param mixed  $value Candidate sender name.
 * @param string $fallback Fallback sender name.
 * @return string
 */
function ctd_sanitize_mail_name_setting( $value, $fallback ) {
	$value = sanitize_text_field( str_replace( array( "\r", "\n" ), '', (string) $value ) );

	return '' !== $value ? $value : $fallback;
}

/**
 * @param mixed  $value Candidate email.
 * @param string $fallback Fallback email.
 * @return string
 */
function ctd_sanitize_email_setting( $value, $fallback ) {
	$value = sanitize_email( (string) $value );

	if ( is_email( $value ) ) {
		return $value;
	}

	return is_email( $fallback ) ? $fallback : '';
}

/**
 * @return void
 */
function ctd_ensure_stats_report_scheduled() {
	$settings = ctd_get_report_settings();

	if ( 'manual' === $settings['frequency'] ) {
		return;
	}

	if ( ! wp_next_scheduled( CTD_REPORT_CRON_HOOK ) ) {
		ctd_schedule_stats_report( $settings['frequency'] );
	}
}

/**
 * @return void
 */
function ctd_reschedule_stats_report() {
	wp_clear_scheduled_hook( CTD_REPORT_CRON_HOOK );

	$settings = ctd_get_report_settings();

	if ( 'manual' !== $settings['frequency'] ) {
		ctd_schedule_stats_report( $settings['frequency'] );
	}
}

/**
 * @param string $frequency Report frequency.
 * @return void
 */
function ctd_schedule_stats_report( $frequency ) {
	$timestamp = ctd_get_next_stats_report_timestamp( $frequency );

	if ( $timestamp ) {
		wp_schedule_single_event( $timestamp, CTD_REPORT_CRON_HOOK );
	}
}

/**
 * @param string $frequency Report frequency.
 * @param int    $from Timestamp used as base.
 * @return int
 */
function ctd_get_next_stats_report_timestamp( $frequency, $from = 0 ) {
	$frequency = sanitize_key( (string) $frequency );

	if ( ! in_array( $frequency, array( 'weekly', 'monthly' ), true ) ) {
		return 0;
	}

	$timezone = ctd_get_report_timezone();
	$now      = $from
		? ( new DateTimeImmutable( '@' . absint( $from ) ) )->setTimezone( $timezone )
		: new DateTimeImmutable( 'now', $timezone );

	if ( 'weekly' === $frequency ) {
		$candidate = $now->modify( 'monday this week' )->setTime( 8, 0, 0 );

		if ( $candidate->getTimestamp() <= $now->getTimestamp() ) {
			$candidate = $candidate->modify( '+1 week' );
		}

		return $candidate->getTimestamp();
	}

	$candidate = $now->modify( 'first day of this month' )->setTime( 8, 0, 0 );

	if ( $candidate->getTimestamp() <= $now->getTimestamp() ) {
		$candidate = $candidate->modify( 'first day of next month' );
	}

	return $candidate->getTimestamp();
}

/**
 * @return int|false
 */
function ctd_get_next_scheduled_stats_report_timestamp() {
	return wp_next_scheduled( CTD_REPORT_CRON_HOOK );
}

/**
 * @return array<string, mixed>
 */
function ctd_get_stats_report_last_run() {
	$last_run = get_option( CTD_REPORT_LAST_RUN_OPTION, array() );

	return is_array( $last_run ) ? $last_run : array();
}

/**
 * @return void
 */
function ctd_send_scheduled_stats_report() {
	$settings = ctd_get_report_settings();

	if ( 'manual' !== $settings['frequency'] ) {
		ctd_send_stats_report( $settings['frequency'], 'scheduled' );
	}

	ctd_reschedule_stats_report();
}

/**
 * @return void
 */
function ctd_handle_manual_stats_report() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'centre-telechargement' ), 403 );
	}

	check_admin_referer( 'ctd_send_stats_report' );

	$result   = ctd_send_stats_report( 'manual', 'manual' );
	$status   = is_wp_error( $result ) ? 'error' : 'sent';
	$redirect = add_query_arg(
		array(
			'post_type'         => CTD_POST_TYPE,
			'page'              => 'ctd-settings',
			'ctd_report_status' => $status,
		),
		admin_url( 'edit.php' )
	);

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * @return void
 */
function ctd_handle_ajax_stats_report() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Accès refusé.', 'centre-telechargement' ),
			),
			403
		);
	}

	if ( ! check_ajax_referer( 'ctd_send_stats_report', '_ajax_nonce', false ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'La vérification de sécurité a échoué. Rechargez la page puis réessayez.', 'centre-telechargement' ),
			),
			403
		);
	}

	$started_at = microtime( true );
	$result     = ctd_send_stats_report( 'manual', 'manual' );
	$elapsed    = round( microtime( true ) - $started_at, 2 );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array(
				'message' => $result->get_error_message(),
				'elapsed' => $elapsed,
			),
			500
		);
	}

	$last_run = ctd_get_stats_report_last_run();
	$message  = isset( $last_run['message'] ) && $last_run['message']
		? (string) $last_run['message']
		: __( 'Rapport envoyé avec succès.', 'centre-telechargement' );

	wp_send_json_success(
		array(
			'message' => $message,
			'elapsed' => $elapsed,
		)
	);
}

/**
 * @return void
 */
function ctd_render_stats_report_admin_notice() {
	$status = isset( $_GET['ctd_report_status'] ) ? sanitize_key( wp_unslash( $_GET['ctd_report_status'] ) ) : '';

	if ( ! $status ) {
		return;
	}

	$last_run = ctd_get_stats_report_last_run();
	$message  = isset( $last_run['message'] ) ? (string) $last_run['message'] : '';

	if ( 'sent' === $status ) {
		$message = $message ? $message : __( 'Rapport envoyé avec succès.', 'centre-telechargement' );
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
		return;
	}

	$message = $message ? $message : __( 'Le rapport n’a pas pu être envoyé. Vérifiez les adresses email configurées.', 'centre-telechargement' );
	printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
}

/**
 * @param string $frequency Report frequency.
 * @param string $trigger Manual or scheduled.
 * @return true|WP_Error
 */
function ctd_send_stats_report( $frequency = 'manual', $trigger = 'manual' ) {
	$settings = ctd_get_report_settings();

	if ( ! is_email( $settings['sender_email'] ) || ! is_email( $settings['recipient_email'] ) ) {
		$error = new WP_Error( 'ctd_report_invalid_email', __( 'Adresse expéditeur ou destinataire invalide.', 'centre-telechargement' ) );
		ctd_store_stats_report_last_run( 'error', $trigger, $error->get_error_message() );
		return $error;
	}

	$report  = ctd_get_stats_report_data( $frequency );
	$subject = sprintf(
		/* translators: %s: report period label. */
		__( 'Rapport statistiques documents - %s', 'centre-telechargement' ),
		$report['period']['label']
	);
	$html              = ctd_render_stats_report_email_html( $report, $settings );
	$report_attachment = ctd_create_stats_report_spreadsheet_attachment( $report );

	if ( is_wp_error( $report_attachment ) ) {
		ctd_store_stats_report_last_run( 'error', $trigger, $report_attachment->get_error_message() );
		return $report_attachment;
	}

	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		sprintf( 'From: %1$s <%2$s>', $settings['sender_name'], $settings['sender_email'] ),
		sprintf( 'Reply-To: %1$s <%2$s>', $settings['sender_name'], $settings['sender_email'] ),
	);
	$sent    = wp_mail( $settings['recipient_email'], $subject, $html, $headers, array( $report_attachment ) );

	ctd_delete_stats_report_attachment( $report_attachment );

	if ( ! $sent ) {
		$error = new WP_Error( 'ctd_report_mail_failed', __( 'WordPress n’a pas confirmé l’envoi du rapport.', 'centre-telechargement' ) );
		ctd_store_stats_report_last_run( 'error', $trigger, $error->get_error_message() );
		return $error;
	}

	ctd_store_stats_report_last_run(
		'sent',
		$trigger,
		sprintf(
			/* translators: %s: recipient email. */
			__( 'Rapport envoyé à %s.', 'centre-telechargement' ),
			$settings['recipient_email']
		)
	);

	return true;
}

/**
 * @param string $status Delivery status.
 * @param string $trigger Delivery trigger.
 * @param string $message Delivery message.
 * @return void
 */
function ctd_store_stats_report_last_run( $status, $trigger, $message ) {
	update_option(
		CTD_REPORT_LAST_RUN_OPTION,
		array(
			'status'     => sanitize_key( $status ),
			'trigger'    => sanitize_key( $trigger ),
			'message'    => sanitize_text_field( $message ),
			'occurred_at' => ctd_get_report_current_mysql(),
		),
		false
	);
}

/**
 * @param array<string, mixed> $report Report data.
 * @return string|WP_Error
 */
function ctd_create_stats_report_spreadsheet_attachment( $report ) {
	$upload_dir = wp_upload_dir();

	if ( ! empty( $upload_dir['error'] ) ) {
		return new WP_Error( 'ctd_report_attachment_upload_dir', $upload_dir['error'] );
	}

	$directory = trailingslashit( $upload_dir['basedir'] ) . 'ctd-reports';

	if ( ! wp_mkdir_p( $directory ) ) {
		return new WP_Error( 'ctd_report_attachment_directory', __( 'Impossible de créer le dossier temporaire du rapport.', 'centre-telechargement' ) );
	}

	$filename  = sanitize_file_name( 'rapport-statistiques-documents-' . ctd_format_report_timestamp( time(), 'Y-m-d-His' ) . '.xls' );
	$file_path = trailingslashit( $directory ) . $filename;
	$html      = ctd_render_stats_report_spreadsheet_html( $report );
	$written   = file_put_contents( $file_path, $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

	if ( false === $written ) {
		return new WP_Error( 'ctd_report_attachment_write', __( 'Impossible de générer le fichier du rapport.', 'centre-telechargement' ) );
	}

	return $file_path;
}

/**
 * @param array<string, mixed> $report Report data.
 * @return string
 */
function ctd_render_stats_report_spreadsheet_html( $report ) {
	$period_label = isset( $report['period']['label'] ) ? (string) $report['period']['label'] : '';
	$generated    = isset( $report['generated'] ) ? (string) $report['generated'] : '';
	$rows         = isset( $report['rows'] ) && is_array( $report['rows'] ) ? $report['rows'] : array();

	ob_start();
	?>
	<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<style>
			body { font-family: Arial, Helvetica, sans-serif; color: #10233f; }
			.report-title { background: #10233f; color: #ffffff; font-size: 20px; font-weight: 700; }
			.report-meta-label { background: #edf8fb; color: #0b88ad; font-weight: 700; }
			.report-meta-value { background: #f8fbfd; color: #10233f; }
			.report-head { background: #11a9cf; color: #ffffff; font-weight: 700; text-align: center; }
			.report-head-document { text-align: left; }
			.report-row-even { background: #ffffff; }
			.report-row-odd { background: #f8fbfd; }
			.report-document { font-weight: 700; text-align: left; }
			.report-number { text-align: center; }
			.report-total { background: #edf8fb; color: #0b88ad; font-weight: 700; text-align: center; }
			.report-date { color: #5b6d7d; }
			td, th { border: 1px solid #d7e4ec; padding: 8px; vertical-align: middle; }
		</style>
	</head>
	<body>
		<table cellspacing="0" cellpadding="0">
			<tr>
				<td class="report-title" colspan="5"><?php esc_html_e( 'Rapport statistiques documents', 'centre-telechargement' ); ?></td>
			</tr>
			<tr>
				<td class="report-meta-label"><?php esc_html_e( 'Période', 'centre-telechargement' ); ?></td>
				<td class="report-meta-value" colspan="4"><?php echo esc_html( $period_label ); ?></td>
			</tr>
			<tr>
				<td class="report-meta-label"><?php esc_html_e( 'Généré le', 'centre-telechargement' ); ?></td>
				<td class="report-meta-value" colspan="4"><?php echo esc_html( $generated ); ?></td>
			</tr>
			<tr>
				<td colspan="5"></td>
			</tr>
			<tr>
				<th class="report-head report-head-document"><?php esc_html_e( 'Document', 'centre-telechargement' ); ?></th>
				<th class="report-head"><?php esc_html_e( 'Ouverture', 'centre-telechargement' ); ?></th>
				<th class="report-head"><?php esc_html_e( 'Téléchargement', 'centre-telechargement' ); ?></th>
				<th class="report-head"><?php esc_html_e( 'Total', 'centre-telechargement' ); ?></th>
				<th class="report-head"><?php esc_html_e( 'Dernière activité', 'centre-telechargement' ); ?></th>
			</tr>
			<?php if ( empty( $rows ) ) : ?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'Aucun document trouvé.', 'centre-telechargement' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $rows as $index => $row ) : ?>
					<?php $period_total = absint( $row['period_open'] ) + absint( $row['period_download'] ); ?>
					<tr class="<?php echo 0 === $index % 2 ? 'report-row-even' : 'report-row-odd'; ?>">
						<td class="report-document"><?php echo esc_html( $row['title'] ?? '' ); ?></td>
						<td class="report-number"><?php echo esc_html( absint( $row['period_open'] ?? 0 ) ); ?></td>
						<td class="report-number"><?php echo esc_html( absint( $row['period_download'] ?? 0 ) ); ?></td>
						<td class="report-total"><?php echo esc_html( $period_total ); ?></td>
						<td class="report-date"><?php echo esc_html( $row['last_event_label'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</table>
	</body>
	</html>
	<?php

	return trim( ob_get_clean() );
}

/**
 * @param string $file_path Attachment path.
 * @return void
 */
function ctd_delete_stats_report_attachment( $file_path ) {
	if ( $file_path && file_exists( $file_path ) ) {
		unlink( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
	}
}

/**
 * @param string $frequency Report frequency.
 * @return array<string, mixed>
 */
function ctd_get_stats_report_data( $frequency ) {
	$period      = ctd_get_stats_report_period( $frequency );
	$documents   = ctd_get_stats_report_documents();
	$document_ids = array_map( 'absint', wp_list_pluck( $documents, 'ID' ) );
	$period_counts = ctd_get_stats_report_event_counts( $document_ids, $period['start_mysql'], $period['end_mysql'] );
	$total_counts  = ctd_get_stats_report_event_counts( $document_ids );
	$last_events   = ctd_get_stats_report_last_event_dates( $document_ids );
	$rows          = array();
	$totals        = array(
		'documents'        => count( $documents ),
		'period_open'      => 0,
		'period_download'  => 0,
		'total_open'       => 0,
		'total_download'   => 0,
	);

	foreach ( $documents as $document ) {
		$document_id     = absint( $document->ID );
		$period_open     = ctd_get_stats_report_count_value( $period_counts, $document_id, 'open' );
		$period_download = ctd_get_stats_report_count_value( $period_counts, $document_id, 'download' );
		$total_open      = ctd_get_stats_report_count_value( $total_counts, $document_id, 'open' );
		$total_download  = ctd_get_stats_report_count_value( $total_counts, $document_id, 'download' );
		$status_object   = get_post_status_object( $document->post_status );
		$category_names  = wp_get_post_terms( $document_id, CTD_TAXONOMY, array( 'fields' => 'names' ) );
		$last_event      = isset( $last_events[ $document_id ] ) ? $last_events[ $document_id ] : '';

		if ( is_wp_error( $category_names ) ) {
			$category_names = array();
		}

		$rows[] = array(
			'id'              => $document_id,
			'title'           => get_the_title( $document ),
			'edit_url'        => get_edit_post_link( $document_id, '' ),
			'status'          => $status_object ? $status_object->label : $document->post_status,
			'categories'      => implode( ', ', $category_names ),
			'period_open'     => $period_open,
			'period_download' => $period_download,
			'total_open'      => $total_open,
			'total_download'  => $total_download,
			'last_event'      => $last_event,
			'last_event_label' => $last_event ? ctd_format_report_mysql_datetime( $last_event ) : __( 'Aucune', 'centre-telechargement' ),
		);

		$totals['period_open']     += $period_open;
		$totals['period_download'] += $period_download;
		$totals['total_open']      += $total_open;
		$totals['total_download']  += $total_download;
	}

	usort( $rows, 'ctd_sort_stats_report_rows' );

	return array(
		'period'    => $period,
		'frequency' => ctd_get_report_frequencies()[ ctd_normalize_report_frequency( $frequency ) ],
		'generated' => ctd_format_report_timestamp( time() ),
		'totals'    => $totals,
		'top_rows'  => array_slice( $rows, 0, 5 ),
		'rows'      => $rows,
	);
}

/**
 * @param mixed $frequency Report frequency.
 * @return string
 */
function ctd_normalize_report_frequency( $frequency ) {
	$frequency   = sanitize_key( (string) $frequency );
	$frequencies = ctd_get_report_frequencies();

	return isset( $frequencies[ $frequency ] ) ? $frequency : 'manual';
}

/**
 * @param string $frequency Report frequency.
 * @return array<string, string>
 */
function ctd_get_stats_report_period( $frequency ) {
	$frequency = ctd_normalize_report_frequency( $frequency );
	$timezone  = ctd_get_report_timezone();
	$now       = new DateTimeImmutable( 'now', $timezone );

	if ( 'weekly' === $frequency ) {
		$this_monday = $now->modify( 'monday this week' )->setTime( 0, 0, 0 );
		$start       = $this_monday->modify( '-7 days' );
		$end         = $this_monday->modify( '-1 second' );
	} elseif ( 'monthly' === $frequency ) {
		$this_month = $now->modify( 'first day of this month' )->setTime( 0, 0, 0 );
		$start      = $this_month->modify( '-1 month' );
		$end        = $this_month->modify( '-1 second' );
	} else {
		$start = $now->modify( '-29 days' )->setTime( 0, 0, 0 );
		$end   = $now->setTime( 23, 59, 59 );
	}

	return array(
		'start_mysql' => $start->format( 'Y-m-d H:i:s' ),
		'end_mysql'   => $end->format( 'Y-m-d H:i:s' ),
		'label'       => sprintf(
			/* translators: 1: start date, 2: end date. */
			__( 'du %1$s au %2$s', 'centre-telechargement' ),
			ctd_format_report_timestamp( $start->getTimestamp(), get_option( 'date_format' ) ),
			ctd_format_report_timestamp( $end->getTimestamp(), get_option( 'date_format' ) )
		),
	);
}

/**
 * @return array<int, WP_Post>
 */
function ctd_get_stats_report_documents() {
	return get_posts(
		array(
			'post_type'      => CTD_POST_TYPE,
			'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
}

/**
 * @param array<int> $document_ids Document IDs.
 * @param string     $start_mysql Start date.
 * @param string     $end_mysql End date.
 * @return array<int, array<string, int>>
 */
function ctd_get_stats_report_event_counts( $document_ids, $start_mysql = '', $end_mysql = '' ) {
	global $wpdb;

	$document_ids = ctd_sanitize_document_id_list( $document_ids );

	if ( empty( $document_ids ) ) {
		return array();
	}

	ctd_maybe_create_analytics_table();

	$table_name   = ctd_get_analytics_table_name();
	$placeholders = implode( ', ', array_fill( 0, count( $document_ids ), '%d' ) );
	$args         = $document_ids;
	$sql          = "SELECT document_id, event_type, COUNT(id) AS event_count
		FROM {$table_name}
		WHERE document_id IN ({$placeholders})";

	if ( $start_mysql ) {
		$sql   .= ' AND occurred_at >= %s';
		$args[] = $start_mysql;
	}

	if ( $end_mysql ) {
		$sql   .= ' AND occurred_at <= %s';
		$args[] = $end_mysql;
	}

	$sql .= ' GROUP BY document_id, event_type';
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

	if ( ! is_array( $rows ) ) {
		return array();
	}

	$counts = array();

	foreach ( $rows as $row ) {
		$document_id = isset( $row['document_id'] ) ? absint( $row['document_id'] ) : 0;
		$event_type  = ctd_normalize_document_event_type( isset( $row['event_type'] ) ? $row['event_type'] : 'open' );

		if ( ! $document_id ) {
			continue;
		}

		if ( ! isset( $counts[ $document_id ] ) ) {
			$counts[ $document_id ] = array();
		}

		$counts[ $document_id ][ $event_type ] = isset( $row['event_count'] ) ? absint( $row['event_count'] ) : 0;
	}

	return $counts;
}

/**
 * @param array<int> $document_ids Document IDs.
 * @return array<int, string>
 */
function ctd_get_stats_report_last_event_dates( $document_ids ) {
	global $wpdb;

	$document_ids = ctd_sanitize_document_id_list( $document_ids );

	if ( empty( $document_ids ) ) {
		return array();
	}

	ctd_maybe_create_analytics_table();

	$table_name   = ctd_get_analytics_table_name();
	$placeholders = implode( ', ', array_fill( 0, count( $document_ids ), '%d' ) );
	$rows         = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT document_id, MAX(occurred_at) AS last_event
			FROM {$table_name}
			WHERE document_id IN ({$placeholders})
			GROUP BY document_id",
			$document_ids
		),
		ARRAY_A
	);

	if ( ! is_array( $rows ) ) {
		return array();
	}

	$dates = array();

	foreach ( $rows as $row ) {
		$document_id = isset( $row['document_id'] ) ? absint( $row['document_id'] ) : 0;

		if ( $document_id && ! empty( $row['last_event'] ) ) {
			$dates[ $document_id ] = (string) $row['last_event'];
		}
	}

	return $dates;
}

/**
 * @param mixed $document_ids Document IDs.
 * @return array<int>
 */
function ctd_sanitize_document_id_list( $document_ids ) {
	if ( ! is_array( $document_ids ) ) {
		$document_ids = array( $document_ids );
	}

	$document_ids = array_map( 'absint', $document_ids );
	$document_ids = array_filter( $document_ids );
	$document_ids = array_unique( $document_ids );

	return array_values( $document_ids );
}

/**
 * @param array<int, array<string, int>> $counts Counts by document.
 * @param int                           $document_id Document ID.
 * @param string                        $event_type Event type.
 * @return int
 */
function ctd_get_stats_report_count_value( $counts, $document_id, $event_type ) {
	$document_id = absint( $document_id );
	$event_type  = ctd_normalize_document_event_type( $event_type );

	return isset( $counts[ $document_id ][ $event_type ] ) ? absint( $counts[ $document_id ][ $event_type ] ) : 0;
}

/**
 * @param array<string, mixed> $a First row.
 * @param array<string, mixed> $b Second row.
 * @return int
 */
function ctd_sort_stats_report_rows( $a, $b ) {
	$a_downloads = absint( $a['period_download'] );
	$b_downloads = absint( $b['period_download'] );

	if ( $a_downloads !== $b_downloads ) {
		return $b_downloads - $a_downloads;
	}

	$a_opens = absint( $a['period_open'] );
	$b_opens = absint( $b['period_open'] );

	if ( $a_opens !== $b_opens ) {
		return $b_opens - $a_opens;
	}

	return strcasecmp( (string) $a['title'], (string) $b['title'] );
}

/**
 * @param array<string, mixed>  $report Report data.
 * @param array<string, string> $settings Report settings.
 * @return string
 */
function ctd_render_stats_report_email_html( $report, $settings ) {
	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$rows      = isset( $report['top_rows'] ) && is_array( $report['top_rows'] ) ? $report['top_rows'] : array();
	$totals    = isset( $report['totals'] ) && is_array( $report['totals'] ) ? $report['totals'] : array();
	$period    = isset( $report['period'] ) && is_array( $report['period'] ) ? $report['period'] : array();

	ob_start();
	?>
	<!doctype html>
	<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?php esc_html_e( 'Rapport statistiques documents', 'centre-telechargement' ); ?></title>
	</head>
	<body style="margin:0;padding:0;background:#edf3f7;font-family:Arial,Helvetica,sans-serif;color:#10233f;">
		<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#edf3f7;padding:28px 12px;">
			<tr>
				<td align="center">
					<table role="presentation" width="760" cellspacing="0" cellpadding="0" style="width:100%;max-width:760px;background:#ffffff;border:1px solid #d7e4ec;border-radius:14px;overflow:hidden;box-shadow:0 18px 44px rgba(16,35,63,0.10);">
						<tr>
							<td style="background:#10233f;padding:28px 30px;">
								<div style="font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#8be4f6;margin-bottom:9px;"><?php echo esc_html( $site_name ); ?></div>
								<h1 style="margin:0;color:#ffffff;font-size:26px;line-height:1.25;font-weight:800;"><?php esc_html_e( 'Rapport statistiques documents', 'centre-telechargement' ); ?></h1>
								<p style="margin:10px 0 0;color:#d8e8f0;font-size:14px;line-height:1.5;">
									<?php echo esc_html( isset( $period['label'] ) ? $period['label'] : '' ); ?> · <?php echo esc_html( $report['frequency'] ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<td style="padding:24px 30px 8px;">
								<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;">
									<tr>
										<?php
										ctd_render_stats_report_email_metric( __( 'Documents', 'centre-telechargement' ), $totals['documents'] ?? 0 );
										ctd_render_stats_report_email_metric( __( 'Ouverture', 'centre-telechargement' ), $totals['period_open'] ?? 0 );
										ctd_render_stats_report_email_metric( __( 'Téléchargement', 'centre-telechargement' ), $totals['period_download'] ?? 0 );
										?>
									</tr>
								</table>
							</td>
						</tr>
						<tr>
							<td style="padding:12px 30px 28px;">
								<h2 style="margin:0 0 8px;color:#10233f;font-size:18px;line-height:1.3;font-weight:800;"><?php esc_html_e( 'Top 5 des fichiers les plus téléchargés', 'centre-telechargement' ); ?></h2>
								<p style="margin:0 0 16px;color:#5b6d7d;font-size:13px;line-height:1.5;">
									<?php esc_html_e( 'L’email affiche uniquement les 5 documents les plus téléchargés sur la période. Consultez le fichier Excel joint pour voir le détail de tous les documents.', 'centre-telechargement' ); ?>
								</p>
								<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:separate;border-spacing:0;border:1px solid #d7e4ec;border-radius:10px;overflow:hidden;">
									<thead>
										<tr>
											<th align="left" style="background:#f8fbfd;color:#10233f;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;padding:12px;border-bottom:1px solid #d7e4ec;"><?php esc_html_e( 'Document', 'centre-telechargement' ); ?></th>
											<th align="center" style="background:#f8fbfd;color:#10233f;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;padding:12px;border-bottom:1px solid #d7e4ec;"><?php esc_html_e( 'Ouverture', 'centre-telechargement' ); ?></th>
											<th align="center" style="background:#f8fbfd;color:#10233f;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;padding:12px;border-bottom:1px solid #d7e4ec;"><?php esc_html_e( 'Téléchargement', 'centre-telechargement' ); ?></th>
											<th align="center" style="background:#f8fbfd;color:#10233f;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;padding:12px;border-bottom:1px solid #d7e4ec;"><?php esc_html_e( 'Total', 'centre-telechargement' ); ?></th>
											<th align="left" style="background:#f8fbfd;color:#10233f;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;padding:12px;border-bottom:1px solid #d7e4ec;"><?php esc_html_e( 'Dernière activité', 'centre-telechargement' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php if ( empty( $rows ) ) : ?>
											<tr>
												<td colspan="5" style="padding:18px;color:#5b6d7d;font-size:14px;text-align:center;"><?php esc_html_e( 'Aucun document trouvé.', 'centre-telechargement' ); ?></td>
											</tr>
										<?php else : ?>
											<?php foreach ( $rows as $row ) : ?>
												<?php ctd_render_stats_report_email_row( $row ); ?>
											<?php endforeach; ?>
										<?php endif; ?>
									</tbody>
								</table>
							</td>
						</tr>
						<tr>
							<td style="background:#f8fbfd;border-top:1px solid #d7e4ec;padding:18px 30px;color:#5b6d7d;font-size:12px;line-height:1.5;">
								<?php
								printf(
									/* translators: %s: generated date. */
									esc_html__( 'Rapport généré automatiquement le %s.', 'centre-telechargement' ),
									esc_html( $report['generated'] )
								);
								?>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</body>
	</html>
	<?php

	return trim( ob_get_clean() );
}

/**
 * @param string $label Metric label.
 * @param int    $value Metric value.
 * @return void
 */
function ctd_render_stats_report_email_metric( $label, $value ) {
	?>
	<td width="33.333%" style="padding:0 6px 12px;">
		<div style="background:#f8fbfd;border:1px solid #d7e4ec;border-radius:10px;padding:16px;">
			<div style="color:#5b6d7d;font-size:11px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;margin-bottom:8px;"><?php echo esc_html( $label ); ?></div>
			<div style="color:#10233f;font-size:26px;font-weight:800;line-height:1;"><?php echo esc_html( number_format_i18n( absint( $value ) ) ); ?></div>
		</div>
	</td>
	<?php
}

/**
 * @param array<string, mixed> $row Report row.
 * @return void
 */
function ctd_render_stats_report_email_row( $row ) {
	$total_period = absint( $row['period_open'] ) + absint( $row['period_download'] );
	$edit_url     = ! empty( $row['edit_url'] ) ? (string) $row['edit_url'] : '';
	?>
	<tr>
		<td style="padding:13px 12px;border-bottom:1px solid #e0ebf1;vertical-align:top;">
			<div style="font-size:14px;font-weight:800;color:#10233f;line-height:1.35;">
				<?php if ( $edit_url ) : ?>
					<a href="<?php echo esc_url( $edit_url ); ?>" style="color:#10233f;text-decoration:none;"><?php echo esc_html( $row['title'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $row['title'] ); ?>
				<?php endif; ?>
			</div>
		</td>
		<td align="center" style="padding:13px 12px;border-bottom:1px solid #e0ebf1;vertical-align:top;color:#10233f;font-weight:800;"><?php echo esc_html( number_format_i18n( absint( $row['period_open'] ) ) ); ?></td>
		<td align="center" style="padding:13px 12px;border-bottom:1px solid #e0ebf1;vertical-align:top;color:#10233f;font-weight:800;"><?php echo esc_html( number_format_i18n( absint( $row['period_download'] ) ) ); ?></td>
		<td align="center" style="padding:13px 12px;border-bottom:1px solid #e0ebf1;vertical-align:top;">
			<span style="display:inline-block;background:#edf8fb;color:#0b88ad;border-radius:999px;padding:4px 9px;font-size:12px;font-weight:800;"><?php echo esc_html( number_format_i18n( $total_period ) ); ?></span>
		</td>
		<td style="padding:13px 12px;border-bottom:1px solid #e0ebf1;vertical-align:top;color:#5b6d7d;font-size:12px;line-height:1.4;"><?php echo esc_html( $row['last_event_label'] ); ?></td>
	</tr>
	<?php
}
