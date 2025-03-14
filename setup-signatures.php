<?php
/**
 * Setup script for signatures collection
 * This script will set up the signatures collection in MongoDB
 */

// Run the schema creation script
require_once __DIR__ . '/schemas/create_signatures_collection.php';

echo "Signature collection setup completed.\n";
echo "You can now use the signature-api.php endpoints to manage signatures.\n";
echo "A test interface is available at signature.html\n"; 