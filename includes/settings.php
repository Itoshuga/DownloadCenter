<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'ctd_register_settings_page' );
add_action( 'admin_init', 'ctd_register_frontend_settings' );
add_action( 'admin_enqueue_scripts', 'ctd_enqueue_settings_assets' );

function ctd_register_settings_page() {
	add_submenu_page(
		'edit.php?post_type=' . CTD_POST_TYPE,
		__( 'Paramètres', 'centre-telechargement' ),
		__( 'Paramètres', 'centre-telechargement' ),
		'manage_options',
		'ctd-settings',
		'ctd_render_settings_page'
	);
}

function ctd_register_frontend_settings() {
	register_setting(
		'ctd_frontend_settings',
		CTD_FRONTEND_SETTINGS_OPTION,
		array(
			'sanitize_callback' => 'ctd_sanitize_frontend_settings',
			'default'           => ctd_get_frontend_settings_defaults(),
		)
	);
}

/**
 * @param string $hook_suffix Current admin hook.
 * @return void
 */
function ctd_enqueue_settings_assets( $hook_suffix ) {
	if ( 'download_document_page_ctd-settings' !== $hook_suffix ) {
		return;
	}

	wp_enqueue_style(
		'ctd-admin',
		CTD_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		CTD_VERSION
	);

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_add_inline_script(
		'wp-color-picker',
		"jQuery(function($) { $('.ctd-color-field').wpColorPicker(); });"
	);
}

/**
 * @return array<string, string>
 */
function ctd_get_frontend_settings_defaults() {
	return array(
		'primary_color'          => '#10233f',
		'accent_color'           => '#11a9cf',
		'accent_hover_color'     => '#0b88ad',
		'border_color'           => '#d7e4ec',
		'soft_border_color'      => '#e0ebf1',
		'surface_color'          => '#ffffff',
		'muted_surface_color'    => '#f8fbfd',
		'filter_hover_bg_color'  => '#edf8fb',
		'empty_text_color'       => '#5b6d7d',
		'filter_category_width'  => '1fr',
		'filter_range_width'     => '1fr',
		'filter_language_width'  => '1fr',
		'filter_gap'             => '14px',
		'document_min_width'     => '150px',
		'document_gap'           => '26px',
	);
}

/**
 * @return array<string, string>
 */
function ctd_get_frontend_settings() {
	$settings = get_option( CTD_FRONTEND_SETTINGS_OPTION, array() );

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return ctd_sanitize_frontend_settings( wp_parse_args( $settings, ctd_get_frontend_settings_defaults() ) );
}

/**
 * @param mixed $settings Settings candidate.
 * @return array<string, string>
 */
function ctd_sanitize_frontend_settings( $settings ) {
	$defaults = ctd_get_frontend_settings_defaults();

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return array(
		'primary_color'          => ctd_sanitize_hex_setting( $settings['primary_color'] ?? '', $defaults['primary_color'] ),
		'accent_color'           => ctd_sanitize_hex_setting( $settings['accent_color'] ?? '', $defaults['accent_color'] ),
		'accent_hover_color'     => ctd_sanitize_hex_setting( $settings['accent_hover_color'] ?? '', $defaults['accent_hover_color'] ),
		'border_color'           => ctd_sanitize_hex_setting( $settings['border_color'] ?? '', $defaults['border_color'] ),
		'soft_border_color'      => ctd_sanitize_hex_setting( $settings['soft_border_color'] ?? '', $defaults['soft_border_color'] ),
		'surface_color'          => ctd_sanitize_hex_setting( $settings['surface_color'] ?? '', $defaults['surface_color'] ),
		'muted_surface_color'    => ctd_sanitize_hex_setting( $settings['muted_surface_color'] ?? '', $defaults['muted_surface_color'] ),
		'filter_hover_bg_color'  => ctd_sanitize_hex_setting( $settings['filter_hover_bg_color'] ?? '', $defaults['filter_hover_bg_color'] ),
		'empty_text_color'       => ctd_sanitize_hex_setting( $settings['empty_text_color'] ?? '', $defaults['empty_text_color'] ),
		'filter_category_width'  => ctd_sanitize_css_track_setting( $settings['filter_category_width'] ?? '', $defaults['filter_category_width'] ),
		'filter_range_width'     => ctd_sanitize_css_track_setting( $settings['filter_range_width'] ?? '', $defaults['filter_range_width'] ),
		'filter_language_width'  => ctd_sanitize_css_track_setting( $settings['filter_language_width'] ?? '', $defaults['filter_language_width'] ),
		'filter_gap'             => ctd_sanitize_css_length_setting( $settings['filter_gap'] ?? '', $defaults['filter_gap'] ),
		'document_min_width'     => ctd_sanitize_css_length_setting( $settings['document_min_width'] ?? '', $defaults['document_min_width'] ),
		'document_gap'           => ctd_sanitize_css_length_setting( $settings['document_gap'] ?? '', $defaults['document_gap'] ),
	);
}

