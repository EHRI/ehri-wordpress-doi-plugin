jQuery(document).ready(function ($) {
  // Only run if we have a DOI and Citation.js is loaded
  Cite = require('citation-js');
  if (typeof postDOI !== 'undefined' && typeof Cite !== 'undefined') {
    const loading = $('.citation-loading');
    const formats = $('.citation-formats');
    const errorEl = $('.citation-error');
    const citationText = $('#citation-text');
    const copyButton = $('#copy-citation');
    const formatSelector = $('#citation-format-selector');
    const copiedNotice = $('.citation-copied');

    // Not jquery
    const container = document.querySelector('.citation-container');
    const showCitationDialog = document.getElementById('show-citation-dialog');
    const closeCitationDialog = document.getElementById('close-citation-dialog');

    showCitationDialog.addEventListener('click', function () {
      container.showModal();
    });

    closeCitationDialog.addEventListener('click', function () {
      container.close();
    });

    // Function to update the citation text based on selected format
    function updateCitationText(cite) {
      const format = formatSelector.val();
      let output = '';

      try {
        switch (format) {
          case 'apa':
            output = cite.format('bibliography', {
              format: 'text',
              template: 'apa'
            });
            break;
          case 'mla':
            output = cite.format('bibliography', {
              format: 'text',
              template: 'mla'
            });
            break;
          case 'chicago':
            output = cite.format('bibliography', {
              format: 'text',
              template: 'chicago'
            });
            break;
          case 'bibtex':
            output = cite.format('bibtex');
            break;
          case 'ris':
            output = cite.format('ris');
            break;
          case 'harvard':
            output = cite.format('bibliography', {
              format: 'text',
              template: 'harvard1'
            });
            break;
        }

        citationText.text(output);
      } catch (e) {
        console.error('Error formatting citation:', e);
        citationText.text('Error formatting citation');
      }
    }

    // Initialize Citation.js with the DOI
    try {
      console.log("Initializing Citation.js with DOI: " + postDOI);
      const cite = new Cite(postDOI);
      console.log("Citation.js initialized");

      loading.hide();
      formats.show();

      // Initial citation update
      updateCitationText(cite);

      // Update citation when format changes
      formatSelector.on('change', () => updateCitationText(cite));

      // Copy citation
      copyButton.on('click', function () {
        const text = citationText.text();

        // Create temporary textarea for copying
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();

        try {
          document.execCommand('copy');
          copiedNotice.fadeIn().delay(2000).fadeOut();
        } catch (e) {
          console.error('Failed to copy citation:', e);
        }

        document.body.removeChild(textarea);
      });
    } catch (e) {
      console.error('Error initializing Citation.js:', e);
      loading.hide();
      errorEl.show();
    }
  } else if (typeof postDOI === 'undefined') {
    console.error('No DOI found for citation widget');
  } else if (typeof Cite === 'undefined') {
    console.error('Citation.js not loaded for citation widget');
  }
});