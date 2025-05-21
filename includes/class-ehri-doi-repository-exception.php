<?php
/**
 * EHRI DOI Repository Exception
 *
 * This class encapsulates exceptions thrown by the EHRI DOI Repository.
 *
 * @package ehri-pid-tools
 */

/**
 * Encapsulates exceptions thrown by the DoiRepository class.
 *
 * @package ehri-pid-tools
 */
class EHRI_DOI_Repository_Exception extends Exception {

	/**
	 * The DOI associated with the exception.
	 *
	 * @var string|null
	 */
	private string $doi;

	/**
	 * Constructor.
	 *
	 * @param string         $string the exception message.
	 * @param int|string     $code the exception code.
	 * @param Throwable|null $previous the previous exception.
	 * @param string|null    $doi the DOI associated with the exception.
	 */
	public function __construct( string $string, $code = 0, ?Throwable $previous = null, $doi = null ) {
		$this->doi = $doi;
		parent::__construct( $string, (int) $code, $previous );
	}

	/**
	 * Get the DOI associated with the exception.
	 *
	 * @return string|null the DOI associated with the exception.
	 */
	public function getDoi(): ?string {
		return $this->doi;
	}
}
