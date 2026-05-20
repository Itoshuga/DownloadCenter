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

		if ( $term_id && ( $force || ! get_term_meta( $term_id, CTD_LANGUAGE_FLAG_META, true ) ) ) {
			update_term_meta( $term_id, CTD_LANGUAGE_FLAG_META, $language['flag'] );
		}
	}

	update_option( 'ctd_default_languages_seeded', CTD_VERSION );
}

function ctd_render_language_add_fields() {
	?>
	<div class="form-field term-ctd-language-flag-wrap">
		<label for="ctd_language_flag"><?php esc_html_e( 'Drapeau', 'centre-telechargement' ); ?></label>
		<?php wp_nonce_field( 'ctd_save_language_meta', 'ctd_language_meta_nonce' ); ?>
		<?php ctd_render_language_flag_select(); ?>
		<p><?php esc_html_e( 'Les drapeaux disponibles proviennent du dossier assets/images/flags.', 'centre-telechargement' ); ?></p>
	</div>
	<?php
}

/**
 * @param WP_Term $term Current term.
 * @return void
 */
function ctd_render_language_edit_fields( $term ) {
	$selected_flag = ctd_get_language_flag_filename( $term );
	?>
	<tr class="form-field term-ctd-language-flag-wrap">
		<th scope="row">
			<label for="ctd_language_flag"><?php esc_html_e( 'Drapeau', 'centre-telechargement' ); ?></label>
		</th>
		<td>
			<?php wp_nonce_field( 'ctd_save_language_meta', 'ctd_language_meta_nonce' ); ?>
			<?php ctd_render_language_flag_select( $selected_flag ); ?>
			<p class="description"><?php esc_html_e( 'Les drapeaux disponibles proviennent du dossier assets/images/flags.', 'centre-telechargement' ); ?></p>
			<div class="ctd-language-preview">
				<?php echo ctd_get_language_badge_html( $term ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</td>
	</tr>
	<?php
}

/**
 * @param string $selected_flag Selected flag filename.
 * @return void
 */
function ctd_render_language_flag_select( $selected_flag = '' ) {
	$flags         = ctd_get_available_language_flags();
	$selected_flag = ctd_sanitize_language_flag_filename( $selected_flag );
	?>
	<select name="ctd_language_flag" id="ctd_language_flag" class="ctd-language-flag-select">
		<option value=""><?php esc_html_e( 'Aucun drapeau', 'centre-telechargement' ); ?></option>
		<?php foreach ( $flags as $filename => $url ) : ?>
			<option value="<?php echo esc_attr( $filename ); ?>" <?php selected( $selected_flag, $filename ); ?>>
				<?php echo esc_html( $filename ); ?>
			</option>
		<?php endforeach; ?>
	</select>
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

	$flag = isset( $_POST['ctd_language_flag'] )
		? ctd_sanitize_language_flag_filename( wp_unslash( $_POST['ctd_language_flag'] ) )
		: '';
	$available_flags = array_keys( ctd_get_available_language_flags() );

	if ( $flag && in_array( $flag, $available_flags, true ) ) {
		update_term_meta( $term_id, CTD_LANGUAGE_FLAG_META, $flag );
		return;
	}

	delete_term_meta( $term_id, CTD_LANGUAGE_FLAG_META );
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