/**
 * @param mixed  $value Candidate color.
 * @param string $fallback Fallback color.
 * @return string
 */
function ctd_sanitize_hex_setting( $value, $fallback ) {
	$value = sanitize_hex_color( (string) $value );

	return $value ? $value : $fallback;
}

/**
 * @param mixed  $value Candidate CSS grid track value.
 * @param string $fallback Fallback track value.
 * @return string
 */
function ctd_sanitize_css_track_setting( $value, $fallback ) {
	$value = trim( wp_strip_all_tags( (string) $value ) );

	if ( '' === $value || 80 < strlen( $value ) ) {
		return $fallback;
	}

	if ( preg_match( '/(?:url|expression|var|;|:|{|})/i', $value ) ) {
		return $fallback;
	}

	if ( ! preg_match( '/^[0-9a-z\s.,()%_\-\/]+$/i', $value ) ) {
		return $fallback;
	}

	return $value;
}

/**
 * @param mixed  $value Candidate CSS length.
 * @param string $fallback Fallback length.
 * @return string
 */
function ctd_sanitize_css_length_setting( $value, $fallback ) {
	$value = trim( wp_strip_all_tags( (string) $value ) );

	if ( preg_match( '/^(?:0|[0-9]+(?:\.[0-9]+)?(?:px|rem|em|%))$/', $value ) ) {
		return $value;
	}

	return $fallback;
}

/**
 * @return string
 */
function ctd_get_frontend_settings_css() {
	$settings = ctd_get_frontend_settings();

	return sprintf(
		".ctd-front-library {\n\t--ctd-color-primary: %1\$s;\n\t--ctd-color-accent: %2\$s;\n\t--ctd-color-accent-hover: %3\$s;\n\t--ctd-color-border: %4\$s;\n\t--ctd-color-border-soft: %5\$s;\n\t--ctd-color-surface: %6\$s;\n\t--ctd-color-muted-surface: %7\$s;\n\t--ctd-color-filter-hover: %8\$s;\n\t--ctd-color-empty-text: %9\$s;\n\t--ctd-color-accent-focus: %10\$s;\n\t--ctd-color-accent-shadow: %11\$s;\n\t--ctd-filter-category-width: %12\$s;\n\t--ctd-filter-range-width: %13\$s;\n\t--ctd-filter-language-width: %14\$s;\n\t--ctd-filter-gap: %15\$s;\n\t--ctd-document-min-width: %16\$s;\n\t--ctd-document-gap: %17\$s;\n}\n",
		$settings['primary_color'],
		$settings['accent_color'],
		$settings['accent_hover_color'],
		$settings['border_color'],
		$settings['soft_border_color'],
		$settings['surface_color'],
		$settings['muted_surface_color'],
		$settings['filter_hover_bg_color'],
		$settings['empty_text_color'],
		ctd_hex_to_rgba( $settings['accent_color'], 0.16 ),
		ctd_hex_to_rgba( $settings['accent_color'], 0.25 ),
		$settings['filter_category_width'],
		$settings['filter_range_width'],
		$settings['filter_language_width'],
		$settings['filter_gap'],
		$settings['document_min_width'],
		$settings['document_gap']
	);
}

/**
 * @param string $hex Hex color.
 * @param float  $alpha Alpha value.
 * @return string
 */
function ctd_hex_to_rgba( $hex, $alpha ) {
	$hex = sanitize_hex_color( $hex );

	if ( ! $hex ) {
		return 'rgba(17, 169, 207, ' . (float) $alpha . ')';
	}

	$hex = ltrim( $hex, '#' );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if ( 6 !== strlen( $hex ) ) {
		return 'rgba(17, 169, 207, ' . (float) $alpha . ')';
	}

	return sprintf(
		'rgba(%1$d, %2$d, %3$d, %4$s)',
		hexdec( substr( $hex, 0, 2 ) ),
		hexdec( substr( $hex, 2, 2 ) ),
		hexdec( substr( $hex, 4, 2 ) ),
		rtrim( rtrim( number_format( max( 0, min( 1, $alpha ) ), 2, '.', '' ), '0' ), '.' )
	);
}

