#!/bin/bash

# TaskFlow AI MCP Server Startup Script
# This script starts the MCP server with the correct configuration

set -e

# Configuration
COMPOSE_FILE="docker-compose.mcp.yml"
TASKFLOW_CONTAINER="taskflow-ai"
MCP_CONTAINER="taskflow-mcp"

echo "🚀 Starting TaskFlow AI with MCP Server..."

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null && ! command -v docker &> /dev/null; then
    echo "❌ Docker and docker-compose are required but not installed."
    exit 1
fi

# Use docker compose or docker-compose based on what's available
if command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
else
    DOCKER_COMPOSE="docker compose"
fi

# Function to get API key from TaskFlow AI container
get_api_key() {
    echo "🔑 Getting TaskFlow AI API key..."
    
    # Wait for TaskFlow AI to be ready
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if curl -s http://localhost:8080 > /dev/null 2>&1; then
            break
        fi
        echo "⏳ Waiting for TaskFlow AI to be ready (attempt $attempt/$max_attempts)..."
        sleep 2
        attempt=$((attempt + 1))
    done
    
    if [ $attempt -gt $max_attempts ]; then
        echo "❌ TaskFlow AI failed to start within expected time"
        exit 1
    fi
    
    # Extract API key from the main page
    local api_key=$(curl -s http://localhost:8080 | grep -o 'taskflow_[a-f0-9]*' | head -1)
    
    if [ -z "$api_key" ]; then
        echo "❌ Failed to extract API key from TaskFlow AI"
        exit 1
    fi
    
    echo "✅ Found API key: $api_key"
    echo "$api_key"
}

# Function to stop services
cleanup() {
    echo "🛑 Stopping services..."
    $DOCKER_COMPOSE -f $COMPOSE_FILE down
}

# Set up cleanup on script exit
trap cleanup EXIT

# Start TaskFlow AI first
echo "📱 Starting TaskFlow AI web application..."
$DOCKER_COMPOSE -f $COMPOSE_FILE up -d taskflow-ai

# Get the API key
API_KEY=$(get_api_key)

# Export the API key for the MCP container
export TASKFLOW_API_KEY="$API_KEY"

# Start the MCP server
echo "🔗 Starting TaskFlow AI MCP Server..."
$DOCKER_COMPOSE -f $COMPOSE_FILE up -d taskflow-mcp

echo ""
echo "✅ TaskFlow AI with MCP Server is now running!"
echo ""
echo "📊 Service URLs:"
echo "   TaskFlow AI Web:  http://localhost:8080"
echo "   MCP Server:       docker exec -it taskflow-mcp node mcp-server.js"
echo ""
echo "🔧 Configuration for Claude Desktop:"
echo "   Add this to your Claude Desktop MCP settings:"
echo ""
echo '   {'
echo '     "mcpServers": {'
echo '       "taskflow-ai": {'
echo '         "command": "docker",'
echo '         "args": ["exec", "-i", "taskflow-mcp", "node", "mcp-server.js"],'
echo '         "env": {'
echo '           "TASKFLOW_API_KEY": "'$API_KEY'",'
echo '           "TASKFLOW_BASE_URL": "http://host.docker.internal:8080"'
echo '         }'
echo '       }'
echo '     }'
echo '   }'
echo ""
echo "📋 Available MCP Tools:"
echo "   • Task Management: get_tasks, create_task, update_task, delete_task"
echo "   • Project Management: get_projects, create_project, update_project, delete_project"  
echo "   • Note Management: get_notes, create_note, update_note, delete_note"
echo "   • Scrap Management: get_scraps, create_scrap, convert_scrap"
echo "   • Planning: get_todays_plan, get_plan_for_date"
echo "   • AI Chat: chat_with_ai (requires Gemini API key in TaskFlow AI settings)"
echo ""
echo "🔄 To stop: Ctrl+C or run: $DOCKER_COMPOSE -f $COMPOSE_FILE down"
echo ""

# Follow logs
echo "📝 Following container logs (Ctrl+C to stop):"
$DOCKER_COMPOSE -f $COMPOSE_FILE logs -f