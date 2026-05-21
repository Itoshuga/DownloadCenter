<?php
/**
 * Plugin Name: Centre de Téléchargement
 * Description: Socle admin pour gérer des documents PDF catégorisés, publics ou protégés.
 * Version: 0.4.2
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
define( 'CTD_RANGE_TAXONOMY', 'download_range' );
define( 'CTD_LANGUAGE_TAXONOMY', 'download_language' );
define( 'CTD_CATEGORY_RANGE_META', '_ctd_category_range_ids' );
define( 'CTD_CATEGORY_LANGUAGE_META', '_ctd_category_language_ids' );
define( 'CTD_LANGUAGE_FLAG_META', '_ctd_language_flag' );
define( 'CTD_LANGUAGE_FLAG_ATTACHMENT_META', '_ctd_language_flag_attachment_id' );
define( 'CTD_LANGUAGE_FLAGS_DIR', CTD_PLUGIN_DIR . 'assets/images/flags/' );
define( 'CTD_LANGUAGE_FLAGS_URL', CTD_PLUGIN_URL . 'assets/images/flags/' );
define( 'CTD_META_FILE_ID', '_ctd_pdf_file_id' );
define( 'CTD_META_STATUS', '_ctd_document_status' );
define( 'CTD_META_ACCESS_MODE', '_ctd_document_access_mode' );
define( 'CTD_META_ALLOWED_USERS', '_ctd_document_allowed_users' );

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
 * @return array<string, string>
 */
function ctd_get_document_access_modes() {
	return array(
		'all'      => __( 'Tous les utilisateurs', 'centre-telechargement' ),
		'selected' => __( 'Utilisateurs sélectionnés', 'centre-telechargement' ),
	);
}

/**
 * @param mixed  $mode Access mode candidate.
 * @param string $fallback Fallback mode.
 * @return string
 */
function ctd_normalize_document_access_mode( $mode, $fallback = 'all' ) {
	$modes = ctd_get_document_access_modes();
	$mode  = sanitize_key( (string) $mode );

	if ( isset( $modes[ $mode ] ) ) {
		return $mode;
	}

	return $fallback;
}

/**
 * @param mixed $mode Access mode candidate.
 * @return string
 */
function ctd_sanitize_document_access_mode( $mode ) {
	return ctd_normalize_document_access_mode( $mode, 'all' );
}

/**
 * @param int $post_id Document post ID.
 * @return string
 */
function ctd_get_document_access_mode( $post_id ) {
	$mode = get_post_meta( $post_id, CTD_META_ACCESS_MODE, true );

	return ctd_normalize_document_access_mode( $mode, 'all' );
}

/**
 * @param mixed $user_ids User IDs candidate.
 * @return array<int>
 */
function ctd_sanitize_document_allowed_user_ids( $user_ids ) {
	if ( ! is_array( $user_ids ) ) {
		$user_ids = array( $user_ids );
	}

	$user_ids = array_map( 'absint', $user_ids );
	$user_ids = array_filter( $user_ids );
	$user_ids = array_unique( $user_ids );

	return array_values( $user_ids );
}

/**
 * @param int $post_id Document post ID.
 * @return array<int>
 */
function ctd_get_document_allowed_user_ids( $post_id ) {
	$user_ids = get_post_meta( $post_id, CTD_META_ALLOWED_USERS, true );

	return ctd_sanitize_document_allowed_user_ids( $user_ids );
}

/**
 * @param int $post_id Document post ID.
 * @param int $user_id User ID. Defaults to current user.
 * @return bool
 */
function ctd_user_can_access_document( $post_id, $user_id = 0 ) {
	if ( 'public' === ctd_get_document_status( $post_id ) ) {
		return true;
	}

	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

	if ( ! $user_id ) {
		return false;
	}

	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}

	if ( 'all' === ctd_get_document_access_mode( $post_id ) ) {
		return true;
	}

	return in_array( $user_id, ctd_get_document_allowed_user_ids( $post_id ), true );
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
 * @return int
 */
function ctd_get_language_flag_attachment_id( $term ) {
	if ( is_numeric( $term ) ) {
		$term = get_term( absint( $term ), CTD_LANGUAGE_TAXONOMY );
	}

	if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
		return 0;
	}

	$attachment_id = absint( get_term_meta( $term->term_id, CTD_LANGUAGE_FLAG_ATTACHMENT_META, true ) );

	if ( ! $attachment_id || ! ctd_attachment_is_language_flag_image( $attachment_id ) ) {
		return 0;
	}

	return $attachment_id;
}

