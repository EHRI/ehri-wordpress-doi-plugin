<?php


class Doi_Metadata_Renderer
{
    private $metadata;

    public function __construct($jsonData, $doi = null)
    {
        // Parse JSON if it's a string, otherwise use as-is
        $this->doi = $doi;
        $this->metadata = is_string($jsonData) ? json_decode($jsonData, true) : $jsonData;
    }

    public function render()
    {
        if (!$this->metadata) {
            return '<p>Invalid or empty metadata.</p>';
        }

        $html = '<div class="datacite-metadata">';

        $html .= $this->renderDoi();

        $html .= $this->renderState();

        // Render Title
        $html .= $this->renderTitleSection();

        // Render Creators
        $html .= $this->renderCreatorsSection();

        // Render Publisher
        $html .= $this->renderPublisherSection();

        // Render Publication Year
        $html .= $this->renderPublicationYearSection();

        // Render Resource Type
        $html .= $this->renderResourceTypeSection();

        // Render Identifiers
        $html .= $this->renderIdentifiersSection();

        // Render Descriptions
        $html .= $this->renderDescriptionsSection();

        // Render Subjects/Keywords
        $html .= $this->renderSubjectsSection();

        // Render Funding References
        $html .= $this->renderFundingReferencesSection();

        $html .= '</div>';

        return $html;
    }

    private function renderState()
    {
        if (empty($this->metadata["state"])) return '';

        return "<h2>Status</h2><p>{$this->metadata["state"]}</p>";
    }

    private function renderDoi()
    {
        return !empty($this->doi) ? "<h2>DOI</h2><p>{$this->doi}</p>" : '';
    }

    private function renderTitleSection()
    {
        if (empty($this->metadata['titles'])) return '';

        $titles = [];
        foreach ($this->metadata['titles'] as $title) {
            $titleText = htmlspecialchars($title['title'] ?? '');
            $titleLang = !empty($title['lang']) ? " (Language: {$title['lang']})" : '';
            $titles[] = $titleText . $titleLang;
        }

        return '<h2>Title(s)</h2>' .
            '<ul>' .
            implode('', array_map(function ($t) {
                return "<li>$t</li>";
            }, $titles)) .
            '</ul>';
    }

    private function renderCreatorsSection()
    {
        if (empty($this->metadata['creators'])) return '';

        $creators = [];
        foreach ($this->metadata['creators'] as $creator) {
            $creatorName = htmlspecialchars($creator['name'] ?? '');
            $nameIdentifiers = $this->renderNameIdentifiers($creator);
            $affiliation = $this->renderAffiliation($creator);

            $creators[] = "{$creatorName}{$nameIdentifiers}{$affiliation}";
        }

        return '<h2>Creator(s)</h2>' .
            '<ul>' .
            implode('', array_map(function ($c) {
                return "<li>$c</li>";
            }, $creators)) .
            '</ul>';
    }

    private function renderNameIdentifiers($creator)
    {
        if (empty($creator['nameIdentifiers'])) return '';

        $identifiers = array_map(function ($identifier) {
            $scheme = htmlspecialchars($identifier['nameIdentifierScheme'] ?? '');
            $id = htmlspecialchars($identifier['nameIdentifier'] ?? '');
            return "$scheme: $id";
        }, $creator['nameIdentifiers']);

        return ' (' . implode(', ', $identifiers) . ')';
    }

    private function renderAffiliation($creator)
    {
        if (empty($creator['affiliation'])) return '';

        $affiliations = array_map(function ($aff) {
            return htmlspecialchars($aff['name'] ?? '');
        }, $creator['affiliation']);

        return ' [' . implode(', ', $affiliations) . ']';
    }

    private function renderPublisherSection()
    {
        $publisher = $this->metadata['publisher'] ?? '';
        return $publisher ? "<h2>Publisher</h2><p>" . htmlspecialchars($publisher) . "</p>" : '';
    }

