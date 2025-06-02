<?php
/**
 * EHRI DOI Metadata Helpers
 *
 * @package ehri-pid-tools
 */

/**
 * Various helper functions for DOI metadata.
 */
class EHRI_DOI_Metadata_Helpers {
	/**
	 * Fetch titles for the post in all the languages for which
	 * it is available (via Polylang, if installed).
	 *
	 * @param int $post_id the post ID.
	 * @return array
	 */
	public function get_title_info( int $post_id ): array {
		$title = array(
			'title' => $this->clean_text( get_post( $post_id )->post_title ),
		);
		if ( function_exists( 'pll_get_post_language' ) ) {
			$title['lang'] = pll_get_post_language( $post_id );
		}
		return array( $title );
	}

	/**
	 * Get the description for the post in all the languages for which
	 * it is available (via Polylang, if installed).
	 *
	 * @param int $post_id the post ID.
	 * @return array
	 */
	public function get_description_info( int $post_id ): array {

		$desc_text = sanitize_text_field( get_post_meta( $post_id, 'doi_description', true ) );
		if ( ! $desc_text ) {
			$desc_text = $this->clean_text( get_the_excerpt( $post_id ) );
		}

		$desc = array(
			'description' => $desc_text,
		);
		if ( function_exists( 'pll_get_post_language' ) ) {
			$desc['lang'] = pll_get_post_language( $post_id );
		}
		return array( $desc );
	}

	/**
	 * Fetch the language code for the post. If using Polylang, this
	 * will be the Polylang language code. If not, this will be the
	 * WordPress language code.
	 *
	 * @param int $post_id the post ID.
	 * @return string the language code.
	 */
	public function get_language_code( int $post_id ): string {
		if ( function_exists( 'pll_get_post_language' ) ) {
			$lang = pll_get_post_language( $post_id );
			if ( $lang ) {
				return $lang;
			}
		}
		return substr( get_locale(), 0, 2 );
	}

	/**
	 * Get the version of this post. This is derived from
	 * other posts which have the metadata key '_previous_version_of'
	 * this post.
	 *
	 * @param int $post_id the post ID.
	 * @return int the version number.
	 */
	public function get_version_info( int $post_id ): int {
		$version = 1;
		// Check if the post has a previous version.
		$this_post = $post_id;
		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		while ( $previous = $this->get_previous_version( $this_post ) ) {
			$version++;
			$this_post = $previous;
		}
		return $version;
	}

	/**
	 * Fetch translations for the post in all the languages for which
	 * it is available (via Polylang, if installed).
	 *
	 * @param int $post_id the post ID.
	 * @return array
	 */
	public function get_related_translations( int $post_id ): array {
		// Fetch translations for the post, and return them as relatedItems.
		// Only translations which already have an existing published DOI
		// assigned will be included, and there needs to be reciprocal
		// links between the posts.
		$translations = array();
		if ( function_exists( 'pll_get_post_language' ) ) {
			foreach ( pll_get_post_translations( $post_id ) as $translation_id ) {
				$translation_doi = get_post_meta( $translation_id, '_doi', true );
				if ( $translation_id !== $post_id && $translation_doi ) {
					$translations[] = array(
						'relatedIdentifier'     => $translation_doi,
						'relatedIdentifierType' => 'DOI',
						'relationType'          => 'HasTranslation',
						'resourceTypeGeneral'   => 'Text',
					);
				}
			}
		}
		return $translations;
	}

