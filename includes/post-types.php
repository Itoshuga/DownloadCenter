<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'ctd_register_post_type' );

function ctd_register_post_type() {
	$labels = array(
		'name'                  => __( 'Documents PDF', 'centre-telechargement' ),
		'singular_name'         => __( 'Document PDF', 'centre-telechargement' ),
		'menu_name'             => __( 'Documents', 'centre-telechargement' ),
		'name_admin_bar'        => __( 'Document PDF', 'centre-telechargement' ),
		'add_new'               => __( 'Ajouter un document', 'centre-telechargement' ),
		'add_new_item'          => __( 'Ajouter un document', 'centre-telechargement' ),
		'new_item'              => __( 'Nouveau document', 'centre-telechargement' ),
		'edit_item'             => __( 'Modifier le document', 'centre-telechargement' ),
		'view_item'             => __( 'Voir le document', 'centre-telechargement' ),
		'all_items'             => __( 'Documents', 'centre-telechargement' ),
		'search_items'          => __( 'Rechercher un document', 'centre-telechargement' ),
		'not_found'             => __( 'Aucun document trouvé.', 'centre-telechargement' ),
		'not_found_in_trash'    => __( 'Aucun document trouvé dans la corbeille.', 'centre-telechargement' ),
		'filter_items_list'     => __( 'Filtrer les documents', 'centre-telechargement' ),
		'items_list_navigation' => __( 'Navigation des documents', 'centre-telechargement' ),
		'items_list'            => __( 'Liste des documents', 'centre-telechargement' ),
	);

	register_post_type(
		CTD_POST_TYPE,
		array(
			'labels'              => $labels,
			'description'         => __( 'Gestion admin de documents PDF.', 'centre-telechargement' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => true,
			'show_in_rest'        => false,
			'query_var'           => false,
			'rewrite'             => false,
			'capabilities'        => ctd_get_admin_only_post_capabilities(),
			'map_meta_cap'        => false,
			'menu_position'       => 25,
			'menu_icon'           => CTD_ADMIN_LOGO_URL,
			'hierarchical'        => false,
			'supports'            => array( 'title' ),
			'has_archive'         => false,
			'can_export'          => false,
			'delete_with_user'    => false,
		)
	);
}
