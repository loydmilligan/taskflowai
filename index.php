<?php
/**
 * TaskFlow AI - Enhanced with AI-Guided Daily Workflow
 * Single File Mobile-First Task Management App with Morning/Evening Rituals
 * Chat-driven interface powered by Google Gemini AI
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// CORS headers for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if this is a workflow API request
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// For workflow API requests, we need special handling
$isWorkflowRequest = (
    strpos($uri, '/api/workflow') === 0 || 
    strpos($uri, '/api/chat') === 0 || 
    strpos($uri, '/api/integration') === 0 || 
    strpos($uri, '/api/projects') === 0 ||
    strpos($uri, '/manifest.json') === 0 ||
    strpos($uri, '/sw.js') === 0
);

if ($isWorkflowRequest) {
    // Set a flag that the main index.php can detect
    define('WORKFLOW_REQUEST', true);
}

// Always load the main index.php first to get core classes
require_once __DIR__ . '/index-original.php';

// If this was a workflow request and it wasn't handled by main index,
// the workflow integrator will be initialized by the main index.php

?>