	/**
	 * Fetch previous/new versions of the post. This is based
	 * NOT on post revisions, but on the post's metadata key
	 * '_previous_version_of' and '_new_version_of'.
	 *
	 * @param int $post_id the post ID.
	 */
	public function get_related_versions( int $post_id ): array {
		// Query posts which have the metadata key '_doi_previous_version_of' or '_doi_new_version_of'.
		$versions         = array();
		$previous_version = get_post_meta( $post_id, '_previous_version_of', true );
		if ( $previous_version ) {
			$previous_doi = get_post_meta( $previous_version, '_doi', true );
			if ( $previous_doi ) {
				$versions[] = array(
					'relatedIdentifier'     => $previous_doi,
					'relatedIdentifierType' => 'DOI',
					'relationType'          => 'IsPreviousVersionOf',
					'resourceTypeGeneral'   => 'Text',
				);
			}
		}
		// Run a meta query for posts where the _previous_version_of key is set to the current post ID.
		$previous_version = $this->get_previous_version( $post_id );
		if ( $previous_version ) {
			$previous_doi = get_post_meta( $previous_version, '_doi', true );
			if ( $previous_doi ) {
				$versions[] = array(
					'relatedIdentifier'     => $previous_doi,
					'relatedIdentifierType' => 'DOI',
					'relationType'          => 'IsNewVersionOf',
					'resourceTypeGeneral'   => 'Text',
				);
			}
		}
		return $versions;
	}

	/**
	 * Fetch related identifiers for the post.
	 *
	 * @param int $post_id the post ID.
	 * @return array
	 */
	public function get_related_identifiers( int $post_id ): array {
		return array_merge(
			$this->get_related_translations( $post_id ),
			$this->get_related_versions( $post_id ),
		);
	}

	/**
	 * Fetch the authors for the post.
	 *
	 * @param int $post_id the post ID.
	 * @return array
	 */
	public function get_author_info( int $post_id ): array {
		$authors = array();
		if ( function_exists( 'coauthors_posts_links' ) ) {
			$coauthors = get_coauthors( $post_id );
			foreach ( $coauthors as $coauthor ) {
				$orcid       = get_the_author_meta( 'orcid', $coauthor->ID );
				$author_data = array(
					'givenName'       => $coauthor->first_name,
					'familyName'      => $coauthor->last_name,
					'name'            => $coauthor->display_name,
					'nameIdentifiers' => array(),
					'affiliation'     => array(),
				);
				if ( $orcid ) {
					$author_data['nameIdentifiers'] = array(
						array(
							'nameIdentifier'       => $orcid,
							'nameIdentifierScheme' => 'ORCID',
						),
					);
				}
				$authors[] = $author_data;
			}
		} else {
			$author = get_the_author_meta( 'display_name', $post_id );
			// Hacky way to split first and last name.
			$parts       = explode( ' ', $author, 2 );
			$orcid       = get_the_author_meta( 'orcid', $post_id );
			$author_data = array(
				'givenName'       => $parts[0],
				'familyName'      => $parts[1],
				'name'            => $author,
				'nameIdentifiers' => array(),
			);
			if ( $orcid ) {
				$author_data['nameIdentifiers'] = array(
					array(
						'nameIdentifier'       => $orcid,
						'nameIdentifierScheme' => 'ORCID',
					),
				);
			}

			$authors[] = $author_data;
		}
		return $authors;
	}

	/**
	 * Returns the publication year of the post if it is published.
	 * Otherwise returns the current year.
	 *
	 * @param int $post_id the post ID.
	 * @return int the publication year.
	 */
	public function get_publication_year( int $post_id ): int {
		$post = get_post( $post_id );
		if ( 'publish' === $post->post_status ) {
			return (int) get_the_date( 'Y', $post );
		} else {
			return (int) gmdate( 'Y' );
		}
	}

	/**
	 * Returns an array of date objects for
	 * publication and modification.
	 *
	 * @param int $post_id the post ID.
	 * @return array
	 */
	public function get_date_info( int $post_id ): array {
		$post = get_post( $post_id );

		$dates = array();
		if ( 'publish' === $post->post_status ) {
			$pub = get_the_date( 'Y-m-d', $post );
			if ( $pub ) {
				$dates[] = array(
					'date'     => $pub,
					'dateType' => 'Created',
				);
			}
			$mod = get_the_modified_date( 'Y-m-d', $post );
			if ( $mod ) {
				$dates[] = array(
					'date'     => $mod,
					'dateType' => 'Updated',
				);
			}
		}

		return $dates;
	}

