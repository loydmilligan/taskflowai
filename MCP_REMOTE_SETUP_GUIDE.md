# TaskFlow AI MCP Server - Remote Server Setup Guide

This guide covers setting up TaskFlow AI MCP Server when TaskFlow AI is running on a remote server (different from your laptop running Claude Desktop).

## 🌐 Network Architecture

```
[Your Laptop]              [Remote Server]
┌─────────────────┐        ┌─────────────────┐
│ Claude Desktop  │◄──────►│ TaskFlow AI     │
│                 │  HTTP  │ (Docker)        │
│ MCP Server      │        │ Port 8080       │
│ (Local Process) │        │                 │
└─────────────────┘        └─────────────────┘
```

## 🚀 Setup Options

### Option 1: MCP Server on Laptop (Recommended)

Run the MCP server locally on your laptop, connecting to the remote TaskFlow AI instance.

#### 1. On Remote Server: Deploy TaskFlow AI

```bash
# On your remote server (e.g., 192.168.1.100)
cd /path/to/taskflowai
docker compose up -d

# Get the API key
API_KEY=$(curl -s http://localhost:8080 | grep -o 'taskflow_[a-f0-9]*' | head -1)
echo "API Key: $API_KEY"

# Make note of your server's IP address
ip addr show | grep 'inet ' | grep -v '127.0.0.1'
```

#### 2. On Your Laptop: Setup MCP Server

```bash
# Clone/copy the TaskFlow AI files to your laptop
git clone https://github.com/your-repo/taskflowai.git
cd taskflowai

# Install Node.js dependencies
cp package-standalone.json package.json
npm install

# Test connectivity to remote server
curl -H "Authorization: Bearer YOUR_API_KEY" http://REMOTE_SERVER_IP:8080/api/tasks
```

#### 3. Configure Claude Desktop

Create/edit your Claude Desktop configuration file:

**macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`
**Windows:** `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "taskflow-ai": {
      "command": "node",
      "args": ["/full/path/to/taskflowai/mcp-server-standalone.js"],
      "env": {
        "TASKFLOW_API_KEY": "your_remote_api_key_here",
        "TASKFLOW_BASE_URL": "http://REMOTE_SERVER_IP:8080"
      }
    }
  }
}
```

**Replace:**
- `/full/path/to/taskflowai/` with the actual path on your laptop
- `your_remote_api_key_here` with the API key from your remote server
- `REMOTE_SERVER_IP` with your server's IP address (e.g., `192.168.1.100`)

### Option 2: MCP Server on Remote Server

Run both TaskFlow AI and MCP Server on the remote server, accessing via SSH.

#### 1. Setup on Remote Server

```bash
# On remote server
cd /path/to/taskflowai

# Start TaskFlow AI
docker compose up -d

# Install Node.js dependencies for MCP server
cp package-standalone.json package.json
npm install

