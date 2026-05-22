<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'manage_' . CTD_POST_TYPE . '_posts_columns', 'ctd_filter_document_columns' );
add_action( 'manage_' . CTD_POST_TYPE . '_posts_custom_column', 'ctd_render_document_column', 10, 2 );
add_filter( 'manage_edit-' . CTD_POST_TYPE . '_sortable_columns', 'ctd_filter_sortable_document_columns' );
add_action( 'restrict_manage_posts', 'ctd_render_document_filters' );
add_action( 'pre_get_posts', 'ctd_filter_admin_documents_query' );

/**
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string>
 */
function ctd_filter_document_columns( $columns ) {
	$new_columns = array();

	if ( isset( $columns['cb'] ) ) {
		$new_columns['cb'] = $columns['cb'];
	}

	$new_columns['title']          = __( 'Titre', 'centre-telechargement' );
	$new_columns['ctd_pdf']        = __( 'Fichier PDF', 'centre-telechargement' );
	$new_columns['ctd_categories'] = __( 'Catégories', 'centre-telechargement' );
	$new_columns['ctd_ranges']     = __( 'Gammes', 'centre-telechargement' );
	$new_columns['ctd_languages']  = __( 'Langues', 'centre-telechargement' );
	$new_columns['ctd_status']     = __( 'Statut', 'centre-telechargement' );

	return $new_columns;
}

/**
 * @param string $column Column key.
 * @param int    $post_id Post ID.
 * @return void
 */
function ctd_render_document_column( $column, $post_id ) {
	switch ( $column ) {
		case 'ctd_pdf':
			ctd_render_document_file_column( $post_id );
			break;

		case 'ctd_categories':
			ctd_render_document_categories_column( $post_id );
			break;

		case 'ctd_ranges':
			ctd_render_document_ranges_column( $post_id );
			break;

		case 'ctd_languages':
			ctd_render_document_languages_column( $post_id );
			break;

		case 'ctd_status':
			echo ctd_get_status_badge_html( ctd_get_document_status( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			break;
	}
}

/**
 * @param int $post_id Post ID.
 * @return void
 */
function ctd_render_document_file_column( $post_id ) {
	$attachment_id = absint( get_post_meta( $post_id, CTD_META_FILE_ID, true ) );

	if ( ! $attachment_id || ! ctd_attachment_is_pdf( $attachment_id ) ) {
		echo '<span class="ctd-empty-value">—</span>';
		return;
	}

	$file_url  = function_exists( 'ctd_get_document_file_action_url' )
		? ctd_get_document_file_action_url( $post_id, 'open' )
		: '';
	$file_path = get_attached_file( $attachment_id );
	$file_name = $file_path ? wp_basename( $file_path ) : get_the_title( $attachment_id );

	if ( ! $file_url ) {
		echo '<span class="ctd-empty-value">—</span>';
		return;
	}

	printf(
		'<a class="ctd-file-pill" href="%1$s" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-media-document" aria-hidden="true"></span><span>%2$s</span></a>',
		esc_url( $file_url ),
		esc_html( $file_name )
	);
}

/**
 * @param int $post_id Post ID.
 * @return void
 */
function ctd_render_document_categories_column( $post_id ) {
	$terms = get_the_terms( $post_id, CTD_TAXONOMY );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		echo '<span class="ctd-empty-value">—</span>';
		return;
	}

	echo '<div class="ctd-category-list">';

	foreach ( $terms as $term ) {
		$url = add_query_arg(
			array(
				'post_type'                    => CTD_POST_TYPE,
				'ctd_download_category_filter' => $term->slug,
			),
			admin_url( 'edit.php' )
		);

		printf(
			'<a class="ctd-category-chip" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $term->name )
		);
	}

	echo '</div>';
}

/**
 * @param int $post_id Post ID.
 * @return void
 */
function ctd_render_document_ranges_column( $post_id ) {
	$terms = get_the_terms( $post_id, CTD_RANGE_TAXONOMY );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		echo '<span class="ctd-empty-value">—</span>';
		return;
	}

	echo '<div class="ctd-category-list">';

	foreach ( $terms as $term ) {
		$url = add_query_arg(
			array(
				'post_type'                 => CTD_POST_TYPE,
				'ctd_download_range_filter' => $term->slug,
			),
			admin_url( 'edit.php' )
		);

		printf(
			'<a class="ctd-category-chip" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $term->name )
		);
	}

	echo '</div>';
}

