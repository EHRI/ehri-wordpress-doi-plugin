<?php
/**
 * EHRI DOI Metadata Helpers Test
 *
 * @package ehri-pid-tools
 */

// Exit if accessed directly.
use PHPUnit\Framework\TestCase;

const ABSPATH = __DIR__ . '/../';

require_once ABSPATH . 'vendor/autoload.php';
require_once ABSPATH . 'includes/class-ehri-doi-metadata-helpers.php';


// phpcs:ignoreWordPress.NamingConventions.ValidClassName.NotCamelCaps/**
/**
 * Test class for EHRI_DOI_Metadata_Helpers.
 */
class EHRI_DOI_Metadata_Helpers_Test extends TestCase {


	/**
	 * Test the comparison between two sets of DOI metadata.
	 *
	 * @return void
	 */
	public function test_compare_data() {
		$existing = array(
			'doi'                  => '10.1234/5678',
			'titles'               => array(
				array(
					'title' => 'Existing Title',
					'lang'  => 'en',
				),
			),
			'alternateIdentifiers' => array(
				array(
					'alternateIdentifier'     => 'Existing Identifier',
					'alternateIdentifierType' => 'URL',
				),
			),
		);
		$new      = array(
			'doi'                  => '10.1234/5678',
			'titles'               => array(
				array(
					'title' => 'Existing Title',
					'lang'  => 'en',
				),
			),
			'alternateIdentifiers' => array(
				array(
					'alternateIdentifier'     => 'New Identifier',
					'alternateIdentifierType' => 'URL',
				),
			),
		);

		$this->assertEquals(
			array( 'alternateIdentifiers' ),
			EHRI_DOI_Metadata_Helpers::changed_fields( $existing, $new ),
			'The data should be different due to the alternate identifier change.'
		);

		$new['alternateIdentifiers'][0]['alternateIdentifier'] = 'Existing Identifier';
		$this->assertEmpty(
			EHRI_DOI_Metadata_Helpers::changed_fields( $existing, $new ),
			'The data should be the same after changing the alternate identifier back.'
		);
	}

	/**
	 * Test extracting ARKs from ehri-item-data shortcodes in post content.
	 *
	 * @return void
	 */
	public function test_extract_item_data_arks() {
		$content = <<<'EOT'
<p>Some text [ehri-item-data id="ark:41045/p0ncv894nf94n"] and more.</p>
[ehri-item-data id='us-005578'][/ehri-item-data]
[ehri-item-data field="title" id=ark:41045/abc123]
[ehri-item-data id="ark:41045/p0ncv894nf94n" field="scope"]
[ehri-item-data-other id="ark:41045/ignored"]
[other id="ark:41045/ignored2"]
[ehri-item-data data-id="ark:41045/ignored3"]
EOT;

		$this->assertEquals(
			array( 'ark:41045/p0ncv894nf94n', 'ark:41045/abc123' ),
			EHRI_DOI_Metadata_Helpers::extract_item_data_arks( $content )
		);
		$this->assertEmpty( EHRI_DOI_Metadata_Helpers::extract_item_data_arks( 'No shortcodes here.' ) );
	}

	/**
	 * Test replacing the IDs of ehri-item-data shortcodes in post content.
	 *
	 * @return void
	 */
	public function test_replace_item_data_ids() {
		$content = <<<'EOT'
<p>[ehri-item-data id="us-005578"] and [ehri-item-data field="title" id='us-005578' lang="en"]</p>
[ehri-item-data id=de-002409]
[ehri-item-data id="ark:41045/p0existing"]
[ehri-item-data id="unknown"]
[ehri-item-data-other id="us-005578"]
[ehri-item-data data-id="us-005578"]
EOT;

		$expected = <<<'EOT'
<p>[ehri-item-data id="ark:41045/p0a"] and [ehri-item-data field="title" id='ark:41045/p0a' lang="en"]</p>
[ehri-item-data id=ark:41045/p0b]
[ehri-item-data id="ark:41045/p0existing"]
[ehri-item-data id="unknown"]
[ehri-item-data-other id="us-005578"]
[ehri-item-data data-id="us-005578"]
EOT;

		$map  = array(
			'us-005578' => 'ark:41045/p0a',
			'de-002409' => 'ark:41045/p0b',
		);
		$seen = array();

		$this->assertEquals(
			$expected,
			EHRI_DOI_Metadata_Helpers::replace_item_data_ids(
				$content,
				function ( $id ) use ( $map, &$seen ) {
					$seen[] = $id;
					return $map[ $id ] ?? $id;
				}
			)
		);
		$this->assertEquals(
			array( 'us-005578', 'us-005578', 'de-002409', 'ark:41045/p0existing', 'unknown' ),
			$seen,
			'Only ehri-item-data id attributes should be passed to the callback.'
		);
	}
}
