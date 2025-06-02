/**
 * DOI Metadata JavaScript
 *
 * This script handles the functionality of the DOI metadata modal in the WordPress admin.
 *
 * @package ehri-pid-tools
 */

(function ($) {
	$( document ).ready(
		function () {

			function initDialog() {
				// Initialize the dialog and wire up the buttons.
				$( '#doi-metadata-modal' ).dialog(
					{
						dialogClass: 'wp-dialog',
						autoOpen: true,
						closeOnEscape: true,
						width: 800,
						modal: true,
						buttons: {
							[doiMetadata.strings.close]: function () {
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
				$( '#save-doi-metadata' ).on(
					'click',
					function () {
						saveDOIMetadata();
					}
				);

				$( '#register-doi-metadata' ).on(
					'click',
					function () {
						registerDOIMetadata();
					}
				);

				$( '#delete-doi' ).on(
					'click',
					function () {
						deleteDraftDOI();
					}
				);

				$( '#register-doi' ).on(
					'click',
					function () {
						updateDOIState( 'register_doi' );
					}
				);

				$( '#publish-doi' ).on(
					'click',
					function () {
						updateDOIState( 'publish_doi' );
					}
				);

				$( '#hide-doi' ).on(
					'click',
					function () {
						updateDOIState( 'hide_doi' );
					}
				);
			}

			// Handle the "Edit DOI Metadata" button click.
			$( '#doi-metadata-box' ).on(
				'click',
				'#edit_doi_button',
				function (e) {
					e.preventDefault();

					// Get the post ID from the URL.
					const urlParams = new URLSearchParams( window.location.search );
					const postId    = urlParams.get( 'post' );

					// Send AJAX request to open the modal.
					$.ajax(
						{
							url: doiMetadata.ajaxUrl,
							type: 'POST',
							data: {
								action: 'open_doi_modal',
								nonce: doiMetadata.nonce,
								post_id: postId
							},
							success: function (response) {
								console.log( response );
								if (response.success) {
									// Append modal HTML to body.
									$( 'body' ).append( response.data.modal_html );

									initDialog();

								} else {
									alert( doiMetadata.strings.error + response.data );
								}
							},
							error: function (e) {
								alert( doiMetadata.strings.errorOpeningModal );
								console.error( e );
							}
						}
					);
				}
			);

			function registerDOIMetadata() {
				let formObject = {};
				$( "#doi-metadata-form" ).serializeArray().forEach(
					function (item) {
						formObject[item.name] = item.value;
					}
				);

				console.log( formObject );

				$.ajax(
					{
						url: doiMetadata.ajaxUrl,
						type: 'POST',
						data: {
							action: 'register_doi_metadata',
							nonce: doiMetadata.nonce,
							...formObject
						},
						success: function (response) {
							if (response.success) {
								// Update the DOI field in the meta box.
								$( '#doi_field' ).val( response.data.doi );

								// Update the dialog.
								$( '#doi-metadata-form' ).replaceWith( $( response.data.modal_html ).find( '#doi-metadata-form' ) );
								initDialog();

								// Update the meta box.
								$( '#doi-metadata-info' ).replaceWith( $( response.data.panel_html ) );
							} else {
								alert( response.data );
							}
						},
						error: function () {
							alert( doiMetadata.strings.errorSavingMetadata );
						}
					}
				);
			}

			// Function to save metadata via AJAX.
			function saveDOIMetadata() {
				let formObject = {};
				$( "#doi-metadata-form" ).serializeArray().forEach(
					function (item) {
						formObject[item.name] = item.value;
					}
				);

				$.ajax(
					{
						url: doiMetadata.ajaxUrl,
						type: 'POST',
						data: {
							action: 'save_doi_metadata',
							nonce: doiMetadata.nonce,
							...formObject
						},
						success: function (response) {
							if (response.success) {
								// Update the DOI field in the meta box.
								$( '#doi_field' ).val( response.data.doi );

								// Update the dialog.
								$( '#doi-metadata-form' ).replaceWith( $( response.data.modal_html ).find( '#doi-metadata-form' ) );
								initDialog();

								// Update the meta box.
								$( '#doi-metadata-info' ).replaceWith( $( response.data.panel_html ) );
							} else {
								alert( 'Error: ' + response.data );
							}
						},
						error: function () {
							alert( doiMetadata.strings.errorSavingMetadata );
						}
					}
				);
			}

			// Delete the DOI if it is a draft.
			function deleteDraftDOI(postId) {
				let formObject = {};
				$( "#doi-metadata-form" ).serializeArray().forEach(
					function (item) {
						formObject[item.name] = item.value;
					}
				);

				if (confirm( doiMetadata.strings.confirmDeleteDoi )) {
					$.ajax(
						{
							url: doiMetadata.ajaxUrl,
							type: 'POST',
							data: {
								action: 'delete_draft_doi',
								nonce: doiMetadata.nonce,
								...formObject
							},
							success: function (response) {
								if (response.success) {
									// Close the dialog.
									$( '#doi-metadata-modal' ).dialog( 'close' );

									// Update the meta box.
									$( '#doi-metadata-info' ).replaceWith( $( response.data.panel_html ) );
								} else {
									alert( doiMetadata.strings.error + response.data );
								}
							},
							error: function () {
								alert( doiMetadata.strings.errorDeletingDoi );
							}
						}
					);
				}
			}

			// Function to hide the DOI.
			function updateDOIState(event) {
				let formObject = {};
				$( "#doi-metadata-form" ).serializeArray().forEach(
					function (item) {
						formObject[item.name] = item.value;
					}
				);

				$.ajax(
					{
						url: doiMetadata.ajaxUrl,
						type: 'POST',
						data: {
							action: event,
							nonce: doiMetadata.nonce,
							...formObject
						},
						success: function (response) {
							if (response.success) {
								// Update the dialog.
								$( '#doi-metadata-form' ).replaceWith( $( response.data.modal_html ).find( '#doi-metadata-form' ) );
								initDialog();

								// Update the meta box.
								$( '#doi-metadata-info' ).replaceWith( $( response.data.panel_html ) );
							} else {
								alert( 'Error: ' + response.data );
							}
						},
						error: function (response) {
							console.log( response.data );
							alert( doiMetadata.strings.errorUpdatingState );
						}
					}
				);
			}
		}
	);
})( jQuery );
