<?php
/**
 * Project import and navigation support.
 *
 * @package Shorthand_Connect
 */

/**
 * Gets a JSON Project response, preserving useful request failures.
 *
 * @param string $path API path.
 * @return array|object|WP_Error
 */
function shorthand_project_api_request( $path ) {
	$response = shorthand_api_v2_request( $path, array( 'timeout' => 30 ) );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	if ( ! is_array( $response ) ) {
		return new WP_Error( 'shorthand_project_request_failed', __( 'Shorthand could not be reached with the configured token.', 'shorthand-connect' ) );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ) );
	if ( 200 !== $code || JSON_ERROR_NONE !== json_last_error() || ( ! is_array( $body ) && ! is_object( $body ) ) ) {
		return new WP_Error( 'shorthand_project_request_failed', __( 'Shorthand did not return an available Project.', 'shorthand-connect' ) );
	}

	return $body;
}

/**
 * Returns Projects available to the configured token.
 *
 * @return array|WP_Error
 */
function shorthand_get_projects() {
	$projects = shorthand_project_api_request( '/v2/projects' );
	if ( is_wp_error( $projects ) ) {
		return $projects;
	}
	if ( ! is_array( $projects ) ) {
		return new WP_Error( 'shorthand_projects_invalid', __( 'Shorthand returned an invalid Project list.', 'shorthand-connect' ) );
	}

	return $projects;
}

/**
 * Gets one Project immediately before an import.
 *
 * @param string $project_id Project ID.
 * @return object|WP_Error
 */
function shorthand_get_project( $project_id ) {
	return shorthand_project_api_request( '/v2/projects/' . rawurlencode( $project_id ) );
}

/**
 * Returns administrator-safe summaries of all Project import jobs.
 *
 * @return array Project progress summaries.
 */
function shorthand_get_project_import_statuses() {
	$jobs     = get_option( 'shorthand_project_import_jobs', array() );
	$statuses = array();
	if ( ! is_array( $jobs ) ) {
		return $statuses;
	}

	foreach ( $jobs as $project_id => $job ) {
		if ( ! isset( $job['project']->stories ) || ! is_array( $job['project']->stories ) ) {
			continue;
		}
		$total    = count( $job['project']->stories );
		$position = isset( $job['position'] ) ? min( $total, max( 0, absint( $job['position'] ) ) ) : 0;
		$status   = isset( $job['status'] ) ? sanitize_key( $job['status'] ) : 'queued';
		$percent  = $total > 0 ? (int) floor( ( $position / $total ) * 100 ) : 0;
		if ( 'complete' === $status ) {
			$percent = 100;
		}
		$statuses[] = array(
			'project_id'    => sanitize_text_field( (string) $project_id ),
			'project_title' => ! empty( $job['project']->title ) ? sanitize_text_field( $job['project']->title ) : sanitize_text_field( (string) $project_id ),
			'position'      => $position,
			'total'         => $total,
			'percent'       => $percent,
			'status'        => $status,
			'phase'         => isset( $job['phase'] ) ? sanitize_key( $job['phase'] ) : '',
			'target_status' => isset( $job['target_status'] ) ? sanitize_key( $job['target_status'] ) : '',
			'error'         => ! empty( $job['error'] ) ? sanitize_text_field( $job['error'] ) : '',
		);
	}

	return $statuses;
}

/**
 * Starts an asynchronous Project import after refetching its detail response.
 */
function shorthand_start_project_import() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to import Projects.', 'shorthand-connect' ) );
	}
	check_admin_referer( 'shorthand_import_project' );
	$project_id = isset( $_POST['project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : '';
	$result     = shorthand_begin_project_import( $project_id );
	$redirect   = admin_url( 'post-new.php?post_type=shorthand_story&shorthand_tab=projects' );
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'shorthand_project_error', $result->get_error_message(), $redirect ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'shorthand_project_started', 1, $redirect ) );
	exit;
}
add_action( 'admin_post_shorthand_import_project', 'shorthand_start_project_import' );

/**
 * Starts or restarts a Project import from a freshly fetched Project response.
 *
 * @param string $project_id    Project ID.
 * @param string $target_status Optional final status for every imported Story.
 * @return true|WP_Error
 */
function shorthand_begin_project_import( $project_id, $target_status = '' ) {
	$target_status = in_array( $target_status, array( 'draft', 'publish' ), true ) ? $target_status : '';
	$lock_key      = shorthand_acquire_project_import_lock( $project_id );
	if ( ! $lock_key ) {
		return new WP_Error( 'shorthand_project_import_locked', __( 'This Project is already being imported. Please wait for the current step to finish.', 'shorthand-connect' ) );
	}

	try {
		return shorthand_prepare_project_import( $project_id, $target_status );
	} finally {
		shorthand_release_project_import_lock( $lock_key );
	}
}

/**
 * Prepares the complete Project Story and URL maps.
 *
 * @param string $project_id    Project ID.
 * @param string $target_status Optional final status for every imported Story.
 * @return true|WP_Error
 */
function shorthand_prepare_project_import( $project_id, $target_status = '' ) {
	$project = shorthand_get_project( $project_id );
	if ( is_wp_error( $project ) || ! is_object( $project ) || empty( $project->id ) || ! isset( $project->stories ) || ! is_array( $project->stories ) ) {
		return is_wp_error( $project ) ? $project : new WP_Error( 'shorthand_project_invalid', __( 'Shorthand returned an invalid Project.', 'shorthand-connect' ) );
	}
	$project->id = sanitize_text_field( $project->id );
	if ( ! $project->id ) {
		return new WP_Error( 'shorthand_project_invalid', __( 'Shorthand returned a Project without a valid ID.', 'shorthand-connect' ) );
	}
	$project_title = ! empty( $project->title ) ? sanitize_text_field( $project->title ) : $project->id;
	$imported      = array();
	$urls          = array();
	foreach ( $project->stories as $story ) {
		if ( empty( $story->id ) ) {
			return new WP_Error( 'shorthand_project_invalid_story', __( 'The Project contains a Story without an ID.', 'shorthand-connect' ) );
		}
		$story->id = sanitize_text_field( $story->id );
		if ( ! $story->id ) {
			return new WP_Error( 'shorthand_project_invalid_story', __( 'The Project contains a Story without a valid ID.', 'shorthand-connect' ) );
		}
		if ( isset( $imported[ $story->id ] ) ) {
			return new WP_Error( 'shorthand_project_duplicate_story', __( 'The Project contains the same Story more than once.', 'shorthand-connect' ) );
		}
		$post_id = shorthand_find_or_create_project_story( $story );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$shortlink = esc_url_raw( wp_get_shortlink( $post_id, 'post', false ) );
		if ( ! $shortlink ) {
			return new WP_Error( 'shorthand_project_shortlink_failed', __( 'WordPress could not create a stable URL for a Project Story.', 'shorthand-connect' ) );
		}
		update_post_meta( $post_id, 'story_id', sanitize_text_field( $story->id ) );
		update_post_meta( $post_id, '_shorthand_project_id', sanitize_text_field( $project->id ) );
		update_post_meta( $post_id, '_shorthand_project_title', $project_title );
		$imported[ $story->id ] = $post_id;
		$urls[ $story->id ]     = $shortlink;
	}

	$jobs         = get_option( 'shorthand_project_import_jobs', array() );
	$previous_job = isset( $jobs[ $project->id ] ) ? $jobs[ $project->id ] : null;
	$new_job      = array(
		'project'          => $project,
		'position'         => 0,
		'imported'         => $imported,
		'urls'             => $urls,
		'download'         => null,
		'status'           => 'running',
		'phase'            => 'queued',
		'target_status'    => in_array( $target_status, array( 'draft', 'publish' ), true ) ? $target_status : '',
		'last_activity_at' => time(),
	);
	if ( shorthand_can_resume_project_import( $previous_job, $new_job ) ) {
		$new_job['position'] = (int) $previous_job['position'];
		$new_job['download'] = isset( $previous_job['download'] ) ? $previous_job['download'] : null;
	}
	$jobs[ $project->id ] = $new_job;
	update_option( 'shorthand_project_import_jobs', $jobs, false );
	wp_clear_scheduled_hook( 'shorthand_import_project_story', array( sanitize_text_field( $project->id ) ) );
	foreach ( $project->stories as $story ) {
		wp_clear_scheduled_hook( 'sh_media_fetch', array( $imported[ $story->id ], $story->id ) );
	}
	if ( ! shorthand_schedule_project_import_step( sanitize_text_field( $project->id ) ) ) {
		$jobs[ $project->id ]['status'] = 'failed';
		$jobs[ $project->id ]['error']  = __( 'WordPress could not schedule the Project import.', 'shorthand-connect' );
		update_option( 'shorthand_project_import_jobs', $jobs, false );
		return new WP_Error( 'shorthand_project_cron_disabled', $jobs[ $project->id ]['error'] );
	}
	return true;
}

