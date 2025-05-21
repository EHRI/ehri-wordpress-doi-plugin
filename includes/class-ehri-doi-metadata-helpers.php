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
		$desc = array(
			'description' => $this->clean_text( get_the_excerpt( $post_id ) ),
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
	 * Fetch translations for the post in all the languages for which
	 * it is available (via Polylang, if installed).
	 *
	 * @param int $post_id the post ID.
	 * @return array
	 */
	public function get_translations( int $post_id ): array {
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
		$changed = array();
		// Compare the existing and new metadata.
		foreach ( $new as $key => $value ) {
			if ( ! isset( $existing[ $key ] ) ) {
				$changed[] = $key;
			}
			if ( is_array( $value ) ) {
				if ( ! empty( self::changed_fields( $existing[ $key ] ?? array(), $value ) ) ) {
					$changed[] = $key;
				}
			} else {
				if ( $existing[ $key ] !== $value ) {
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
}
