/**
 * EHRI Citation Widget
 *
 * @package ehri-pid-tools
 */

jQuery( document ).ready(
	function ($) {
		// Only run if we have a DOI and Citation.js is loaded.
		let Cite = require( 'citation-js' );

		if (typeof postDOI === 'undefined') {
			console.error( 'No DOI found for citation widget' );
			return;
		}
		if (typeof Cite === 'undefined') {
			console.error( 'Citation.js not loaded for citation widget' );
			return;
		}

		const $loading        = $( '.citation-loading' );
		const $formats        = $( '.citation-formats' );
		const $errorEl        = $( '.citation-error' );
		const $citationText   = $( '#citation-text' );
		const $copyButton     = $( '#copy-citation' );
		const $formatSelector = $( '#citation-format-selector' );
		const $copiedNotice   = $( '.citation-copied' );

		// Not jquery.
		const dialog              = document.querySelector( '.citation-dialog' );
		const showCitationDialog  = document.getElementById( 'show-citation-dialog' );
		const closeCitationDialog = document.getElementById( 'close-citation-dialog' );

		showCitationDialog.addEventListener(
			'click',
			function () {
				dialog.showModal();
			}
		);

		closeCitationDialog.addEventListener(
			'click',
			function () {
				dialog.close();
			}
		);

		function showCopyMessage() {
			console.log( 'Text copied to clipboard' );
			$copiedNotice.fadeIn().delay( 2000 ).fadeOut();
		}

		function legacyCopy(text) {
			console.log( 'Using legacy copy method...' );
			// Create a temporary textarea element.
			const textarea = document.createElement( 'textarea' );
			textarea.value = text;

			// Make it non-editable to avoid focus and ensure it's not visible.
			textarea.setAttribute( 'readonly', '' );
			textarea.style.position = 'absolute';
			textarea.style.left     = '-9999px';

			document.body.appendChild( textarea );

			// Check if the user is on iOS.
			const isIOS = navigator.userAgent.match( /ipad|iphone/i );

			if (isIOS) {
				// iOS doesn't allow programmatic selection normally.
				// Create a selectable range.
				const range = document.createRange();
				range.selectNodeContents( textarea );

				const selection = window.getSelection();
				selection.removeAllRanges();
				selection.addRange( range );
				textarea.setSelectionRange( 0, 999999 );
			} else {
				// Select the text for other devices.
				textarea.select();
			}

			try {
				// Execute copy command.
				const successful = document.execCommand( 'copy' );

				if (successful) {
					showCopyMessage();
				} else {
					console.error( 'Copy command failed' );
				}
			} catch (err) {
				console.error( 'Error during copy: ', err );
			}

			// Clean up.
			document.body.removeChild( textarea );
		}

		function copyToClipboard(text) {
			// Try using the Clipboard API (modern browsers).
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText( text )
					.then( showCopyMessage )
					.catch( ()	=> legacyCopy( text ) );
			} else {
				console.log( navigator.clipboard, window.isSecureContext );
				// Use legacy method for older browsers or non-secure contexts.
				legacyCopy( text );
			}

			return true;
		}

		// Function to update the citation text based on selected format.
		function updateCitationText(cite) {
			const format = $formatSelector.val();
			let output   = '';

			try {
				switch (format) {
					case 'apa':
						output = cite.format(
							'bibliography',
							{
								format: 'text',
								template: 'apa'
							}
						);
						break;
					case 'mla':
						output = cite.format(
							'bibliography',
							{
								format: 'text',
								template: 'mla'
							}
						);
						break;
					case 'chicago':
						output = cite.format(
							'bibliography',
							{
								format: 'text',
								template: 'chicago'
							}
						);
						break;
					case 'bibtex':
						output = cite.format( 'bibtex' );
						break;
					case 'ris':
						output = cite.format( 'ris' );
						break;
					case 'harvard':
						output = cite.format(
							'bibliography',
							{
								format: 'text',
								template: 'harvard1'
							}
						);
						break;
				}

				$citationText.text( output );
			} catch (e) {
				console.error( 'Error formatting citation:', e );
				$citationText.text( 'Error formatting citation' );
			}
		}

		function interceptXHRUrl(originalUrl, replacementUrl, once = false) {
			const originalOpen = XMLHttpRequest.prototype.open;
			let intercepted    = false;

			XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
				if ( ! intercepted && typeof url === 'string' && url.includes( originalUrl )) {
					console.log( `Intercepted XHR to ${url}` );
					const newUrl = url.replace( originalUrl, replacementUrl );
					console.log( `Redirecting to ${newUrl}` );

					if (once) {
						intercepted = true;
						setTimeout( restoreOriginal, 0 );
					}

					return originalOpen.call( this, method, newUrl, async, user, password );
				}

				return originalOpen.apply( this, arguments );
			};

			function restoreOriginal() {
				XMLHttpRequest.prototype.open = originalOpen;
				console.log( 'Restored original XHR behavior' );
				return true;
			}

			return restoreOriginal;
		}

		// Initialize Citation.js with the DOI.
		try {
			// We're going to hack XHR to swap out https://doi.org/
			// for the DataCite API URL...
			if ( doiCitationWidget.resolverUrlPrefix ) {
				interceptXHRUrl( 'https://doi.org/', doiCitationWidget.resolverUrlPrefix, true );
			}
			let cite = new Cite( postDOI );

			$loading.hide();
			$formats.show();
			// Initial citation update.
			updateCitationText( cite );
			// Update citation when format changes.
			$formatSelector.on( 'change', () => updateCitationText( cite ) );
			// Copy citation.
			$copyButton.on(
				'click',
				function () {
					const text = $citationText.text();
					copyToClipboard( text );
				}
			);
		} catch ( e ) {
			console.error( 'Error initializing Citation.js:', e );
			$loading.hide();
			$errorEl.show();
		}
	}
);
