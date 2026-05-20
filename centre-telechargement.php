<?php
/**
 * Plugin Name: Centre de téléchargement
 * Description: Socle admin pour gérer des documents PDF catégorisés, publics ou protégés.
 * Version: 0.1.0
 * Author: Technique
 * Text Domain: centre-telechargement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CTD_VERSION', '0.1.0' );
define( 'CTD_PLUGIN_FILE', __FILE__ );
define( 'CTD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CTD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CTD_POST_TYPE', 'download_document' );
define( 'CTD_TAXONOMY', 'download_category' );
define( 'CTD_META_FILE_ID', '_ctd_pdf_file_id' );
define( 'CTD_META_STATUS', '_ctd_document_status' );

/**
 * Capabilities mapped to administrators through manage_options.
 *
 * @return array<string, string>
 */
function ctd_get_admin_only_post_capabilities() {
	return array(
		'edit_post'              => 'manage_options',
		'read_post'              => 'manage_options',
		'delete_post'            => 'manage_options',
		'edit_posts'             => 'manage_options',
		'edit_others_posts'      => 'manage_options',
		'delete_posts'           => 'manage_options',
		'publish_posts'          => 'manage_options',
		'read_private_posts'     => 'manage_options',
		'create_posts'           => 'manage_options',
		'delete_private_posts'   => 'manage_options',
		'delete_published_posts' => 'manage_options',
		'delete_others_posts'    => 'manage_options',
		'edit_private_posts'     => 'manage_options',
		'edit_published_posts'   => 'manage_options',
	);
}

/**
 * @return array<string, string>
 */
function ctd_get_document_statuses() {
	return array(
		'public'    => __( 'Public', 'centre-telechargement' ),
		'protected' => __( 'Protégé', 'centre-telechargement' ),
	);
}

/**
 * @param mixed  $status Status candidate.
 * @param string $fallback Fallback status.
 * @return string
 */
function ctd_normalize_document_status( $status, $fallback = 'public' ) {
	$statuses = ctd_get_document_statuses();
	$status   = sanitize_key( (string) $status );

	if ( isset( $statuses[ $status ] ) ) {
		return $status;
	}

	return $fallback;
}

/**
 * @param mixed $status Status candidate.
 * @return string
 */
function ctd_sanitize_document_status( $status ) {
	return ctd_normalize_document_status( $status, 'public' );
}

/**
 * @param int $post_id Document post ID.
 * @return string
 */
function ctd_get_document_status( $post_id ) {
	$status = get_post_meta( $post_id, CTD_META_STATUS, true );

	return ctd_normalize_document_status( $status, 'public' );
}

/**
 * @param string $status Document status.
 * @return string
 */
function ctd_get_status_badge_html( $status ) {
	$status   = ctd_normalize_document_status( $status, 'public' );
	$statuses = ctd_get_document_statuses();

	return sprintf(
		'<span class="ctd-status-badge ctd-status-%1$s">%2$s</span>',
		esc_attr( $status ),
		esc_html( $statuses[ $status ] )
	);
}

require_once CTD_PLUGIN_DIR . 'includes/post-types.php';
require_once CTD_PLUGIN_DIR . 'includes/taxonomies.php';
require_once CTD_PLUGIN_DIR . 'includes/meta-boxes.php';
require_once CTD_PLUGIN_DIR . 'includes/admin-columns.php';

register_activation_hook( __FILE__, 'ctd_activate' );
register_deactivation_hook( __FILE__, 'ctd_deactivate' );

function ctd_activate() {
	ctd_register_post_type();
	ctd_register_taxonomy();
	flush_rewrite_rules();
}

function ctd_deactivate() {
	flush_rewrite_rules();
}

add_action( 'admin_enqueue_scripts', 'ctd_enqueue_admin_assets' );

/**
 * @param string $hook_suffix Current admin hook.
 * @return void
 */
function ctd_enqueue_admin_assets( $hook_suffix ) {
	$screen = get_current_screen();

	if ( ! $screen ) {
		return;
	}

	$is_document_post_type = isset( $screen->post_type ) && CTD_POST_TYPE === $screen->post_type;
	$is_document_taxonomy  = isset( $screen->taxonomy ) && CTD_TAXONOMY === $screen->taxonomy;

	if ( ! $is_document_post_type && ! $is_document_taxonomy ) {
		return;
	}

	wp_enqueue_style(
		'ctd-admin',
		CTD_PLUGIN_URL . 'assets/admin.css',
		array(),
		CTD_VERSION
	);

	if ( $is_document_post_type && in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
		wp_enqueue_media();
		wp_enqueue_script( 'jquery' );
		wp_add_inline_script( 'jquery', ctd_get_media_script() );
	}
}

/**
 * @return string
 */
function ctd_get_media_script() {
	return <<<'JS'
(function($) {
	'use strict';

	var mediaFrame = null;

	function renderSelection(attachment) {
		$('#ctd_pdf_file_id').val(attachment.id || '');
		$('#ctd-pdf-filename').text(attachment.filename || attachment.title || 'Document PDF');
		$('#ctd-pdf-link').attr('href', attachment.url || '#').toggleClass('hidden', !attachment.url);
		$('#ctd-remove-pdf').removeClass('hidden');
		$('#ctd-pdf-placeholder').addClass('hidden');
		$('.ctd-file-preview').addClass('has-file');
	}

	$(document).on('click', '.ctd-select-file', function(event) {
		event.preventDefault();

		if (mediaFrame) {
			mediaFrame.open();
			return;
		}

		mediaFrame = wp.media({
			title: 'Sélectionner un PDF',
			button: {
				text: 'Utiliser ce PDF'
			},
			multiple: false,
			library: {
				type: 'application/pdf'
			}
		});

		mediaFrame.on('select', function() {
			var attachment = mediaFrame.state().get('selection').first().toJSON();

			if (attachment.mime && attachment.mime !== 'application/pdf') {
				window.alert('Veuillez choisir un fichier PDF.');
				return;
			}

			renderSelection(attachment);
		});

		mediaFrame.open();
	});

	$(document).on('click', '.ctd-remove-file', function(event) {
		event.preventDefault();

		$('#ctd_pdf_file_id').val('');
		$('#ctd-pdf-filename').text('Aucun fichier sélectionné');
		$('#ctd-pdf-link').attr('href', '#').addClass('hidden');
		$('#ctd-remove-pdf').addClass('hidden');
		$('#ctd-pdf-placeholder').removeClass('hidden');
		$('.ctd-file-preview').removeClass('has-file');
	});
})(jQuery);
JS;
}
