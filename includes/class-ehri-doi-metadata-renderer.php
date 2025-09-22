<?php
/**
 * EHRI DOI Metadata Renderer
 *
 * @package ehri-pid-tools
 */

/**
 * Renders the metadata for a DOI in a table format.
 */
class EHRI_DOI_Metadata_Renderer {
	/**
	 * The DOI, if it exists.
	 *
	 * @var string|null The DOI string.
	 */
	private ?string $doi;

	/**
	 * The DOI state.
	 *
	 * @var string The state of the DOI (e.g., 'draft', 'findable').
	 */
	private string $state;

	/**
	 * Keys that have current changes between the DOI and
	 * post metadata.
	 *
	 * @var array The array of changed metadata keys.
	 */
	private array $changed;

	/**
	 * The metadata array from the post.
	 *
	 * @var array The array of metadata.
	 */
	private array $metadata;

	/**
	 * Constructor.
	 *
	 * @param array       $metadata The array of metadata.
	 * @param string|null $doi The DOI string.
	 * @param string      $state The state of the DOI (e.g., 'draft', 'findable').
	 * @param array       $changed The array of changed fields.
	 */
	public function __construct( array $metadata, string $doi = null, string $state = 'draft', array $changed = array() ) {
		$this->doi      = $doi;
		$this->state    = $state;
		$this->changed  = $changed;
		$this->metadata = $metadata;
	}

	/**
	 * Renders the DOI metadata in a table format.
	 *
	 * @return string HTML string of the rendered metadata.
	 */
	public function render_doi_metadata(): string {
		if ( ! $this->metadata ) {
			return '<p>Invalid or empty metadata.</p>';
		}

		$html  = $this->render_doi();
		$html .= '<div class="doi-metadata">';
		$html .= $this->render_titles();
		$html .= $this->render_creators();
		$html .= $this->render_publishers();
		$html .= $this->render_publication_year();
		$html .= $this->render_language();
		$html .= $this->render_resource_type();
		$html .= $this->render_alternate_identifiers();
		$html .= $this->render_descriptions();
		$html .= $this->render_subjects();
		$html .= $this->render_related_identifiers();
		$html .= $this->render_related_items();
		$html .= $this->render_url();
		$html .= $this->render_version();
		$html .= '</div>';

		return $html;
	}

	/**
	 * Renders a section of the metadata table.
	 *
	 * @param string $key the key of the metadata section.
	 * @param string $title the title of the section.
	 * @param string $content the content of the section.
	 * @param string $description the description of the section.
	 * @return string HTML string of the rendered section.
	 */
	private function render_section( string $key, string $title, string $content, string $description ): string {
		return '<div class="doi-metadata-section doi-metadata-'
					. $key . ( in_array( $key, $this->changed, true ) ? ' changed' : '' ) . '">' .
				'<div>' . $title . '</div>' .
				'<div>' . $content . '</div>' .
				'<div>' . $description . '</div>' .
				'</div>';
	}

	/**
	 * Renders the DOI and its state.
	 *
	 * @return string HTML string of the rendered DOI.
	 */
	private function render_doi(): string {
		if ( empty( $this->doi ) ) {
			return '';
		}

		return sprintf(
			'<h2>%s
					<span class="doi-state-container doi-state-%s">%s</span></h2>',
			esc_html( htmlspecialchars( $this->doi ) ),
			esc_attr( $this->state ),
			esc_html( $this->state )
		);
	}

	/**
	 * Renders the titles of the post.
	 *
	 * @return string HTML string of the rendered titles.
	 */
	private function render_titles(): string {
		if ( $this->skip_field( $this->metadata, 'titles' ) ) {
			return '';
		}

		$titles = array();
		foreach ( $this->metadata['titles'] as $title ) {
			$title_text = htmlspecialchars( $title['title'] ?? '' );
			$title_lang = ! empty( $title['lang'] ) ? " <strong>[{$title['lang']}]</strong>" : '';
			$titles[]   = $title_text . $title_lang;
		}

		$content =
			'<ul>' .
			implode(
				'',
				array_map(
					function ( $t ) {
						return "<li>$t</li>";
					},
					$titles
				)
			) .
			'</ul>';

		return $this->render_section(
			'titles',
			esc_html__( 'Title(s)' ),
			$content,
			esc_html__( 'The title of the post.' )
		);
	}