/**
 * Determines whether a failed job can safely reuse its current download state.
 *
 * @param array|null $previous_job Previous job.
 * @param array      $new_job Freshly prepared job.
 * @return bool
 */
function shorthand_can_resume_project_import( $previous_job, $new_job ) {
	if ( ! is_array( $previous_job ) || 'failed' !== ( isset( $previous_job['status'] ) ? $previous_job['status'] : '' ) ) {
		return false;
	}
	if ( ! isset( $previous_job['project']->stories, $previous_job['urls'], $previous_job['position'] ) || $previous_job['urls'] !== $new_job['urls'] ) {
		return false;
	}
	$previous_ids = wp_list_pluck( $previous_job['project']->stories, 'id' );
	$new_ids      = wp_list_pluck( $new_job['project']->stories, 'id' );
	return $previous_ids === $new_ids && (int) $previous_job['position'] < count( $new_ids );
}

/**
 * Starts a Project synchronisation from an imported Story's edit screen.
 */
function shorthand_update_project_from_story() {
	$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to update this Project.', 'shorthand-connect' ) );
	}
	check_admin_referer( 'shorthand_update_project_' . $post_id );
	$project_id = get_post_meta( $post_id, '_shorthand_project_id', true );
	$result     = $project_id ? shorthand_begin_project_import( $project_id ) : new WP_Error( 'shorthand_project_missing', __( 'This Story is not associated with a Shorthand Project.', 'shorthand-connect' ) );
	$redirect   = admin_url( 'post-new.php?post_type=shorthand_story&shorthand_tab=projects' );
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'shorthand_project_error', $result->get_error_message(), $redirect ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'shorthand_project_started', 1, $redirect ) );
	exit;
}
add_action( 'admin_post_shorthand_update_project', 'shorthand_update_project_from_story' );

/**
 * Removes the trash action for Project-managed Stories.
 *
 * @param array   $actions Post row actions.
 * @param WP_Post $post Post object.
 * @return array
 */
function shorthand_remove_project_story_delete_action( $actions, $post ) {
	$project_id = 'shorthand_story' === $post->post_type ? get_post_meta( $post->ID, '_shorthand_project_id', true ) : '';
	if ( $project_id ) {
		unset( $actions['delete'] );
		if ( 'trash' === $post->post_status ) {
			if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'delete_post', $post->ID ) ) {
				unset( $actions['untrash'] );
				return $actions;
			}
			$restore_url = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'untrash',
						'post'   => $post->ID,
					),
					admin_url( 'post.php' )
				),
				'untrash-post_' . $post->ID
			);
			$actions['untrash'] = sprintf(
				'<a href="%1$s" aria-label="%2$s">%3$s</a>',
				esc_url( $restore_url ),
				esc_attr__( 'Restore the complete Shorthand Project', 'shorthand-connect' ),
				esc_html__( 'Restore Project', 'shorthand-connect' )
			);
			return $actions;
		}
		unset( $actions['trash'] );
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'delete_post', $post->ID ) ) {
			return $actions;
		}
		$actions['remove-project'] = sprintf(
			'<a href="%1$s" aria-haspopup="dialog" aria-controls="%2$s" data-shorthand-project-dialog-open="%2$s">%3$s</a>',
			esc_url( shorthand_get_project_removal_confirmation_url( $post->ID ) ),
			esc_attr( shorthand_get_project_removal_dialog_id( $project_id ) ),
			esc_html__( 'Remove Project', 'shorthand-connect' )
		);
		if ( ! isset( $GLOBALS['shorthand_project_removal_dialogs'] ) || ! is_array( $GLOBALS['shorthand_project_removal_dialogs'] ) ) {
			$GLOBALS['shorthand_project_removal_dialogs'] = array();
		}
		if ( ! isset( $GLOBALS['shorthand_project_removal_dialogs'][ $project_id ] ) ) {
			$GLOBALS['shorthand_project_removal_dialogs'][ $project_id ] = $post->ID;
		}
	}
	return $actions;
}
add_filter( 'post_row_actions', 'shorthand_remove_project_story_delete_action', 10, 2 );

/**
 * Returns the Project edit URL that opens its removal confirmation dialog.
 *
 * The Project ID is deliberately looked up by the request handler rather than
 * passed in the URL, so a request cannot be retargeted to another Project.
 *
 * @param int $post_id WordPress post ID.
 * @return string
 */
function shorthand_get_project_removal_url( $post_id ) {
	return add_query_arg(
		array(
			'post'                     => absint( $post_id ),
			'action'                   => 'edit',
			'shorthand_remove_project' => 1,
		),
		admin_url( 'post.php' )
	);
}

/**
 * Backwards-compatible descriptive alias for the Project removal confirmation URL.
 *
 * @param int $post_id WordPress post ID.
 * @return string
 */
function shorthand_get_project_removal_confirmation_url( $post_id ) {
	return shorthand_get_project_removal_url( $post_id );
}

/**
 * Returns the DOM ID for a Project removal dialog.
 *
 * @param string $project_id Shorthand Project ID.
 * @return string
 */
function shorthand_get_project_removal_dialog_id( $project_id ) {
	return 'shorthand-remove-project-dialog-' . md5( (string) $project_id );
}

/**
 * Returns every local Story post belonging to a Shorthand Project.
 *
 * @param string $project_id Shorthand Project ID.
 * @return int[]
 */
