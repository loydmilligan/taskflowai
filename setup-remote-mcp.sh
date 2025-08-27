#!/bin/bash
# TaskFlow AI MCP Remote Setup Script
# This script sets up MCP server on your laptop to connect to remote TaskFlow AI

set -e

# Configuration - EDIT THESE VALUES
REMOTE_SERVER="192.168.5.128"     # Your TaskFlow AI server
REMOTE_USER="mmariani"             # Your username on remote server

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
print_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
print_success() { echo -e "${GREEN}✅ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
print_error() { echo -e "${RED}❌ $1${NC}"; }

# Check if configuration is set
if [ -z "$REMOTE_SERVER" ] || [ -z "$REMOTE_USER" ]; then
    print_error "Please edit this script and set REMOTE_SERVER and REMOTE_USER variables"
    echo ""
    echo "Example:"
    echo "  REMOTE_SERVER=\"192.168.1.100\""
    echo "  REMOTE_USER=\"ubuntu\""
    exit 1
fi

echo "🌐 TaskFlow AI MCP Remote Setup"
echo "================================"
echo ""
print_info "Remote Server: $REMOTE_USER@$REMOTE_SERVER"
echo ""

# Step 1: Check SSH connectivity
print_info "Testing SSH connectivity to remote server..."
if ssh -o BatchMode=yes -o ConnectTimeout=5 $REMOTE_USER@$REMOTE_SERVER echo "SSH OK" 2>/dev/null; then
    print_success "SSH connection successful"
else
    print_error "Cannot connect via SSH. Please check:"
    echo "  1. SSH keys are set up for password-less login"
    echo "  2. Remote server is accessible"
    echo "  3. Username is correct"
    exit 1
fi

# Step 2: Check if TaskFlow AI is running on remote server
print_info "Checking if TaskFlow AI is running on remote server..."
if ssh $REMOTE_USER@$REMOTE_SERVER "curl -s http://localhost:8080 >/dev/null 2>&1"; then
    print_success "TaskFlow AI is running on remote server"
else
    print_error "TaskFlow AI is not accessible on remote server port 8080"
    echo ""
    echo "Please ensure TaskFlow AI is running on the remote server:"
    echo "  ssh $REMOTE_USER@$REMOTE_SERVER"
    echo "  cd /path/to/taskflowai"
    echo "  docker compose up -d"
    exit 1
fi

# Step 3: Get API key from remote server
print_info "Getting API key from remote server..."
API_KEY=$(ssh $REMOTE_USER@$REMOTE_SERVER "curl -s http://localhost:8080 | grep -o 'taskflow_[a-f0-9]*' | head -1")

if [ -z "$API_KEY" ]; then
    print_error "Failed to extract API key from remote server"
    exit 1
fi

print_success "Got API key: ${API_KEY:0:12}..."

# Step 4: Test external connectivity to remote server
print_info "Testing external connectivity to remote TaskFlow AI..."
if curl -s -m 10 -H "Authorization: Bearer $API_KEY" http://$REMOTE_SERVER:8080/api/tasks >/dev/null 2>&1; then
    print_success "Remote TaskFlow AI is externally accessible"
else
    print_error "Cannot connect to remote TaskFlow AI externally"
    echo ""
    echo "Common issues:"
    echo "  1. Firewall blocking port 8080"
    echo "  2. Docker not binding to external interface"
    echo "  3. Network routing issues"
    echo ""
    echo "Debug commands to run on remote server:"
    echo "  sudo ufw status                    # Check firewall"
    echo "  docker ps                          # Check container status"
    echo "  ss -tlnp | grep :8080             # Check port binding"
    exit 1
fi

# Step 5: Setup local MCP server dependencies
print_info "Setting up local MCP server dependencies..."
if [ ! -f "package.json" ]; then
    if [ -f "package-standalone.json" ]; then
        cp package-standalone.json package.json
        print_success "Copied standalone package.json"
    else
        print_error "package-standalone.json not found. Are you in the TaskFlow AI directory?"
        exit 1
    fi
fi

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed. Please install Node.js 18+ and try again."
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    print_error "npm is not installed. Please install npm and try again."
    exit 1
fi

# Install dependencies
print_info "Installing Node.js dependencies..."
npm install --silent
print_success "Dependencies installed"

# Step 6: Determine Claude Desktop config directory
print_info "Detecting Claude Desktop configuration directory..."
CLAUDE_CONFIG_DIR=""
if [[ "$OSTYPE" == "darwin"* ]]; then
    CLAUDE_CONFIG_DIR="$HOME/Library/Application Support/Claude"
elif [[ "$OSTYPE" == "msys" || "$OSTYPE" == "win32" ]]; then
    CLAUDE_CONFIG_DIR="$APPDATA/Claude"
else
    # Linux/other
    CLAUDE_CONFIG_DIR="$HOME/.config/claude"
fi

mkdir -p "$CLAUDE_CONFIG_DIR"
CONFIG_FILE="$CLAUDE_CONFIG_DIR/claude_desktop_config.json"
print_success "Config directory: $CLAUDE_CONFIG_DIR"

# Step 7: Create or update Claude Desktop configuration
print_info "Creating Claude Desktop configuration..."

# Check if config file exists and has content
if [ -f "$CONFIG_FILE" ] && [ -s "$CONFIG_FILE" ]; then
    print_warning "Existing configuration found. Creating backup..."
    cp "$CONFIG_FILE" "$CONFIG_FILE.backup.$(date +%s)"
    
    # Try to merge with existing config (basic approach)
    print_info "Updating existing configuration..."
    # For now, we'll just overwrite. In production, you might want to merge JSON properly
fi

cat > "$CONFIG_FILE" << EOF
{
  "mcpServers": {
    "taskflow-ai": {
      "command": "node",
      "args": ["$(pwd)/mcp-server-standalone.js"],
      "env": {
        "TASKFLOW_API_KEY": "$API_KEY",
        "TASKFLOW_BASE_URL": "http://$REMOTE_SERVER:8080"
      }
    }
  }
}
EOF

print_success "Claude Desktop configuration created"

# Step 8: Test MCP server functionality
print_info "Testing MCP server functionality..."
export TASKFLOW_API_KEY="$API_KEY"
export TASKFLOW_BASE_URL="http://$REMOTE_SERVER:8080"

if [ -f "test-mcp-server.js" ]; then
    if timeout 30 node test-mcp-server.js >/dev/null 2>&1; then
        print_success "MCP server test passed!"
    else
        print_warning "MCP server test failed, but continuing with setup"
        print_info "You can manually test with: TASKFLOW_API_KEY=\"$API_KEY\" TASKFLOW_BASE_URL=\"http://$REMOTE_SERVER:8080\" node test-mcp-server.js"
    fi
else
    print_warning "MCP test script not found, skipping test"
fi

# Step 9: Final instructions
echo ""
echo "🎉 Setup Complete!"
echo "=================="
echo ""
print_success "TaskFlow AI MCP Server is configured for remote deployment"
echo ""
echo "📊 Configuration Summary:"
echo "  Remote Server: $REMOTE_SERVER:8080"
echo "  API Key: ${API_KEY:0:12}...${API_KEY: -8}"
echo "  Config File: $CONFIG_FILE"
echo ""
echo "🔄 Next Steps:"
echo "  1. Restart Claude Desktop to load the new MCP configuration"
echo "  2. In Claude Desktop, test with these commands:"
echo "     • 'Show me my tasks for today'"
echo "     • 'Create a task to test the MCP integration'"
echo "     • 'What projects do I have?'"
echo ""
echo "🔧 If you encounter issues:"
echo "  1. Check Claude Desktop's error console/logs"
echo "  2. Verify remote server is still accessible"
echo "  3. Test manually: TASKFLOW_API_KEY=\"$API_KEY\" TASKFLOW_BASE_URL=\"http://$REMOTE_SERVER:8080\" node test-mcp-server.js"
echo ""
print_info "Remote TaskFlow AI URL: http://$REMOTE_SERVER:8080"
print_info "Your laptop's MCP server will connect to the remote server"

# Optional: Open Claude Desktop config directory
if command -v open &> /dev/null && [[ "$OSTYPE" == "darwin"* ]]; then
    read -p "Open Claude Desktop config directory? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        open "$CLAUDE_CONFIG_DIR"
    fi
fi