	/**
	 * Renders the creators of the post.
	 *
	 * @return string HTML string of the rendered creators.
	 */
	private function render_creators(): string {
		if ( $this->skip_field( $this->metadata, 'creators' ) ) {
			return '';
		}

		$creators = array();
		foreach ( $this->metadata['creators'] as $creator ) {
			$creator_name     = htmlspecialchars( $creator['name'] ?? '' );
			$name_identifiers = $this->render_name_identifiers( $creator );
			$affiliation      = $this->render_affiliation( $creator );
			$creators[]       = $creator_name . $name_identifiers . $affiliation;
		}

		$content =
			'<ul>' .
			implode(
				'',
				array_map(
					function ( $c ) {
						return "<li>$c</li>";
					},
					$creators
				)
			) .
			'</ul>';

		return $this->render_section(
			'creators',
			esc_html__( 'Creator(s)', 'edmp' ),
			$content,
			esc_html__( 'The author(s) of the post.', 'edmp' )
		);
	}

	/**
	 * Renders the name identifiers of the creator.
	 *
	 * @param array $creator The creator array.
	 * @return string HTML string of the rendered name identifiers.
	 */
	private function render_name_identifiers( array $creator ): string {
		if ( empty( $creator['nameIdentifiers'] ) && ! in_array( 'nameIdentifiers', $this->changed, true ) ) {
			return '';
		}

		$identifiers = array_map(
			function ( $identifier ) {
				$scheme = htmlspecialchars( $identifier['nameIdentifierScheme'] ?? '' );
				$id     = htmlspecialchars( $identifier['nameIdentifier'] ?? '' );
				return "$scheme: $id";
			},
			$creator['nameIdentifiers']
		);

		return ' (' . implode( ', ', $identifiers ) . ')';
	}

	/**
	 * Renders the affiliation of the creator.
	 *
	 * @param array $creator The creator array.
	 * @return string HTML string of the rendered affiliation.
	 */
	private function render_affiliation( array $creator ): string {
		if ( empty( $creator['affiliation'] ) ) {
			return '';
		}

		$affiliations = array_map(
			function ( $aff ) {
				return htmlspecialchars( $aff['name'] ?? '' );
			},
			$creator['affiliation']
		);

		return ' [' . implode( ', ', $affiliations ) . ']';
	}

	/**
	 * Renders the publisher of the post.
	 *
	 * @return string HTML string of the rendered publisher.
	 */
	private function render_publishers(): string {
		$publisher_obj  = $this->metadata['publisher'];
		$publisher      = is_array( $publisher_obj ) ? ( $publisher_obj['name'] ?? '' ) : '';
		$publisher_ror  = is_array( $publisher_obj ) ? ( $publisher_obj['publisherIdentifier'] ?? '' ) : '';
		$full_publisher = empty( $publisher_ror ) ? $publisher : sprintf( '%s (%s)', $publisher, $publisher_ror );

		return $this->render_section(
			'publisher',
			esc_html__( 'Publisher' ),
			$full_publisher,
			esc_html__( 'The publisher of the post as set in the DOI plugin settings.', 'edmp' )
		);
	}

	/**
	 * Renders the publication year of the post.
	 *
	 * @return string HTML string of the rendered publication year.
	 */
	private function render_publication_year(): string {
		$year = $this->metadata['publicationYear'] ?? '';
		return $this->render_section(
			'publicationYear',
			esc_html__( 'Publication Year', 'edmp' ),
			$year,
			esc_html__( 'The year the post was published according to the WordPress dates.', 'edmp' )
		);
	}

	/**
	 * Renders the language code of the post.
	 *
	 * @return string HTML string of the rendered language code.
	 */
	private function render_version(): string {
		$version = $this->metadata['version'] ?? '';
		return $this->render_section(
			'version',
			esc_html__( 'Version', 'edmp' ),
			$version,
			esc_html__( 'The version of the post, according to its metadata.', 'edmp' )
		);
	}

