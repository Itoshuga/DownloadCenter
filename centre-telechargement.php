<?php
/**
 * Plugin Name: Centre de téléchargement
 * Description: Socle admin pour gérer des documents PDF catégorisés, publics ou protégés.
 * Version: 0.1.0
 * Author: IMS ON LINE
 * Text Domain: centre-telechargement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CTD_VERSION', '0.1.0' );
define( 'CTD_PLUGIN_FILE', __FILE__ );
define( 'CTD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CTD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CTD_ADMIN_LOGO_URL', CTD_PLUGIN_URL . 'assets/images/icons/plasteurop_logo.svg' );
define( 'CTD_POST_TYPE', 'download_document' );
define( 'CTD_TAXONOMY', 'download_category' );
define( 'CTD_LANGUAGE_TAXONOMY', 'download_language' );
define( 'CTD_LANGUAGE_FLAG_META', '_ctd_language_flag' );
define( 'CTD_LANGUAGE_FLAGS_DIR', CTD_PLUGIN_DIR . 'assets/images/flags/' );
define( 'CTD_LANGUAGE_FLAGS_URL', CTD_PLUGIN_URL . 'assets/images/flags/' );
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

/**
 * @return array<string, array<string, string>>
 */
function ctd_get_default_languages() {
	return array(
		'fr' => array(
			'name' => __( 'Français', 'centre-telechargement' ),
			'slug' => 'fr',
			'flag' => 'fr_FR.png',
		),
		'en' => array(
			'name' => __( 'Anglais', 'centre-telechargement' ),
			'slug' => 'en',
			'flag' => 'en_UK.png',
		),
		'es' => array(
			'name' => __( 'Espagnol', 'centre-telechargement' ),
			'slug' => 'es',
			'flag' => 'es_ES.png',
		),
	);
}

/**
 * @param mixed $filename Flag filename.
 * @return string
 */
function ctd_sanitize_language_flag_filename( $filename ) {
	$filename = sanitize_file_name( (string) $filename );

	if ( ! preg_match( '/\.(png|jpe?g|gif|svg)$/i', $filename ) ) {
		return '';
	}

	return $filename;
}

/**
 * @return array<string, string>
 */
function ctd_get_available_language_flags() {
	$flags      = array();
	$extensions = array( 'png', 'jpg', 'jpeg', 'gif', 'svg' );

	foreach ( $extensions as $extension ) {
		$files = glob( CTD_LANGUAGE_FLAGS_DIR . '*.' . $extension );

		if ( ! is_array( $files ) ) {
			continue;
		}

		foreach ( $files as $file ) {
			$filename           = wp_basename( $file );
			$flags[ $filename ] = CTD_LANGUAGE_FLAGS_URL . rawurlencode( $filename );
		}
	}

	ksort( $flags, SORT_NATURAL | SORT_FLAG_CASE );

	return $flags;
}

/**
 * @param WP_Term|int $term Language term or term ID.
 * @return string
 */
function ctd_get_language_flag_filename( $term ) {
	if ( is_numeric( $term ) ) {
		$term = get_term( absint( $term ), CTD_LANGUAGE_TAXONOMY );
	}

	if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
		return '';
	}

	$flag = get_term_meta( $term->term_id, CTD_LANGUAGE_FLAG_META, true );

	if ( ! $flag ) {
		$default_languages = ctd_get_default_languages();

		if ( isset( $default_languages[ $term->slug ]['flag'] ) ) {
			$flag = $default_languages[ $term->slug ]['flag'];
		}
	}

	return ctd_sanitize_language_flag_filename( $flag );
}

/**
 * @param WP_Term|int $term Language term or term ID.
 * @return string
 */
function ctd_get_language_flag_url( $term ) {
	$filename = ctd_get_language_flag_filename( $term );

	if ( ! $filename || ! file_exists( CTD_LANGUAGE_FLAGS_DIR . $filename ) ) {
		return '';
	}

	return CTD_LANGUAGE_FLAGS_URL . rawurlencode( $filename );
}

/**
 * @param WP_Term|int $term Language term or term ID.
 * @return string
 */
function ctd_get_language_badge_html( $term ) {
	if ( is_numeric( $term ) ) {
		$term = get_term( absint( $term ), CTD_LANGUAGE_TAXONOMY );
	}

	if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
		return '';
	}

	$flag_url = ctd_get_language_flag_url( $term );
	$flag     = $flag_url
		? sprintf( '<img src="%1$s" alt="" loading="lazy" />', esc_url( $flag_url ) )
		: '';

	return sprintf(
		'<span class="ctd-language-badge">%1$s<span>%2$s</span></span>',
		$flag,
		esc_html( $term->name )
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
	ctd_seed_default_languages( true );
	flush_rewrite_rules();
}

function ctd_deactivate() {
	flush_rewrite_rules();
}

add_action( 'admin_enqueue_scripts', 'ctd_enqueue_admin_assets' );
add_action( 'admin_head', 'ctd_render_admin_menu_logo_styles' );

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
	$is_document_taxonomy  = isset( $screen->taxonomy )
		&& in_array( $screen->taxonomy, array( CTD_TAXONOMY, CTD_LANGUAGE_TAXONOMY ), true );

	if ( ! $is_document_post_type && ! $is_document_taxonomy ) {
		return;
	}

	wp_enqueue_style(
		'ctd-admin',
		CTD_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		CTD_VERSION
	);

	if ( $is_document_post_type && in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
		wp_enqueue_media();
		wp_enqueue_script( 'jquery' );
		wp_add_inline_script( 'jquery', ctd_get_media_script() );
	}
}

function ctd_render_admin_menu_logo_styles() {
	?>
	<style>
		#adminmenu #menu-posts-download_document .wp-menu-image img {
			box-sizing: border-box;
			height: 22px;
			object-fit: contain;
			opacity: 1;
			padding: 6px 0 0;
			width: 22px;
		}

		#adminmenu #menu-posts-download_document.current .wp-menu-image img,
		#adminmenu #menu-posts-download_document.wp-has-current-submenu .wp-menu-image img,
		#adminmenu #menu-posts-download_document:hover .wp-menu-image img {
			opacity: 1;
		}
	</style>
	<?php
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
