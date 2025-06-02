/**
 * DOI Version JavaScript
 *
 * This script handles the functionality of the DOI version modal in the WordPress admin.
 *
 * @package ehri-pid-tools
 */

(function ($) {
	$( document ).ready(
		function () {

			function initDialog() {
				// Initialize the dialog and wire up the buttons.
				$( '#doi-version-modal' ).dialog(
					{
						dialogClass: 'wp-dialog',
						autoOpen: true,
						closeOnEscape: true,
						width: 800,
						modal: true,
						buttons: {
							Close: function () {
								$( this ).dialog( 'close' );
							}
						},
						close: function () {
							// Remove the modal when closed.
							$( this ).remove();
						}
					}
				);

				// Attach save button handler.
				$( '#save_doi_version_button' ).on(
					'click',
					function () {
						savePostReplacedVersionId();
					}
				);
			}

			function savePostReplacedVersionId() {
				console.log( "Saving version info" );

				// Get the post ID from the URL.
				const urlParams = new URLSearchParams( window.location.search );
				const postId    = urlParams.get( 'post' );

				const newVersionId = $( '#new_version_select' ).val();

				// Send AJAX request to open the modal.
				$.ajax(
					{
						url: doiVersion.ajaxUrl,
						type: 'POST',
						data: {
							action: 'save_post_replaced_version_id',
							nonce: doiVersion.nonce,
							post_id: postId,
							new_version_id: newVersionId,
						},
						success: function (response) {
							alert( response.data.message );
							// Close the dialog.
							$( '#doi-version-modal' ).dialog( 'close' );

							// Update the meta box.
							$( '#doi-version-info' ).replaceWith( $( response.data.panel_html ) );
						},
						error: function (e) {
							alert( 'An error occurred while trying to save the version info.' );
							console.error( e );
						}
					}
				);
			}

			// Handle the "Edit DOI Metadata" button click.
			$( '#doi-version-box' ).on(
				'click',
				'#edit_doi_version_button',
				function (e) {
					e.preventDefault();

					// Get the post ID from the URL.
					const urlParams = new URLSearchParams( window.location.search );
					const postId    = urlParams.get( 'post' );

					// Send AJAX request to open the modal.
					$.ajax(
						{
							url: doiVersion.ajaxUrl,
							type: 'POST',
							data: {
								action: 'open_doi_version_modal',
								nonce: doiVersion.nonce,
								post_id: postId
							},
							success: function (response) {
								console.log( response );
								if (response.success) {
									// Append modal HTML to body.
									$( 'body' ).append( response.data.modal_html );

									initDialog();

								} else {
									alert( 'Error: ' + response.data );
								}
							},
							error: function (e) {
								alert( 'An error occurred while trying to open the DOI version metadata editor.' );
								console.error( e );
							}
						}
					);
				}
			);
		}
	);
})( jQuery );