/**
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function ctd_attachment_is_language_flag_image( $attachment_id ) {
	$attachment = get_post( $attachment_id );

	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return false;
	}

	$mime = (string) get_post_mime_type( $attachment );

	return 0 === strpos( $mime, 'image/' );
}

/**
 * @param WP_Term|int $term Language term or term ID.
 * @return string
 */
function ctd_get_language_flag_url( $term ) {
	$attachment_id = ctd_get_language_flag_attachment_id( $term );

	if ( $attachment_id ) {
		$attachment_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

		if ( ! $attachment_url ) {
			$attachment_url = wp_get_attachment_url( $attachment_id );
		}

		return $attachment_url ? $attachment_url : '';
	}

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

/**
 * @param mixed $term_ids Term IDs candidate.
 * @return array<int>
 */
function ctd_sanitize_term_id_list( $term_ids ) {
	if ( ! is_array( $term_ids ) ) {
		$term_ids = array( $term_ids );
	}

	$term_ids = array_map( 'absint', $term_ids );
	$term_ids = array_filter( $term_ids );
	$term_ids = array_unique( $term_ids );

	return array_values( $term_ids );
}

/**
 * @param WP_Term|int $category Category term or term ID.
 * @return WP_Term|null
 */
function ctd_get_category_term( $category ) {
	if ( is_numeric( $category ) ) {
		$category = get_term( absint( $category ), CTD_TAXONOMY );
	}

	if ( ! ( $category instanceof WP_Term ) || is_wp_error( $category ) ) {
		return null;
	}

	return $category;
}

/**
 * @param WP_Term|int $category Category term or term ID.
 * @param string      $meta_key Relationship meta key.
 * @return array<int>
 */
function ctd_get_category_linked_term_ids( $category, $meta_key ) {
	$category = ctd_get_category_term( $category );

	if ( ! $category ) {
		return array();
	}

	return ctd_sanitize_term_id_list( get_term_meta( $category->term_id, $meta_key, true ) );
}

/**
 * @param WP_Term|int $category Category term or term ID.
 * @return array<int>
 */
function ctd_get_category_linked_range_ids( $category ) {
	return ctd_get_category_linked_term_ids( $category, CTD_CATEGORY_RANGE_META );
}

/**
 * @param WP_Term|int $category Category term or term ID.
 * @return array<int>
 */
function ctd_get_category_linked_language_ids( $category ) {
	return ctd_get_category_linked_term_ids( $category, CTD_CATEGORY_LANGUAGE_META );
}

/**
 * Returns configured linked terms. An empty result means the category has no restriction.
 *
 * @param WP_Term|int $category Category term or term ID.
 * @param string      $taxonomy Target taxonomy.
 * @return array<WP_Term>
 */
function ctd_get_category_linked_terms( $category, $taxonomy ) {
	$meta_key = '';

	if ( CTD_RANGE_TAXONOMY === $taxonomy ) {
		$meta_key = CTD_CATEGORY_RANGE_META;
	} elseif ( CTD_LANGUAGE_TAXONOMY === $taxonomy ) {
		$meta_key = CTD_CATEGORY_LANGUAGE_META;
	}

	if ( ! $meta_key ) {
		return array();
	}

	$term_ids = ctd_get_category_linked_term_ids( $category, $meta_key );

	if ( empty( $term_ids ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'include'    => $term_ids,
			'hide_empty' => false,
			'orderby'    => 'include',
		)
	);

	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Returns terms available for filters. If a category has no configured links,
 * all terms stay available for backward compatibility.
 *
 * @param WP_Term|int|string $category Category term, term ID or slug.
 * @param string             $taxonomy Target taxonomy.
 * @return array<WP_Term>
 */
function ctd_get_category_available_filter_terms( $category, $taxonomy ) {
	if ( is_string( $category ) && ! is_numeric( $category ) ) {
		$category = get_term_by( 'slug', sanitize_title( $category ), CTD_TAXONOMY );
	}

	$linked_terms = ctd_get_category_linked_terms( $category, $taxonomy );

	if ( ! empty( $linked_terms ) ) {
		return $linked_terms;
	}

	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	return is_wp_error( $terms ) ? array() : $terms;
}

require_once CTD_PLUGIN_DIR . 'includes/post-types.php';
require_once CTD_PLUGIN_DIR . 'includes/taxonomies.php';
require_once CTD_PLUGIN_DIR . 'includes/analytics.php';
require_once CTD_PLUGIN_DIR . 'includes/meta-boxes.php';
require_once CTD_PLUGIN_DIR . 'includes/admin-columns.php';

register_activation_hook( __FILE__, 'ctd_activate' );
register_deactivation_hook( __FILE__, 'ctd_deactivate' );

function ctd_activate() {
	ctd_register_post_type();
	ctd_register_taxonomy();
	ctd_seed_default_languages( true );
	ctd_create_analytics_table();
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
		&& in_array( $screen->taxonomy, array( CTD_TAXONOMY, CTD_RANGE_TAXONOMY, CTD_LANGUAGE_TAXONOMY ), true );

	if ( ! $is_document_post_type && ! $is_document_taxonomy ) {
		return;
	}

	wp_enqueue_style(
		'ctd-admin',
		CTD_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		CTD_VERSION
	);

	if ( $is_document_taxonomy ) {
		wp_enqueue_script( 'jquery' );
		wp_add_inline_script( 'jquery', ctd_get_taxonomy_refresh_script() );
	}

	if ( $is_document_post_type && 'edit' === $screen->base ) {
		wp_enqueue_script( 'jquery' );
		wp_add_inline_script( 'jquery', ctd_get_admin_document_filter_relationship_script() );
	}

	if ( $is_document_post_type && in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
		wp_enqueue_media();
		wp_enqueue_script( 'jquery' );
		wp_add_inline_script( 'jquery', ctd_get_media_script() );
	}

	if ( isset( $screen->taxonomy ) && CTD_LANGUAGE_TAXONOMY === $screen->taxonomy ) {
		wp_enqueue_media();
		wp_enqueue_script( 'jquery' );
		wp_add_inline_script( 'jquery', ctd_get_language_flag_media_script() );
	}
}

/**
 * @return array<string, array<string, array<int, string>>>
 */
function ctd_get_category_filter_relationships() {
	$relationships = array();
	$categories    = get_terms(
		array(
			'taxonomy'   => CTD_TAXONOMY,
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $categories ) ) {
		return $relationships;
	}

	foreach ( $categories as $category ) {
		$range_terms    = ctd_get_category_linked_terms( $category, CTD_RANGE_TAXONOMY );
		$language_terms = ctd_get_category_linked_terms( $category, CTD_LANGUAGE_TAXONOMY );

		$relationships[ $category->slug ] = array(
			'ranges'    => array_values( wp_list_pluck( $range_terms, 'slug' ) ),
			'languages' => array_values( wp_list_pluck( $language_terms, 'slug' ) ),
		);
	}

	return $relationships;
}

function ctd_render_admin_menu_logo_styles() {
	?>
	<style>
		#adminmenu #menu-posts-download_document .wp-menu-image img {
			box-sizing: border-box;
			height: 28px;
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

	function refreshChoiceCards() {
		$('.ctd-access-mode-option').removeClass('is-selected');
		$('.ctd-status-option').removeClass('is-selected');
		$('input[name="ctd_document_access_mode"]:checked').closest('.ctd-access-mode-option').addClass('is-selected');
		$('input[name="ctd_document_status"]:checked').closest('.ctd-status-option').addClass('is-selected');
	}

	function filterAccessUsers() {
		var query = String($('[data-ctd-user-search]').val() || '').toLowerCase();

		$('[data-ctd-user-row]').each(function() {
			var row = $(this);
			var label = String(row.data('user-search') || '').toLowerCase();

			row.toggle(!query || label.indexOf(query) !== -1);
		});
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

	$(document).on('change', 'input[name="ctd_document_status"], input[name="ctd_document_access_mode"]', refreshChoiceCards);
	$(document).on('input', '[data-ctd-user-search]', filterAccessUsers);

	$(function() {
		refreshChoiceCards();
		filterAccessUsers();
	});
})(jQuery);
JS;
}

/**
 * @return string
 */
function ctd_get_taxonomy_refresh_script() {
	$taxonomies = wp_json_encode(
		array(
			CTD_TAXONOMY,
			CTD_RANGE_TAXONOMY,
			CTD_LANGUAGE_TAXONOMY,
		)
	);

	return <<<JS
(function($) {
	'use strict';

	var managedTaxonomies = $taxonomies;
	var reloadQueued = false;

	function getDataValue(data, key) {
		var params;

		if (!data) {
			return '';
		}

		if (typeof data === 'string') {
			params = new URLSearchParams(data);
			return params.get(key) || '';
		}

		if (typeof FormData !== 'undefined' && data instanceof FormData) {
			return data.get(key) || '';
		}

		if (typeof data === 'object' && data[key] !== undefined) {
			return data[key];
		}

		return '';
	}

	function shouldReload(settings, responseText, statusCode) {
		var action = getDataValue(settings.data, 'action');
		var taxonomy = getDataValue(settings.data, 'taxonomy');

		if (statusCode !== 200 || (action !== 'add-tag' && action !== 'delete-tag')) {
			return false;
		}

		if (taxonomy && managedTaxonomies.indexOf(taxonomy) === -1) {
			return false;
		}

		if (responseText === '0' || responseText === '-1' || responseText.indexOf('<wp_error') !== -1) {
			return false;
		}

		return true;
	}

	$(document).ajaxComplete(function(event, jqXHR, settings) {
		if (reloadQueued || !shouldReload(settings || {}, jqXHR.responseText || '', jqXHR.status)) {
			return;
		}

		reloadQueued = true;
		window.setTimeout(function() {
			window.location.reload();
		}, 250);
	});
})(jQuery);
JS;
}

/**
 * @return string
 */
function ctd_get_admin_document_filter_relationship_script() {
	$relationships = wp_json_encode( ctd_get_category_filter_relationships() );
	$relationships = $relationships ? $relationships : '{}';

	return <<<JS
(function($) {
	'use strict';

	var relationships = $relationships;

	function applyRelatedOptions(select, allowedSlugs) {
		var hasRestriction = Array.isArray(allowedSlugs) && allowedSlugs.length > 0;
		var selectedValue = select.val() || '';

		select.find('option').each(function() {
			var option = this;
			var value = option.value || '';
			var isAvailable = !value || !hasRestriction || allowedSlugs.indexOf(value) !== -1;

			option.hidden = !isAvailable;
			option.disabled = !isAvailable;

			if (!isAvailable && selectedValue === value) {
				select.val('');
			}
		});
	}

	function refreshRelatedFilters() {
		var category = $('#ctd_download_category_filter').val() || '';
		var relationship = relationships[category] || {};

		applyRelatedOptions($('#ctd_download_range_filter'), relationship.ranges || []);
		applyRelatedOptions($('#ctd_download_language_filter'), relationship.languages || []);
	}

	$(document).on('change', '#ctd_download_category_filter', refreshRelatedFilters);
	$(refreshRelatedFilters);
})(jQuery);
JS;
}

/**
 * @return string
 */
function ctd_get_language_flag_media_script() {
	return <<<'JS'
(function($) {
	'use strict';

	var mediaFrame = null;

	function getPreviewUrl(attachment) {
		if (attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
			return attachment.sizes.thumbnail.url;
		}

		return attachment.url || '';
	}

	function clearMediaFlag() {
		$('#ctd_language_flag_attachment_id').val('');
		$('#ctd-language-flag-media-preview').attr('src', '').addClass('hidden');
		$('#ctd-language-flag-media-placeholder').removeClass('hidden');
		$('#ctd-language-flag-media-filename').text('Aucune image sélectionnée');
		$('#ctd-remove-language-flag-media').addClass('hidden');
	}

	function renderSelection(attachment) {
		var previewUrl = getPreviewUrl(attachment);

		$('#ctd_language_flag_attachment_id').val(attachment.id || '');
		$('#ctd-language-flag-media-preview').attr('src', previewUrl).toggleClass('hidden', !previewUrl);
		$('#ctd-language-flag-media-placeholder').toggleClass('hidden', !!previewUrl);
		$('#ctd-language-flag-media-filename').text(attachment.filename || attachment.title || 'Image sélectionnée');
		$('#ctd-remove-language-flag-media').removeClass('hidden');
	}

	$(document).on('click', '.ctd-select-language-flag-media', function(event) {
		event.preventDefault();

		if (mediaFrame) {
			mediaFrame.open();
			return;
		}

		mediaFrame = wp.media({
			title: 'Sélectionner un drapeau',
			button: {
				text: 'Utiliser cette image'
			},
			multiple: false,
			library: {
				type: 'image'
			}
		});

		mediaFrame.on('select', function() {
			var attachment = mediaFrame.state().get('selection').first().toJSON();

			if (attachment.mime && attachment.mime.indexOf('image/') !== 0) {
				window.alert('Veuillez choisir une image.');
				return;
			}

			renderSelection(attachment);
		});

		mediaFrame.open();
	});

	$(document).on('click', '.ctd-remove-language-flag-media', function(event) {
		event.preventDefault();
		clearMediaFlag();
	});
})(jQuery);
JS;
}