	/**
	 * Renders the language code of the post.
	 *
	 * @return string HTML string of the rendered language code.
	 */
	private function render_language(): string {
		$year = $this->metadata['language'] ?? '';
		return $this->render_section(
			'language',
			esc_html__( 'Language', 'edmp' ),
			$year,
			esc_html__( 'The language code of this post.', 'edmp' )
		);
	}

	/**
	 * Renders the resource type of the post.
	 *
	 * @return string HTML string of the rendered resource type.
	 */
	private function render_resource_type(): string {
		if ( $this->skip_field( $this->metadata, 'types' ) ) {
			return '';
		}

		$type      = $this->metadata['types'];
		$type_info = array();

		if ( ! empty( $type['resourceTypeGeneral'] ) ) {
			$type_info[] = __( 'Type: ', 'edmp' ) . htmlspecialchars( $type['resourceTypeGeneral'] );
		}
		if ( ! empty( $type['resourceType'] ) ) {
			$type_info[] = __( 'Specific Type: ', 'edmp' ) . htmlspecialchars( $type['resourceType'] );
		}

		return $this->render_section(
			'resourceType',
			esc_html__( 'Resource Type', 'edmp' ),
			implode( ', ', $type_info ),
			esc_html__( 'The type of the post as set in the DOI plugin settings.', 'edmp' )
		);
	}

	/**
	 * Renders the alternate identifiers of the post.
	 *
	 * @return string HTML string of the rendered alternate identifiers.
	 */
	private function render_alternate_identifiers(): string {
		if ( $this->skip_field( $this->metadata, 'alternateIdentifiers' ) ) {
			return '';
		}

		$identifiers = array_map(
			function ( $identifier ) {
				$scheme = htmlspecialchars( $identifier['alternateIdentifierType'] ?? '' );
				$id     = htmlspecialchars( $identifier['alternateIdentifier'] ?? '' );
				return "$scheme: $id";
			},
			$this->metadata['alternateIdentifiers']
		);

		$content = '<ul>' .
			implode(
				'',
				array_map(
					function ( $i ) {
						return "<li>$i</li>";
					},
					$identifiers
				)
			) .
			'</ul>';

		return $this->render_section(
			'alternateIdentifiers',
			esc_html__( 'Alt. Identifier(s)', 'edmp' ),
			$content,
			esc_html__( 'The alternate identifier(s) of the post.', 'edmp' )
		);
	}

	/**
	 * Renders the descriptions of the post.
	 *
	 * @return string HTML string of the rendered descriptions.
	 */
	private function render_descriptions(): string {
		if ( $this->skip_field( $this->metadata, 'descriptions' ) ) {
			return '';
		}

		$descriptions = array_map(
			function ( $desc ) {
				$desc_text = htmlspecialchars( $desc['description'] ?? '' );
				$desc_type = ! empty( $desc['descriptionType'] ) ?
				' (Type: ' . htmlspecialchars( $desc['descriptionType'] ) . ')' : '';
				$desc_lang = ! empty( $desc['lang'] ) ? " <strong>[{$desc['lang']}]</strong>" : '';
				return $desc_text . $desc_type . $desc_lang;
			},
			$this->metadata['descriptions']
		);

		$content = '<ul>' .
			implode(
				'',
				array_map(
					function ( $d ) {
						return sprintf( '<li>%s</li>', $d );
					},
					$descriptions
				)
			) .
			'</ul>';

		return $this->render_section(
			'descriptions',
			esc_html__( 'Description(s)', 'edmp' ),
			$content,
			esc_html__( 'The description(s) of the post, taken from the post excerpt(s)', 'edmp' )
		);
	}

