#!/bin/bash
# Setup script to run on mashlap (your laptop)
# This sets up the MCP server to connect to SilentSteno

set -e

# Configuration
REMOTE_SERVER="192.168.5.128"
API_KEY="taskflow_539bb7ab05e67de83e114ba70e68f60e"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
print_success() { echo -e "${GREEN}✅ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }

echo "🚀 TaskFlow AI MCP Setup for mashlap"
echo "===================================="
echo ""

# Step 1: Check Node.js
print_info "Checking Node.js installation..."
if command -v node &> /dev/null; then
    NODE_VERSION=$(node --version)
    print_success "Node.js found: $NODE_VERSION"
else
    echo "❌ Node.js not found. Please install Node.js 18+ first:"
    echo "   Visit: https://nodejs.org/"
    exit 1
fi

# Step 2: Install dependencies
print_info "Installing MCP server dependencies..."
npm install --silent
print_success "Dependencies installed"

# Step 3: Test connectivity to TaskFlow AI server
print_info "Testing connectivity to TaskFlow AI on 192.168.5.128..."
if curl -s -m 5 http://$REMOTE_SERVER:8080 > /dev/null; then
    print_success "TaskFlow AI is accessible from mashlap"
else
    echo "❌ Cannot connect to TaskFlow AI on 192.168.5.128"
    echo "   Make sure TaskFlow AI is running: docker compose ps"
    exit 1
fi

# Step 4: Test API with current key
print_info "Testing API authentication..."
if curl -s -H "Authorization: Bearer $API_KEY" http://$REMOTE_SERVER:8080/api/tasks > /dev/null; then
    print_success "API authentication successful"
else
    print_warning "API authentication failed - you may need to update the API key"
    echo "   Get fresh key: curl -s http://$REMOTE_SERVER:8080 | grep -o 'taskflow_[a-f0-9]*'"
fi

# Step 5: Create Claude Desktop configuration
print_info "Setting up Claude Desktop configuration..."

# Detect OS and set config directory
if [[ "$OSTYPE" == "darwin"* ]]; then
    CLAUDE_DIR="$HOME/Library/Application Support/Claude"
elif [[ "$OSTYPE" == "msys" || "$OSTYPE" == "cygwin" ]]; then
    CLAUDE_DIR="$APPDATA/Claude"
else
    CLAUDE_DIR="$HOME/.config/claude"
fi

mkdir -p "$CLAUDE_DIR"
CONFIG_FILE="$CLAUDE_DIR/claude_desktop_config.json"

# Get absolute path for MCP server
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

cat > "$CONFIG_FILE" << EOF
{
  "mcpServers": {
    "taskflow-ai": {
      "command": "node",
      "args": ["$SCRIPT_DIR/mcp-server-standalone.js"],
      "env": {
        "TASKFLOW_API_KEY": "$API_KEY",
        "TASKFLOW_BASE_URL": "http://$REMOTE_SERVER:8080"
      }
    }
  }
}
EOF

print_success "Claude Desktop configuration created at: $CONFIG_FILE"

# Step 6: Test MCP server
print_info "Testing MCP server functionality..."
export TASKFLOW_API_KEY="$API_KEY"
export TASKFLOW_BASE_URL="http://$REMOTE_SERVER:8080"

if timeout 15 node test-mcp-server.js > /dev/null 2>&1; then
    print_success "MCP server test passed!"
else
    print_warning "MCP server test had issues, but continuing..."
fi

echo ""
echo "🎉 Setup Complete!"
echo "=================="
echo ""
echo "📊 Configuration:"
echo "   Server: $REMOTE_SERVER:8080"
echo "   API Key: ${API_KEY:0:12}...${API_KEY: -8}"
echo "   Config: $CONFIG_FILE"
echo ""
echo "🔄 Next Steps:"
echo "   1. Restart Claude Desktop to load the MCP configuration"
echo "   2. Test in Claude Desktop:"
echo "      • 'Show me my tasks for today'"
echo "      • 'Create a test task'"
echo "      • 'What projects do I have?'"
echo ""
echo "🔧 If API key changes (after Docker restart):"
echo "   1. Get new key: curl -s http://192.168.5.128:8080 | grep -o 'taskflow_[a-f0-9]*'"
echo "   2. Edit: $CONFIG_FILE"
echo "   3. Restart Claude Desktop"