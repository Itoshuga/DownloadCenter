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

	ctd_enqueue_admin_tour_assets( 'settings' );
}

/**
 * @param WP_Screen|null $screen Current screen.
 * @param string         $hook_suffix Current admin hook.
 * @return string
 */
function ctd_get_admin_tour_current_page_key( $screen = null, $hook_suffix = '' ) {
	if ( 'download_document_page_ctd-settings' === $hook_suffix ) {
		return 'settings';
	}

	if ( ! $screen instanceof WP_Screen ) {
		return '';
	}

	if ( isset( $screen->taxonomy ) ) {
		if ( CTD_RANGE_TAXONOMY === $screen->taxonomy ) {
			return 'range';
		}

		if ( CTD_TAXONOMY === $screen->taxonomy ) {
			return 'category';
		}
	}

	if (
		isset( $screen->post_type )
		&& CTD_POST_TYPE === $screen->post_type
		&& in_array( $screen->base, array( 'post', 'post-new' ), true )
	) {
		return 'document';
	}

	return '';
}

/**
 * @param string $current_page Current tour page key.
 * @return void
 */
function ctd_enqueue_admin_tour_assets( $current_page = '' ) {
	if ( ! current_user_can( 'manage_options' ) || wp_script_is( 'ctd-admin-tour', 'enqueued' ) ) {
		return;
	}

	wp_enqueue_script(
		'ctd-admin-tour',
		CTD_PLUGIN_URL . 'assets/js/admin-tour.js',
		array(),
		CTD_VERSION,
		true
	);

	wp_localize_script( 'ctd-admin-tour', 'ctdAdminTour', ctd_get_admin_tour_config( $current_page ) );
}

/**
 * @param string $current_page Current tour page key.
 * @return array<string, mixed>
 */
function ctd_get_admin_tour_config( $current_page ) {
	return array(
		'currentPage' => $current_page,
		'storageKey'  => 'ctd_admin_tour_state',
		'pages'       => array(
			'settings' => admin_url( 'edit.php?post_type=' . CTD_POST_TYPE . '&page=ctd-settings' ),
			'range'    => admin_url( 'edit-tags.php?taxonomy=' . CTD_RANGE_TAXONOMY . '&post_type=' . CTD_POST_TYPE ),
			'category' => admin_url( 'edit-tags.php?taxonomy=' . CTD_TAXONOMY . '&post_type=' . CTD_POST_TYPE ),
			'document' => admin_url( 'post-new.php?post_type=' . CTD_POST_TYPE ),
		),
		'i18n'        => array(
			'next'     => __( 'Suivant', 'centre-telechargement' ),
			'previous' => __( 'Précédent', 'centre-telechargement' ),
			'finish'   => __( 'Terminer', 'centre-telechargement' ),
			'close'    => __( 'Fermer', 'centre-telechargement' ),
			'step'     => __( 'Étape', 'centre-telechargement' ),
			'of'       => __( 'sur', 'centre-telechargement' ),
		),
		'steps'       => ctd_get_admin_tour_steps(),
	);
}

/**
 * @return array<int, array<string, string>>
 */