	/**
	 * Fetch relevant alternative identifiers for the post.
	 *
	 * @param int $post_id int the post ID.
	 * @return array
	 */
	public function get_alternative_identifier_info( int $post_id ): array {
		$post = get_post( $post_id );

		$alts = array(
			array(
				'alternateIdentifier'     => (string) $post->ID,
				'alternateIdentifierType' => 'Post ID',
			),
		);
		$slug = $post->post_name;
		if ( $slug ) {
			$alts[] = array(
				'alternateIdentifier'     => $slug,
				'alternateIdentifierType' => 'Slug',
			);
		}
		return $alts;
	}

	/**
	 * Check if the existing metadata is the same as the new metadata.
	 *
	 * @param array $existing the existing metadata.
	 * @param array $new the new metadata.
	 * @return array of changed fields
	 */
	public static function changed_fields( array $existing, array $new ): array {

		// HACK: we're comparing our WordPress derived data with that
		// returned from the DataCite API. In most cases the data we
		// give the API comes back unchanged, but in some cases they
		// augment it with additional values. One of these cases is
		// the `types` field, which comes back with definitions for
		// `res`, `bibtex`, and `schemaOrg` types. I don't really know
		// why they do this, but it means that we can't do a strict
		// comparison of the two arrays, as the existing metadata.
		$augmented_keys = array(
			'types',
		);

		$changed = array();
		// Compare the existing and new metadata.
		foreach ( $new as $key => $value ) {
			if ( ! isset( $existing[ $key ] ) ) {
				$changed[] = $key;
				continue;
			}

			// Check if we have an array that is a different length from that
			// in the existing metadata.
			if ( is_array( $existing[ $key ] ) && is_array( $value ) && count( $existing[ $key ] ) !== count( $value )
				&& ! in_array( $key, $augmented_keys, true ) ) {
				$changed[] = $key;
				continue;
			}

			if ( is_array( $value ) ) {
				if ( ! empty( self::changed_fields( $existing[ $key ], $value ) ) ) {
					$changed[] = $key;
				}
			} else {
				// If the value is not an array, compare it directly using non-strict comparison.
				// This allows for type juggling, e.g. comparing '1' and 1.
				// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
				if ( $existing[ $key ] != $value ) {
					$changed[] = $key;
				}
			}
		}

		return $changed;
	}

	/**
	 * Reformat a date string.
	 *
	 * @param string $iso_date A date string in ISO 8601 format.
	 * @param string $format  The format to use for the output date string.
	 *
	 * @return string The formatted date string.
	 * @throws DateMalformedStringException If the date string is malformed.
	 */
	public static function format_iso_date( string $iso_date, string $format = 'F j, Y' ): string {
		$date_time = new DateTime( $iso_date );
		return $date_time->format( $format );
	}

	/**
	 * Clean the text by removing HTML tags and decoding HTML entities.
	 *
	 * @param string $text the text to clean.
	 * @return string the cleaned text.
	 */
	private function clean_text( string $text ): string {
		$filtered = wp_filter_nohtml_kses( $text );
		return html_entity_decode( $filtered, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Find if another post exists with a _previous_version_of meta key
	 * pointing to this one.
	 *
	 * @param int $post_id the post ID.
	 * @return int|false the ID of the post if found, false otherwise.
	 */
	private function get_previous_version( int $post_id ) {
		// Run a meta query for posts where the _previous_version_of key is set to the current post ID.
		$found = false;
		$args  = array(
			'post_type'      => 'post',
			'posts_per_page' => -1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => '_previous_version_of',
					'value' => $post_id,
				),
			),
		);

		$versions_query = new WP_Query( $args );
		if ( $versions_query->have_posts() ) {
			while ( $versions_query->have_posts() ) {
				$versions_query->the_post();
				$this_id = get_the_ID();
				if ( $this_id === $post_id ) {
					continue; // Skip the current post.
				}
				$found = $this_id;
			}
			wp_reset_postdata();
		}
		return $found;
	}
}
