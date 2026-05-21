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
	$atts = shortcode_atts(
		array(
			'empty_message' => __( 'Aucun document ne correspond aux filtres sélectionnés.', 'centre-telechargement' ),
		),
		$atts,
		'documents_library'
	);

	$documents = ctd_get_frontend_library_documents();
	$filters   = ctd_get_frontend_library_filters( $documents );

	ctd_enqueue_frontend_assets();

	ob_start();
	?>
	<div class="ctd-front-library" data-ctd-library>
		<div class="ctd-front-filters" aria-label="<?php esc_attr_e( 'Filtres des documents', 'centre-telechargement' ); ?>">
			<?php
			ctd_render_frontend_filter_select(
				'category',
				__( 'Catégorie', 'centre-telechargement' ),
				__( 'Toutes les catégories', 'centre-telechargement' ),
				$filters['categories']
			);

			ctd_render_frontend_filter_select(
				'range',
				__( 'Gamme', 'centre-telechargement' ),
				__( 'Toutes les gammes', 'centre-telechargement' ),
				$filters['ranges']
			);

			ctd_render_frontend_filter_select(
				'language',
				__( 'Langue', 'centre-telechargement' ),
				__( 'Toutes les langues', 'centre-telechargement' ),
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
 * @return void
 */
function ctd_enqueue_frontend_assets() {
	ctd_enqueue_frontend_base_assets();
}

function ctd_enqueue_frontend_base_assets() {
	if ( is_admin() ) {
		return;
	}

	foreach ( array( 'elementor-icons-fa-solid', 'font-awesome', 'fontawesome' ) as $font_awesome_handle ) {
		if ( wp_style_is( $font_awesome_handle, 'registered' ) ) {
			wp_enqueue_style( $font_awesome_handle );
			break;
		}
	}

	wp_enqueue_style(
		'ctd-frontend',
		CTD_PLUGIN_URL . 'assets/css/frontend.css',
		array(),
		CTD_VERSION
	);

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

		if ( ! $attachment_id || ! ctd_attachment_is_pdf( $attachment_id ) || ! ctd_user_can_access_document( $post->ID ) ) {
			continue;
		}

		$documents[] = array(
			'id'           => $post->ID,
			'title'        => get_the_title( $post ),
			'attachment_id' => $attachment_id,
			'preview_url'  => ctd_get_document_pdf_preview_url( $attachment_id ),
			'open_url'     => ctd_get_document_file_action_url( $post->ID, 'open' ),
			'download_url' => ctd_get_document_file_action_url( $post->ID, 'download' ),
			'categories'   => ctd_get_document_frontend_terms( $post->ID, CTD_TAXONOMY ),
			'ranges'       => ctd_get_document_frontend_terms( $post->ID, CTD_RANGE_TAXONOMY ),
			'languages'    => ctd_get_document_frontend_terms( $post->ID, CTD_LANGUAGE_TAXONOMY ),
		);
	}

	wp_reset_postdata();

	return $documents;
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
			'name' => $term->name,
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
		static function ( $term ) {
			return array(
				'name' => $term->name,
				'slug' => $term->slug,
			);
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
	$field_id = 'ctd-front-filter-' . sanitize_html_class( $filter_key ) . '-' . wp_rand( 1000, 9999 );
	?>
	<label class="ctd-front-filter" for="<?php echo esc_attr( $field_id ); ?>">
		<span><?php echo esc_html( $label ); ?></span>
		<select id="<?php echo esc_attr( $field_id ); ?>" data-ctd-filter="<?php echo esc_attr( $filter_key ); ?>">
			<option value=""><?php echo esc_html( $empty_label ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( $term['slug'] ); ?>">
					<?php echo esc_html( $term['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</label>
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

	if ( CTD_POST_TYPE !== get_post_type( $document_id ) || 'publish' !== get_post_status( $document_id ) ) {
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
	$file_url  = wp_get_attachment_url( $attachment_id );

	if ( ! $file_path || ! is_readable( $file_path ) ) {
		if ( $file_url ) {
			wp_safe_redirect( $file_url );
			exit;
		}

		ctd_frontend_document_die( __( 'Fichier PDF introuvable.', 'centre-telechargement' ), 404 );
	}

	$file_name   = wp_basename( $file_path );
	$disposition = 'download' === $event_type ? 'attachment' : 'inline';

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Length: ' . filesize( $file_path ) );
	header( 'Content-Disposition: ' . $disposition . '; filename="' . sanitize_file_name( $file_name ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );

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
