<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'ctd_register_taxonomy' );
add_action( 'admin_init', 'ctd_seed_default_languages' );
add_action( CTD_LANGUAGE_TAXONOMY . '_add_form_fields', 'ctd_render_language_add_fields' );
add_action( CTD_LANGUAGE_TAXONOMY . '_edit_form_fields', 'ctd_render_language_edit_fields' );
add_action( 'created_' . CTD_LANGUAGE_TAXONOMY, 'ctd_save_language_term_meta' );
add_action( 'edited_' . CTD_LANGUAGE_TAXONOMY, 'ctd_save_language_term_meta' );
add_filter( 'manage_edit-' . CTD_LANGUAGE_TAXONOMY . '_columns', 'ctd_filter_language_term_columns' );
add_filter( 'manage_' . CTD_LANGUAGE_TAXONOMY . '_custom_column', 'ctd_render_language_term_column', 10, 3 );

function ctd_register_taxonomy() {
	ctd_register_category_taxonomy();
	ctd_register_range_taxonomy();
	ctd_register_language_taxonomy();
}

function ctd_register_category_taxonomy() {
	$labels = array(
		'name'              => __( 'Catégories de documents', 'centre-telechargement' ),
		'singular_name'     => __( 'Catégorie de documents', 'centre-telechargement' ),
		'search_items'      => __( 'Rechercher une catégorie', 'centre-telechargement' ),
		'all_items'         => __( 'Toutes les catégories', 'centre-telechargement' ),
		'parent_item'       => __( 'Catégorie parente', 'centre-telechargement' ),
		'parent_item_colon' => __( 'Catégorie parente :', 'centre-telechargement' ),
		'edit_item'         => __( 'Modifier la catégorie', 'centre-telechargement' ),
		'update_item'       => __( 'Mettre à jour la catégorie', 'centre-telechargement' ),
		'add_new_item'      => __( 'Ajouter une catégorie', 'centre-telechargement' ),
		'new_item_name'     => __( 'Nom de la nouvelle catégorie', 'centre-telechargement' ),
		'menu_name'         => __( 'Catégories', 'centre-telechargement' ),
	);

	register_taxonomy(
		CTD_TAXONOMY,
		array( CTD_POST_TYPE ),
		array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'hierarchical'       => true,
			'show_ui'            => true,
			'show_admin_column'  => false,
			'show_in_quick_edit' => true,
			'show_in_rest'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capabilities'       => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'manage_options',
			),
		)
	);
}

function ctd_register_range_taxonomy() {
	$labels = array(
		'name'              => __( 'Gammes', 'centre-telechargement' ),
		'singular_name'     => __( 'Gamme', 'centre-telechargement' ),
		'search_items'      => __( 'Rechercher une gamme', 'centre-telechargement' ),
		'all_items'         => __( 'Toutes les gammes', 'centre-telechargement' ),
		'parent_item'       => __( 'Gamme parente', 'centre-telechargement' ),
		'parent_item_colon' => __( 'Gamme parente :', 'centre-telechargement' ),
		'edit_item'         => __( 'Modifier la gamme', 'centre-telechargement' ),
		'update_item'       => __( 'Mettre à jour la gamme', 'centre-telechargement' ),
		'add_new_item'      => __( 'Ajouter une gamme', 'centre-telechargement' ),
		'new_item_name'     => __( 'Nom de la nouvelle gamme', 'centre-telechargement' ),
		'menu_name'         => __( 'Gammes', 'centre-telechargement' ),
	);

	register_taxonomy(
		CTD_RANGE_TAXONOMY,
		array( CTD_POST_TYPE ),
		array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'hierarchical'       => true,
			'show_ui'            => true,
			'show_admin_column'  => false,
			'show_in_quick_edit' => true,
			'show_in_rest'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capabilities'       => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'manage_options',
			),
		)
	);
}

