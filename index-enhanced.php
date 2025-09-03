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

// Include AI-Guided Workflow Integration
require_once __DIR__ . '/src/integration-layer.php';

// Continue with existing index.php content and add workflow integration
// This serves as the enhanced entry point with workflow features

// Check if this is a workflow API request
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Initialize feature integrator
$featureIntegrator = new TaskFlowFeatureIntegrator(Database::getInstance());

// Handle workflow requests through feature integrator
$workflowResponse = $featureIntegrator->handleRequest($method, $uri, 
    $method === 'POST' ? json_decode(file_get_contents('php://input'), true) : $_GET);

if ($workflowResponse !== null) {
    header('Content-Type: application/json');
    echo json_encode($workflowResponse);
    exit;
}

// Continue with existing index.php logic for non-workflow requests
// Include the main index.php content here or redirect to it
require_once __DIR__ . '/index.php';

?>