function ctd_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = ctd_get_frontend_settings();
	?>
	<div class="wrap ctd-settings-page">
		<h1><?php esc_html_e( 'Paramètres', 'centre-telechargement' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'ctd_frontend_settings' ); ?>

			<div class="ctd-settings-layout">
				<section class="ctd-settings-panel">
					<header class="ctd-settings-panel-header">
						<h2><?php esc_html_e( 'Couleurs du front', 'centre-telechargement' ); ?></h2>
						<p><?php esc_html_e( 'Ces couleurs pilotent le shortcode de bibliothèque de documents.', 'centre-telechargement' ); ?></p>
					</header>

					<div class="ctd-settings-fields ctd-settings-fields-colors">
						<?php
						ctd_render_color_setting_field( 'primary_color', __( 'Bleu marine / texte', 'centre-telechargement' ), $settings['primary_color'] );
						ctd_render_color_setting_field( 'accent_color', __( 'Accent cyan', 'centre-telechargement' ), $settings['accent_color'] );
						ctd_render_color_setting_field( 'accent_hover_color', __( 'Accent au survol', 'centre-telechargement' ), $settings['accent_hover_color'] );
						ctd_render_color_setting_field( 'border_color', __( 'Bordures principales', 'centre-telechargement' ), $settings['border_color'] );
						ctd_render_color_setting_field( 'soft_border_color', __( 'Bordures des cartes', 'centre-telechargement' ), $settings['soft_border_color'] );
						ctd_render_color_setting_field( 'surface_color', __( 'Fond des filtres/cartes', 'centre-telechargement' ), $settings['surface_color'] );
						ctd_render_color_setting_field( 'muted_surface_color', __( 'Fond clair', 'centre-telechargement' ), $settings['muted_surface_color'] );
						ctd_render_color_setting_field( 'filter_hover_bg_color', __( 'Fond actif des filtres', 'centre-telechargement' ), $settings['filter_hover_bg_color'] );
						ctd_render_color_setting_field( 'empty_text_color', __( 'Texte vide', 'centre-telechargement' ), $settings['empty_text_color'] );
						?>
					</div>
				</section>

				<section class="ctd-settings-panel">
					<header class="ctd-settings-panel-header">
						<h2><?php esc_html_e( 'Disposition des filtres', 'centre-telechargement' ); ?></h2>
						<p><?php esc_html_e( 'Utilise des valeurs CSS comme 1fr, 0.7fr, 180px ou minmax(240px, 1.5fr).', 'centre-telechargement' ); ?></p>
					</header>

					<div class="ctd-settings-fields ctd-settings-fields-tracks">
						<?php
						ctd_render_text_setting_field( 'filter_category_width', __( 'Largeur Catégorie', 'centre-telechargement' ), $settings['filter_category_width'] );
						ctd_render_text_setting_field( 'filter_range_width', __( 'Largeur Gamme', 'centre-telechargement' ), $settings['filter_range_width'] );
						ctd_render_text_setting_field( 'filter_language_width', __( 'Largeur Langue', 'centre-telechargement' ), $settings['filter_language_width'] );
						ctd_render_text_setting_field( 'filter_gap', __( 'Espace entre filtres', 'centre-telechargement' ), $settings['filter_gap'] );
						?>
					</div>
				</section>

				<section class="ctd-settings-panel">
					<header class="ctd-settings-panel-header">
						<h2><?php esc_html_e( 'Grille des documents', 'centre-telechargement' ); ?></h2>
						<p><?php esc_html_e( 'Ces réglages pilotent la taille minimale des vignettes et leur espacement.', 'centre-telechargement' ); ?></p>
					</header>

					<div class="ctd-settings-fields ctd-settings-fields-inline">
						<?php
						ctd_render_text_setting_field( 'document_min_width', __( 'Largeur minimale d’une vignette', 'centre-telechargement' ), $settings['document_min_width'] );
						ctd_render_text_setting_field( 'document_gap', __( 'Espace entre les vignettes', 'centre-telechargement' ), $settings['document_gap'] );
						?>
					</div>
				</section>
			</div>

			<?php submit_button( __( 'Enregistrer les paramètres', 'centre-telechargement' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * @param string $key Field key.
 * @param string $label Field label.
 * @param string $value Field value.
 * @return void
 */
function ctd_render_color_setting_field( $key, $label, $value ) {
	?>
	<label class="ctd-settings-field">
		<span><?php echo esc_html( $label ); ?></span>
		<input
			type="text"
			class="ctd-color-field"
			name="<?php echo esc_attr( CTD_FRONTEND_SETTINGS_OPTION . '[' . $key . ']' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			data-default-color="<?php echo esc_attr( ctd_get_frontend_settings_defaults()[ $key ] ); ?>"
		/>
	</label>
	<?php
}

/**
 * @param string $key Field key.
 * @param string $label Field label.
 * @param string $value Field value.
 * @return void
 */
function ctd_render_text_setting_field( $key, $label, $value ) {
	?>
	<label class="ctd-settings-field">
		<span><?php echo esc_html( $label ); ?></span>
		<input
			type="text"
			name="<?php echo esc_attr( CTD_FRONTEND_SETTINGS_OPTION . '[' . $key . ']' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
		/>
	</label>
	<?php
}