function ctd_register_language_taxonomy() {
	$labels = array(
		'name'                       => __( 'Langues', 'centre-telechargement' ),
		'singular_name'              => __( 'Langue', 'centre-telechargement' ),
		'search_items'               => __( 'Rechercher une langue', 'centre-telechargement' ),
		'all_items'                  => __( 'Toutes les langues', 'centre-telechargement' ),
		'parent_item'                => __( 'Langue parente', 'centre-telechargement' ),
		'parent_item_colon'          => __( 'Langue parente :', 'centre-telechargement' ),
		'edit_item'                  => __( 'Modifier la langue', 'centre-telechargement' ),
		'update_item'                => __( 'Mettre à jour la langue', 'centre-telechargement' ),
		'add_new_item'               => __( 'Ajouter une langue', 'centre-telechargement' ),
		'new_item_name'              => __( 'Nom de la nouvelle langue', 'centre-telechargement' ),
		'separate_items_with_commas' => __( 'Séparer les langues par des virgules', 'centre-telechargement' ),
		'add_or_remove_items'        => __( 'Ajouter ou retirer des langues', 'centre-telechargement' ),
		'choose_from_most_used'      => __( 'Choisir parmi les langues les plus utilisées', 'centre-telechargement' ),
		'menu_name'                  => __( 'Langues', 'centre-telechargement' ),
	);

	register_taxonomy(
		CTD_LANGUAGE_TAXONOMY,
		array( CTD_POST_TYPE ),
		array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'hierarchical'       => true,
			'show_ui'            => true,
			'show_admin_column'  => false,
			'show_in_quick_edit' => true,
			'show_in_rest'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'capabilities'       => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'manage_options',
			),
		)
	);
}

/**
 * @param bool $force Force seeding even if it already ran.
 * @return void
 */
function ctd_seed_default_languages( $force = false ) {
	if ( ! taxonomy_exists( CTD_LANGUAGE_TAXONOMY ) ) {
		return;
	}

	foreach ( ctd_get_default_languages() as $language ) {
		$term    = get_term_by( 'slug', $language['slug'], CTD_LANGUAGE_TAXONOMY );
		$term_id = 0;

		if ( $term instanceof WP_Term ) {
			$term_id = $term->term_id;
		} else {
			$created = wp_insert_term(
				$language['name'],
				CTD_LANGUAGE_TAXONOMY,
				array(
					'slug' => $language['slug'],
				)
			);

			if ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) {
				$term_id = absint( $created['term_id'] );
			}
		}

		if (
			$term_id
			&& ! get_term_meta( $term_id, CTD_LANGUAGE_FLAG_ATTACHMENT_META, true )
			&& ( $force || ! get_term_meta( $term_id, CTD_LANGUAGE_FLAG_META, true ) )
		) {
			update_term_meta( $term_id, CTD_LANGUAGE_FLAG_META, $language['flag'] );
		}
	}

	update_option( 'ctd_default_languages_seeded', CTD_VERSION );
}

function ctd_render_language_add_fields() {
	?>
	<div class="form-field term-ctd-language-flag-wrap">
		<label for="ctd_language_flag_attachment_id"><?php esc_html_e( 'Drapeau', 'centre-telechargement' ); ?></label>
		<?php wp_nonce_field( 'ctd_save_language_meta', 'ctd_language_meta_nonce' ); ?>
		<?php ctd_render_language_flag_media_controls(); ?>
		<p><?php esc_html_e( 'Choisissez une image depuis la médiathèque WordPress.', 'centre-telechargement' ); ?></p>
	</div>
	<?php
}

/**
 * @param WP_Term $term Current term.
 * @return void
 */