function shorthand_get_project_post_ids( $project_id ) {
	return get_posts(
		array(
			'post_type'              => 'shorthand_story',
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'meta_key'               => '_shorthand_project_id',
			'meta_value'             => sanitize_text_field( $project_id ),
			'fields'                 => 'ids',
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
}

/**
 * Renders an accessible confirmation dialog listing every affected Story.
 *
 * @param string $project_id Shorthand Project ID.
 * @param int    $post_id    WordPress post ID used to identify the Project.
 */
function shorthand_render_project_removal_dialog( $project_id, $post_id ) {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'delete_post', $post_id ) ) {
		return;
	}
	$project_posts = shorthand_get_project_post_ids( $project_id );
	$dialog_id     = shorthand_get_project_removal_dialog_id( $project_id );
	$title_id      = $dialog_id . '-title';
	$project_title = shorthand_get_project_admin_title( $project_id, $post_id );
	?>
	<dialog id="<?php echo esc_attr( $dialog_id ); ?>" class="shorthand-project-removal-dialog" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
		<div class="shorthand-project-removal-dialog__content">
			<button class="shorthand-project-removal-dialog__close" type="button" data-shorthand-project-dialog-close aria-label="<?php esc_attr_e( 'Close confirmation', 'shorthand-connect' ); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
			<h2 id="<?php echo esc_attr( $title_id ); ?>">
				<?php
			printf(
					/* translators: %s: Shorthand Project title. */
					esc_html__( 'Remove “%s” Shorthand Project?', 'shorthand-connect' ),
					esc_html( $project_title )
				);
				?>
			</h2>
			<p><?php esc_html_e( 'A Shorthand Project is a collection of related Stories that are managed and synchronised together.', 'shorthand-connect' ); ?></p>
			<p><?php esc_html_e( 'Removing this Project moves all of these WordPress Stories to Trash:', 'shorthand-connect' ); ?></p>
			<ul class="shorthand-project-removal-dialog__stories">
				<?php
				foreach ( $project_posts as $project_post_id ) :
					$story_title = get_the_title( $project_post_id );
					?>
					<li>
						<a href="<?php echo esc_url( get_edit_post_link( $project_post_id, 'raw' ) ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $story_title ? $story_title : __( '(Untitled Story)', 'shorthand-connect' ) ); ?>
							<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'shorthand-connect' ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<p><?php esc_html_e( 'Restoring any one Story restores the complete Project.', 'shorthand-connect' ); ?></p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input name="action" type="hidden" value="shorthand_remove_project" />
				<input name="post_id" type="hidden" value="<?php echo esc_attr( absint( $post_id ) ); ?>" />
				<?php wp_nonce_field( 'shorthand_remove_project_' . absint( $post_id ) ); ?>
				<div class="shorthand-project-removal-dialog__actions">
					<button class="button" type="button" data-shorthand-project-dialog-close><?php esc_html_e( 'Cancel', 'shorthand-connect' ); ?></button>
					<button class="button shorthand-project-removal-dialog__confirm" type="submit"><?php esc_html_e( 'Remove Project', 'shorthand-connect' ); ?></button>
				</div>
			</form>
		</div>
	</dialog>
	<?php
}

/**
 * Renders the Project removal dialog outside the main post editor form.
 */
function shorthand_render_project_removal_dialog_for_screen() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $post_id ) {
		return;
	}
	$project_id = get_post_meta( $post_id, '_shorthand_project_id', true );
	if ( $project_id ) {
		shorthand_render_project_removal_dialog( $project_id, $post_id );
	}
}

add_action( 'admin_footer-post.php', 'shorthand_render_project_removal_dialog_for_screen' );

/**
 * Renders confirmation dialogs for Projects represented on the current list page.
 */
function shorthand_render_project_removal_dialogs_for_list() {
	$dialogs = isset( $GLOBALS['shorthand_project_removal_dialogs'] ) && is_array( $GLOBALS['shorthand_project_removal_dialogs'] ) ? $GLOBALS['shorthand_project_removal_dialogs'] : array();
	foreach ( $dialogs as $project_id => $post_id ) {
		shorthand_render_project_removal_dialog( $project_id, $post_id );
	}
}

add_action( 'admin_footer-edit.php', 'shorthand_render_project_removal_dialogs_for_list' );

/**
 * Moves every local Story belonging to a Shorthand Project to Trash.
 */
function shorthand_remove_project() {
	$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
	if ( ! $post_id || ! current_user_can( 'manage_options' ) || ! current_user_can( 'delete_post', $post_id ) ) {
		wp_die( esc_html__( 'You do not have permission to remove this Project.', 'shorthand-connect' ) );
	}

	check_admin_referer( 'shorthand_remove_project_' . $post_id );
	$project_id = get_post_meta( $post_id, '_shorthand_project_id', true );
	if ( ! $project_id ) {
		wp_die( esc_html__( 'This Story is not associated with a Shorthand Project.', 'shorthand-connect' ) );
	}

	$project_posts = shorthand_get_project_post_ids( $project_id );
	if ( empty( $project_posts ) ) {
		wp_die( esc_html__( 'No local Stories were found for this Shorthand Project.', 'shorthand-connect' ) );
	}
	foreach ( $project_posts as $project_post_id ) {
		if ( ! current_user_can( 'delete_post', $project_post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to remove every Story in this Project.', 'shorthand-connect' ) );
		}
	}

	$jobs = get_option( 'shorthand_project_import_jobs', array() );
	if ( is_array( $jobs ) && isset( $jobs[ $project_id ] ) ) {
		$job = $jobs[ $project_id ];
		if ( isset( $job['imported'] ) && is_array( $job['imported'] ) ) {
			foreach ( $job['imported'] as $story_id => $imported_post_id ) {
				wp_clear_scheduled_hook( 'sh_media_fetch', array( (int) $imported_post_id, $story_id ) );
			}
		}
		unset( $jobs[ $project_id ] );
		update_option( 'shorthand_project_import_jobs', $jobs, false );
	}
	wp_clear_scheduled_hook( 'shorthand_import_project_story', array( $project_id ) );
	delete_option( 'shorthand_project_import_lock_' . md5( $project_id ) );

	global $shorthand_project_removal_in_progress;
	$shorthand_project_removal_in_progress = true;
	$removed                                = 0;
	try {
		foreach ( $project_posts as $project_post_id ) {
			$story_id = get_post_meta( $project_post_id, 'story_id', true );
			if ( $story_id ) {
				wp_clear_scheduled_hook( 'sh_media_fetch', array( (int) $project_post_id, $story_id ) );
			}
			if ( 'trash' === get_post_status( $project_post_id ) || wp_trash_post( $project_post_id ) ) {
				$removed++;
			}
		}
	} finally {
		$shorthand_project_removal_in_progress = false;
	}
	wp_cache_delete( 'shorthand_project_' . $project_id, 'shorthand-connect' );

	$redirect = add_query_arg(
		array(
			'post_type'                     => 'shorthand_story',
			'post_status'                   => 'trash',
			'shorthand_project'             => $project_id,
			'shorthand_project_removed'     => $removed,
			'shorthand_project_removal_nonce' => wp_create_nonce( 'shorthand_project_removed' ),
		),
		admin_url( 'edit.php' )
	);
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_shorthand_remove_project', 'shorthand_remove_project' );

/**
 * Displays the result of a Project removal on the local Story list.
 */
function shorthand_project_removal_notice() {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['shorthand_project_removed'], $_GET['shorthand_project_removal_nonce'] ) ) {
		return;
	}
	$nonce = sanitize_text_field( wp_unslash( $_GET['shorthand_project_removal_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'shorthand_project_removed' ) ) {
		return;
	}
	$count = absint( $_GET['shorthand_project_removed'] );
	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %d: Number of local Stories moved to Trash. */
				_n( 'Moved %d Shorthand Project Story to Trash.', 'Moved %d Shorthand Project Stories to Trash.', $count, 'shorthand-connect' ),
				$count
			)
		)
	);
}
add_action( 'admin_notices', 'shorthand_project_removal_notice' );

/**
 * Prevents partial Project restoration when the user cannot restore all Stories.
 *
 * @param mixed   $untrash Whether to short-circuit restoring the post.
 * @param WP_Post $post    Post being restored.
 * @return mixed
 */
