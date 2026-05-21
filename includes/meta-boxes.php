<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'ctd_register_document_meta' );
add_action( 'add_meta_boxes', 'ctd_add_document_meta_boxes' );
add_action( 'save_post_' . CTD_POST_TYPE, 'ctd_save_document_meta', 10, 2 );
add_action( 'admin_notices', 'ctd_render_admin_notices' );
add_filter( 'get_user_option_meta-box-order_' . CTD_POST_TYPE, 'ctd_filter_document_meta_box_order' );

function ctd_register_document_meta() {
	register_post_meta(
		CTD_POST_TYPE,
		CTD_META_FILE_ID,
		array(
			'type'              => 'integer',
			'single'            => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => 'ctd_current_user_can_manage_documents',
			'show_in_rest'      => false,
		)
	);

	register_post_meta(
		CTD_POST_TYPE,
		CTD_META_STATUS,
		array(
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'ctd_sanitize_document_status',
			'auth_callback'     => 'ctd_current_user_can_manage_documents',
			'show_in_rest'      => false,
		)
	);

	register_post_meta(
		CTD_POST_TYPE,
		CTD_META_ACCESS_MODE,
		array(
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'ctd_sanitize_document_access_mode',
			'auth_callback'     => 'ctd_current_user_can_manage_documents',
			'show_in_rest'      => false,
		)
	);

	register_post_meta(
		CTD_POST_TYPE,
		CTD_META_ALLOWED_USERS,
		array(
			'type'              => 'array',
			'single'            => true,
			'sanitize_callback' => 'ctd_sanitize_document_allowed_user_ids',
			'auth_callback'     => 'ctd_current_user_can_manage_documents',
			'show_in_rest'      => false,
		)
	);
}

function ctd_current_user_can_manage_documents() {
	return current_user_can( 'manage_options' );
}

function ctd_add_document_meta_boxes() {
	add_meta_box(
		'ctd_document_file',
		__( 'Fichier PDF', 'centre-telechargement' ),
		'ctd_render_document_file_meta_box',
		CTD_POST_TYPE,
		'normal',
		'high'
	);

	add_meta_box(
		'ctd_document_access',
		__( 'Accès', 'centre-telechargement' ),
		'ctd_render_document_access_meta_box',
		CTD_POST_TYPE,
		'normal',
		'default'
	);
}

/**
 * Keep the document edit screen in a predictable order.
 *
 * @param mixed $order Stored user meta box order.
 * @return mixed
 */
function ctd_filter_document_meta_box_order( $order ) {
	$order    = is_array( $order ) ? $order : array();
	$contexts = array( 'normal', 'side', 'advanced' );
	$managed  = array( 'ctd_document_file', 'ctd_document_access' );

	foreach ( $contexts as $context ) {
		$box_ids = isset( $order[ $context ] )
			? array_filter( array_map( 'trim', explode( ',', (string) $order[ $context ] ) ) )
			: array();
		$box_ids = array_values( array_diff( $box_ids, $managed ) );

		$order[ $context ] = implode( ',', $box_ids );
	}

	$normal_ids      = empty( $order['normal'] ) ? array() : explode( ',', $order['normal'] );
	$order['normal'] = implode( ',', array_merge( $managed, $normal_ids ) );

	return $order;
}

/**
 * @param WP_Post $post Current post.
 * @return void
 */
