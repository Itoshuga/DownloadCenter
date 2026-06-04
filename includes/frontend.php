<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'documents_library', 'ctd_render_documents_library_shortcode' );
add_shortcode( 'centre_telechargement', 'ctd_render_documents_library_shortcode' );
add_action( 'wp_enqueue_scripts', 'ctd_enqueue_frontend_base_assets' );
add_action( 'admin_post_ctd_document_file', 'ctd_handle_document_file_request' );
add_action( 'admin_post_nopriv_ctd_document_file', 'ctd_handle_document_file_request' );

/**
 * @param array<string, mixed> $atts Shortcode attributes.
 * @return string
 */
function ctd_render_documents_library_shortcode( $atts = array() ) {
	$settings = ctd_get_frontend_settings();
	$language = ctd_get_current_frontend_language();
	$strings  = ctd_get_frontend_i18n_strings( $language, $settings );
	$atts     = shortcode_atts(
		array(
			'empty_message' => $strings['empty_message'],
		),
		$atts,
		'documents_library'
	);

	$documents = ctd_get_frontend_library_documents();
	$filters   = ctd_get_frontend_library_filters( $documents );
	$relationships = ctd_get_frontend_filter_relationships_json();

	ctd_enqueue_frontend_assets();

	ob_start();
	?>
	<div class="ctd-front-library" data-ctd-library data-ctd-filter-relationships="<?php echo esc_attr( $relationships ); ?>">
		<?php ctd_render_frontend_login_prompt( $settings, $language ); ?>

		<div class="ctd-front-filters" aria-label="<?php esc_attr_e( 'Filtres des documents', 'centre-telechargement' ); ?>">
			<?php
			ctd_render_frontend_filter_select(
				'category',
				$strings['filter_category_label'],
				$strings['filter_category_empty_label'],
				$filters['categories']
			);

			ctd_render_frontend_filter_select(
				'range',
				$strings['filter_range_label'],
				$strings['filter_range_empty_label'],
				$filters['ranges']
			);

			ctd_render_frontend_filter_select(
				'language',
				$strings['filter_language_label'],
				$strings['filter_language_empty_label'],
				$filters['languages']
			);
			?>
		</div>

		<div class="ctd-front-grid" data-ctd-documents-grid>
			<?php foreach ( $documents as $document ) : ?>
				<?php ctd_render_frontend_document_card( $document ); ?>
			<?php endforeach; ?>
		</div>

		<p class="ctd-front-empty<?php echo empty( $documents ) ? ' is-visible' : ''; ?>" data-ctd-empty>
			<?php echo esc_html( $atts['empty_message'] ); ?>
		</p>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * @return string
 */
function ctd_get_frontend_filter_relationships_json() {
	$relationships = function_exists( 'ctd_get_category_filter_relationships' )
		? ctd_get_category_filter_relationships()
		: array();
	$json          = wp_json_encode( $relationships );

	return $json ? $json : '{}';
}

/**
 * @param array<string, string> $settings Frontend settings.
 * @param string                $language Current language.
 * @return void
 */
function ctd_render_frontend_login_prompt( $settings, $language = '' ) {
	if ( is_user_logged_in() ) {
		return;
	}

	$strings     = ctd_get_frontend_i18n_strings( $language, $settings );
	$modal_id    = 'ctd-front-login-modal-' . wp_rand( 1000, 9999 );
	$notice_text = $strings['login_notice_text'];
	$button_text = $strings['login_button_text'];
	$shortcode   = trim( $strings['password_request_shortcode'] );
	?>
	<div class="ctd-front-login-prompt">
		<p><?php echo esc_html( $notice_text ); ?></p>
		<button
			type="button"
			class="ctd-front-login-button"
			aria-haspopup="dialog"
			aria-controls="<?php echo esc_attr( $modal_id ); ?>"
			data-ctd-modal-open="<?php echo esc_attr( $modal_id ); ?>"
		>
			<?php echo esc_html( $button_text ); ?>
		</button>
	</div>

	<div
		id="<?php echo esc_attr( $modal_id ); ?>"
		class="ctd-front-modal"
		role="dialog"
		aria-modal="true"
		aria-labelledby="<?php echo esc_attr( $modal_id ); ?>-title"
		hidden
		data-ctd-modal
	>
		<div class="ctd-front-modal-backdrop" data-ctd-modal-close></div>
		<div class="ctd-front-modal-panel" role="document">
			<div class="ctd-front-modal-header">
				<h2 id="<?php echo esc_attr( $modal_id ); ?>-title">
					<?php echo esc_html( $strings['login_modal_title'] ); ?>
				</h2>
				<button type="button" class="ctd-front-modal-close" aria-label="<?php esc_attr_e( 'Fermer', 'centre-telechargement' ); ?>" data-ctd-modal-close>
					<i class="fa-solid fa-xmark" aria-hidden="true"></i>
				</button>
			</div>

			<div class="ctd-front-modal-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Modes d\'accès', 'centre-telechargement' ); ?>">
				<button type="button" class="ctd-front-modal-tab is-active" role="tab" aria-selected="true" data-ctd-tab-button="login">
					<?php echo esc_html( $strings['login_tab_label'] ); ?>
				</button>
				<button type="button" class="ctd-front-modal-tab" role="tab" aria-selected="false" data-ctd-tab-button="password">
					<?php echo esc_html( $strings['password_tab_label'] ); ?>
				</button>
			</div>

			<div class="ctd-front-modal-content">
				<div class="ctd-front-modal-pane is-active" role="tabpanel" data-ctd-tab-panel="login">
					<?php
					echo ctd_get_frontend_login_form_html( $modal_id, $strings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>

				<div class="ctd-front-modal-pane" role="tabpanel" data-ctd-tab-panel="password" hidden>
					<?php if ( $shortcode ) : ?>
						<?php echo do_shortcode( $shortcode ); ?>
					<?php else : ?>
						<p class="ctd-front-modal-empty">
							<?php echo esc_html( $strings['password_shortcode_empty_message'] ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * @param string $id_suffix Unique field suffix.
 * @param array<string, string> $strings Frontend i18n strings.
 * @return string
 */
function ctd_get_frontend_login_form_html( $id_suffix = '', $strings = array() ) {
	$redirect_url = ctd_get_current_frontend_url();
	$id_suffix    = sanitize_html_class( $id_suffix );
	$username_label = isset( $strings['login_username_label'] ) ? $strings['login_username_label'] : __( 'Identifiant ou adresse e-mail', 'centre-telechargement' );
	$password_label = isset( $strings['login_password_label'] ) ? $strings['login_password_label'] : __( 'Mot de passe', 'centre-telechargement' );

	return wp_login_form(
		array(
			'echo'           => false,
			'redirect'       => $redirect_url,
			'remember'       => true,
			'label_username' => $username_label,
			'label_password' => $password_label,
			'label_remember' => __( 'Se souvenir de moi', 'centre-telechargement' ),
			'label_log_in'   => __( 'Connexion', 'centre-telechargement' ),
			'id_username'    => $id_suffix ? $id_suffix . '-user-login' : 'user_login',
			'id_password'    => $id_suffix ? $id_suffix . '-user-pass' : 'user_pass',
			'id_remember'    => $id_suffix ? $id_suffix . '-rememberme' : 'rememberme',
			'id_submit'      => $id_suffix ? $id_suffix . '-wp-submit' : 'wp-submit',
		)
	);
}

/**
 * @return string
 */
function ctd_get_current_frontend_url() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

	if ( '' === $uri || '/' !== $uri[0] ) {
		$uri = '/';
	}

	return esc_url_raw( home_url( $uri ) );
}

/**
 * @return void
 */
function ctd_enqueue_frontend_assets() {
	ctd_enqueue_frontend_base_assets();
}

function ctd_enqueue_frontend_base_assets() {
	if ( is_admin() ) {
		return;
	}

	ctd_enqueue_font_awesome();

	wp_enqueue_style(
		'ctd-frontend',
		CTD_PLUGIN_URL . 'assets/css/frontend.css',
		array(),
		CTD_VERSION
	);
	wp_add_inline_style( 'ctd-frontend', ctd_get_frontend_settings_css() );

	wp_enqueue_script(
		'ctd-frontend',
		CTD_PLUGIN_URL . 'assets/js/frontend.js',
		array(),
		CTD_VERSION,
		true
	);

}

/**
 * @return array<int, array<string, mixed>>
 */
function ctd_get_frontend_library_documents() {
	$query = new WP_Query(
		array(
			'post_type'              => CTD_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		)
	);

	$documents = array();

	foreach ( $query->posts as $post ) {
		$attachment_id = absint( get_post_meta( $post->ID, CTD_META_FILE_ID, true ) );

		if ( ! $attachment_id || ! ctd_attachment_is_pdf( $attachment_id ) || ! ctd_user_can_see_document_card( $post->ID ) ) {
			continue;
		}

		$can_access = ctd_user_can_access_document( $post->ID );

		$documents[] = array(
			'id'           => $post->ID,
			'title'        => get_the_title( $post ),
			'attachment_id' => $attachment_id,
			'preview_url'  => ctd_get_document_pdf_preview_url( $attachment_id ),
			'can_access'   => $can_access,
			'open_url'     => $can_access ? ctd_get_document_file_action_url( $post->ID, 'open' ) : '',
			'download_url' => $can_access ? ctd_get_document_file_action_url( $post->ID, 'download' ) : '',
			'categories'   => ctd_get_document_frontend_terms( $post->ID, CTD_TAXONOMY ),
			'ranges'       => ctd_get_document_frontend_terms( $post->ID, CTD_RANGE_TAXONOMY ),
			'languages'    => ctd_get_document_frontend_terms( $post->ID, CTD_LANGUAGE_TAXONOMY ),
		);
	}

	wp_reset_postdata();

	return $documents;
}

/**
 * Logged-in users can see every document card, but opening/downloading still
 * depends on ctd_user_can_access_document(). Anonymous visitors see no cards.
 *
 * @param int $post_id Document post ID.
 * @return bool
 */
function ctd_user_can_see_document_card( $post_id ) {
	return absint( $post_id ) && is_user_logged_in();
}

/**
 * @param int    $post_id Post ID.
 * @param string $taxonomy Taxonomy name.
 * @return array<int, array{id:int,name:string,slug:string}>
 */
function ctd_get_document_frontend_terms( $post_id, $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return array();
	}

	$items = array();

	foreach ( $terms as $term ) {
		$items[] = array(
			'id'   => $term->term_id,
			'name' => ctd_get_translated_term_name( $term, ctd_get_current_frontend_language() ),
			'slug' => $term->slug,
		);
	}

	return $items;
}

/**
 * @param int $attachment_id PDF attachment ID.
 * @return string
 */
function ctd_get_document_pdf_preview_url( $attachment_id ) {
	$preview_url = wp_get_attachment_image_url( $attachment_id, 'medium_large' );

	if ( ! $preview_url ) {
		$preview_url = wp_get_attachment_image_url( $attachment_id, 'medium' );
	}

	return $preview_url ? $preview_url : '';
}

/**
 * @param int    $document_id Document post ID.
 * @param string $event_type Event type.
 * @return string
 */
function ctd_get_document_file_action_url( $document_id, $event_type ) {
	$event_type = ctd_normalize_document_event_type( $event_type );

	return add_query_arg(
		array(
			'action'      => 'ctd_document_file',
			'document_id' => absint( $document_id ),
			'event'       => $event_type,
			'_wpnonce'    => wp_create_nonce( 'ctd_document_file_' . absint( $document_id ) . '_' . $event_type ),
		),
		admin_url( 'admin-post.php' )
	);
}

/**
 * @param array<int, array<string, mixed>> $documents Frontend documents.
 * @return array{categories:array<int,array<string,string>>,ranges:array<int,array<string,string>>,languages:array<int,array<string,string>>}
 */
function ctd_get_frontend_library_filters( $documents ) {
	return array(
		'categories' => ctd_get_frontend_filter_terms( CTD_TAXONOMY ),
		'ranges'     => ctd_get_frontend_filter_terms( CTD_RANGE_TAXONOMY ),
		'languages'  => ctd_get_frontend_filter_terms( CTD_LANGUAGE_TAXONOMY ),
	);
}

/**
 * @param string $taxonomy Taxonomy name.
 * @return array<int, array<string, string>>
 */
function ctd_get_frontend_filter_terms( $taxonomy ) {
	$language = ctd_get_current_frontend_language();
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	return array_map(
		static function ( $term ) use ( $taxonomy, $language ) {
			$item = array(
				'name' => ctd_get_translated_term_name( $term, $language ),
				'slug' => $term->slug,
			);

			if ( CTD_TAXONOMY === $taxonomy ) {
				$item['protected'] = ctd_category_has_protected_hint( $term );
			}

			if ( CTD_LANGUAGE_TAXONOMY === $taxonomy ) {
				$item['flag_url'] = ctd_get_language_flag_url( $term );
			}

			return $item;
		},
		$terms
	);
}

/**
 * @param string $filter_key Filter key.
 * @param string $label Field label.
 * @param string $empty_label Empty option label.
 * @param array<int, array<string, string>> $terms Filter terms.
 * @return void
 */
function ctd_render_frontend_filter_select( $filter_key, $label, $empty_label, $terms ) {
	$field_id  = 'ctd-front-filter-' . sanitize_html_class( $filter_key ) . '-' . wp_rand( 1000, 9999 );
	$label_id  = $field_id . '-label';
	$button_id = $field_id . '-button';
	$menu_id   = $field_id . '-menu';
	?>
	<div class="ctd-front-filter ctd-front-filter-<?php echo esc_attr( sanitize_html_class( $filter_key ) ); ?>" data-ctd-filter-control="<?php echo esc_attr( $filter_key ); ?>">
		<span class="ctd-front-filter-label" id="<?php echo esc_attr( $label_id ); ?>">
			<?php echo esc_html( $label ); ?>
		</span>
		<input type="hidden" id="<?php echo esc_attr( $field_id ); ?>" value="" data-ctd-filter="<?php echo esc_attr( $filter_key ); ?>" />
		<button
			type="button"
			id="<?php echo esc_attr( $button_id ); ?>"
			class="ctd-front-filter-button"
			aria-haspopup="listbox"
			aria-expanded="false"
			aria-controls="<?php echo esc_attr( $menu_id ); ?>"
			aria-labelledby="<?php echo esc_attr( $label_id . ' ' . $button_id ); ?>"
			data-ctd-filter-button
		>
			<span class="ctd-front-filter-current" data-ctd-filter-current>
				<?php ctd_render_frontend_filter_option_content( $filter_key, array( 'name' => $empty_label ) ); ?>
			</span>
		</button>
		<div
			id="<?php echo esc_attr( $menu_id ); ?>"
			class="ctd-front-filter-menu"
			role="listbox"
			aria-labelledby="<?php echo esc_attr( $label_id ); ?>"
			data-ctd-filter-menu
		>
			<button
				type="button"
				class="ctd-front-filter-option is-selected"
				role="option"
				aria-selected="true"
				data-ctd-filter-option
				data-value=""
			>
				<span data-ctd-filter-option-content>
					<?php ctd_render_frontend_filter_option_content( $filter_key, array( 'name' => $empty_label ) ); ?>
				</span>
			</button>
			<?php foreach ( $terms as $term ) : ?>
				<button
					type="button"
					class="ctd-front-filter-option"
					role="option"
					aria-selected="false"
					data-ctd-filter-option
					data-value="<?php echo esc_attr( $term['slug'] ); ?>"
				>
					<span data-ctd-filter-option-content>
						<?php ctd_render_frontend_filter_option_content( $filter_key, $term ); ?>
					</span>
				</button>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * @param string               $filter_key Filter key.
 * @param array<string, mixed> $term Term data.
 * @return void
 */
function ctd_render_frontend_filter_option_content( $filter_key, $term ) {
	$name = isset( $term['name'] ) ? (string) $term['name'] : '';

	if ( 'category' === $filter_key && array_key_exists( 'protected', $term ) ) {
		$is_protected = ! empty( $term['protected'] );
		$icon_class   = $is_protected ? 'fa-lock' : 'fa-lock-open';
		$label        = $is_protected
			? __( 'Catégorie protégée', 'centre-telechargement' )
			: __( 'Catégorie non protégée', 'centre-telechargement' );
		?>
		<span class="ctd-front-filter-icon ctd-front-filter-lock<?php echo $is_protected ? ' is-locked' : ' is-unlocked'; ?>" aria-label="<?php echo esc_attr( $label ); ?>">
			<i class="fa-solid <?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></i>
		</span>
		<?php
	}

	if ( 'language' === $filter_key && ! empty( $term['flag_url'] ) ) {
		?>
		<img class="ctd-front-filter-flag" src="<?php echo esc_url( $term['flag_url'] ); ?>" alt="" loading="lazy" />
		<?php
	}
	?>
	<span class="ctd-front-filter-option-text"><?php echo esc_html( $name ); ?></span>
	<?php
}

/**
 * @param array<string, mixed> $document Frontend document.
 * @return void
 */
function ctd_render_frontend_document_card( $document ) {
	$template = CTD_PLUGIN_DIR . 'templates/document-card.php';

	if ( file_exists( $template ) ) {
		include $template;
	}
}

function ctd_handle_document_file_request() {
	$document_id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;
	$event_type  = isset( $_GET['event'] ) ? ctd_normalize_document_event_type( wp_unslash( $_GET['event'] ) ) : 'open';
	$nonce       = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

	if ( ! $document_id || ! wp_verify_nonce( $nonce, 'ctd_document_file_' . $document_id . '_' . $event_type ) ) {
		ctd_frontend_document_die( __( 'Lien invalide.', 'centre-telechargement' ), 403 );
	}

	if (
		CTD_POST_TYPE !== get_post_type( $document_id )
		|| ( 'publish' !== get_post_status( $document_id ) && ! current_user_can( 'edit_post', $document_id ) )
	) {
		ctd_frontend_document_die( __( 'Document introuvable.', 'centre-telechargement' ), 404 );
	}

	if ( ! ctd_user_can_access_document( $document_id ) ) {
		ctd_frontend_document_die( __( 'Vous n’avez pas accès à ce document.', 'centre-telechargement' ), 403 );
	}

	$attachment_id = absint( get_post_meta( $document_id, CTD_META_FILE_ID, true ) );

	if ( ! $attachment_id || ! ctd_attachment_is_pdf( $attachment_id ) ) {
		ctd_frontend_document_die( __( 'Fichier PDF introuvable.', 'centre-telechargement' ), 404 );
	}

	ctd_log_document_event( $document_id, $event_type );
	ctd_output_document_file( $attachment_id, $event_type );
}

/**
 * @param int    $attachment_id PDF attachment ID.
 * @param string $event_type Event type.
 * @return void
 */
function ctd_output_document_file( $attachment_id, $event_type ) {
	$file_path = get_attached_file( $attachment_id );

	if ( ! $file_path || ! is_readable( $file_path ) ) {
		ctd_frontend_document_die( __( 'Fichier PDF introuvable.', 'centre-telechargement' ), 404 );
	}

	$file_name         = sanitize_file_name( wp_basename( $file_path ) );
	$file_name         = $file_name ? $file_name : 'document.pdf';
	$encoded_file_name = rawurlencode( $file_name );
	$file_size         = filesize( $file_path );
	$disposition       = 'download' === $event_type ? 'attachment' : 'inline';

	while ( ob_get_level() ) {
		ob_end_clean();
	}

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: ' . $disposition . '; filename="' . $file_name . '"; filename*=UTF-8\'\'' . $encoded_file_name );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Robots-Tag: noindex, nofollow', true );

	if ( false !== $file_size ) {
		header( 'Content-Length: ' . absint( $file_size ) );
	}

	readfile( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	exit;
}

/**
 * @param string $message Error message.
 * @param int    $response HTTP status code.
 * @return void
 */
function ctd_frontend_document_die( $message, $response ) {
	wp_die(
		esc_html( $message ),
		esc_html__( 'Centre de téléchargement', 'centre-telechargement' ),
		array(
			'response' => absint( $response ),
		)
	);
}
