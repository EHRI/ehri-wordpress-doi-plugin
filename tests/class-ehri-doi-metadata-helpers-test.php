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
}
