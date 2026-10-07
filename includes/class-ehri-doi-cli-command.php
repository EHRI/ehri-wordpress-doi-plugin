<?php
/**
 * WP-CLI commands for the EHRI DOI Metadata plugin.
 *
 * @package ehri-pid-tools
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage post DOIs via WP-CLI.
 */
class EHRI_DOI_CLI_Command {
	/**
	 * The DOI metadata manager.
	 *
	 * @var EHRI_DOI_Metadata_Manager
	 */
	private EHRI_DOI_Metadata_Manager $manager;

	/**
	 * Constructor.
	 *
	 * @param EHRI_DOI_Metadata_Manager $manager the DOI metadata manager.
	 */
	public function __construct( EHRI_DOI_Metadata_Manager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Create or update the DOI for a single post.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The ID of the post to create or update a DOI for.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ehri-doi update 42
	 *
	 * @param array $args The positional arguments.
	 *
	 * @return void
	 */
	public function update( array $args ): void {
		$post_id = (int) $args[0];
		if ( ! get_post( $post_id ) ) {
			WP_CLI::error( sprintf( 'No post found with ID %d.', $post_id ) );
		}

		try {
			$result = $this->manager->create_or_update_doi( $post_id );
			WP_CLI::success( sprintf( 'Post %d: DOI %s (%s).', $post_id, $result['doi'], $result['state'] ) );
		} catch ( EHRI_DOI_Repository_Exception $e ) {
			WP_CLI::error( sprintf( 'Post %d: %s', $post_id, $e->getMessage() ) );
		}
	}

	/**
	 * Show DOI info for a single post, including whether its DataCite
	 * metadata is out of date relative to the post.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The ID of the post to show DOI info for.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp ehri-doi info 42
	 *
	 * @param array $args The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function info( array $args, array $assoc_args ): void {
		$post_id = (int) $args[0];
		if ( ! get_post( $post_id ) ) {
			WP_CLI::error( sprintf( 'No post found with ID %d.', $post_id ) );
		}

		try {
			$info = $this->manager->get_doi_info( $post_id );
		} catch ( EHRI_DOI_Repository_Exception $e ) {
			WP_CLI::error( sprintf( 'Post %d: %s', $post_id, $e->getMessage() ) );
		}

		if ( ! $info['doi'] ) {
			WP_CLI::log( sprintf( 'Post %d has no registered DOI.', $post_id ) );
			return;
		}

		$up_to_date = empty( $info['changed_fields'] );

		$rows = array(
			array(
				'Field' => 'Post ID',
				'Value' => $post_id,
			),
			array(
				'Field' => 'DOI',
				'Value' => $info['doi'],
			),
			array(
				'Field' => 'State',
				'Value' => $info['state'],
			),
			array(
				'Field' => 'Tombstoned',
				'Value' => $info['tombstone'] ? 'yes' : 'no',
			),
			array(
				'Field' => 'Up to date',
				'Value' => $up_to_date ? 'yes' : 'no',
			),
		);
		if ( ! $up_to_date ) {
			$rows[] = array(
				'Field' => 'Stale fields',
				'Value' => implode( ', ', $info['changed_fields'] ),
			);
		}

		\WP_CLI\Utils\format_items( \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' ), $rows, array( 'Field', 'Value' ) );

		if ( ! $up_to_date ) {
			WP_CLI::warning( sprintf( 'DOI metadata is out of date. Run `wp ehri-doi update %d` to refresh it.', $post_id ) );
		}
	}

	/**
	 * Update metadata for every post that already has a registered DOI.
	 * Posts without a DOI are skipped; this command never creates new DOIs.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Update every DOI, including those whose metadata is already up to date.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ehri-doi update-all
	 *     wp ehri-doi update-all --force
	 *
	 * @subcommand update-all
	 *
	 * @param array $args The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function update_all( array $args, array $assoc_args ): void {
		$force = \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );

		$post_ids = $this->get_posts_with_doi();
		if ( ! $post_ids ) {
			WP_CLI::log( 'No posts with a registered DOI found.' );
			return;
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Updating DOIs', count( $post_ids ) );
		$failures = array();
		$updated  = 0;

		foreach ( $post_ids as $i => $post_id ) {
			try {
				if ( $this->manager->create_or_update_doi( $post_id, ! $force )['updated'] ) {
					$updated++;
				}
			} catch ( EHRI_DOI_Repository_Exception $e ) {
				$failures[ $post_id ] = $e->getMessage();
			}
			$progress->tick();

			// Bound memory growth on long runs.
			if ( 0 === $i % 50 ) {
				wp_cache_flush();
			}
		}
		$progress->finish();

		$up_to_date = count( $post_ids ) - count( $failures ) - $updated;
		WP_CLI::log( sprintf( 'Updated %d of %d DOIs (%d already up to date).', $updated, count( $post_ids ), $up_to_date ) );
		foreach ( $failures as $post_id => $message ) {
			WP_CLI::warning( sprintf( 'Post %d: %s', $post_id, $message ) );
		}

		if ( $failures ) {
			WP_CLI::error( sprintf( '%d DOI update(s) failed.', count( $failures ) ), false );
		}
	}

	/**
	 * List posts that have a registered DOI.
	 *
	 * ## OPTIONS
	 *
	 * [--check]
	 * : Also check each DOI's DataCite metadata against the post and report
	 * whether it needs updating. This makes one DataCite API call per post.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp ehri-doi list
	 *     wp ehri-doi list --check
	 *
	 * @subcommand list
	 *
	 * @param array $args The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function list_posts( array $args, array $assoc_args ): void {
		$check  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'check', false );
		$fields = array( 'ID', 'doi', 'doi_state' );
		if ( $check ) {
			$fields = array_merge( $fields, array( 'up_to_date', 'stale_fields' ) );
		}

		$rows = array();
		foreach ( $this->get_posts_with_doi() as $post_id ) {
			$row = array(
				'ID'        => $post_id,
				'doi'       => get_post_meta( $post_id, EHRI_DOI_META_KEY, true ),
				'doi_state' => get_post_meta( $post_id, EHRI_DOI_STATE_META_KEY, true ),
			);
			if ( $check ) {
				try {
					$changed             = $this->manager->get_doi_info( $post_id )['changed_fields'];
					$row['up_to_date']   = empty( $changed ) ? 'yes' : 'no';
					$row['stale_fields'] = implode( ', ', $changed );
				} catch ( EHRI_DOI_Repository_Exception $e ) {
					$row['up_to_date']   = 'error';
					$row['stale_fields'] = $e->getMessage();
				}
			}
			$rows[] = $row;
		}

		\WP_CLI\Utils\format_items( \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' ), $rows, $fields );
	}

	/**
	 * Get the IDs of all posts (any type/status) that have a registered DOI.
	 *
	 * @return int[]
	 */
	private function get_posts_with_doi(): array {
		$query = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'     => EHRI_DOI_META_KEY,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return $query->posts;
	}
}