function ctd_render_language_edit_fields( $term ) {
	$attachment_id = ctd_get_language_flag_attachment_id( $term );
	?>
	<tr class="form-field term-ctd-language-flag-wrap">
		<th scope="row">
			<label for="ctd_language_flag_attachment_id"><?php esc_html_e( 'Drapeau', 'centre-telechargement' ); ?></label>
		</th>
		<td>
			<?php wp_nonce_field( 'ctd_save_language_meta', 'ctd_language_meta_nonce' ); ?>
			<?php ctd_render_language_flag_media_controls( $attachment_id ); ?>
			<p class="description"><?php esc_html_e( 'Choisissez une image depuis la médiathèque WordPress.', 'centre-telechargement' ); ?></p>
			<div class="ctd-language-preview">
				<?php echo ctd_get_language_badge_html( $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</td>
	</tr>
	<?php
}

/**
 * @param int $attachment_id Selected attachment ID.
 * @return void
 */
function ctd_render_language_flag_media_controls( $attachment_id = 0 ) {
	$attachment_id = absint( $attachment_id );
	$image_url     = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
	$file_name     = $attachment_id ? get_the_title( $attachment_id ) : '';

	if ( $attachment_id && ! $image_url ) {
		$image_url = wp_get_attachment_url( $attachment_id );
	}

	if ( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );

		if ( $file_path ) {
			$file_name = wp_basename( $file_path );
		}
	}
	?>
	<input
		type="hidden"
		id="ctd_language_flag_attachment_id"
		name="ctd_language_flag_attachment_id"
		value="<?php echo esc_attr( $attachment_id ); ?>"
	/>
	<div class="ctd-language-media-control">
		<div class="ctd-language-media-preview">
			<img
				id="ctd-language-flag-media-preview"
				class="<?php echo $image_url ? '' : 'hidden'; ?>"
				src="<?php echo esc_url( $image_url ); ?>"
				alt=""
			/>
			<span
				id="ctd-language-flag-media-placeholder"
				class="dashicons dashicons-format-image<?php echo $image_url ? ' hidden' : ''; ?>"
				aria-hidden="true"
			></span>
			<span id="ctd-language-flag-media-filename">
				<?php echo $file_name ? esc_html( $file_name ) : esc_html__( 'Aucune image sélectionnée', 'centre-telechargement' ); ?>
			</span>
		</div>
		<div class="ctd-language-media-actions">
			<button type="button" class="button ctd-select-language-flag-media">
				<?php esc_html_e( 'Choisir depuis la médiathèque', 'centre-telechargement' ); ?>
			</button>
			<button
				type="button"
				id="ctd-remove-language-flag-media"
				class="button ctd-remove-language-flag-media<?php echo $attachment_id ? '' : ' hidden'; ?>"
			>
				<?php esc_html_e( 'Retirer', 'centre-telechargement' ); ?>
			</button>
		</div>
	</div>
	<?php
}

/**
 * @param int $term_id Term ID.
 * @return void
 */
function ctd_save_language_term_meta( $term_id ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! isset( $_POST['ctd_language_meta_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['ctd_language_meta_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'ctd_save_language_meta' ) ) {
		return;
	}

	$attachment_id = isset( $_POST['ctd_language_flag_attachment_id'] )
		? absint( wp_unslash( $_POST['ctd_language_flag_attachment_id'] ) )
		: 0;

	if ( $attachment_id && ctd_attachment_is_language_flag_image( $attachment_id ) ) {
		update_term_meta( $term_id, CTD_LANGUAGE_FLAG_ATTACHMENT_META, $attachment_id );
		return;
	}

	delete_term_meta( $term_id, CTD_LANGUAGE_FLAG_ATTACHMENT_META );
}

/**
 * @param array<string, string> $columns Existing columns.
 * @return array<string, string>
 */
function ctd_filter_language_term_columns( $columns ) {
	$new_columns = array();

	foreach ( $columns as $column_key => $column_label ) {
		$new_columns[ $column_key ] = $column_label;

		if ( 'name' === $column_key ) {
			$new_columns['ctd_flag'] = __( 'Drapeau', 'centre-telechargement' );
		}
	}

	return $new_columns;
}

/**
 * @param string $content Existing column content.
 * @param string $column_name Column key.
 * @param int    $term_id Term ID.
 * @return string
 */
function ctd_render_language_term_column( $content, $column_name, $term_id ) {
	if ( 'ctd_flag' !== $column_name ) {
		return $content;
	}

	return ctd_get_language_badge_html( $term_id );
}
