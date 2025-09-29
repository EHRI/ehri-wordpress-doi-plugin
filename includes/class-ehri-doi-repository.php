<?php
/**
 * DOI Repository
 *
 * This class provides a wrapper around the EHRI PID Tools DOI service,
 * which is in turn a wrapper around the DataCite API.
 *
 * @package ehri-pid-tools
 */

/**
 * Include the EHRI_DOI_Repository_Exception class.
 */
require_once __DIR__ . '/class-ehri-doi-repository-exception.php';

/**
 * Class EHRI_DOI_Repository
 *
 * This class provides a wrapper around the EHRI PID Tools DOI service,
 * which is in turn a wrapper around the DataCite API.
 */
class EHRI_DOI_Repository {

	/**
	 * The URL of the DataCite API /dois endpoint.
	 *
	 * @var string
	 */
	private string $service_url;

	/**
	 * The client ID for the DataCite API.
	 *
	 * @var string
	 */
	private string $client_id;

	/**
	 * The client secret for the DataCite API.
	 *
	 * @var string
	 */
	private string $client_secret;

	/**
	 * EHRI_DOI_Repository constructor.
	 *
	 * @param string $service_url the URL of the DataCite API /dois endpoint.
	 * @param string $client_id the client ID for the DataCite API.
	 * @param string $client_secret the client secret for the DataCite API.
	 */
	public function __construct( string $service_url, string $client_id, string $client_secret ) {
		$this->service_url   = $service_url;
		$this->client_id     = $client_id;
		$this->client_secret = $client_secret;
	}

	/**
	 * Fetch a DOI from the DataCite API.
	 *
	 * @param string $doi the DOI to fetch, starting with the prefix.
	 *
	 * @return array the DOI metadata as supplied by the DataCite API.
	 * @throws EHRI_DOI_Repository_Exception If there is an error querying the API.
	 */
	public function get_doi_metadata( string $doi ): array {
		$api_response = wp_remote_get(
			$this->service_url . '/' . $doi,
			array(
				'headers' => array(
					'Accept'        => 'application/vnd.api+json',
					'Authorization' => 'Basic ' . $this->get_basic_auth(),
				),
			)
		);

		$this->check_auth( $api_response, $doi );
		// We're normally expecting a 200 response, but we might get 410 if the
		// item exists but has been removed. In that case we *should* still receive
		// a valid response body.
		$code = wp_remote_retrieve_response_code( $api_response );
		if ( is_wp_error( $api_response ) || ( 200 !== $code && 410 !== $code ) ) {
			if ( is_wp_error( $api_response ) && ( 'http_request_failed' === $api_response->get_error_code() ) ) {
				throw new EHRI_DOI_Repository_Exception(
					sprintf( 'Unable to fetch DOI metadata: %s. Please check the URL is correct.', $api_response->get_error_message() ),
					500,
					null,
					$doi
				);
			} else {
				$error = wp_remote_retrieve_body( $api_response );
				throw new EHRI_DOI_Repository_Exception(
					sprintf( 'Unable to fetch DOI metadata: [%s] %s.', $code, $error ),
					$code,
					null,
					$doi
				);
			}
		}
		$response_body = wp_remote_retrieve_body( $api_response );
		$doi_metadata  = json_decode( $response_body, true );
		if ( empty( $doi_metadata ) ) {
			throw new EHRI_DOI_Repository_Exception( 'Unable to decode DOI metadata.', 400, null, $doi );
		}

		return $doi_metadata;
	}