/**
 * @param int $post_id Post ID.
 * @return void
 */
function ctd_render_document_languages_column( $post_id ) {
	$terms = get_the_terms( $post_id, CTD_LANGUAGE_TAXONOMY );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		echo '<span class="ctd-empty-value">—</span>';
		return;
	}

	echo '<div class="ctd-language-list">';

	foreach ( $terms as $term ) {
		$url = add_query_arg(
			array(
				'post_type'                  => CTD_POST_TYPE,
				'ctd_download_language_filter' => $term->slug,
			),
			admin_url( 'edit.php' )
		);

		printf(
			'<a class="ctd-language-badge-link" href="%1$s">%2$s</a>',
			esc_url( $url ),
			ctd_get_language_badge_html( $term ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	echo '</div>';
}

/**
 * @param array<string, string> $columns Sortable columns.
 * @return array<string, string>
 */
function ctd_filter_sortable_document_columns( $columns ) {
	$columns['ctd_status'] = 'ctd_status';

	return $columns;
}

/**
 * @param string $post_type Current post type.
 * @return void
 */
function ctd_render_document_filters( $post_type ) {
	if ( CTD_POST_TYPE !== $post_type ) {
		return;
	}

	$selected_category = isset( $_GET['ctd_download_category_filter'] )
		? sanitize_title( wp_unslash( $_GET['ctd_download_category_filter'] ) )
		: '';
	$selected_range    = isset( $_GET['ctd_download_range_filter'] )
		? sanitize_title( wp_unslash( $_GET['ctd_download_range_filter'] ) )
		: '';
	$selected_language = isset( $_GET['ctd_download_language_filter'] )
		? sanitize_title( wp_unslash( $_GET['ctd_download_language_filter'] ) )
		: '';
	$selected_status   = isset( $_GET['ctd_document_status_filter'] )
		? ctd_normalize_document_status( wp_unslash( $_GET['ctd_document_status_filter'] ), '' )
		: '';

	wp_dropdown_categories(
		array(
			'taxonomy'        => CTD_TAXONOMY,
			'name'            => 'ctd_download_category_filter',
			'id'              => 'ctd_download_category_filter',
			'show_option_all' => __( 'Toutes les catégories', 'centre-telechargement' ),
			'hide_empty'      => false,
			'hierarchical'    => true,
			'orderby'         => 'name',
			'value_field'     => 'slug',
			'selected'        => $selected_category,
			'show_count'      => false,
		)
	);

	ctd_render_document_related_taxonomy_filter(
		CTD_RANGE_TAXONOMY,
		'ctd_download_range_filter',
		__( 'Toutes les gammes', 'centre-telechargement' ),
		$selected_range
	);

	ctd_render_document_related_taxonomy_filter(
		CTD_LANGUAGE_TAXONOMY,
		'ctd_download_language_filter',
		__( 'Toutes les langues', 'centre-telechargement' ),
		$selected_language
	);
	?>
	<label class="screen-reader-text" for="ctd_document_status_filter">
		<?php esc_html_e( 'Filtrer par statut', 'centre-telechargement' ); ?>
	</label>
	<select name="ctd_document_status_filter" id="ctd_document_status_filter">
		<option value=""><?php esc_html_e( 'Tous les statuts', 'centre-telechargement' ); ?></option>
		<?php foreach ( ctd_get_document_statuses() as $status_key => $status_label ) : ?>
			<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $selected_status, $status_key ); ?>>
				<?php echo esc_html( $status_label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * @param string $taxonomy Related taxonomy.
 * @param string $name Select field name and ID.
 * @param string $all_label Default option label.
 * @param string $selected Selected term slug.
 * @return void
 */
function ctd_render_document_related_taxonomy_filter( $taxonomy, $name, $all_label, $selected ) {
	$args = array(
		'taxonomy'        => $taxonomy,
		'name'            => $name,
		'id'              => $name,
		'show_option_all' => $all_label,
		'hide_empty'      => false,
		'hierarchical'    => CTD_LANGUAGE_TAXONOMY !== $taxonomy,
		'orderby'         => 'name',
		'value_field'     => 'slug',
		'selected'        => $selected,
		'show_count'      => false,
	);

	wp_dropdown_categories( $args );
}

/**
 * @param WP_Query $query Current query.
 * @return void
 */
function ctd_filter_admin_documents_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( CTD_POST_TYPE !== $query->get( 'post_type' ) ) {
		return;
	}

	$status = isset( $_GET['ctd_document_status_filter'] )
		? ctd_normalize_document_status( wp_unslash( $_GET['ctd_document_status_filter'] ), '' )
		: '';

	if ( $status ) {
		$meta_query = $query->get( 'meta_query' );
		$meta_query = is_array( $meta_query ) ? $meta_query : array();

		$meta_query[] = array(
			'key'   => CTD_META_STATUS,
			'value' => $status,
		);

		$query->set( 'meta_query', $meta_query );
	}

	$tax_query = $query->get( 'tax_query' );
	$tax_query = is_array( $tax_query ) ? $tax_query : array();
	$category  = isset( $_GET['ctd_download_category_filter'] )
		? sanitize_title( wp_unslash( $_GET['ctd_download_category_filter'] ) )
		: '';
	$range     = isset( $_GET['ctd_download_range_filter'] )
		? sanitize_title( wp_unslash( $_GET['ctd_download_range_filter'] ) )
		: '';
	$language  = isset( $_GET['ctd_download_language_filter'] )
		? sanitize_title( wp_unslash( $_GET['ctd_download_language_filter'] ) )
		: '';

	if ( $category ) {
		ctd_normalize_admin_related_filters_for_category( $category, $range, $language );
	}

	if ( $category ) {
		$tax_query[] = array(
			'taxonomy' => CTD_TAXONOMY,
			'field'    => 'slug',
			'terms'    => $category,
		);
	}

	if ( $range ) {
		$tax_query[] = array(
			'taxonomy' => CTD_RANGE_TAXONOMY,
			'field'    => 'slug',
			'terms'    => $range,
		);
	}

	if ( $language ) {
		$tax_query[] = array(
			'taxonomy' => CTD_LANGUAGE_TAXONOMY,
			'field'    => 'slug',
			'terms'    => $language,
		);
	}

	if ( ! empty( $tax_query ) ) {
		$query->set( 'tax_query', $tax_query );
	}

	if ( 'ctd_status' === $query->get( 'orderby' ) ) {
		$query->set( 'meta_key', CTD_META_STATUS );
		$query->set( 'orderby', 'meta_value' );
	}
}

/**
 * Clears incompatible range/language filters when a category has explicit links.
 *
 * @param string $category_slug Selected category slug.
 * @param string $range_slug Selected range slug.
 * @param string $language_slug Selected language slug.
 * @return void
 */
function ctd_normalize_admin_related_filters_for_category( $category_slug, &$range_slug, &$language_slug ) {
	$category = get_term_by( 'slug', $category_slug, CTD_TAXONOMY );

	if ( ! ( $category instanceof WP_Term ) ) {
		return;
	}

	$range_ids = ctd_get_category_linked_range_ids( $category );

	if ( ! empty( $range_ids ) && $range_slug ) {
		$range = get_term_by( 'slug', $range_slug, CTD_RANGE_TAXONOMY );

		if ( ! ( $range instanceof WP_Term ) || ! in_array( $range->term_id, $range_ids, true ) ) {
			$range_slug = '';
		}
	}

	$language_ids = ctd_get_category_linked_language_ids( $category );

	if ( ! empty( $language_ids ) && $language_slug ) {
		$language = get_term_by( 'slug', $language_slug, CTD_LANGUAGE_TAXONOMY );

		if ( ! ( $language instanceof WP_Term ) || ! in_array( $language->term_id, $language_ids, true ) ) {
			$language_slug = '';
		}
	}
}
