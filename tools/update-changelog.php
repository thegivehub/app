<?php
/**
 * Update Changelog Script
 * 
 * This script helps maintain the CHANGELOG.md file by extracting commit messages
 * from git and formatting them according to Keep a Changelog conventions.
 * 
 * Usage: php tools/update-changelog.php [--since=<date>] [--version=<version>]
 * 
 * Options:
 *   --since=<date>     Only include commits since this date (e.g., "2025-01-01")
 *   --version=<version> Version to create (e.g., "1.1.0")
 */

// Parse command line arguments
$options = getopt('', ['since::', 'version::']);
$since = $options['since'] ?? '';
$version = $options['version'] ?? '';

// Set up git command
$gitCommand = 'git log';
if ($since) {
    $gitCommand .= ' --since="' . $since . '"';
}
$gitCommand .= ' --pretty=format:"%h - %s (%an)" --no-merges';

// Execute git command
exec($gitCommand, $commits, $returnCode);

if ($returnCode !== 0) {
    echo "Error executing git command.\n";
    exit(1);
}

if (empty($commits)) {
    echo "No commits found.\n";
    exit(0);
}

// Read existing changelog
$changelogPath = __DIR__ . '/../CHANGELOG.md';
if (!file_exists($changelogPath)) {
    echo "CHANGELOG.md not found. Creating a new one.\n";
    $changelog = "# Changelog\n\n";
    $changelog .= "All notable changes to the GiveHub project will be documented in this file.\n\n";
    $changelog .= "The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),\n";
    $changelog .= "and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).\n\n";
    $changelog .= "## [Unreleased]\n\n";
} else {
    $changelog = file_get_contents($changelogPath);
}

// Categorize commits
$categories = [
    'added' => [],
    'changed' => [],
    'deprecated' => [],
    'removed' => [],
    'fixed' => [],
    'security' => []
];

$keywords = [
    'added' => ['add', 'new', 'feature', 'implement', 'create'],
    'changed' => ['change', 'update', 'modify', 'refactor', 'improve', 'enhance'],
    'deprecated' => ['deprecate'],
    'removed' => ['remove', 'delete', 'eliminate'],
    'fixed' => ['fix', 'bug', 'issue', 'resolve', 'correct'],
    'security' => ['security', 'vulnerability', 'secure', 'protect']
];

foreach ($commits as $commit) {
    // Extract hash and message
    preg_match('/^([a-f0-9]+) - (.+) \((.+)\)$/', $commit, $matches);
    if (count($matches) < 4) continue;
    
    $hash = $matches[1];
    $message = $matches[2];
    $author = $matches[3];
    
    // Determine category based on keywords
    $category = 'changed'; // Default category
    $lowerMessage = strtolower($message);
    
    foreach ($keywords as $cat => $words) {
        foreach ($words as $word) {
            if (strpos($lowerMessage, $word) === 0 || 
                preg_match('/^[^a-zA-Z]*' . $word . '/', $lowerMessage)) {
                $category = $cat;
                break 2;
            }
        }
    }
    
    // Format the commit message
    $formattedMessage = ucfirst($message) . " (commit: {$hash})";
    $categories[$category][] = $formattedMessage;
}

// Generate new content
$newContent = "";

if ($version) {
    // Create a new version section
    $date = date('Y-m-d');
    $newContent .= "## [{$version}] - {$date}\n\n";
    
    foreach ($categories as $category => $messages) {
        if (!empty($messages)) {
            $newContent .= "### " . ucfirst($category) . "\n";
            foreach ($messages as $message) {
                $newContent .= "- {$message}\n";
            }
            $newContent .= "\n";
        }
    }
    
    // Replace [Unreleased] section with the new version and an empty [Unreleased]
    $unreleasedPattern = '/## \[Unreleased\]\n\n(.*?)(?=\n## \[|$)/s';
    $replacement = "## [Unreleased]\n\n" . $newContent;
    $changelog = preg_replace($unreleasedPattern, $replacement, $changelog);
} else {
    // Add to [Unreleased] section
    foreach ($categories as $category => $messages) {
        if (!empty($messages)) {
            $newContent .= "### " . ucfirst($category) . "\n";
            foreach ($messages as $message) {
                $newContent .= "- {$message}\n";
            }
            $newContent .= "\n";
        }
    }
    
    // Replace [Unreleased] section
    $unreleasedPattern = '/## \[Unreleased\]\n\n(.*?)(?=\n## \[|$)/s';
    $replacement = "## [Unreleased]\n\n" . $newContent;
    $changelog = preg_replace($unreleasedPattern, $replacement, $changelog);
}

// Write updated changelog
file_put_contents($changelogPath, $changelog);

echo "Changelog updated successfully.\n";
if ($version) {
    echo "Created new version: {$version}\n";
} else {
    echo "Updated [Unreleased] section.\n";
}

// Display summary
echo "\nSummary of changes:\n";
foreach ($categories as $category => $messages) {
    if (!empty($messages)) {
        echo ucfirst($category) . ": " . count($messages) . " commits\n";
    }
} 