	/**
	 * Create a DOI in the DataCite API. This will create a DOI in a draft state.
	 *
	 * @param array $metadata the (partial) metadata for the DOI.
	 *
	 * @return array the full DOI metadata as supplied by the DataCite API
	 * @throws EHRI_DOI_Repository_Exception If there is an error creating the DOI.
	 */
	public function create_doi( array $metadata ): array {
		$api_response = wp_remote_post(
			$this->service_url,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Authorization' => 'Basic ' . $this->get_basic_auth(),
					'Accept'        => 'application/vnd.api+json',
					'Content-Type'  => 'application/vnd.api+json',
				),
				'body'    => wp_json_encode( $metadata ),
			)
		);
		$this->check_auth( $api_response );
		// Ensure we get a 201 response.
		$code = wp_remote_retrieve_response_code( $api_response );
		if ( is_wp_error( $api_response ) || 201 !== $code ) {
			$error = wp_remote_retrieve_body( $api_response );
			throw new EHRI_DOI_Repository_Exception( sprintf( 'Error creating DOI [%s]: %s', $code, $error ), $code, null, '' );
		}
		$response_body = wp_remote_retrieve_body( $api_response );

		return json_decode( $response_body, true );
	}

	/**
	 * Update a DOI in the DataCite API. This will update the metadata for the DOI.
	 *
	 * @param string $doi the DOI to update, starting with the prefix.
	 * @param array  $metadata the metadata to update.
	 *
	 * @return array the full DOI metadata as supplied by the DataCite API
	 * @throws EHRI_DOI_Repository_Exception If there is an error updating the DOI.
	 */
	public function update_doi( string $doi, array $metadata ): array {
		$api_response = wp_remote_request(
			$this->service_url . '/' . $doi,
			array(
				'method'  => 'PUT',
				'headers' => array(
					'Authorization' => 'Basic ' . $this->get_basic_auth(),
					'Accept'        => 'application/vnd.api+json',
					'Content-Type'  => 'application/vnd.api+json',
				),
				'body'    => wp_json_encode( $metadata ),
			)
		);
		$this->check_auth( $api_response, $doi );
		// Ensure we get a 200 response.
		$code = wp_remote_retrieve_response_code( $api_response );
		if ( is_wp_error( $api_response ) || 200 !== $code ) {
			$error = wp_remote_retrieve_body( $api_response );
			throw new EHRI_DOI_Repository_Exception( sprintf( 'Error updating DOI [%s]: %s', $code, $error ), $code, null, $doi );
		}
		$response_body = wp_remote_retrieve_body( $api_response );

		return json_decode( $response_body, true );
	}

	/**
	 * Delete a DOI from the DataCite API. This is only possible if the DOI is in a
	 * draft state.
	 *
	 * @param string $doi the DOI to delete, starting with the prefix.
	 *
	 * @return bool true if the DOI was deleted, false otherwise.
	 * @throws EHRI_DOI_Repository_Exception If there is an error deleting the DOI.
	 */
	public function delete_doi( string $doi ): bool {
		$api_response = wp_remote_request(
			$this->service_url . '/' . $doi,
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization' => 'Basic ' . $this->get_basic_auth(),
				),
			)
		);
		$this->check_auth( $api_response, $doi );
		// Ensure we get a 204 response.
		$code = wp_remote_retrieve_response_code( $api_response );
		if ( is_wp_error( $api_response ) || 204 !== $code ) {
			$error = wp_remote_retrieve_body( $api_response );
			throw new EHRI_DOI_Repository_Exception( sprintf( 'Error deleting DOI [%s]: %s', $code, $error ), $code, null, $doi );
		}

		return true;
	}

	/**
	 * Check a response for authentication errors.
	 *
	 * @param array|WP_Error $response the remote response.
	 * @param string|null    $doi the optional DOI.
	 *
	 * @throws EHRI_DOI_Repository_Exception If there is an authentication error.
	 * @return void
	 */
	private function check_auth( $response, $doi = null ) {
		$code = wp_remote_retrieve_response_code( $response );
		if ( 401 === $code ) {
			throw new EHRI_DOI_Repository_Exception( sprintf( 'Authentication error [%s]', $code ), $code, null, $doi );
		}
	}

	/**
	 * Encode the client ID and secret for basic authentication.
	 *
	 * @return string
	 */
	private function get_basic_auth(): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $this->client_id . ':' . $this->client_secret );
	}
}
