<?php
/**
 * Plugin Name: Centre de Téléchargement
 * Description: Socle admin pour gérer des documents PDF catégorisés, publics ou protégés.
 * Version: 0.5.19
 * Author: IMS ON LINE
 * Text Domain: centre-telechargement
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CTD_VERSION', '0.5.19' );
define( 'CTD_ANALYTICS_SCHEMA_VERSION', '1.1.0' );
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
define( 'CTD_CATEGORY_PROTECTED_HINT_META', '_ctd_category_protected_hint' );
define( 'CTD_TERM_TRANSLATION_EN_META', '_ctd_term_translation_en' );
define( 'CTD_TERM_TRANSLATION_META_PREFIX', '_ctd_term_translation_' );
define( 'CTD_LANGUAGE_FLAG_META', '_ctd_language_flag' );
define( 'CTD_LANGUAGE_FLAG_ATTACHMENT_META', '_ctd_language_flag_attachment_id' );
define( 'CTD_LANGUAGE_FLAGS_DIR', CTD_PLUGIN_DIR . 'assets/images/flags/' );
define( 'CTD_LANGUAGE_FLAGS_URL', CTD_PLUGIN_URL . 'assets/images/flags/' );
define( 'CTD_META_FILE_ID', '_ctd_pdf_file_id' );
define( 'CTD_META_STATUS', '_ctd_document_status' );
define( 'CTD_META_ACCESS_MODE', '_ctd_document_access_mode' );
define( 'CTD_META_ALLOWED_USERS', '_ctd_document_allowed_users' );
define( 'CTD_FRONTEND_SETTINGS_OPTION', 'ctd_frontend_settings' );
define( 'CTD_REPORT_SETTINGS_OPTION', 'ctd_report_settings' );
define( 'CTD_REPORT_LAST_RUN_OPTION', 'ctd_report_last_run' );
define( 'CTD_REPORT_CRON_HOOK', 'ctd_send_scheduled_stats_report' );

/**
 * @return void
 */