function ctd_get_admin_tour_steps() {
	return array(
		array(
			'page'     => 'settings',
			'selector' => '.ctd-tour-launch-card',
			'title'    => __( 'Guide du Centre de Téléchargement', 'centre-telechargement' ),
			'body'     => __( 'Cette visite vous accompagne dans le flux conseillé : créer les filtres, lier les catégories, puis publier un document PDF.', 'centre-telechargement' ),
		),
		array(
			'page'     => 'range',
			'selector' => '#addtag',
			'title'    => __( 'Créer une gamme', 'centre-telechargement' ),
			'body'     => __( 'Commencez par créer les gammes qui serviront de filtres. Une gamme peut ensuite être liée à une ou plusieurs catégories.', 'centre-telechargement' ),
		),
		array(
			'page'     => 'category',
			'selector' => '#addtag',
			'title'    => __( 'Créer une catégorie', 'centre-telechargement' ),
			'body'     => __( 'La catégorie est le filtre principal du front. Donnez-lui un nom clair, puis configurez les informations liées juste en dessous.', 'centre-telechargement' ),
		),
		array(
			'page'     => 'category',
			'selector' => '.term-ctd-category-protected-wrap',
			'title'    => __( 'Indiquer la protection', 'centre-telechargement' ),
			'body'     => __( 'Cette case est informative : elle affiche un cadenas dans le filtre front, sans modifier les droits réels des documents.', 'centre-telechargement' ),
		),
		array(
			'page'     => 'category',
			'selector' => '.term-ctd-category-relations-wrap',
			'title'    => __( 'Lier gammes et langues', 'centre-telechargement' ),
			'body'     => __( 'Sélectionnez les gammes et langues disponibles pour cette catégorie. Ces liaisons servent à guider les filtres et la saisie des documents.', 'centre-telechargement' ),
		),
		array(
			'page'     => 'document',
			'selector' => '#titlediv',
			'title'    => __( 'Nom du document', 'centre-telechargement' ),
			'body'     => __( 'Le titre WordPress devient le nom affiché sous la vignette PDF sur le front-office.', 'centre-telechargement' ),
		),
		array(
			'page'     => 'document',
			'selector' => '#ctd_document_file',
			'title'    => __( 'Ajouter le PDF', 'centre-telechargement' ),
			'body'     => __( 'Choisissez un fichier PDF depuis la médiathèque. Le plugin vérifie que le fichier sélectionné est bien un PDF.', 'centre-telechargement' ),
		),
		array(
			'page'     => 'document',
			'selector' => '#' . CTD_TAXONOMY . 'div',
			'title'    => __( 'Attribuer une catégorie', 'centre-telechargement' ),
			'body'     => __( 'Cochez la catégorie du document. Les gammes et langues visibles dans les blocs suivants s’adaptent aux liaisons configurées sur la catégorie.', 'centre-telechargement' ),
		),
		array(
			'page'     => 'document',
			'selector' => '#' . CTD_RANGE_TAXONOMY . 'div',
			'title'    => __( 'Choisir la gamme', 'centre-telechargement' ),
			'body'     => __( 'Sélectionnez la gamme du document. Si aucune catégorie n’est cochée, les gammes ne sont pas proposées afin d’éviter les erreurs de saisie.', 'centre-telechargement' ),
		),
		array(
			'page'     => 'document',
			'selector' => '#' . CTD_LANGUAGE_TAXONOMY . 'div',
			'title'    => __( 'Choisir la langue', 'centre-telechargement' ),
			'body'     => __( 'Sélectionnez la langue du PDF. Les langues proposées suivent les liaisons définies dans la catégorie.', 'centre-telechargement' ),
		),
		array(
			'page'     => 'document',
			'selector' => '#ctd_document_access',
			'title'    => __( 'Définir la protection', 'centre-telechargement' ),
			'body'     => __( 'Choisissez Public ou Protégé. En protégé, vous pouvez autoriser tous les utilisateurs connectés ou seulement certains utilisateurs.', 'centre-telechargement' ),
		),
		array(
			'page'     => 'document',
			'selector' => '#submitdiv',
			'title'    => __( 'Publier le document', 'centre-telechargement' ),
			'body'     => __( 'Une fois le PDF, les filtres et l’accès configurés, publiez le document. Il apparaîtra ensuite dans le shortcode si l’utilisateur est autorisé.', 'centre-telechargement' ),
		),
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
		'login_notice_text'      => __( 'Merci de vous connecter pour tÃ©lÃ©charger les fichiers', 'centre-telechargement' ),
		'login_button_text'      => __( 'Connexion / Demande de Mot de Passe', 'centre-telechargement' ),
		'password_request_shortcode' => '',
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
		'login_notice_text'      => ctd_sanitize_plain_text_setting( $settings['login_notice_text'] ?? '', $defaults['login_notice_text'] ),
		'login_button_text'      => ctd_sanitize_plain_text_setting( $settings['login_button_text'] ?? '', $defaults['login_button_text'] ),
		'password_request_shortcode' => ctd_sanitize_shortcode_setting( $settings['password_request_shortcode'] ?? '' ),
	);
}

/**
 * @param mixed  $value Candidate text.
 * @param string $fallback Fallback text.
 * @return string
 */
function ctd_sanitize_plain_text_setting( $value, $fallback ) {
	$value = sanitize_text_field( (string) $value );

	return '' !== $value ? $value : $fallback;
}

/**
 * @param mixed $value Candidate shortcode.
 * @return string
 */
function ctd_sanitize_shortcode_setting( $value ) {
	return sanitize_text_field( (string) $value );
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

		<section class="ctd-tour-launch-card">
			<div>
				<h2><?php esc_html_e( 'Guide d’utilisation', 'centre-telechargement' ); ?></h2>
				<p><?php esc_html_e( 'Lancez une procédure pas à pas pour apprendre à créer une gamme, une catégorie liée, puis un document PDF protégé ou public.', 'centre-telechargement' ); ?></p>
			</div>
			<button type="button" class="button button-primary" data-ctd-tour-start>
				<?php esc_html_e( 'Lancer la procédure pas à pas', 'centre-telechargement' ); ?>
			</button>
		</section>

		<form method="post" action="options.php">
			<?php settings_fields( 'ctd_frontend_settings' ); ?>

			<div class="ctd-settings-layout">
				<section class="ctd-settings-panel">
					<header class="ctd-settings-panel-header">
						<h2><?php esc_html_e( 'Connexion front', 'centre-telechargement' ); ?></h2>
						<p><?php esc_html_e( 'Ces textes sont affichÃ©s au-dessus des filtres du shortcode pour les visiteurs non connectÃ©s.', 'centre-telechargement' ); ?></p>
					</header>

					<div class="ctd-settings-fields ctd-settings-fields-inline">
						<?php
						ctd_render_text_setting_field( 'login_notice_text', __( 'Texte au-dessus du bouton', 'centre-telechargement' ), $settings['login_notice_text'] );
						ctd_render_text_setting_field( 'login_button_text', __( 'Texte du bouton', 'centre-telechargement' ), $settings['login_button_text'] );
						?>
					</div>

					<div class="ctd-settings-fields ctd-settings-fields-single">
						<?php
						ctd_render_text_setting_field( 'password_request_shortcode', __( 'Shortcode du formulaire de demande de mot de passe', 'centre-telechargement' ), $settings['password_request_shortcode'] );
						?>
					</div>
				</section>

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