    private function renderPublicationYearSection()
    {
        $year = $this->metadata['publicationYear'] ?? '';
        return $year ? "<h2>Publication Year</h2><p>{$year}</p>" : '';
    }

    private function renderResourceTypeSection()
    {
        if (empty($this->metadata['types'])) return '';

        $type = $this->metadata['types'];
        $typeInfo = [];

        if (!empty($type['resourceTypeGeneral'])) {
            $typeInfo[] = "Type: " . htmlspecialchars($type['resourceTypeGeneral']);
        }
        if (!empty($type['resourceType'])) {
            $typeInfo[] = "Specific Type: " . htmlspecialchars($type['resourceType']);
        }

        return '<h2>Resource Type</h2><p>' . implode(' | ', $typeInfo) . '</p>';
    }

    private function renderIdentifiersSection()
    {
        if (empty($this->metadata['identifiers'])) return '';

        $identifiers = array_map(function ($identifier) {
            $scheme = htmlspecialchars($identifier['identifierType'] ?? '');
            $id = htmlspecialchars($identifier['identifier'] ?? '');
            return "{$scheme}: {$id}";
        }, $this->metadata['identifiers']);

        return '<h2>Identifiers</h2><ul>' .
            implode('', array_map(function ($i) {
                return "<li>$i</li>";
            }, $identifiers)) .
            '</ul>';
    }

    private function renderDescriptionsSection()
    {
        if (empty($this->metadata['descriptions'])) return '';

        $descriptions = array_map(function ($desc) {
            $descText = htmlspecialchars($desc['description'] ?? '');
            $descType = !empty($desc['descriptionType']) ?
                " (Type: " . htmlspecialchars($desc['descriptionType']) . ")" : '';
            return $descText . $descType;
        }, $this->metadata['descriptions']);

        return '<h2>Description(s)</h2><ul>' .
            implode('', array_map(function ($d) {
                return "<li>$d</li>";
            }, $descriptions)) .
            '</ul>';
    }

    private function renderSubjectsSection()
    {
        if (empty($this->metadata['subjects'])) return '';

        $subjects = array_map(function ($subject) {
            $subjectText = htmlspecialchars($subject['subject'] ?? '');
            $subjectScheme = !empty($subject['subjectScheme']) ?
                " (Scheme: " . htmlspecialchars($subject['subjectScheme']) . ")" : '';
            return $subjectText . $subjectScheme;
        }, $this->metadata['subjects']);

        return '<h2>Subject(s)/Keyword(s)</h2><ul>' .
            implode('', array_map(function ($s) {
                return "<li>$s</li>";
            }, $subjects)) .
            '</ul>';
    }

    private function renderFundingReferencesSection()
    {
        if (empty($this->metadata['fundingReferences'])) return '';

        $fundings = array_map(function ($funding) {
            $funderName = htmlspecialchars($funding['funderName'] ?? '');
            $awardTitle = !empty($funding['awardTitle']) ?
                " - Award: " . htmlspecialchars($funding['awardTitle']) : '';
            $awardNumber = !empty($funding['awardNumber']) ?
                " (Number: " . htmlspecialchars($funding['awardNumber']) . ")" : '';

            return $funderName . $awardTitle . $awardNumber;
        }, $this->metadata['fundingReferences']);

        return '<h2>Funding Reference(s)</h2><ul>' .
            implode('', array_map(function ($f) {
                return "<li>$f</li>";
            }, $fundings)) .
            '</ul>';
    }

    // Optional: CSS for basic styling
    public function getCSS()
    {
        return '
        <style>
            .datacite-metadata {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
                line-height: 1.6;
            }
            .datacite-metadata h2 {
                color: #333;
                border-bottom: 1px solid #ddd;
                padding-bottom: 10px;
            }
            .datacite-metadata ul {
                padding-left: 20px;
            }
            .datacite-metadata li {
                margin-bottom: 10px;
            }
        </style>';
    }
}