	/**
	 * Renders the subjects of the post.
	 *
	 * @return string HTML string of the rendered subjects.
	 */
	private function render_subjects(): string {
		if ( $this->skip_field( $this->metadata, 'subjects' ) ) {
			return '';
		}

		$subjects = array_map(
			function ( $subject ) {
				$subject_text   = htmlspecialchars( $subject['subject'] ?? '' );
				$subject_scheme = ! empty( $subject['subjectScheme'] ) ?
				' (Scheme: ' . htmlspecialchars( $subject['subjectScheme'] ) . ')' : '';
				return $subject_text . $subject_scheme;
			},
			$this->metadata['subjects']
		);

		$content = '<ul>' .
			implode(
				'',
				array_map(
					function ( $s ) {
						return "<li>$s</li>";
					},
					$subjects
				)
			) .
			'</ul>';

		return $this->render_section(
			'subjects',
			esc_html__( 'Subject(s)', 'edmp' ),
			$content,
			esc_html__( 'The subject(s) of the post.', 'edmp' )
		);
	}

	/**
	 * Renders the related identifiers of the post.
	 *
	 * @return string HTML string of the rendered related identifiers.
	 */
	private function render_related_identifiers(): string {
		if ( $this->skip_field( $this->metadata, 'relatedIdentifiers' ) ) {
			return '';
		}

		$related_identifiers = array_map(
			function ( $item ) {
				$item_type     = htmlspecialchars( $item['relatedIdentifierType'] ?? '' );
				$item_ident    = htmlspecialchars( $item['relatedIdentifier'] ?? '' );
				$relation_type = ! empty( $item['relationType'] ) ? '<strong>[' . htmlspecialchars( $item['relationType'] ) . ']</strong> ' : '';
				return "$relation_type $item_type: $item_ident";
			},
			$this->metadata['relatedIdentifiers']
		);

		$content = '<ul>' .
				implode(
					'',
					array_map(
						function ( $i ) {
							return "<li>$i</li>";
						},
						$related_identifiers
					)
				) .
				'</ul>';
		if ( empty( $related_identifiers ) ) {
			$content = '&lt;empty&gt;';
		}

		return $this->render_section(
			'relatedIdentifiers',
			esc_html__( 'Related Identifiers', 'edmp' ),
			$content,
			esc_html__( 'Identifiers of items related to the post, if any.', 'edmp' )
		);
	}

	/**
	 * Renders the related items of the post.
	 *
	 * @return string HTML string of the rendered related items.
	 */
	private function render_related_items(): string {
		if ( $this->skip_field( $this->metadata, 'relatedItems' ) ) {
			return '';
		}

		$related_items = array_map(
			function ( $item ) {
				$item_type     = htmlspecialchars( $item['relatedItemType'] ?? '' );
				$item_ident    = htmlspecialchars( $item['titles'][0]['title'] ?? '' );
				$relation_type = ! empty( $item['relationType'] ) ? '<strong>[' . htmlspecialchars( $item['relationType'] ) . ']</strong> ' : '';
				return "$relation_type $item_type: $item_ident";
			},
			$this->metadata['relatedItems']
		);

		$content = '<ul>' .
			implode(
				'',
				array_map(
					function ( $i ) {
						return "<li>$i</li>";
					},
					$related_items
				)
			) .
			'</ul>';
		if ( empty( $related_items ) ) {
			$content = '&lt;empty&gt;';
		}

		return $this->render_section(
			'relatedItems',
			esc_html__( 'Related Items', 'edmp' ),
			$content,
			esc_html__( 'Items related to the post, if any.', 'edmp' )
		);
	}

	/**
	 * Renders the URL of the post.
	 *
	 * @return string HTML string of the rendered URL.
	 */
	private function render_url(): string {
		if ( $this->skip_field( $this->metadata, 'url' ) ) {
			return '';
		}
		$url = htmlspecialchars( $this->metadata['url'] );
		return $this->render_section(
			'url',
			esc_html__( 'URL', 'edmp' ),
			sprintf( '<a href="%s" target="_blank">%s</a>', $url, $url ),
			esc_html__( 'The permanent URL of the post.', 'edmp' )
		);
	}

	/**
	 * Determine if we should skip rendering a field.
	 *
	 * @param array  $data The metadata array.
	 * @param string $key The key of the metadata field.
	 *
	 * @return bool True if the field should be skipped, false otherwise.
	 */
	private function skip_field( array $data, string $key ): bool {
		// Skip if the field is empty and not in the changed array.
		if ( empty( $data[ $key ] ) && ! in_array( $key, $this->changed, true ) ) {
			return true;
		}

		return false;
	}
}