function ctd_render_document_file_meta_box( $post ) {
	wp_nonce_field( 'ctd_save_document_meta', 'ctd_document_meta_nonce' );

	$attachment_id = absint( get_post_meta( $post->ID, CTD_META_FILE_ID, true ) );
	$has_pdf       = $attachment_id && ctd_attachment_is_pdf( $attachment_id );
	$attachment_id = $has_pdf ? $attachment_id : 0;
	$file_url      = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
	$file_path     = $attachment_id ? get_attached_file( $attachment_id ) : '';
	$file_name     = $file_path ? wp_basename( $file_path ) : '';
	$has_file      = $attachment_id && $file_url;

	if ( ! $file_name && $attachment_id ) {
		$file_name = get_the_title( $attachment_id );
	}

	?>
	<div class="ctd-document-card">
		<div class="ctd-field">
			<label class="ctd-field-label" for="ctd_pdf_file_id">
				<?php esc_html_e( 'Fichier PDF', 'centre-telechargement' ); ?>
			</label>
			<input
				type="hidden"
				id="ctd_pdf_file_id"
				name="ctd_pdf_file_id"
				value="<?php echo esc_attr( $attachment_id ); ?>"
			/>

			<div class="ctd-file-preview<?php echo $has_file ? ' has-file' : ''; ?>">
				<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
				<div class="ctd-file-preview-text">
					<strong id="ctd-pdf-filename">
						<?php echo $has_file ? esc_html( $file_name ) : esc_html__( 'Aucun fichier sélectionné', 'centre-telechargement' ); ?>
					</strong>
					<span id="ctd-pdf-placeholder" class="<?php echo $has_file ? 'hidden' : ''; ?>">
						<?php esc_html_e( 'PDF uniquement', 'centre-telechargement' ); ?>
					</span>
				</div>
				<a
					id="ctd-pdf-link"
					class="button button-small<?php echo $has_file ? '' : ' hidden'; ?>"
					href="<?php echo esc_url( $file_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
				>
					<?php esc_html_e( 'Ouvrir', 'centre-telechargement' ); ?>
				</a>
			</div>

			<div class="ctd-button-row">
				<button type="button" class="button button-primary ctd-select-file">
					<?php esc_html_e( 'Choisir un PDF', 'centre-telechargement' ); ?>
				</button>
				<button
					type="button"
					id="ctd-remove-pdf"
					class="button ctd-remove-file<?php echo $has_file ? '' : ' hidden'; ?>"
				>
					<?php esc_html_e( 'Retirer', 'centre-telechargement' ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php
}

/**
 * @param WP_Post $post Current post.
 * @return void
 */
function ctd_render_document_access_meta_box( $post ) {
	$status            = ctd_get_document_status( $post->ID );
	$access_mode       = ctd_get_document_access_mode( $post->ID );
	$selected_user_ids = ctd_get_document_allowed_user_ids( $post->ID );
	$selected_users    = array_flip( $selected_user_ids );
	$users             = get_users(
		array(
			'orderby' => 'display_name',
			'order'   => 'ASC',
		)
	);
	$status_captions = array(
		'public'    => __( 'Visible sans restriction.', 'centre-telechargement' ),
		'protected' => __( 'Réservé aux visiteurs connectés selon les règles ci-dessous.', 'centre-telechargement' ),
	);
	$access_captions = array(
		'all'      => __( 'Toute personne connectée peut ouvrir ou télécharger le document.', 'centre-telechargement' ),
		'selected' => __( 'Seuls les utilisateurs cochés auront accès au document.', 'centre-telechargement' ),
	);
	?>
	<div class="ctd-document-card ctd-access-card">
		<input
			type="hidden"
			name="ctd_document_meta_nonce"
			value="<?php echo esc_attr( wp_create_nonce( 'ctd_save_document_meta' ) ); ?>"
		/>

		<div class="ctd-field ctd-status-field">
			<span class="ctd-field-label">
				<?php esc_html_e( 'Statut du document', 'centre-telechargement' ); ?>
			</span>
			<div class="ctd-status-options">
				<?php foreach ( ctd_get_document_statuses() as $status_key => $status_label ) : ?>
					<?php $is_checked = $status_key === $status; ?>
					<label class="ctd-status-option<?php echo $is_checked ? ' is-selected' : ''; ?>">
						<input
							type="radio"
							name="ctd_document_status"
							value="<?php echo esc_attr( $status_key ); ?>"
							<?php checked( $is_checked ); ?>
						/>
						<span class="ctd-status-option-content">
							<?php echo ctd_get_status_badge_html( $status_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="ctd-status-caption">
								<?php echo esc_html( $status_captions[ $status_key ] ); ?>
							</span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="ctd-protected-access-settings">
			<div class="ctd-field">
				<span class="ctd-field-label">
					<?php esc_html_e( 'Accès protégé', 'centre-telechargement' ); ?>
				</span>
				<p class="ctd-field-help">
					<?php esc_html_e( 'Choisissez qui peut ouvrir ou télécharger ce document quand il est protégé.', 'centre-telechargement' ); ?>
				</p>
				<div class="ctd-access-mode-options">
					<?php foreach ( ctd_get_document_access_modes() as $mode_key => $mode_label ) : ?>
						<?php $is_checked = $mode_key === $access_mode; ?>
						<label class="ctd-access-mode-option<?php echo $is_checked ? ' is-selected' : ''; ?>">
							<input
								type="radio"
								name="ctd_document_access_mode"
								value="<?php echo esc_attr( $mode_key ); ?>"
								<?php checked( $is_checked ); ?>
							/>
							<span class="ctd-access-mode-content">
								<strong><?php echo esc_html( $mode_label ); ?></strong>
								<span><?php echo esc_html( $access_captions[ $mode_key ] ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="ctd-user-access-panel">
				<label class="ctd-field-label" for="ctd-user-access-search">
					<?php esc_html_e( 'Utilisateurs autorisés', 'centre-telechargement' ); ?>
				</label>
				<input
					type="search"
					id="ctd-user-access-search"
					class="regular-text ctd-user-access-search"
					placeholder="<?php esc_attr_e( 'Rechercher un utilisateur...', 'centre-telechargement' ); ?>"
					data-ctd-user-search
				/>

				<div class="ctd-user-access-list">
					<?php if ( empty( $users ) ) : ?>
						<p class="ctd-empty-value"><?php esc_html_e( 'Aucun utilisateur disponible.', 'centre-telechargement' ); ?></p>
					<?php else : ?>
						<?php foreach ( $users as $user ) : ?>
							<?php
							$user_id      = absint( $user->ID );
							$is_checked   = isset( $selected_users[ $user_id ] );
							$search_label = trim( $user->display_name . ' ' . $user->user_email );
							?>
							<label class="ctd-user-access-row" data-ctd-user-row data-user-search="<?php echo esc_attr( $search_label ); ?>">
								<input
									type="checkbox"
									name="ctd_document_allowed_user_ids[]"
									value="<?php echo esc_attr( $user_id ); ?>"
									<?php checked( $is_checked ); ?>
								/>
								<span class="ctd-user-access-identity">
									<strong><?php echo esc_html( $user->display_name ); ?></strong>
									<span><?php echo esc_html( $user->user_email ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * @param int     $post_id Current post ID.
 * @param WP_Post $post Current post.
 * @return void
 */
function ctd_save_document_meta( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['ctd_document_meta_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['ctd_document_meta_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'ctd_save_document_meta' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$status = isset( $_POST['ctd_document_status'] )
		? ctd_sanitize_document_status( wp_unslash( $_POST['ctd_document_status'] ) )
		: 'public';

	update_post_meta( $post_id, CTD_META_STATUS, $status );

	$access_mode = isset( $_POST['ctd_document_access_mode'] )
		? ctd_sanitize_document_access_mode( wp_unslash( $_POST['ctd_document_access_mode'] ) )
		: 'all';
	$allowed_user_ids = isset( $_POST['ctd_document_allowed_user_ids'] )
		? ctd_sanitize_document_allowed_user_ids( wp_unslash( $_POST['ctd_document_allowed_user_ids'] ) )
		: array();

	if ( 'protected' !== $status ) {
		update_post_meta( $post_id, CTD_META_ACCESS_MODE, 'all' );
		delete_post_meta( $post_id, CTD_META_ALLOWED_USERS );
	} else {
		update_post_meta( $post_id, CTD_META_ACCESS_MODE, $access_mode );

		if ( 'selected' === $access_mode ) {
			update_post_meta( $post_id, CTD_META_ALLOWED_USERS, $allowed_user_ids );
		} else {
			delete_post_meta( $post_id, CTD_META_ALLOWED_USERS );
		}
	}

	$attachment_id = isset( $_POST['ctd_pdf_file_id'] ) ? absint( wp_unslash( $_POST['ctd_pdf_file_id'] ) ) : 0;

	if ( ! $attachment_id ) {
		delete_post_meta( $post_id, CTD_META_FILE_ID );
		return;
	}

	if ( ! ctd_attachment_is_pdf( $attachment_id ) ) {
		delete_post_meta( $post_id, CTD_META_FILE_ID );
		ctd_queue_admin_notice( __( 'Le fichier sélectionné doit être un PDF.', 'centre-telechargement' ) );
		return;
	}

	update_post_meta( $post_id, CTD_META_FILE_ID, $attachment_id );
}

/**
 * @param int $attachment_id Attachment ID.
 * @return bool
 */
function ctd_attachment_is_pdf( $attachment_id ) {
	$attachment = get_post( $attachment_id );

	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return false;
	}

	$mime = get_post_mime_type( $attachment );

	if ( 'application/pdf' !== $mime ) {
		return false;
	}

	$file_reference = get_attached_file( $attachment_id );

	if ( ! $file_reference ) {
		$file_reference = wp_get_attachment_url( $attachment_id );
	}

	$file_type = wp_check_filetype( $file_reference );

	return isset( $file_type['ext'], $file_type['type'] )
		&& 'pdf' === strtolower( (string) $file_type['ext'] )
		&& 'application/pdf' === $file_type['type'];
}

/**
 * @param string $message Notice message.
 * @param string $type Notice type.
 * @return void
 */
function ctd_queue_admin_notice( $message, $type = 'error' ) {
	$key     = 'ctd_admin_notices_' . get_current_user_id();
	$notices = get_transient( $key );

	if ( ! is_array( $notices ) ) {
		$notices = array();
	}

	$notices[] = array(
		'message' => $message,
		'type'    => $type,
	);

	set_transient( $key, $notices, MINUTE_IN_SECONDS );
}

function ctd_render_admin_notices() {
	$screen = get_current_screen();

	if ( ! $screen || ! isset( $screen->post_type ) || CTD_POST_TYPE !== $screen->post_type ) {
		return;
	}

	$key     = 'ctd_admin_notices_' . get_current_user_id();
	$notices = get_transient( $key );

	if ( ! is_array( $notices ) || empty( $notices ) ) {
		return;
	}

	delete_transient( $key );

	foreach ( $notices as $notice ) {
		$type    = isset( $notice['type'] ) ? sanitize_html_class( $notice['type'] ) : 'info';
		$message = isset( $notice['message'] ) ? $notice['message'] : '';

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
