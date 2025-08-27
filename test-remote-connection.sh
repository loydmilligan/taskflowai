#!/bin/bash
# Test connectivity to remote TaskFlow AI server

# Configuration
REMOTE_SERVER="${1:-}"
API_KEY="${2:-}"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_success() { echo -e "${GREEN}✅ $1${NC}"; }
print_error() { echo -e "${RED}❌ $1${NC}"; }
print_info() { echo -e "${YELLOW}ℹ️  $1${NC}"; }

if [ -z "$REMOTE_SERVER" ]; then
    echo "Usage: $0 <remote_server_ip> [api_key]"
    echo "Example: $0 192.168.1.100"
    echo "         $0 192.168.1.100 taskflow_abc123..."
    exit 1
fi

echo "🔍 Testing connectivity to TaskFlow AI on $REMOTE_SERVER"
echo "======================================================"
echo ""

# Test 1: Basic connectivity
print_info "Testing basic network connectivity..."
if ping -c 1 -W 3 $REMOTE_SERVER >/dev/null 2>&1; then
    print_success "Server is reachable"
else
    print_error "Server is not reachable via ping"
    exit 1
fi

# Test 2: Port connectivity
print_info "Testing port 8080 connectivity..."
if timeout 5 bash -c "</dev/tcp/$REMOTE_SERVER/8080" >/dev/null 2>&1; then
    print_success "Port 8080 is accessible"
else
    print_error "Port 8080 is not accessible"
    echo "  - Check firewall settings on remote server"
    echo "  - Ensure TaskFlow AI Docker container is running"
    echo "  - Verify Docker port binding: docker ps"
    exit 1
fi

# Test 3: HTTP connectivity
print_info "Testing HTTP response..."
if curl -s -m 10 http://$REMOTE_SERVER:8080 >/dev/null; then
    print_success "TaskFlow AI web interface is responding"
else
    print_error "TaskFlow AI web interface is not responding"
    exit 1
fi

# Test 4: Get API key if not provided
if [ -z "$API_KEY" ]; then
    print_info "Attempting to extract API key..."
    API_KEY=$(curl -s -m 10 http://$REMOTE_SERVER:8080 | grep -o 'taskflow_[a-f0-9]*' | head -1)
    
    if [ -z "$API_KEY" ]; then
        print_error "Could not extract API key from web interface"
        echo "  - Check if TaskFlow AI is fully initialized"
        echo "  - Try manually visiting: http://$REMOTE_SERVER:8080"
        exit 1
    else
        print_success "Extracted API key: ${API_KEY:0:12}..."
    fi
fi

# Test 5: API authentication
print_info "Testing API authentication..."
response=$(curl -s -m 10 -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $API_KEY" http://$REMOTE_SERVER:8080/api/tasks)

if [ "$response" = "200" ]; then
    print_success "API authentication successful"
else
    print_error "API authentication failed (HTTP $response)"
    echo "  - Verify API key is correct"
    echo "  - Check TaskFlow AI logs: docker logs taskflow-ai"
    exit 1
fi

# Test 6: API functionality
print_info "Testing API functionality..."
task_count=$(curl -s -m 10 -H "Authorization: Bearer $API_KEY" http://$REMOTE_SERVER:8080/api/tasks | jq '. | length' 2>/dev/null || echo "0")
print_success "API returned $task_count tasks"

plan_response=$(curl -s -m 10 -H "Authorization: Bearer $API_KEY" http://$REMOTE_SERVER:8080/api/plan)
if echo "$plan_response" | grep -q '"date"'; then
    print_success "Today's plan API is working"
else
    print_error "Today's plan API returned unexpected response"
fi

echo ""
echo "🎉 All connectivity tests passed!"
echo ""
echo "📋 Connection Details:"
echo "  Server: $REMOTE_SERVER:8080"
echo "  API Key: ${API_KEY:0:12}...${API_KEY: -8}"
echo ""
echo "🔧 Ready for MCP setup!"
echo "Next step: Edit setup-remote-mcp.sh and set:"
echo "  REMOTE_SERVER=\"$REMOTE_SERVER\""
echo "  REMOTE_USER=\"your_username\""
echo ""
echo "Then run: ./setup-remote-mcp.sh"