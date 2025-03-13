<?php
// lib/jumio-config.php
// Configuration settings for Jumio integration

// Define Jumio API credentials
define('JUMIO_API_TOKEN', getenv('JUMIO_API_TOKEN') ?: '');
define('JUMIO_API_SECRET', getenv('JUMIO_API_SECRET') ?: '');
define('JUMIO_WEBHOOK_SECRET', getenv('JUMIO_WEBHOOK_SECRET') ?: '');

// Define Jumio workflow IDs (can have different workflows for different verification types)
define('JUMIO_STANDARD_WORKFLOW_ID', getenv('JUMIO_STANDARD_WORKFLOW_ID') ?: '100');
define('JUMIO_ENHANCED_WORKFLOW_ID', getenv('JUMIO_ENHANCED_WORKFLOW_ID') ?: '200');

// Define callback and redirect URLs
define('JUMIO_BASE_URL', APP_ENV === 'production' ? 'https://netverify.com/api/v4' : 'https://sandbox-netverify.com/api/v4');
define('JUMIO_CALLBACK_URL', APP_ENV === 'production' ? 'https://app.thegivehub.com/api/kyc/webhook' : 'https://dev.thegivehub.com/api/kyc/webhook');
define('JUMIO_SUCCESS_URL', APP_ENV === 'production' ? 'https://app.thegivehub.com/verification/success' : 'https://dev.thegivehub.com/verification/success');
define('JUMIO_ERROR_URL', APP_ENV === 'production' ? 'https://app.thegivehub.com/verification/error' : 'https://dev.thegivehub.com/verification/error');

// Define verification statuses
define('KYC_STATUS_NOT_STARTED', 'NOT_STARTED');
define('KYC_STATUS_INITIATED', 'INITIATED');
define('KYC_STATUS_PENDING', 'PENDING');
define('KYC_STATUS_APPROVED', 'APPROVED');
define('KYC_STATUS_REJECTED', 'REJECTED');
define('KYC_STATUS_ERROR', 'ERROR');
define('KYC_STATUS_EXPIRED', 'EXPIRED');