function ctd_enqueue_font_awesome() {
	foreach ( array( 'elementor-icons-fa-solid', 'font-awesome', 'fontawesome' ) as $font_awesome_handle ) {
		if ( wp_style_is( $font_awesome_handle, 'registered' ) ) {
			wp_enqueue_style( $font_awesome_handle );
			return;
		}
	}

	wp_enqueue_style(
		'ctd-font-awesome',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
		array(),
		'6.5.2'
	);
}

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
	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

	if ( ! $user_id ) {
		return false;
	}

	if ( 'public' === ctd_get_document_status( $post_id ) ) {
		return true;
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
 * @param WP_Term|int $category Category term or term ID.
 * @return bool
 */
function ctd_category_has_protected_hint( $category ) {
	if ( is_numeric( $category ) ) {
		$category = get_term( absint( $category ), CTD_TAXONOMY );
	}

	if ( ! ( $category instanceof WP_Term ) || is_wp_error( $category ) ) {
		return false;
	}

	return (bool) get_term_meta( $category->term_id, CTD_CATEGORY_PROTECTED_HINT_META, true );
}

/**
 * @param WP_Term|int $term Term object or term ID.
 * @param string      $language Language code.
 * @param string      $taxonomy Optional taxonomy when passing an ID.
 * @return string
 */
function ctd_get_translated_term_name( $term, $language = '', $taxonomy = '' ) {
	if ( is_numeric( $term ) ) {
		$term = get_term( absint( $term ), $taxonomy );
	}

	if ( ! ( $term instanceof WP_Term ) || is_wp_error( $term ) ) {
		return '';
	}

	$language = function_exists( 'ctd_normalize_frontend_language' )
		? ctd_normalize_frontend_language( $language )
		: ctd_normalize_language_code( $language );

	if ( 'fr' !== $language ) {
		$translation = get_term_meta( $term->term_id, ctd_get_term_translation_meta_key( $language ), true );
		$translation = is_string( $translation ) ? trim( $translation ) : '';

		if ( '' !== $translation ) {
			return $translation;
		}
	}

	return $term->name;
}

/**
 * @param string $language Language code.
 * @return string
 */
function ctd_get_term_translation_meta_key( $language ) {
	$language = ctd_normalize_language_code( $language );

	if ( 'en' === $language ) {
		return CTD_TERM_TRANSLATION_EN_META;
	}

	return CTD_TERM_TRANSLATION_META_PREFIX . $language;
}

/**
 * @param mixed $language Language candidate.
 * @return string
 */
function ctd_normalize_language_code( $language ) {
	$language = strtolower( (string) $language );
	$language = str_replace( '_', '-', $language );
	$parts    = explode( '-', $language );
	$language = sanitize_key( $parts[0] ?? '' );

	return preg_match( '/^[a-z]{2}$/', $language ) ? $language : 'fr';
}

/**
 * @return array<string, array<string, mixed>>
 */
function ctd_get_predefined_frontend_languages() {
	return array(
		'fr' => array(
			'label'   => __( 'Français', 'centre-telechargement' ),
			'strings' => array(
				'login_notice_text'      => __( 'Merci de vous connecter pour télécharger les fichiers', 'centre-telechargement' ),
				'login_button_text'      => __( 'Connexion / Demande de Mot de Passe', 'centre-telechargement' ),
				'login_modal_title'      => __( 'Accès aux documents', 'centre-telechargement' ),
				'login_tab_label'        => __( 'Connexion', 'centre-telechargement' ),
				'login_username_label'   => __( 'Identifiant ou adresse e-mail', 'centre-telechargement' ),
				'login_password_label'   => __( 'Mot de passe', 'centre-telechargement' ),
				'password_tab_label'     => __( 'Demande de mot de passe', 'centre-telechargement' ),
				'password_shortcode_empty_message' => __( 'Ajoutez le shortcode du formulaire de contact dans les paramètres du Centre de Téléchargement.', 'centre-telechargement' ),
				'filter_category_label'  => __( 'Catégorie', 'centre-telechargement' ),
				'filter_category_empty_label' => __( 'Toutes les catégories', 'centre-telechargement' ),
				'filter_range_label'     => __( 'Gamme', 'centre-telechargement' ),
				'filter_range_empty_label' => __( 'Toutes les gammes', 'centre-telechargement' ),
				'filter_language_label'  => __( 'Langue', 'centre-telechargement' ),
				'filter_language_empty_label' => __( 'Toutes les langues', 'centre-telechargement' ),
				'empty_message'          => __( 'Aucun document ne correspond aux filtres sélectionnés.', 'centre-telechargement' ),
			),
		),
		'en' => array(
			'label'   => __( 'Anglais', 'centre-telechargement' ),
			'strings' => array(
				'login_notice_text'      => __( 'Please log in to download files', 'centre-telechargement' ),
				'login_button_text'      => __( 'Login / Password Request', 'centre-telechargement' ),
				'login_modal_title'      => __( 'Document access', 'centre-telechargement' ),
				'login_tab_label'        => __( 'Login', 'centre-telechargement' ),
				'login_username_label'   => __( 'Username or email address', 'centre-telechargement' ),
				'login_password_label'   => __( 'Password', 'centre-telechargement' ),
				'password_tab_label'     => __( 'Password request', 'centre-telechargement' ),
				'password_shortcode_empty_message' => __( 'Add the contact form shortcode in the Centre de Téléchargement settings.', 'centre-telechargement' ),
				'filter_category_label'  => __( 'Category', 'centre-telechargement' ),
				'filter_category_empty_label' => __( 'All categories', 'centre-telechargement' ),
				'filter_range_label'     => __( 'Range', 'centre-telechargement' ),
				'filter_range_empty_label' => __( 'All ranges', 'centre-telechargement' ),
				'filter_language_label'  => __( 'Language', 'centre-telechargement' ),
				'filter_language_empty_label' => __( 'All languages', 'centre-telechargement' ),
				'empty_message'          => __( 'No document matches the selected filters.', 'centre-telechargement' ),
			),
		),
		'es' => array(
			'label'   => __( 'Espagnol', 'centre-telechargement' ),
			'strings' => array(
				'login_notice_text'      => __( 'Inicie sesión para descargar archivos', 'centre-telechargement' ),
				'login_button_text'      => __( 'Acceso / Solicitud de contraseña', 'centre-telechargement' ),
				'login_modal_title'      => __( 'Acceso a documentos', 'centre-telechargement' ),
				'login_tab_label'        => __( 'Acceso', 'centre-telechargement' ),
				'login_username_label'   => __( 'Usuario o correo electrónico', 'centre-telechargement' ),
				'login_password_label'   => __( 'Contraseña', 'centre-telechargement' ),
				'password_tab_label'     => __( 'Solicitud de contraseña', 'centre-telechargement' ),
				'password_shortcode_empty_message' => __( 'Añada el shortcode del formulario de contacto en los ajustes.', 'centre-telechargement' ),
				'filter_category_label'  => __( 'Categoría', 'centre-telechargement' ),
				'filter_category_empty_label' => __( 'Todas las categorías', 'centre-telechargement' ),
				'filter_range_label'     => __( 'Gama', 'centre-telechargement' ),
				'filter_range_empty_label' => __( 'Todas las gamas', 'centre-telechargement' ),
				'filter_language_label'  => __( 'Idioma', 'centre-telechargement' ),
				'filter_language_empty_label' => __( 'Todos los idiomas', 'centre-telechargement' ),
				'empty_message'          => __( 'Ningún documento corresponde a los filtros seleccionados.', 'centre-telechargement' ),
			),
		),
		'de' => array(
			'label'   => __( 'Allemand', 'centre-telechargement' ),
			'strings' => array(
				'login_notice_text'      => __( 'Bitte melden Sie sich an, um Dateien herunterzuladen', 'centre-telechargement' ),
				'login_button_text'      => __( 'Login / Passwort anfordern', 'centre-telechargement' ),
				'login_modal_title'      => __( 'Dokumentenzugriff', 'centre-telechargement' ),
				'login_tab_label'        => __( 'Login', 'centre-telechargement' ),
				'login_username_label'   => __( 'Benutzername oder E-Mail-Adresse', 'centre-telechargement' ),
				'login_password_label'   => __( 'Passwort', 'centre-telechargement' ),
				'password_tab_label'     => __( 'Passwort anfordern', 'centre-telechargement' ),
				'password_shortcode_empty_message' => __( 'Fügen Sie den Shortcode des Kontaktformulars in den Einstellungen hinzu.', 'centre-telechargement' ),
				'filter_category_label'  => __( 'Kategorie', 'centre-telechargement' ),
				'filter_category_empty_label' => __( 'Alle Kategorien', 'centre-telechargement' ),
				'filter_range_label'     => __( 'Produktreihe', 'centre-telechargement' ),
				'filter_range_empty_label' => __( 'Alle Produktreihen', 'centre-telechargement' ),
				'filter_language_label'  => __( 'Sprache', 'centre-telechargement' ),
				'filter_language_empty_label' => __( 'Alle Sprachen', 'centre-telechargement' ),
				'empty_message'          => __( 'Kein Dokument entspricht den ausgewählten Filtern.', 'centre-telechargement' ),
			),
		),
		'it' => array(
			'label'   => __( 'Italien', 'centre-telechargement' ),
			'strings' => array(
				'login_notice_text'      => __( 'Accedi per scaricare i file', 'centre-telechargement' ),
				'login_button_text'      => __( 'Accesso / Richiesta password', 'centre-telechargement' ),
				'login_modal_title'      => __( 'Accesso ai documenti', 'centre-telechargement' ),
				'login_tab_label'        => __( 'Accesso', 'centre-telechargement' ),
				'login_username_label'   => __( 'Nome utente o indirizzo e-mail', 'centre-telechargement' ),
				'login_password_label'   => __( 'Password', 'centre-telechargement' ),
				'password_tab_label'     => __( 'Richiesta password', 'centre-telechargement' ),
				'password_shortcode_empty_message' => __( 'Aggiungi lo shortcode del modulo di contatto nelle impostazioni.', 'centre-telechargement' ),
				'filter_category_label'  => __( 'Categoria', 'centre-telechargement' ),
				'filter_category_empty_label' => __( 'Tutte le categorie', 'centre-telechargement' ),
				'filter_range_label'     => __( 'Gamma', 'centre-telechargement' ),
				'filter_range_empty_label' => __( 'Tutte le gamme', 'centre-telechargement' ),
				'filter_language_label'  => __( 'Lingua', 'centre-telechargement' ),
				'filter_language_empty_label' => __( 'Tutte le lingue', 'centre-telechargement' ),
				'empty_message'          => __( 'Nessun documento corrisponde ai filtri selezionati.', 'centre-telechargement' ),
			),
		),
	);
}

/**
 * @return array<int, string>
 */
function ctd_get_default_frontend_language_codes() {
	return array( 'fr', 'en' );
}

/**
 * @return array<string, array<string, string>>
 */
function ctd_get_default_languages() {
	return array(
		'fr' => array(
			'name'           => __( 'Français', 'centre-telechargement' ),
			'slug'           => 'fr',
			'flag'           => 'fr_FR.png',
			'translation_en' => 'French',
			'translations'   => array(
				'en' => 'French',
				'es' => 'Francés',
				'de' => 'Französisch',
				'it' => 'Francese',
			),
		),
		'en' => array(
			'name'           => __( 'Anglais', 'centre-telechargement' ),
			'slug'           => 'en',
			'flag'           => 'en_UK.png',
			'translation_en' => 'English',
			'translations'   => array(
				'en' => 'English',
				'es' => 'Inglés',
				'de' => 'Englisch',
				'it' => 'Inglese',
			),
		),
		'es' => array(
			'name'           => __( 'Espagnol', 'centre-telechargement' ),
			'slug'           => 'es',
			'flag'           => 'es_ES.png',
			'translation_en' => 'Spanish',
			'translations'   => array(
				'en' => 'Spanish',
				'es' => 'Español',
				'de' => 'Spanisch',
				'it' => 'Spagnolo',
			),
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

/**
 * Returns allowed related terms for selected categories.
 *
 * @param mixed  $category_ids Category term IDs.
 * @param string $taxonomy Target taxonomy.
 * @return array{restricted: bool, ids: array<int>}
 */
function ctd_get_allowed_related_term_ids_for_categories( $category_ids, $taxonomy ) {
	$category_ids = ctd_sanitize_term_id_list( $category_ids );

	if ( ! in_array( $taxonomy, array( CTD_RANGE_TAXONOMY, CTD_LANGUAGE_TAXONOMY ), true ) ) {
		return array(
			'restricted' => false,
			'ids'        => array(),
		);
	}

	if ( empty( $category_ids ) ) {
		return array(
			'restricted' => true,
			'ids'        => array(),
		);
	}

	$allowed_ids = array();

	foreach ( $category_ids as $category_id ) {
		$category = ctd_get_category_term( $category_id );

		if ( ! $category ) {
			continue;
		}

		$term_ids = CTD_RANGE_TAXONOMY === $taxonomy
			? ctd_get_category_linked_range_ids( $category )
			: ctd_get_category_linked_language_ids( $category );

		if ( empty( $term_ids ) ) {
			return array(
				'restricted' => false,
				'ids'        => array(),
			);
		}

		$allowed_ids = array_merge( $allowed_ids, $term_ids );
	}

	return array(
		'restricted' => true,
		'ids'        => ctd_sanitize_term_id_list( $allowed_ids ),
	);
}

require_once CTD_PLUGIN_DIR . 'includes/post-types.php';
require_once CTD_PLUGIN_DIR . 'includes/taxonomies.php';
require_once CTD_PLUGIN_DIR . 'includes/analytics.php';
require_once CTD_PLUGIN_DIR . 'includes/reports.php';
require_once CTD_PLUGIN_DIR . 'includes/meta-boxes.php';
require_once CTD_PLUGIN_DIR . 'includes/settings.php';
require_once CTD_PLUGIN_DIR . 'includes/frontend.php';
require_once CTD_PLUGIN_DIR . 'includes/admin-columns.php';

register_activation_hook( __FILE__, 'ctd_activate' );
register_deactivation_hook( __FILE__, 'ctd_deactivate' );

function ctd_activate() {
	ctd_register_post_type();
	ctd_register_taxonomy();
	ctd_seed_default_languages( true );
	ctd_create_analytics_table();
	ctd_reschedule_stats_report();
	flush_rewrite_rules();
}

function ctd_deactivate() {
	wp_clear_scheduled_hook( CTD_REPORT_CRON_HOOK );
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

	$tour_page_key = ctd_get_admin_tour_current_page_key( $screen, $hook_suffix );

	wp_enqueue_style(
		'ctd-admin',
		CTD_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		CTD_VERSION
	);

	ctd_enqueue_admin_tour_assets( $tour_page_key );

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
		wp_add_inline_script( 'jquery', ctd_get_document_edit_relationship_script() );
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

/**
 * @return array<string, array<string, array{restricted: bool, ids: array<int>}>>
 */
function ctd_get_category_filter_relationship_term_ids() {
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
		$range_ids    = ctd_get_category_linked_range_ids( $category );
		$language_ids = ctd_get_category_linked_language_ids( $category );

		$relationships[ (string) $category->term_id ] = array(
			'ranges'    => array(
				'restricted' => ! empty( $range_ids ),
				'ids'        => $range_ids,
			),
			'languages' => array(
				'restricted' => ! empty( $language_ids ),
				'ids'        => $language_ids,
			),
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
		$('#ctd_pdf_file_id').val(attachment.id || '').trigger('change');
		$('#ctd-pdf-filename').text(attachment.filename || attachment.title || 'Document PDF');
		$('#ctd-pdf-link').attr('href', '#').addClass('hidden');
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

		$('#ctd_pdf_file_id').val('').trigger('change');
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
function ctd_get_document_edit_relationship_script() {
	$relationships = wp_json_encode( ctd_get_category_filter_relationship_term_ids() );
	$relationships = $relationships ? $relationships : '{}';
	$category_box  = esc_js( CTD_TAXONOMY . 'div' );
	$range_box     = esc_js( CTD_RANGE_TAXONOMY . 'div' );
	$language_box  = esc_js( CTD_LANGUAGE_TAXONOMY . 'div' );

	return <<<JS
(function($) {
	'use strict';

	var relationships = $relationships;
	var boxes = {
		category: '$category_box',
		range: '$range_box',
		language: '$language_box'
	};

	function getCheckboxes(boxId) {
		return $('#' + boxId + ' input[type="checkbox"]');
	}

	function normalizeIds(ids) {
		if (!Array.isArray(ids)) {
			return [];
		}

		return ids.map(function(id) {
			return String(id);
		}).filter(Boolean);
	}

	function getSelectedCategoryIds() {
		return getCheckboxes(boxes.category).filter(':checked').map(function() {
			return String(this.value);
		}).get();
	}

	function normalizeRelationship(raw) {
		var ids;

		if (!raw) {
			return {
				restricted: false,
				ids: []
			};
		}

		if (Array.isArray(raw)) {
			ids = normalizeIds(raw);

			return {
				restricted: ids.length > 0,
				ids: ids
			};
		}

		return {
			restricted: !!raw.restricted,
			ids: normalizeIds(raw.ids)
		};
	}

	function getAvailability(selectedCategoryIds, key) {
		var allowedIds = [];
		var index;

		if (!selectedCategoryIds.length) {
			return {
				restricted: true,
				ids: []
			};
		}

		for (index = 0; index < selectedCategoryIds.length; index++) {
			var categoryId = selectedCategoryIds[index];
			var relationship = relationships[categoryId];
			var availability = relationship ? normalizeRelationship(relationship[key]) : null;

			if (!relationship) {
				return {
					restricted: false,
					ids: []
				};
			}

			if (!availability.restricted) {
				return {
					restricted: false,
					ids: []
				};
			}

			availability.ids.forEach(function(id) {
				if (allowedIds.indexOf(id) === -1) {
					allowedIds.push(id);
				}
			});
		}

		return {
			restricted: true,
			ids: allowedIds
		};
	}

	function setTermVisibility(checkbox, isVisible) {
		var field = $(checkbox);
		var wrapper = field.closest('li');

		if (!wrapper.length) {
			wrapper = field.closest('label');
		}

		if (!isVisible && checkbox.checked) {
			checkbox.checked = false;
		}

		checkbox.disabled = !isVisible;
		wrapper.toggleClass('ctd-related-term-hidden', !isVisible);
	}

	function applyRelatedTerms(boxId, key) {
		var selectedCategoryIds = getSelectedCategoryIds();
		var availability = getAvailability(selectedCategoryIds, key);

		getCheckboxes(boxId).each(function() {
			var value = String(this.value);
			var isVisible = !availability.restricted || availability.ids.indexOf(value) !== -1;

			setTermVisibility(this, isVisible);
		});
	}

	function refreshRelatedTerms() {
		applyRelatedTerms(boxes.range, 'ranges');
		applyRelatedTerms(boxes.language, 'languages');
	}

	$(document).on('change', '#' + boxes.category + ' input[type="checkbox"]', refreshRelatedTerms);

	$(function() {
		var categoryBox = document.getElementById(boxes.category);
		var rangeBox = document.getElementById(boxes.range);
		var languageBox = document.getElementById(boxes.language);
		var observer;

		refreshRelatedTerms();

		if (typeof MutationObserver !== 'undefined') {
			observer = new MutationObserver(refreshRelatedTerms);

			[categoryBox, rangeBox, languageBox].forEach(function(box) {
				if (box) {
					observer.observe(box, {
						childList: true,
						subtree: true
					});
				}
			});
		}
	});
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