function shorthand_prevent_partial_project_restore( $untrash, $post ) {
	if ( 'shorthand_story' !== $post->post_type || ! get_post_meta( $post->ID, '_shorthand_project_id', true ) ) {
		return $untrash;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	foreach ( shorthand_get_project_post_ids( get_post_meta( $post->ID, '_shorthand_project_id', true ) ) as $project_post_id ) {
		if ( ! current_user_can( 'delete_post', $project_post_id ) ) {
			return false;
		}
	}
	return $untrash;
}

add_filter( 'pre_untrash_post', 'shorthand_prevent_partial_project_restore', 10, 2 );

/**
 * Restores every Story in a Project when one Project Story is restored.
 *
 * @param int $post_id Story post being restored by WordPress.
 */
function shorthand_restore_complete_project( $post_id ) {
	global $shorthand_project_restore_in_progress;
	if ( ! empty( $shorthand_project_restore_in_progress ) || 'shorthand_story' !== get_post_type( $post_id ) ) {
		return;
	}
	$project_id = get_post_meta( $post_id, '_shorthand_project_id', true );
	if ( ! $project_id ) {
		return;
	}

	$restored                              = 1;
	$shorthand_project_restore_in_progress = true;
	try {
		foreach ( shorthand_get_project_post_ids( $project_id ) as $project_post_id ) {
			if ( (int) $project_post_id === (int) $post_id || 'trash' !== get_post_status( $project_post_id ) ) {
				continue;
			}
			if ( wp_untrash_post( $project_post_id ) ) {
				$restored++;
			}
		}
	} finally {
		$shorthand_project_restore_in_progress = false;
	}

	set_transient( 'shorthand_project_restored_' . get_current_user_id(), $restored, MINUTE_IN_SECONDS );
}

add_action( 'untrashed_post', 'shorthand_restore_complete_project' );

/**
 * Reports the complete Project restoration triggered by a single Story.
 */
function shorthand_project_restoration_notice() {
	$user_id = get_current_user_id();
	$count   = absint( get_transient( 'shorthand_project_restored_' . $user_id ) );
	if ( ! $count ) {
		return;
	}
	delete_transient( 'shorthand_project_restored_' . $user_id );
	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %d: Number of restored Project Stories. */
				_n( 'Restored %d Shorthand Project Story.', 'Restored all %d Shorthand Project Stories.', $count, 'shorthand-connect' ),
				$count
			)
		)
	);
}

add_action( 'admin_notices', 'shorthand_project_restoration_notice' );

/**
 * Restores a missing cron event for an active Project import.
 */
function shorthand_recover_project_import_schedules() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$jobs = get_option( 'shorthand_project_import_jobs', array() );
	if ( ! is_array( $jobs ) ) {
		return;
	}
	foreach ( $jobs as $project_id => $job ) {
		if ( isset( $job['status'] ) && 'running' === $job['status'] ) {
			shorthand_schedule_project_import_step( $project_id );
		}
	}
}

add_action( 'admin_init', 'shorthand_recover_project_import_schedules' );

/**
 * Determines whether the Shorthand Story list should display Project progress.
 *
 * @return bool
 */
function shorthand_should_show_project_progress() {
	if ( isset( $_GET['shorthand_project_started'] ) || isset( $_GET['shorthand_project_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return true;
	}
	$jobs = get_option( 'shorthand_project_import_jobs', array() );
	if ( ! is_array( $jobs ) ) {
		return false;
	}
	foreach ( $jobs as $job ) {
		if ( isset( $job['status'] ) && 'running' === $job['status'] ) {
			return true;
		}
	}
	return false;
}

/**
 * Loads the Project progress controller on the Shorthand Story list.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function shorthand_enqueue_project_progress_assets( $hook_suffix ) {
	if ( 'edit.php' !== $hook_suffix || ! shorthand_should_show_project_progress() ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'shorthand_story' !== $screen->post_type ) {
		return;
	}

	$plugin_url = plugin_dir_url( dirname( __DIR__ ) . '/shorthand-connect.php' );
	wp_enqueue_style( 'connect_style', $plugin_url . 'css/connect.css', array(), SHORTHAND_VERSION );
	wp_enqueue_script(
		'shorthand-project-progress',
		$plugin_url . 'js/project-import-progress.js',
		array(),
		SHORTHAND_VERSION,
		array( 'strategy' => 'defer' )
	);
	wp_localize_script(
		'shorthand-project-progress',
		'shorthand_project_progress',
		array(
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'project_id'      => isset( $_GET['shorthand_project_id'] ) ? sanitize_text_field( wp_unslash( $_GET['shorthand_project_id'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'status_url'      => esc_url_raw( rest_url( 'shorthand_connect/v1/projects/import/status' ) ),
			'list_url'        => esc_url_raw( add_query_arg( 'post_type', 'shorthand_story', admin_url( 'edit.php' ) ) ),
			'publishing'      => __( 'Publishing Project Stories', 'shorthand-connect' ),
			'saving_drafts'   => __( 'Saving Project Stories as drafts', 'shorthand-connect' ),
			'complete'        => __( 'Project import complete', 'shorthand-connect' ),
			'failed'          => __( 'Project import failed', 'shorthand-connect' ),
			'loading'         => __( 'Loading Project progress…', 'shorthand-connect' ),
			'progress_error'  => __( 'Project progress could not be loaded. WordPress will continue the background import.', 'shorthand-connect' ),
			'progress_format' => __( '%1$d%% — %2$d of %3$d Stories imported', 'shorthand-connect' ),
			'aria_progress'   => __( '%1$s: %2$d%% complete', 'shorthand-connect' ),
		)
	);
}

add_action( 'admin_enqueue_scripts', 'shorthand_enqueue_project_progress_assets' );

/**
 * Loads the Project removal dialog on a Project-managed Story edit screen.
 *
 * @param string $hook_suffix Current admin page hook.
 */
function shorthand_enqueue_project_removal_assets( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'edit.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'shorthand_story' !== $screen->post_type ) {
		return;
	}
	if ( 'post.php' === $hook_suffix ) {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $post_id || ! get_post_meta( $post_id, '_shorthand_project_id', true ) ) {
			return;
		}
	}

	$plugin_url = plugin_dir_url( dirname( __DIR__ ) . '/shorthand-connect.php' );
	wp_enqueue_style( 'connect_style', $plugin_url . 'css/connect.css', array(), SHORTHAND_VERSION );
	wp_enqueue_script(
		'shorthand-project-removal-dialog',
		$plugin_url . 'js/project-removal-dialog.js',
		array(),
		SHORTHAND_VERSION,
		array( 'strategy' => 'defer' )
	);
}

add_action( 'admin_enqueue_scripts', 'shorthand_enqueue_project_removal_assets' );

/**
 * Renders the accessible Project progress region on the Story list.
 */
function shorthand_render_project_progress_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-shorthand_story' !== $screen->id || ! shorthand_should_show_project_progress() ) {
		return;
	}
	?>
	<div class="notice notice-info shorthand-project-progress-notice">
		<p><strong><?php esc_html_e( 'Shorthand Project progress', 'shorthand-connect' ); ?></strong></p>
		<div id="shorthand-project-progress" aria-live="polite" aria-busy="true">
			<p><?php esc_html_e( 'Loading Project progress…', 'shorthand-connect' ); ?></p>
		</div>
	</div>
	<?php
}

add_action( 'admin_notices', 'shorthand_render_project_progress_notice' );

/**
 * Prevents Project-managed Stories being deleted individually.
 *
 * @param mixed   $delete Whether to delete the post.
 * @param WP_Post $post Post being deleted.
 * @return mixed
 */
function shorthand_prevent_project_story_deletion( $delete, $post ) {
	global $shorthand_project_removal_in_progress;
	if ( empty( $shorthand_project_removal_in_progress ) && 'shorthand_story' === $post->post_type && get_post_meta( $post->ID, '_shorthand_project_id', true ) ) {
		return false;
	}
	return $delete;
}
add_filter( 'pre_trash_post', 'shorthand_prevent_project_story_deletion', 10, 2 );
add_filter( 'pre_delete_post', 'shorthand_prevent_project_story_deletion', 10, 2 );

/**
 * Hides the standard trash link on a Project-managed Story edit screen.
 */
