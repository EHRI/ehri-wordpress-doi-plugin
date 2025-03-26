(function($) {
    $(document).ready(function() {
        // Handle the "Edit DOI Metadata" button click
        $('#edit_doi_button').on('click', function(e) {
            e.preventDefault();
            
            // Get the post ID from the URL
            const urlParams = new URLSearchParams(window.location.search);
            const postId = urlParams.get('post');
            
            // Send AJAX request to open the modal
            $.ajax({
                url: doiMetadata.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'open_doi_modal',
                    nonce: doiMetadata.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        // Append modal HTML to body
                        $('body').append(response.data.modal_html);
                        
                        // Initialize the dialog
                        $('#doi-metadata-modal').dialog({
                            dialogClass: 'wp-dialog',
                            autoOpen: true,
                            closeOnEscape: true,
                            width: 500,
                            modal: true,
                            buttons: {
                                Close: function() {
                                    $(this).dialog('close');
                                }
                            },
                            close: function() {
                                // Remove the modal when closed
                                $(this).remove();
                            }
                        });
                        
                        // Attach save button handler
                        $('#save-doi-metadata').on('click', function() {
                            saveDOIMetadata();
                        });

                        $('#register-doi-metadata').on('click', function() {
                            registerDOIMetadata();
                        });
                        
                        // Attach fetch button handler
                        $('#fetch-doi-data').on('click', function() {
                            fetchDOIData();
                        });
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An error occurred while trying to open the DOI metadata editor.');
                }
            });
        });

        function registerDOIMetadata() {
            var formObject = {};
            $("#doi-metadata-form").serializeArray().forEach(function(item) {
                formObject[item.name] = item.value;
            });

            console.log(formObject);

            $.ajax({
                url: doiMetadata.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'register_doi_metadata',
                    nonce: doiMetadata.nonce,
                    ...formObject
                },
                success: function(response) {
                    if (response.success) {
                        // Update the DOI field in the meta box
                        $('#doi_field').val(response.data.doi);

                        // Close the dialog
                        $('#doi-metadata-modal').dialog('close');

                        // Show success message
                        alert(response.data.message);
                    } else {
                        alert('Error: failed here ' + response.data);
                    }
                },
                error: function() {
                    alert('An error occurred while saving the DOI metadata.');
                }
            });
        }
        
        // Function to save metadata via AJAX
        function saveDOIMetadata() {
            const formData = $('#doi-metadata-form').serialize();
            
            $.ajax({
                url: doiMetadata.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'save_doi_metadata',
                    nonce: doiMetadata.nonce,
                    ...formData
                },
                success: function(response) {
                    if (response.success) {
                        // Update the DOI field in the meta box
                        $('#doi_field').val(response.data.doi);
                        
                        // Close the dialog
                        $('#doi-metadata-modal').dialog('close');
                        
                        // Show success message
                        alert(response.data.message);
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An error occurred while saving the DOI metadata.');
                }
            });
        }
        
        // Function to fetch DOI data from external API
        function fetchDOIData() {
            const doi = $('#modal_doi').val();
            
            if (!doi) {
                alert('Please enter a DOI to fetch metadata.');
                return;
            }
            
            // Show loading indicator
            $('#fetch-doi-data').text('Loading...').prop('disabled', true);
            
            // Example using the Crossref API (you may need to use a different DOI resolver)
            $.ajax({
                // NB: deliberately not using encodeURIComponent here because
                // the DOI contains significant characters
                url: doiMetadata.providerBaseUrl + doi,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log(response.data);
                    if (response.data) {
                        // Extract and populate fields from the response
                        const item = response.data.attributes;
                        
                        if (item.titles && item.titles.length > 0) {
                            $('#modal_title').val(item.titles[0].title);
                        }

                        if (item.descriptions && item.descriptions.length > 0) {
                            $('#model_description').val(item.descriptions[0].description);
                        }
                        
                        if (item.creators) {
                            const authors = item.creators.map(creator => creator.name).join('\n');
                            $('#modal_authors').val(authors);
                        }
                        
                        if (item.publisher) {
                            $('#modal_publisher').val(item.publisher);
                        }
                        
                        if (item.published && item.published['date-parts'] && 
                            item.published['date-parts'][0] && 
                            item.published['date-parts'][0][0]) {
                            $('#modal_year').val(item.published['date-parts'][0][0]);
                        }
                    } else {
                        alert('Could not find metadata for the provided DOI. URL: ' + doiMetadata.providerBaseUrl + doi);
                    }
                },
                error: function() {
                    alert('An error occurred while fetching DOI metadata.');
                },
                complete: function() {
                    // Reset button
                    $('#fetch-doi-data').text('Fetch from DOI').prop('disabled', false);
                }
            });
        }
    });
})(jQuery);
