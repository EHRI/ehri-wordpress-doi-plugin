<?php
/**
 * EHRI DOI Events System
 *
 * Provides extensible hook system for DOI operations to allow
 * third-party plugins and themes to integrate with DOI workflows.
 *
 * @package ehri-pid-tools
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * EHRI DOI Events class for managing WordPress action hooks
 * throughout the DOI lifecycle.
 */
class EHRI_DOI_Events {

	/**
	 * Fire event when a new DOI is created.
	 *
	 * @param string $doi The DOI identifier.
	 * @param int    $post_id The WordPress post ID.
	 * @param array  $metadata The DOI metadata.
	 * @param string $state The initial DOI state (draft, registered, findable).
	 */
	public static function doi_created( string $doi, int $post_id, array $metadata, string $state = 'draft' ): void {
		do_action( 'ehri_doi_created', $doi, $post_id, $metadata, $state );
	}

	/**
	 * Fire event when DOI metadata is updated.
	 *
	 * @param string $doi The DOI identifier.
	 * @param int    $post_id The WordPress post ID.
	 * @param array  $old_metadata The previous metadata.
	 * @param array  $new_metadata The updated metadata.
	 * @param array  $changed_fields List of fields that changed.
	 */
	public static function doi_updated( string $doi, int $post_id, array $old_metadata, array $new_metadata, array $changed_fields = array() ): void {
		do_action( 'ehri_doi_updated', $doi, $post_id, $old_metadata, $new_metadata, $changed_fields );
	}

	/**
	 * Fire event when a DOI is deleted.
	 *
	 * @param string $doi The DOI identifier.
	 * @param int    $post_id The WordPress post ID.
	 * @param array  $metadata The metadata before deletion.
	 */
	public static function doi_deleted( string $doi, int $post_id, array $metadata = array() ): void {
		do_action( 'ehri_doi_deleted', $doi, $post_id, $metadata );
	}

	/**
	 * Fire event when DOI state changes.
	 *
	 * @param string $doi The DOI identifier.
	 * @param int    $post_id The WordPress post ID.
	 * @param string $old_state The previous state.
	 * @param string $new_state The new state.
	 * @param string $event The event that triggered the state change.
	 */
	public static function doi_state_changed( string $doi, int $post_id, string $old_state, string $new_state, string $event ): void {
		do_action( 'ehri_doi_state_changed', $doi, $post_id, $old_state, $new_state, $event );
	}

	/**
	 * Fire event before any DOI operation.
	 *
	 * @param string $operation The operation being performed (create, update, delete, state_change).
	 * @param string $doi The DOI identifier (empty for create operations).
	 * @param int    $post_id The WordPress post ID.
	 * @param array  $context Additional context data for the operation.
	 */
	public static function before_doi_operation( string $operation, string $doi, int $post_id, array $context = array() ): void {
		do_action( 'ehri_before_doi_operation', $operation, $doi, $post_id, $context );
	}

	/**
	 * Fire event after any DOI operation.
	 *
	 * @param string $operation The operation that was performed.
	 * @param string $doi The DOI identifier.
	 * @param int    $post_id The WordPress post ID.
	 * @param bool   $success Whether the operation was successful.
	 * @param array  $result The operation result data.
	 */
	public static function after_doi_operation( string $operation, string $doi, int $post_id, bool $success, array $result = array() ): void {
		do_action( 'ehri_after_doi_operation', $operation, $doi, $post_id, $success, $result );
	}

	/**
	 * Fire event when post version relationship is established.
	 *
	 * @param int $post_id The current post ID.
	 * @param int $other_post_id The ID of the post this replaces.
	 */
	public static function post_version_set( int $post_id, int $other_post_id ): void {
		do_action( 'ehri_post_version_set', $post_id, $other_post_id );
	}

	/**
	 * Fire event when post version relationship is removed.
	 *
	 * @param int      $post_id The post ID.
	 * @param int|null $other_post_id The ID of the post that was previously set as replaced.
	 */
	public static function post_version_removed( int $post_id, ?int $other_post_id ): void {
		do_action( 'ehri_post_version_removed', $post_id, $other_post_id );
	}

	/**
	 * Fire event when DOI API error occurs.
	 *
	 * @param string $operation The operation that failed.
	 * @param string $doi The DOI identifier.
	 * @param int    $post_id The WordPress post ID.
	 * @param string $error_message The error message.
	 * @param int    $http_code The HTTP response code.
	 */
	public static function doi_api_error( string $operation, string $doi, int $post_id, string $error_message, int $http_code = 0 ): void {
		do_action( 'ehri_doi_api_error', $operation, $doi, $post_id, $error_message, $http_code );
	}
}
