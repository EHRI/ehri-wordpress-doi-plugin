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

		$html  = '<table class="doi-metadata">';
		$html .= $this->render_doi();
		$html .= $this->render_titles();
		$html .= $this->render_creators();
		$html .= $this->render_publishers();
		$html .= $this->render_publication_year();
		$html .= $this->render_language();
		$html .= $this->render_resource_type();
		$html .= $this->render_alternate_identifiers();
		$html .= $this->render_descriptions();
		$html .= $this->render_subjects();
		$html .= $this->render_references();
		$html .= $this->render_related();
		$html .= $this->render_url();
		$html .= $this->render_version();
		$html .= '</table>';

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
		return '<tr class="doi-metadata-section doi-metadata-'
					. $key . ( in_array( $key, $this->changed, true ) ? ' changed' : '' ) . '">' .
				'<td>' . $title . '</td>' .
				'<td>' . $content . '</td>' .
				'<td>' . $description . '</td>' .
				'</tr>';
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
		if ( empty( $this->metadata['titles'] ) ) {
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
		if ( empty( $this->metadata['creators'] ) ) {
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
		if ( empty( $creator['nameIdentifiers'] ) ) {
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
		$publisher_obj = $this->metadata['publisher']['name'] ?? '';
		$publisher     = empty( $publisher_obj ) ? ( $this->metadata['publisher'] ?? '' ) : '';
		return $this->render_section(
			'publisher',
			esc_html__( 'Publisher' ),
			$publisher,
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
		$year = $this->metadata['version'] ?? '';
		return $this->render_section(
			'version',
			esc_html__( 'Version', 'edmp' ),
			$year,
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
		if ( empty( $this->metadata['types'] ) ) {
			return '';
		}

		$type      = $this->metadata['types'];
		$type_info = array();

		if ( ! empty( $type['resourceTypeGeneral'] ) ) {
			$type_info[] = 'Type: ' . htmlspecialchars( $type['resourceTypeGeneral'] );
		}
		if ( ! empty( $type['resourceType'] ) ) {
			$type_info[] = 'Specific Type: ' . htmlspecialchars( $type['resourceType'] );
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
		if ( empty( $this->metadata['alternateIdentifiers'] ) ) {
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
		if ( empty( $this->metadata['descriptions'] ) ) {
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
		if ( empty( $this->metadata['subjects'] ) ) {
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
	 * Renders the funding references of the post.
	 *
	 * @return string HTML string of the rendered funding references.
	 */
	private function render_references(): string {
		if ( empty( $this->metadata['fundingReferences'] ) ) {
			return '';
		}

		$funding_awards = array_map(
			function ( $funding ) {
				$funder_name  = htmlspecialchars( $funding['funderName'] ?? '' );
				$award_title  = ! empty( $funding['awardTitle'] ) ?
				' - Award: ' . htmlspecialchars( $funding['awardTitle'] ) : '';
				$award_number = ! empty( $funding['awardNumber'] ) ?
				' (Number: ' . htmlspecialchars( $funding['awardNumber'] ) . ')' : '';

				return $funder_name . $award_title . $award_number;
			},
			$this->metadata['fundingReferences']
		);

		$content = '<ul>' .
			implode(
				'',
				array_map(
					function ( $f ) {
						return "<li>$f</li>";
					},
					$funding_awards
				)
			) .
			'</ul>';

		return $this->render_section(
			'fundingReferences',
			esc_html__( 'Funding Reference(s)', 'edmp' ),
			$content,
			esc_html__( 'The funding reference(s) of the post, if any.', 'edmp' )
		);
	}

	/**
	 * Renders the related identifiers of the post.
	 *
	 * @return string HTML string of the rendered related identifiers.
	 */
	private function render_related(): string {
		if ( empty( $this->metadata['relatedIdentifiers'] ) ) {
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

		return $this->render_section(
			'relatedIdentifiers',
			esc_html__( 'Related Identifiers', 'edmp' ),
			$content,
			esc_html__( 'Identifiers of items related to the post, if any.', 'edmp' )
		);
	}

	/**
	 * Renders the URL of the post.
	 *
	 * @return string HTML string of the rendered URL.
	 */
	private function render_url(): string {
		if ( empty( $this->metadata['url'] ) ) {
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
}
