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
	 * The shortcode provided by the EHRI Portal Shortcode Plugin
	 * for embedding portal item data.
	 *
	 * @var string
	 */
	const ITEM_DATA_SHORTCODE = 'ehri-item-data';

	/**
	 * Matches an `ehri-item-data` opening tag, capturing its attributes.
	 *
	 * @var string
	 */
	private const ITEM_DATA_PATTERN = '/\[' . self::ITEM_DATA_SHORTCODE . '(?=[\s\]\/])([^\]]*)\]/';

	/**
	 * Matches a double-quoted, single-quoted or unquoted `id` attribute.
	 *
	 * @var string
	 */
	private const ID_ATTR_PATTERN = '/(?:^|\s)id\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'\]]+))/';

	/**
	 * Admin panel data helpers.
	 *
	 * @var EHRI_DOI_Metadata_Admin
	 */
	private EHRI_DOI_Metadata_Admin $admin;

	/**
	 * Constructor.
	 *
	 * @param EHRI_DOI_Metadata_Admin $admin the admin instance.
	 */
	public function __construct( EHRI_DOI_Metadata_Admin $admin ) {
		// Initialize admin panel.
		$this->admin = $admin;
	}

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
	 * Get the description for the post from the `doi_description`
	 * meta field, if it is populated.
	 *
	 * @param int $post_id the post ID.
	 * @return array
	 */
	public function get_description_info( int $post_id ): array {

		$descriptions = array();
		$desc_text    = sanitize_text_field( get_post_meta( $post_id, 'doi_description', true ) );
		if ( $desc_text ) {
			$descriptions[] = array(
				'description' => $desc_text,
			);
		}
		return $descriptions;
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
		// Guard against circular '_previous_version_of' references, which would
		// otherwise cause this loop to run forever.
		$seen = array( $post_id => true );
		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		while ( $previous = $this->get_previous_version( $this_post ) ) {
			if ( isset( $seen[ $previous ] ) ) {
				break;
			}
			$seen[ $previous ] = true;
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
				$translation_doi = get_post_meta( $translation_id, EHRI_DOI_META_KEY, true );
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
	 * '_previous_version_of'.
	 *
	 * @param int $post_id the post ID.
	 */
	public function get_related_versions( int $post_id ): array {
		// Query posts which have the metadata key '_doi_previous_version_of' or '_doi_new_version_of'.
		$versions         = array();
		$previous_version = get_post_meta( $post_id, EHRI_DOI_PREVIOUS_VERSION_META_KEY, true );
		if ( $previous_version ) {
			$previous_doi = get_post_meta( $previous_version, EHRI_DOI_META_KEY, true );
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
			$previous_doi = get_post_meta( $previous_version, EHRI_DOI_META_KEY, true );
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
	 * Fetch related resource URLs.
	 *
	 * @param int $post_id the post ID.
	 * @return array array of related type URLs
	 */
	public function get_related_urls( int $post_id ): array {
		return array(
			// TODO.
		);
	}

	/**
	 * Fetch ARKs of EHRI portal items referenced in the post content via
	 * the `ehri-item-data` shortcode. This only applies if the EHRI Portal
	 * Shortcode Plugin is active (i.e. the shortcode is registered.)
	 *
	 * @param int $post_id the post ID.
	 * @return array an array of related identifier arrays.
	 */
	public function get_related_arks( int $post_id ): array {
		if ( ! shortcode_exists( self::ITEM_DATA_SHORTCODE ) ) {
			return array();
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}
		return array_map(
			function ( $ark ) {
				return array(
					'relatedIdentifier'     => $ark,
					'relatedIdentifierType' => 'ARK',
					'relationType'          => 'References',
				);
			},
			self::extract_item_data_arks( $post->post_content )
		);
	}

	/**
	 * Extract unique ARK identifiers from the `id` attribute of
	 * `ehri-item-data` shortcodes in the given content. IDs which
	 * are not ARKs (i.e. do not start with `ark:`) are ignored.
	 *
	 * @param string $content the post content.
	 * @return array an array of ARK strings.
	 */
	public static function extract_item_data_arks( string $content ): array {
		$arks = array();
		self::replace_item_data_ids(
			$content,
			function ( string $id ) use ( &$arks ): string {
				if ( 0 === strpos( $id, 'ark:' ) && ! in_array( $id, $arks, true ) ) {
					$arks[] = $id;
				}
				return $id;
			}
		);
		return $arks;
	}

	/**
	 * Replace the `id` attribute of each `ehri-item-data` shortcode in the
	 * given content with the result of calling `$replace` on it. Quoting and
	 * other attributes are preserved.
	 *
	 * @param string   $content the post content.
	 * @param callable $replace given an item ID, returns its replacement.
	 * @return string the updated content.
	 */
	public static function replace_item_data_ids( string $content, callable $replace ): string {
		return preg_replace_callback(
			self::ITEM_DATA_PATTERN,
			function ( array $tag ) use ( $replace ): string {
				$atts = $tag[1];
				if ( ! preg_match( self::ID_ATTR_PATTERN, $atts, $m, PREG_OFFSET_CAPTURE ) ) {
					return $tag[0];
				}
				// The value is in whichever quoted/unquoted group matched.
				foreach ( array_slice( $m, 1 ) as list( $value, $offset ) ) {
					if ( -1 !== $offset ) {
						break;
					}
				}
				$id  = trim( $value );
				$new = $replace( $id );
				if ( $new === $id ) {
					return $tag[0];
				}
				return '[' . self::ITEM_DATA_SHORTCODE . substr_replace( $atts, $new, $offset, strlen( $value ) ) . ']';
			},
			$content
		);
	}

	/**
	 * Fetch related identifiers for the post.
	 *
	 * @param int $post_id the post ID.
	 * @return array an array of related identifier arrays.
	 */
	public function get_related_identifiers( int $post_id ): array {
		return array_merge(
			$this->get_related_translations( $post_id ),
			$this->get_related_versions( $post_id ),
			$this->get_related_urls( $post_id ),
			$this->get_related_arks( $post_id ),
		);
	}

	/**
	 * Fetch related items for the post.
	 *
	 * @param int $post_id the post ID.
	 * @return array an array of related item arrays.
	 */
	public function get_related_items( int $post_id ): array {
		return array(
			array(
				'titles'                => array(
					array(
						'title' => $this->admin->get_options()['publication_name'],
					),
				),
				'relatedItemIdentifier' => array(
					'relatedItemIdentifier'     => get_site_url(),
					'relatedItemIdentifierType' => 'URL',
				),
				'relatedItemType'       => 'Other',
				'relationType'          => 'IsPublishedIn',
			),
		);
	}

	/**
	 * Fetch subjects for the post from its tags. For the moment
	 * this is just free text. Ideally, it would contain links to
	 * a controlled vocabulary, but this is not yet implemented.
	 *
	 * @param int $post_id the post ID.
	 * @return array
	 */
	public function get_subject_info( int $post_id ): array {
		$subjects = array();
		$tags     = get_the_tags( $post_id );
		if ( $tags ) {
			foreach ( $tags as $tag ) {
				$subjects[] = array(
					'subject' => $this->clean_text( $tag->name ),
				);
			}
		}
		return $subjects;
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
			$post_author_id = get_post( $post_id )->post_author;
			$author         = get_the_author_meta( 'display_name', $post_author_id );
			// Hacky way to split first and last name.
			$parts       = explode( ' ', $author, 2 );
			$orcid       = get_the_author_meta( 'orcid', $post_author_id );
			$author_data = array(
				'givenName'       => $parts[0],
				'familyName'      => $parts[1] ?? '',
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
	 * Returns information about the current publisher.
	 *
	 * @return array|string an array of publisher information, or a simple string.
	 */
	public function get_publisher() {
		$publisher_name = $this->admin->get_options()['publisher'];
		$publisher_ror  = $this->admin->get_options()['publisher_ror'];

		if ( ! empty( $publisher_ror ) ) {
			return array(
				'name'                      => $publisher_name,
				'publisherIdentifier'       => $publisher_ror,
				'publisherIdentifierScheme' => 'ROR',
				'schemeUri'                 => 'https://ror.org/',
				'lang'                      => 'en',
			);
		} else {
			return $publisher_name;
		}
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
		if ( self::is_post_published( $post ) ) {
			return (int) get_the_date( 'Y', $post );
		} else {
			return (int) gmdate( 'Y' );
		}
	}

	/**
	 * Determine whether the given post is published.
	 *
	 * @param WP_Post|null $post the post object, or null if it could not be found.
	 * @return bool
	 */
	public static function is_post_published( ?WP_Post $post ): bool {
		return $post instanceof WP_Post && 'publish' === $post->post_status;
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
		if ( self::is_post_published( $post ) ) {
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
				continue;
			}

			// Check if we have an array that is a different length from that
			// in the existing metadata.
			if ( is_array( $existing[ $key ] ) && is_array( $value ) && count( $existing[ $key ] ) !== count( $value ) ) {
				$changed[] = $key;
				continue;
			}

			if ( is_array( $value ) ) {
				if ( ! is_array( $existing[ $key ] ) || ! empty( self::changed_fields( $existing[ $key ], $value ) ) ) {
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
		return wp_unslash( html_entity_decode( $filtered, ENT_QUOTES, 'UTF-8' ) );
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
					'key'   => EHRI_DOI_PREVIOUS_VERSION_META_KEY,
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