function shorthand_hide_project_story_trash_link() {
	global $pagenow;
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( 'post.php' === $pagenow && $post_id && get_post_meta( $post_id, '_shorthand_project_id', true ) ) {
		echo '<style>#delete-action { display: none; }</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
add_action( 'admin_head', 'shorthand_hide_project_story_trash_link' );

/**
 * Imports one Story and schedules the next, keeping the request bounded.
 *
 * @param string $project_id Project ID.
 */
function shorthand_import_project_story( $project_id ) {
	$jobs = get_option( 'shorthand_project_import_jobs', array() );
	if ( empty( $jobs[ $project_id ] ) || 'running' !== $jobs[ $project_id ]['status'] ) {
		return;
	}
	if ( ! empty( $jobs[ $project_id ]['next_attempt_at'] ) && time() < $jobs[ $project_id ]['next_attempt_at'] ) {
		shorthand_schedule_project_import_step( $project_id, max( 1, $jobs[ $project_id ]['next_attempt_at'] - time() ) );
		return;
	}
	$lock_key = shorthand_acquire_project_import_lock( $project_id );
	if ( ! $lock_key ) {
		shorthand_schedule_project_import_step( $project_id, 5 );
		return;
	}
	try {
		$jobs = get_option( 'shorthand_project_import_jobs', array() );
		if ( empty( $jobs[ $project_id ] ) || 'running' !== $jobs[ $project_id ]['status'] ) {
			return;
		}
		$jobs[ $project_id ]['phase']            = 'processing';
		$jobs[ $project_id ]['last_activity_at'] = time();
		update_option( 'shorthand_project_import_jobs', $jobs, false );
		$job = $jobs[ $project_id ];
		if ( $job['position'] >= count( $job['project']->stories ) ) {
			$result                        = shorthand_finish_project_import( $project_id, $job );
			$jobs[ $project_id ]['status'] = is_wp_error( $result ) ? 'failed' : 'complete';
			$jobs[ $project_id ]['phase']  = is_wp_error( $result ) ? 'failed' : 'complete';
			$jobs[ $project_id ]['error']  = is_wp_error( $result ) ? $result->get_error_message() : '';
			$jobs[ $project_id ]['debug']  = is_wp_error( $result ) ? $result->get_error_data() : array();
			update_option( 'shorthand_project_import_jobs', $jobs, false );
			return;
		}

		$story = $job['project']->stories[ $job['position'] ];
		if ( empty( $story->id ) ) {
			$jobs[ $project_id ]['status'] = 'failed';
			$jobs[ $project_id ]['error']  = __( 'The Project contains a Story without an ID.', 'shorthand-connect' );
			update_option( 'shorthand_project_import_jobs', $jobs, false );
			return;
		}
		$post_id = isset( $job['imported'][ $story->id ] ) ? (int) $job['imported'][ $story->id ] : 0;
		if ( ! $post_id ) {
			$jobs[ $project_id ]['status'] = 'failed';
			$jobs[ $project_id ]['error']  = __( 'A prepared WordPress Story mapping is missing.', 'shorthand-connect' );
			update_option( 'shorthand_project_import_jobs', $jobs, false );
			return;
		}

		if ( empty( $job['download']['story_id'] ) || $story->id !== $job['download']['story_id'] ) {
			$download = shorthand_start_project_story_download( $story->id );
			if ( is_wp_error( $download ) ) {
				$result = $download;
			} else {
				$jobs[ $project_id ]['download']         = array(
					'story_id'   => $story->id,
					'status_url' => $download,
				);
				$jobs[ $project_id ]['phase']            = 'waiting';
				$jobs[ $project_id ]['next_attempt_at']  = time() + 5;
				$jobs[ $project_id ]['last_activity_at'] = time();
				update_option( 'shorthand_project_import_jobs', $jobs, false );
				shorthand_schedule_project_import_step( $project_id, 5 );
				return;
			}
		} else {
			$result = shorthand_complete_project_story_download( $post_id, $story->id, $job['download']['status_url'], $job['urls'] );
			if ( true === $result ) {
				$jobs[ $project_id ]['download'] = null;
			} elseif ( 'pending' === $result ) {
				$jobs[ $project_id ]['phase']            = 'waiting';
				$jobs[ $project_id ]['next_attempt_at']  = time() + 5;
				$jobs[ $project_id ]['last_activity_at'] = time();
				update_option( 'shorthand_project_import_jobs', $jobs, false );
				shorthand_schedule_project_import_step( $project_id, 5 );
				return;
			}
		}
		if ( is_wp_error( $result ) ) {
			$jobs[ $project_id ]['status'] = 'failed';
			$jobs[ $project_id ]['phase']  = 'failed';
			/* translators: 1: Story ID, 2: Story title, 3: import error. */
			$jobs[ $project_id ]['error'] = sprintf( __( 'Story %1$s (%2$s) failed: %3$s', 'shorthand-connect' ), $story->id, isset( $story->title ) ? $story->title : $story->id, $result->get_error_message() );
			update_option( 'shorthand_project_import_jobs', $jobs, false );
			return;
		}

		update_post_meta( $post_id, '_shorthand_project_id', sanitize_text_field( $project_id ) );
		if ( ! empty( $job['project']->title ) ) {
			update_post_meta( $post_id, '_shorthand_project_title', sanitize_text_field( $job['project']->title ) );
		}
		++$jobs[ $project_id ]['position'];
		$jobs[ $project_id ]['phase']           = 'queued';
		$jobs[ $project_id ]['next_attempt_at'] = 0;
		$jobs[ $project_id ]['last_activity_at'] = time();
		update_option( 'shorthand_project_import_jobs', $jobs, false );
		if ( $jobs[ $project_id ]['position'] >= count( $job['project']->stories ) ) {
			$result                        = shorthand_finish_project_import( $project_id, $jobs[ $project_id ] );
			$jobs[ $project_id ]['status'] = is_wp_error( $result ) ? 'failed' : 'complete';
			$jobs[ $project_id ]['phase']  = is_wp_error( $result ) ? 'failed' : 'complete';
			$jobs[ $project_id ]['error']  = is_wp_error( $result ) ? $result->get_error_message() : '';
			$jobs[ $project_id ]['debug']  = is_wp_error( $result ) ? $result->get_error_data() : array();
			update_option( 'shorthand_project_import_jobs', $jobs, false );
		} elseif ( ! shorthand_schedule_project_import_step( $project_id ) ) {
			$jobs[ $project_id ]['status'] = 'failed';
			$jobs[ $project_id ]['error']  = __( 'WordPress could not schedule the next Project Story import.', 'shorthand-connect' );
			update_option( 'shorthand_project_import_jobs', $jobs, false );
		}
	} finally {
		shorthand_release_project_import_lock( $lock_key );
	}
}

// Preserve already-scheduled jobs from plugin versions that used a separate Project hook.
add_action( 'shorthand_import_project_story', 'shorthand_import_project_story' );

/**
 * Acquires an atomic lock for one Project import.
 *
 * @param string $project_id Project ID.
 * @return string|false Lock option name, or false when another worker owns it.
 */
function shorthand_acquire_project_import_lock( $project_id ) {
	$lock_key   = 'shorthand_project_import_lock_' . md5( $project_id );
	$expires_at = time() + ( 20 * MINUTE_IN_SECONDS );

	if ( add_option( $lock_key, $expires_at, '', 'no' ) ) {
		return $lock_key;
	}

	if ( (int) get_option( $lock_key ) >= time() ) {
		return false;
	}

	delete_option( $lock_key );
	return add_option( $lock_key, $expires_at, '', 'no' ) ? $lock_key : false;
}

/**
 * Releases a Project import lock.
 *
 * @param string $lock_key Lock option name.
 */
function shorthand_release_project_import_lock( $lock_key ) {
	delete_option( $lock_key );
}

/**
 * Schedules one bounded import step through the existing media-fetch Cron hook.
 *
 * @param string $project_id Project ID.
 * @param int    $delay Delay before the next attempt, in seconds.
 * @return bool Whether background processing is available.
 */
function shorthand_schedule_project_import_step( $project_id, $delay = 0 ) {
	$jobs = get_option( 'shorthand_project_import_jobs', array() );
	if ( empty( $jobs[ $project_id ] ) || 'running' !== $jobs[ $project_id ]['status'] ) {
		return false;
	}
	$job = $jobs[ $project_id ];
	if ( $job['position'] >= count( $job['project']->stories ) ) {
		return false;
	}
	$story = $job['project']->stories[ $job['position'] ];
	if ( empty( $story->id ) || empty( $job['imported'][ $story->id ] ) ) {
		return false;
	}

	$args         = array( (int) $job['imported'][ $story->id ], $story->id );
	$scheduled_at = wp_next_scheduled( 'sh_media_fetch', $args );
	$stale_before = time() - MINUTE_IN_SECONDS;

	if ( $scheduled_at ) {
		if ( $scheduled_at < $stale_before ) {
			wp_unschedule_event( $scheduled_at, 'sh_media_fetch', $args );
		} else {
			return true;
		}
	}
	return false !== wp_schedule_single_event( time() + absint( $delay ), 'sh_media_fetch', $args );
}

/**
 * Finds the stable local Story mapping, creating a draft only when absent.
 *
 * @param object $story Project Story summary.
 * @return int|WP_Error
 */
function shorthand_find_or_create_project_story( $story ) {
	$posts = get_posts(
		array(
			'post_type'      => 'shorthand_story',
			'post_status'    => 'any',
			'meta_key'       => 'story_id',
			'meta_value'     => sanitize_text_field( $story->id ),
			'fields'         => 'ids',
			'posts_per_page' => 1,
		)
	);
	if ( ! empty( $posts ) ) {
		return (int) $posts[0];
	}
	return wp_insert_post(
		array(
			'post_type'   => 'shorthand_story',
			'post_status' => 'draft',
			'post_title'  => isset( $story->title ) ? sanitize_text_field( $story->title ) : sanitize_text_field( $story->id ),
		),
		true
	);
}

/**
 * Starts generation of a Project-aware Story archive.
 *
 * @param string $story_id Shorthand Story ID.
 * @return string|WP_Error Download status path.
 */
function shorthand_start_project_story_download( $story_id ) {
	$response = shorthand_api_v2_request(
		'/v2/stories/' . rawurlencode( $story_id ) . '/generate',
		array(
			'method'      => 'POST',
			'timeout'     => 30,
			'redirection' => 0,
			'headers'     => array( 'Content-Type' => 'application/json' ),
			'body'        => wp_json_encode( array( 'includeProject' => true ) ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	if ( ! is_array( $response ) || 202 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'shorthand_project_generation_failed', __( 'Shorthand could not start the Project Story download.', 'shorthand-connect' ) );
	}

	return shorthand_get_api_path_from_location( wp_remote_retrieve_header( $response, 'location' ) );
}

/**
 * Polls and, when ready, applies a generated Project-aware Story archive.
 *
 * @param int    $post_id WordPress post ID.
 * @param string $story_id Shorthand Story ID.
 * @param string $status_path Download status path.
 * @param array  $story_urls Stable WordPress Story URLs keyed by Shorthand Story ID.
 * @return true|string|WP_Error True when complete, "pending" while generating, or an error.
 */
function shorthand_complete_project_story_download( $post_id, $story_id, $status_path, $story_urls ) {
	$response = shorthand_api_v2_request(
		$status_path,
		array(
			'timeout'     => 30,
			'redirection' => 0,
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$status = is_array( $response ) ? wp_remote_retrieve_response_code( $response ) : 0;
	if ( 202 === $status ) {
		return 'pending';
	}
	if ( 302 !== $status ) {
		return new WP_Error( 'shorthand_project_download_failed', __( 'Shorthand could not finish the Project Story download.', 'shorthand-connect' ) );
	}

	$file_path = shorthand_get_api_path_from_location( wp_remote_retrieve_header( $response, 'location' ) );
	if ( is_wp_error( $file_path ) ) {
		return $file_path;
	}
	$zip_file = wp_tempnam( 'sh_zip', get_temp_dir() );
	if ( ! $zip_file ) {
		return new WP_Error( 'shorthand_project_temp_file_failed', __( 'WordPress could not create a temporary file for the Project Story archive.', 'shorthand-connect' ) );
	}
	$response = shorthand_api_v2_request(
		$file_path,
		array(
			'timeout'  => 600,
			'stream'   => true,
			'filename' => $zip_file,
		)
	);
	if ( is_wp_error( $response ) || ! is_array( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		wp_delete_file( $zip_file );
		return is_wp_error( $response ) ? $response : new WP_Error( 'shorthand_project_archive_failed', __( 'The generated Project Story archive could not be downloaded.', 'shorthand-connect' ) );
	}

	$safe_story_id = preg_replace( '/\W|_/', '', $story_id );
	$bundle_id     = $safe_story_id . '-project-' . str_replace( '-', '', wp_generate_uuid4() );
	$uploads       = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		wp_delete_file( $zip_file );
		return new WP_Error( 'shorthand_project_upload_path_failed', __( 'WordPress could not prepare the Story upload directory.', 'shorthand-connect' ) );
	}
	$destination_path = $uploads['path'] . '/shorthand/' . $post_id;
	$story            = extract_story_content( $zip_file, $destination_path, $bundle_id );
	wp_delete_file( $zip_file );
	if ( isset( $story['error'] ) ) {
		shorthand_delete_failed_project_bundle( $destination_path . '/' . $bundle_id );
		return new WP_Error( 'shorthand_project_archive_invalid', __( 'The generated Project Story archive could not be extracted.', 'shorthand-connect' ) );
	}

	$story_path = isset( $story['path'] ) ? $story['path'] : '';
	$story_url  = shorthand_get_story_url( $post_id, $bundle_id, $story_path );
	$head       = shorthand_post_process_project_files( $story_path, $story['head'], $story_url, $story_urls, $story_id );
	if ( is_wp_error( $head ) ) {
		shorthand_delete_failed_project_bundle( $story_path );
		return $head;
	}
	$story['head'] = $head;

	$settings = shorthand_api_request_json( '/v2/stories/' . rawurlencode( $story_id ) . '/settings', array( 'timeout' => 240 ) );
	if ( ! is_object( $settings ) || ! isset( $settings->meta->title ) ) {
		shorthand_delete_failed_project_bundle( $story_path );
		return new WP_Error( 'shorthand_project_settings_failed', __( 'Shorthand did not return the Story settings.', 'shorthand-connect' ) );
	}

	do_action( 'sh_copy_story', $post_id, $story_id, $story );
	$result = shand_apply_story_update( $post_id, $story_id, $settings, $story, false, $story_path );
	if ( is_wp_error( $result ) ) {
		shorthand_delete_failed_project_bundle( $story_path );
	}
	return $result;
}

/**
 * Removes a newly created Project bundle that was never committed to a post.
 *
 * @param string $story_path Failed bundle path.
 */
function shorthand_delete_failed_project_bundle( $story_path ) {
	if ( ! $story_path || false === strpos( basename( $story_path ), '-project-' ) ) {
		return;
	}
	$uploads    = wp_upload_dir();
	$base_path  = ! empty( $uploads['basedir'] ) ? wp_normalize_path( trailingslashit( $uploads['basedir'] ) ) : '';
	$story_path = wp_normalize_path( $story_path );
	if ( ! $base_path || 0 !== strpos( $story_path, $base_path ) || false === strpos( $story_path, '/shorthand/' ) ) {
		return;
	}
	init_wp_filesystem();
	global $wp_filesystem;
	$wp_filesystem->delete( $story_path, true );
}

/**
 * Rewrites the portable Project index and Story context for this WordPress site.
 *
 * @param string $story_path Extracted Story directory.
 * @param string $head Story head markup.
 * @param string $story_url Public URL of the extracted Story directory.
 * @param array  $story_urls Stable WordPress Story URLs keyed by Shorthand Story ID.
 * @param string $story_id Current Shorthand Story ID.
 * @return string|WP_Error Updated head markup or an error.
 */
function shorthand_post_process_project_files( $story_path, $head, $story_url, $story_urls, $story_id ) {
	init_wp_filesystem();
	global $wp_filesystem;

	$project_file = trailingslashit( $story_path ) . 'project.js';
	$script       = $wp_filesystem->get_contents( $project_file );
	if ( false === $script ) {
		return new WP_Error( 'shorthand_project_index_missing', __( 'The Project index is missing from the Story archive.', 'shorthand-connect' ) );
	}

	$parsed_index = shorthand_parse_project_json_assignment( $script, 'window.$shproject.index', true );
	if ( is_wp_error( $parsed_index ) ) {
		return $parsed_index;
	}
	$index = $parsed_index['value'];
	if ( ! isset( $index['pages'] ) || ! is_array( $index['pages'] ) ) {
		return new WP_Error( 'shorthand_project_index_invalid', __( 'The Project index could not be decoded.', 'shorthand-connect' ) );
	}
	$index_story_ids  = array_keys( $index['pages'] );
	$mapped_story_ids = array_keys( $story_urls );
	sort( $index_story_ids );
	sort( $mapped_story_ids );
	if ( $index_story_ids !== $mapped_story_ids ) {
		return new WP_Error( 'shorthand_project_index_incomplete', __( 'The Project index does not contain every imported Story.', 'shorthand-connect' ) );
	}
	if ( empty( $story_urls[ $story_id ] ) ) {
		return new WP_Error( 'shorthand_project_story_url_missing', __( 'The current Story does not have a stable WordPress URL.', 'shorthand-connect' ) );
	}

	foreach ( $story_urls as $mapped_story_id => $mapped_story_url ) {
		if ( ! isset( $index['pages'][ $mapped_story_id ] ) || ! is_array( $index['pages'][ $mapped_story_id ] ) || ! $mapped_story_url ) {
			return new WP_Error( 'shorthand_project_index_incomplete', __( 'The Project index does not match the imported Stories.', 'shorthand-connect' ) );
		}
		$mapped_story_url = esc_url_raw( $mapped_story_url );
		if ( ! $mapped_story_url ) {
			return new WP_Error( 'shorthand_project_url_invalid', __( 'A WordPress Story URL is invalid.', 'shorthand-connect' ) );
		}
		$index['pages'][ $mapped_story_id ]['uri'] = $mapped_story_url;
	}

	$head = shorthand_update_project_story_context( $head, trailingslashit( $story_url ), $story_urls[ $story_id ] );
	if ( is_wp_error( $head ) ) {
		return $head;
	}

	$encoded_index = wp_json_encode( $index );
	if ( false === $encoded_index || ! $wp_filesystem->put_contents( $project_file, 'window.$shproject.index = ' . $encoded_index . ';', FS_CHMOD_FILE ) ) {
		return new WP_Error( 'shorthand_project_index_write_failed', __( 'The Project index could not be updated for WordPress.', 'shorthand-connect' ) );
	}

	return $head;
}

/**
 * Rewrites the generated Project context without parsing arbitrary HTML links.
 *
 * @param string $head Story head markup.
 * @param string $project_url Public URL containing project.js and Project assets.
 * @param string $page_url Stable URL of the current WordPress Story.
 * @return string|WP_Error Updated head markup or an error.
 */
function shorthand_update_project_story_context( $head, $project_url, $page_url ) {
	$parsed_context = shorthand_parse_project_json_assignment( $head, 'window.$shproject', false );
	if ( is_wp_error( $parsed_context ) ) {
		return $parsed_context;
	}
	$context = $parsed_context['value'];

	$context['baseUrl']    = esc_url_raw( $project_url );
	$context['publishUrl'] = esc_url_raw( $project_url );
	$context['pageUri']    = esc_url_raw( $page_url );
	$encoded_context       = wp_json_encode( $context );
	if ( false === $encoded_context ) {
		return new WP_Error( 'shorthand_project_context_invalid', __( 'The Story Project context could not be encoded.', 'shorthand-connect' ) );
	}

	return substr( $head, 0, $parsed_context['json_start'] ) . $encoded_context . substr( $head, $parsed_context['json_end'] + 1 );
}

/**
 * Adds the current WordPress permalinks to a Project Story head at render time.
 *
 * The downloaded project.js keeps stable WordPress shortlinks as a fallback.
 * When the local Project index can be read, it is inlined before Shorthand's
 * deferred Project runtime and its Story URLs are replaced with the current
 * permalinks without requiring another Shorthand download.
 *
 * @param string $head Story head markup.
 * @param int    $post_id Current WordPress post ID.
 * @return string Story head markup.
 */
function shorthand_add_runtime_project_permalinks( $head, $post_id ) {
	$project_id = get_post_meta( $post_id, '_shorthand_project_id', true );
	$story_path = get_post_meta( $post_id, 'story_path', true );
	if ( ! $project_id || ! $story_path ) {
		return $head;
	}
	$uploads    = wp_upload_dir();
	$base_path  = ! empty( $uploads['basedir'] ) ? wp_normalize_path( trailingslashit( $uploads['basedir'] ) ) : '';
	$story_path = wp_normalize_path( $story_path );
	if ( ! $base_path || 0 !== strpos( $story_path, $base_path ) || false === strpos( $story_path, '/shorthand/' ) ) {
		return $head;
	}

	init_wp_filesystem();
	global $wp_filesystem;
	if ( ! $wp_filesystem ) {
		return $head;
	}
	$project_script = $wp_filesystem->get_contents( trailingslashit( $story_path ) . 'project.js' );
	if ( false === $project_script ) {
		return $head;
	}

	$parsed_index = shorthand_parse_project_json_assignment( $project_script, 'window.$shproject.index', true );
	if ( is_wp_error( $parsed_index ) || empty( $parsed_index['value']['pages'] ) || ! is_array( $parsed_index['value']['pages'] ) ) {
		return $head;
	}
	$index = $parsed_index['value'];

	$project_posts = get_posts(
		array(
			'post_type'      => 'shorthand_story',
			'post_status'    => 'any',
			'meta_key'       => '_shorthand_project_id',
			'meta_value'     => $project_id,
			'fields'         => 'ids',
			'posts_per_page' => -1,
		)
	);
	foreach ( $project_posts as $project_post_id ) {
		$story_id  = get_post_meta( $project_post_id, 'story_id', true );
		$permalink = get_permalink( $project_post_id );
		if ( $story_id && $permalink && isset( $index['pages'][ $story_id ] ) && is_array( $index['pages'][ $story_id ] ) ) {
			$index['pages'][ $story_id ]['uri'] = esc_url_raw( $permalink );
		}
	}

	$current_permalink = get_permalink( $post_id );
	$encoded_index      = wp_json_encode( $index, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	$encoded_permalink  = wp_json_encode( esc_url_raw( $current_permalink ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
	if ( false === $encoded_index || false === $encoded_permalink || ! $current_permalink ) {
		return $head;
	}

	return $head . '<script>window.$shproject.index=' . $encoded_index . ';window.$shproject.pageUri=' . $encoded_permalink . ';</script>';
}

/**
 * Parses one deterministic JavaScript JSON assignment.
 *
 * @param string $source JavaScript or HTML source.
 * @param string $assignment Assignment name without the equals sign.
 * @param bool   $require_only Whether the assignment must be the whole source.
 * @return array|WP_Error Parsed value and JSON offsets, or an error.
 */
function shorthand_parse_project_json_assignment( $source, $assignment, $require_only ) {
	$assignment_start = false;
	$search_offset    = 0;
	$length           = strlen( $source );
	while ( true ) {
		$candidate = strpos( $source, $assignment, $search_offset );
		if ( false === $candidate ) {
			break;
		}
		$equals = $candidate + strlen( $assignment );
		while ( $equals < $length && ctype_space( $source[ $equals ] ) ) {
			++$equals;
		}
		if ( $equals < $length && '=' === $source[ $equals ] ) {
			if ( false !== $assignment_start ) {
				return new WP_Error( 'shorthand_project_json_wrapper_missing', __( 'The Project JSON wrapper is missing or ambiguous.', 'shorthand-connect' ) );
			}
			$assignment_start = $candidate;
		}
		$search_offset = $candidate + strlen( $assignment );
	}
	if ( false === $assignment_start ) {
		return new WP_Error( 'shorthand_project_json_wrapper_missing', __( 'The Project JSON wrapper is missing or ambiguous.', 'shorthand-connect' ) );
	}

	$cursor = $assignment_start + strlen( $assignment );
	while ( $cursor < $length && ctype_space( $source[ $cursor ] ) ) {
		++$cursor;
	}
	if ( $cursor >= $length || '=' !== $source[ $cursor ] ) {
		return new WP_Error( 'shorthand_project_json_wrapper_invalid', __( 'The Project JSON wrapper is invalid.', 'shorthand-connect' ) );
	}
	++$cursor;
	while ( $cursor < $length && ctype_space( $source[ $cursor ] ) ) {
		++$cursor;
	}
	if ( $cursor >= $length || '{' !== $source[ $cursor ] ) {
		return new WP_Error( 'shorthand_project_json_invalid', __( 'The Project JSON value is invalid.', 'shorthand-connect' ) );
	}

	$json_start = $cursor;
	$depth      = 0;
	$in_string  = false;
	$escaped    = false;
	$json_end   = null;
	for ( ; $cursor < $length; ++$cursor ) {
		$character = $source[ $cursor ];
		if ( $in_string ) {
			if ( $escaped ) {
				$escaped = false;
			} elseif ( '\\' === $character ) {
				$escaped = true;
			} elseif ( '"' === $character ) {
				$in_string = false;
			}
			continue;
		}
		if ( '"' === $character ) {
			$in_string = true;
		} elseif ( '{' === $character ) {
			++$depth;
		} elseif ( '}' === $character ) {
			--$depth;
			if ( 0 === $depth ) {
				$json_end = $cursor;
				break;
			}
		}
	}

	if ( null === $json_end || $in_string ) {
		return new WP_Error( 'shorthand_project_json_invalid', __( 'The Project JSON value is incomplete.', 'shorthand-connect' ) );
	}
	$cursor = $json_end + 1;
	while ( $cursor < $length && ctype_space( $source[ $cursor ] ) ) {
		++$cursor;
	}
	if ( $cursor >= $length || ';' !== $source[ $cursor ] ) {
		return new WP_Error( 'shorthand_project_json_wrapper_invalid', __( 'The Project JSON assignment is not terminated.', 'shorthand-connect' ) );
	}
	if ( $require_only && ( '' !== trim( substr( $source, 0, $assignment_start ) ) || '' !== trim( substr( $source, $cursor + 1 ) ) ) ) {
		return new WP_Error( 'shorthand_project_json_wrapper_invalid', __( 'The Project index contains unexpected JavaScript.', 'shorthand-connect' ) );
	}

	$value = json_decode( substr( $source, $json_start, $json_end - $json_start + 1 ), true );
	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $value ) ) {
		return new WP_Error( 'shorthand_project_json_invalid', __( 'The Project JSON value could not be decoded.', 'shorthand-connect' ) );
	}

	return array(
		'value'      => $value,
		'json_start' => $json_start,
		'json_end'   => $json_end,
	);
}

/**
 * Converts a Dylan download Location header to an authenticated local API path.
 *
 * @param string $location Download Location header.
 * @return string|WP_Error
 */
function shorthand_get_api_path_from_location( $location ) {
	global $server_url;
	if ( ! is_string( $location ) ) {
		return new WP_Error( 'shorthand_project_location_invalid', __( 'Shorthand returned an invalid download location.', 'shorthand-connect' ) );
	}
	$location = trim( $location );
	if ( ! $location ) {
		return new WP_Error( 'shorthand_project_location_missing', __( 'Shorthand did not provide a download location.', 'shorthand-connect' ) );
	}
	if ( '/' === substr( $location, 0, 1 ) && '//' !== substr( $location, 0, 2 ) ) {
		return shorthand_remove_api_base_path( $location, $server_url );
	}

	$server        = wp_parse_url( $server_url );
	$download      = wp_parse_url( $location );
	$server_port   = shorthand_get_url_port( $server );
	$download_port = shorthand_get_url_port( $download );
	if ( empty( $server['scheme'] ) || empty( $server['host'] ) || empty( $download['scheme'] ) || empty( $download['host'] ) || strtolower( $server['scheme'] ) !== strtolower( $download['scheme'] ) || strtolower( $server['host'] ) !== strtolower( $download['host'] ) || $server_port !== $download_port || isset( $download['user'] ) || isset( $download['pass'] ) || isset( $download['fragment'] ) ) {
		return new WP_Error( 'shorthand_project_location_invalid', __( 'Shorthand returned an unexpected download location.', 'shorthand-connect' ) );
	}

	$path = shorthand_remove_api_base_path( isset( $download['path'] ) ? $download['path'] : '/', $server_url );
	return isset( $download['query'] ) ? $path . '?' . $download['query'] : $path;
}

/**
 * Returns the effective port for a parsed HTTP URL.
 *
 * @param array $url Parsed URL.
 * @return int|null
 */
function shorthand_get_url_port( $url ) {
	if ( isset( $url['port'] ) ) {
		return (int) $url['port'];
	}
	if ( isset( $url['scheme'] ) && 'https' === strtolower( $url['scheme'] ) ) {
		return 443;
	}
	if ( isset( $url['scheme'] ) && 'http' === strtolower( $url['scheme'] ) ) {
		return 80;
	}
	return null;
}

/**
 * Converts an origin-relative Location into the API helper's relative path.
 *
 * @param string $path Location path.
 * @param string $api_url Configured API base URL.
 * @return string
 */
function shorthand_remove_api_base_path( $path, $api_url ) {
	$api_parts = wp_parse_url( $api_url );
	$base_path = isset( $api_parts['path'] ) ? untrailingslashit( $api_parts['path'] ) : '';
	if ( $base_path && ( $path === $base_path || 0 === strpos( $path, $base_path . '/' ) ) ) {
		$path = substr( $path, strlen( $base_path ) );
	}
	return $path ? $path : '/';
}

/**
 * Detaches stale local Stories only after every Story succeeded.
 *
 * @param string $project_id Project ID.
 * @param array  $job Import job.
 * @return true|WP_Error
 */
function shorthand_finish_project_import( $project_id, $job ) {
	$previous_posts = get_posts(
		array(
			'post_type'      => 'shorthand_story',
			'post_status'    => 'any',
			'meta_key'       => '_shorthand_project_id',
			'meta_value'     => $project_id,
			'fields'         => 'ids',
			'posts_per_page' => -1,
		)
	);
	foreach ( $previous_posts as $post_id ) {
		if ( ! in_array( (int) $post_id, array_map( 'intval', $job['imported'] ), true ) ) {
			delete_post_meta( $post_id, '_shorthand_project_id' );
			delete_post_meta( $post_id, '_shorthand_project_title' );
		}
	}
	$target_status = isset( $job['target_status'] ) && in_array( $job['target_status'], array( 'draft', 'publish' ), true ) ? $job['target_status'] : '';
	if ( $target_status ) {
		foreach ( $job['imported'] as $post_id ) {
			$result = wp_update_post(
				array(
					'ID'          => absint( $post_id ),
					'post_status' => $target_status,
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
	}
	wp_cache_delete( 'shorthand_project_' . $project_id, 'shorthand-connect' );
	return true;
}