# Get API key
API_KEY=$(curl -s http://localhost:8080 | grep -o 'taskflow_[a-f0-9]*' | head -1)
echo "API Key: $API_KEY"
```

#### 2. Configure Claude Desktop (SSH Method)

```json
{
  "mcpServers": {
    "taskflow-ai": {
      "command": "ssh",
      "args": [
        "user@REMOTE_SERVER_IP",
        "cd /path/to/taskflowai && TASKFLOW_API_KEY=your_api_key_here TASKFLOW_BASE_URL=http://localhost:8080 node mcp-server-standalone.js"
      ]
    }
  }
}
```

**Requirements for SSH method:**
- Password-less SSH setup (SSH keys)
- Node.js installed on remote server
- Persistent SSH connection

## 🔧 Network Configuration

### Firewall Setup

Ensure your remote server allows incoming connections on port 8080:

```bash
# Ubuntu/Debian
sudo ufw allow 8080

# CentOS/RHEL
sudo firewall-cmd --permanent --add-port=8080/tcp
sudo firewall-cmd --reload

# Check if port is accessible
sudo netstat -tlnp | grep :8080
```

### Docker Network Configuration

The existing docker-compose.yml already binds to all interfaces (`0.0.0.0:8080`). If you need to verify or modify:

```yaml
# Current configuration (already correct)
services:
  taskflow-ai:
    ports:
      - "8080:80"  # This binds to all interfaces (0.0.0.0:8080)
    
    # If you need to bind to a specific interface:
    # - "192.168.1.100:8080:80"  # Only accessible from this IP
```

### Testing Connectivity

From your laptop, test if you can reach the remote TaskFlow AI:

```bash
# Test basic connectivity
curl http://REMOTE_SERVER_IP:8080

# Test API with authentication
curl -H "Authorization: Bearer YOUR_API_KEY" http://REMOTE_SERVER_IP:8080/api/tasks

# If you get connection refused, check:
# 1. Server firewall settings
# 2. Docker container status: docker ps
# 3. Network connectivity: ping REMOTE_SERVER_IP
```

## 🔍 Troubleshooting Remote Setup

### Common Issues

**1. Connection Refused**
```bash
# Check if TaskFlow AI is running
docker ps
docker logs taskflow-ai

# Check if port is open
nmap -p 8080 REMOTE_SERVER_IP

# Check firewall
sudo ufw status
```

**2. API Key Issues**
```bash
# Get fresh API key from remote server
ssh user@REMOTE_SERVER_IP
curl -s http://localhost:8080 | grep -o 'taskflow_[a-f0-9]*' | head -1
```

**3. Network Routing Issues**
```bash
# Test from laptop
traceroute REMOTE_SERVER_IP
telnet REMOTE_SERVER_IP 8080

# Test from server
ss -tlnp | grep :8080
netstat -tlnp | grep :8080
```

**4. Docker Internal Networking**
```bash
# Check container network
docker network ls
docker inspect taskflow-ai | grep IPAddress

# Restart if needed
docker compose down && docker compose up -d
```

### MCP Server Connection Issues

**1. DNS Resolution**
If using hostname instead of IP:

```json
{
  "mcpServers": {
    "taskflow-ai": {
      "command": "node",
      "args": ["/path/to/mcp-server-standalone.js"],
      "env": {
        "TASKFLOW_API_KEY": "your_key",
        "TASKFLOW_BASE_URL": "http://your-server-hostname.local:8080"
      }
    }
  }
}
```

**2. Testing MCP Server Locally**
```bash
# On your laptop, test the MCP server
export TASKFLOW_API_KEY="your_key"
export TASKFLOW_BASE_URL="http://REMOTE_SERVER_IP:8080"
node test-mcp-server.js
```

**3. Claude Desktop Debug Mode**
Enable debug logging in Claude Desktop to see connection issues:
- Check Claude Desktop logs/console for MCP errors
- Verify the configuration file syntax is valid JSON

## 🚀 Quick Setup Script for Remote Deployment

Create this script on your laptop for easy setup:

```bash
#!/bin/bash
# setup-remote-mcp.sh

# Configuration
REMOTE_SERVER="192.168.1.100"  # Change this to your server IP
REMOTE_USER="your_username"    # Change this to your username

echo "🌐 Setting up TaskFlow AI MCP for remote server..."

# Step 1: Get API key from remote server
echo "🔑 Getting API key from remote server..."
API_KEY=$(ssh $REMOTE_USER@$REMOTE_SERVER "curl -s http://localhost:8080 | grep -o 'taskflow_[a-f0-9]*' | head -1")

if [ -z "$API_KEY" ]; then
    echo "❌ Failed to get API key. Is TaskFlow AI running on the remote server?"
    exit 1
fi

echo "✅ Got API key: $API_KEY"

# Step 2: Test connectivity
echo "🔍 Testing connectivity..."
if curl -s -H "Authorization: Bearer $API_KEY" http://$REMOTE_SERVER:8080/api/tasks > /dev/null; then
    echo "✅ Remote TaskFlow AI is accessible"
else
    echo "❌ Cannot connect to remote TaskFlow AI. Check firewall and network settings."
    exit 1
fi

# Step 3: Setup local MCP server
echo "🔧 Setting up local MCP server..."
if [ ! -f "package.json" ]; then
    cp package-standalone.json package.json
    npm install
fi

# Step 4: Create Claude Desktop configuration
CLAUDE_CONFIG_DIR=""
if [[ "$OSTYPE" == "darwin"* ]]; then
    CLAUDE_CONFIG_DIR="$HOME/Library/Application Support/Claude"
elif [[ "$OSTYPE" == "msys" || "$OSTYPE" == "win32" ]]; then
    CLAUDE_CONFIG_DIR="$APPDATA/Claude"
else
    CLAUDE_CONFIG_DIR="$HOME/.config/claude"
fi

mkdir -p "$CLAUDE_CONFIG_DIR"
CONFIG_FILE="$CLAUDE_CONFIG_DIR/claude_desktop_config.json"

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

echo "✅ Claude Desktop configuration created at: $CONFIG_FILE"

# Step 5: Test MCP server
echo "🧪 Testing MCP server..."
export TASKFLOW_API_KEY="$API_KEY"
export TASKFLOW_BASE_URL="http://$REMOTE_SERVER:8080"

if node test-mcp-server.js; then
    echo "✅ MCP server test passed!"
else
    echo "❌ MCP server test failed. Check the logs above."
    exit 1
fi

echo ""
echo "🎉 Setup complete! "
echo ""
echo "Next steps:"
echo "1. Restart Claude Desktop to load the new MCP configuration"
echo "2. In Claude Desktop, you can now use commands like:"
echo "   - 'Show me my tasks for today'"
echo "   - 'Create a task to review the quarterly report'"
echo "   - 'What projects do I have?'"
echo ""
echo "📊 Remote TaskFlow AI URL: http://$REMOTE_SERVER:8080"
echo "🔑 API Key: $API_KEY"
```

Save this as `setup-remote-mcp.sh` and run:

```bash
chmod +x setup-remote-mcp.sh
./setup-remote-mcp.sh
```

## 🔒 Security Considerations for Remote Setup

### Network Security
1. **Use HTTPS in Production**: Consider setting up nginx with SSL
2. **VPN Access**: Use VPN for accessing internal servers
3. **Firewall Rules**: Restrict access to specific IP ranges
4. **API Key Management**: Rotate keys regularly

### Example Nginx SSL Configuration
```nginx
server {
    listen 443 ssl;
    server_name taskflow.yourdomain.com;
    
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

## 📋 Checklist for Remote Setup

- [ ] TaskFlow AI running on remote server (port 8080 accessible)
- [ ] Firewall configured to allow port 8080
- [ ] API key obtained from remote server
- [ ] Network connectivity tested from laptop
- [ ] MCP server files copied to laptop
- [ ] Node.js dependencies installed on laptop
- [ ] Claude Desktop configuration updated
- [ ] Claude Desktop restarted
- [ ] MCP server functionality tested

With this setup, you'll have Claude Desktop on your laptop seamlessly managing tasks on your remote TaskFlow AI server!

<function_calls>
<invoke name="Read">
<parameter name="file_path">/home/mmariani/taskflowai/docker-compose.yml