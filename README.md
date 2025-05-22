[![Build Status](https://github.com/EHRI/ehri-wordpress-doi-plugin/workflows/CI/badge.svg)](https://github.com/EHRI/ehri-wordpress-doi-plugin/actions?query=workflow%3ACI)

EHRI DOI Wordpress Plugin
=========================

This is a WordPress plugin for creating, managing, and displaying DOIs (Digital Object Identifiers) for posts.
It contains a few separate components:

 - A WordPress plugin that provides an admin UI for creating and managing DOIs from the post editor
 - A widget that displays the DOI for a post
 - A widget that allows users to more easily cite posts in multiple formats (using [citation-js](https://citation.js.org))

Because EHRI has specific requirements regarding landing pages for DOIs, this plugin is
not intended for general use. It is designed to be used with the EHRI PID tools service,
which provides a proxy for the actual DOI provider service and registers posts with a 
landing page. However, it can be used as a standalone plugin for creating DOIs with
the DataCite DOI REST API (though this functionality is less tested.)
