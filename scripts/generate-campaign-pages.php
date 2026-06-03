#!/usr/bin/env php
<?php
/**
 * Generate static HTML pages for all campaigns
 * Creates SEO-friendly URLs like /campaigns/clean-water-kenya.html
 *
 * Usage: php scripts/generate-campaign-pages.php [campaignId]
 *
 * If campaignId is provided, regenerates only that campaign.
 * Otherwise, regenerates all published campaigns.
 */

require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/../lib/Campaign.php';

class CampaignPageGenerator {
    private $campaign;
    private $templatePath;
    private $outputDir;

    public function __construct() {
        $this->campaign = new Campaign();
        $this->templatePath = __DIR__ . '/../pages/campaign-detail.html';
        $this->outputDir = __DIR__ . '/../campaigns';

        // Ensure output directory exists
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    /**
     * Generate slug from campaign title
     */
    private function generateSlug($title) {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return substr($slug, 0, 100); // Limit length
    }

    /**
     * Generate static page for a single campaign
     */
    public function generateCampaignPage($campaignData) {
        // Load template
        $template = file_get_contents($this->templatePath);

        if (!$template) {
            throw new Exception("Failed to load template from {$this->templatePath}");
        }

        // Generate slug
        $slug = $this->generateSlug($campaignData['title']);
        $campaignId = (string)$campaignData['_id'];

        // Inject campaign data into the page
        $dataScript = sprintf(
            '<script id="campaign-data" type="application/json">%s</script>',
            json_encode($campaignData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        // Add meta tags for SEO
        $metaTags = $this->generateMetaTags($campaignData, $slug);

        // Inject data script and meta tags before </head>
        $template = str_replace('</head>', $metaTags . "\n" . $dataScript . "\n</head>", $template);

        // Modify the init() function to check for embedded data first
        $initModification = <<<'JS'
<script>
// Override init to use embedded data if available
(function() {
    const originalInit = window.app?.init;
    if (originalInit) {
        window.app.init = function() {
            // Check for embedded campaign data
            const dataEl = document.getElementById('campaign-data');
            if (dataEl) {
                try {
                    const data = JSON.parse(dataEl.textContent);
                    console.log('Using embedded campaign data');
                    this.data = data;
                    this.state.loaded = true;
                    this.state.id = data._id;
                    this.state.isAuthenticated = !!localStorage.getItem('accessToken');
                    this.render();
                    return;
                } catch (e) {
                    console.error('Failed to parse embedded data:', e);
                }
            }
            // Fall back to original init (fetch from API)
            originalInit.call(this);
        };
    }
})();
</script>
JS;

        // Inject before the closing </body> tag
        $template = str_replace('</body>', $initModification . "\n</body>", $template);

        // Write to file
        $filename = "{$slug}.html";
        $filepath = "{$this->outputDir}/{$filename}";

        if (file_put_contents($filepath, $template) === false) {
            throw new Exception("Failed to write file: {$filepath}");
        }

        echo "✓ Generated: /campaigns/{$filename} for campaign: {$campaignData['title']}\n";

        return [
            'slug' => $slug,
            'filename' => $filename,
            'path' => $filepath,
            'url' => "/campaigns/{$filename}"
        ];
    }

    /**
     * Generate SEO meta tags
     */
    private function generateMetaTags($campaign, $slug) {
        $title = htmlspecialchars($campaign['title'] ?? 'Campaign');
        $description = htmlspecialchars(
            substr(strip_tags($campaign['description'] ?? ''), 0, 160)
        );
        $image = '';

        if (!empty($campaign['media']) && is_array($campaign['media'])) {
            foreach ($campaign['media'] as $media) {
                if ($media['type'] === 'image') {
                    $image = htmlspecialchars($media['url']);
                    break;
                }
            }
        }

        $url = "https://app.thegivehub.com/campaigns/{$slug}.html";

        return <<<HTML
    <!-- SEO Meta Tags -->
    <meta name="description" content="{$description}">
    <link rel="canonical" href="{$url}">

    <!-- Open Graph -->
    <meta property="og:title" content="{$title}">
    <meta property="og:description" content="{$description}">
    <meta property="og:url" content="{$url}">
    <meta property="og:type" content="website">
    {$this->conditionalImage($image, 'og:image')}

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{$title}">
    <meta name="twitter:description" content="{$description}">
    {$this->conditionalImage($image, 'twitter:image')}
HTML;
    }

    private function conditionalImage($image, $tag) {
        return $image ? "<meta property=\"{$tag}\" content=\"{$image}\">" : '';
    }

    /**
     * Generate pages for all published campaigns
     */
    public function generateAll() {
        echo "Generating static pages for all campaigns...\n\n";

        // Fetch all published campaigns
        $campaigns = $this->campaign->get(null, [
            'status' => 'published',
            'limit' => 1000  // Get all published campaigns
        ]);

        if (empty($campaigns)) {
            echo "No published campaigns found.\n";
            return;
        }

        $generated = 0;
        $errors = 0;

        foreach ($campaigns as $campaignData) {
            try {
                $this->generateCampaignPage($campaignData);
                $generated++;
            } catch (Exception $e) {
                echo "✗ Error generating page for campaign {$campaignData['_id']}: {$e->getMessage()}\n";
                $errors++;
            }
        }

        echo "\n" . str_repeat('=', 50) . "\n";
        echo "Summary:\n";
        echo "  Generated: {$generated}\n";
        echo "  Errors: {$errors}\n";
        echo "  Output directory: {$this->outputDir}\n";
        echo str_repeat('=', 50) . "\n";
    }

    /**
     * Generate page for a specific campaign by ID
     */
    public function generateById($campaignId) {
        echo "Generating static page for campaign {$campaignId}...\n\n";

        $campaignData = $this->campaign->get($campaignId);

        if (!$campaignData) {
            throw new Exception("Campaign not found: {$campaignId}");
        }

        return $this->generateCampaignPage($campaignData);
    }
}

// Main execution
try {
    $generator = new CampaignPageGenerator();

    // Check if specific campaign ID was provided
    if (isset($argv[1])) {
        $generator->generateById($argv[1]);
    } else {
        $generator->generateAll();
    }

    exit(0);
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}
