# TaskFlow AI MCP Server Setup Guide

This guide will help you set up the Model Context Protocol (MCP) server for TaskFlow AI, allowing Claude Desktop to interact directly with your task management system.

## 🚀 Quick Start

### Option 1: Standalone MCP Server (Recommended)

This is the easiest way to get started with Claude Desktop integration.

#### 1. Prerequisites

- Node.js 18+ installed on your system
- TaskFlow AI running (either via Docker or standalone)
- Claude Desktop app installed

#### 2. Install Dependencies

```bash
cd /path/to/taskflowai
cp package-standalone.json package.json
npm install
```

#### 3. Get Your TaskFlow AI API Key

Start TaskFlow AI and get your API key:

```bash
# If using Docker
docker compose up -d
curl -s http://localhost:8080 | grep -o 'taskflow_[a-f0-9]*' | head -1

# If running standalone PHP
php -S localhost:8080 index.php
# Then visit http://localhost:8080 and go to Settings to get your API key
```

#### 4. Configure Claude Desktop

Add this configuration to your Claude Desktop MCP settings file:

**macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`
**Windows:** `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "taskflow-ai": {
      "command": "node",
      "args": ["/path/to/taskflowai/mcp-server-standalone.js"],
      "env": {
        "TASKFLOW_API_KEY": "your_api_key_here",
        "TASKFLOW_BASE_URL": "http://localhost:8080"
      }
    }
  }
}
```

Replace:
- `/path/to/taskflowai/` with the actual path to your TaskFlow AI directory
- `your_api_key_here` with your actual TaskFlow AI API key

#### 5. Restart Claude Desktop

Restart Claude Desktop to load the new MCP configuration.

### Option 2: Docker-based MCP Server

If you prefer to run everything in Docker:

#### 1. Start with MCP Support

```bash
cd /path/to/taskflowai
chmod +x start-mcp-server.sh
./start-mcp-server.sh
```

This script will:
- Start TaskFlow AI
- Extract the API key automatically
- Start the MCP server in a Docker container
- Display the configuration you need for Claude Desktop

#### 2. Configure Claude Desktop

Use the configuration displayed by the startup script, which will look like:

```json
{
  "mcpServers": {
    "taskflow-ai": {
      "command": "docker",
      "args": ["exec", "-i", "taskflow-mcp", "node", "mcp-server.js"],
      "env": {
        "TASKFLOW_API_KEY": "your_extracted_api_key",
        "TASKFLOW_BASE_URL": "http://host.docker.internal:8080"
      }
    }
  }
}
```

## 🛠 Available MCP Tools

Once configured, Claude Desktop will have access to these TaskFlow AI tools:

### Task Management
- `get_tasks` - Retrieve tasks with optional filtering
- `create_task` - Create new tasks with title, description, due date, priority
- `update_task` - Update existing tasks
- `delete_task` - Delete tasks

### Project Management  
- `get_projects` - Retrieve projects with optional filtering
- `create_project` - Create new projects

### Planning
- `get_todays_plan` - Get today's tasks and overdue items

### Quick Notes
- `create_scrap` - Quickly capture thoughts and ideas
- `get_scraps` - Retrieve scraps for processing

## 💬 Example Claude Desktop Interactions

Once set up, you can interact with TaskFlow AI through Claude Desktop like this:

**"Show me my tasks for today"**
- Claude will call `get_todays_plan` to show your current tasks

**"Create a high-priority task to review the quarterly report, due tomorrow"**
- Claude will call `create_task` with the appropriate parameters

**"Create a project called 'Website Redesign' for the marketing area"**
- Claude will call `create_project` to set up your new project

**"Update task ID 5 to mark it as completed"**
- Claude will call `update_task` to change the task status

## 🔧 Configuration Options

### Environment Variables

- `TASKFLOW_API_KEY` (required) - Your TaskFlow AI API key
- `TASKFLOW_BASE_URL` (optional) - TaskFlow AI base URL (default: http://localhost:8080)

### TaskFlow AI Settings

For full functionality, configure these in TaskFlow AI:
- **Google Gemini API Key** - Enables the internal AI chat system
- **App Name** - Customize your installation name

## 🐛 Troubleshooting

### Common Issues

**1. "Invalid API key" errors**
- Make sure you're using the correct API key from TaskFlow AI
- The API key changes each time you restart TaskFlow AI in Docker

**2. Connection refused errors**
- Ensure TaskFlow AI is running on the expected port (8080)
- Check that there are no firewall issues

**3. MCP server not appearing in Claude Desktop**
- Verify the configuration file path and syntax
- Restart Claude Desktop after configuration changes
- Check the Claude Desktop logs for error messages

**4. Tools not working**
- Verify TaskFlow AI API endpoints are working: `curl http://localhost:8080/api/tasks`
- Check that the API key has the correct permissions

### Testing the MCP Server

You can test the MCP server directly:

```bash
# Set environment variables
export TASKFLOW_API_KEY="your_api_key_here"
export TASKFLOW_BASE_URL="http://localhost:8080"

# Test the server
echo '{"jsonrpc": "2.0", "id": 1, "method": "tools/list", "params": {}}' | node mcp-server-standalone.js
```

### Debug Logging

To see what's happening behind the scenes:

```bash
# Run the MCP server with debug output
DEBUG=1 TASKFLOW_API_KEY="your_key" node mcp-server-standalone.js
```

## 📊 Integration Benefits

With MCP integration, you can:

1. **Natural Language Task Management** - "Add a task to call the client tomorrow"
2. **Quick Planning** - "What do I need to do today?"
3. **Project Organization** - "Create a project for the new marketing campaign"
4. **Context-Aware Updates** - Claude can see your existing tasks and projects
5. **Seamless Workflow** - Manage tasks without leaving Claude Desktop

## 🔄 Keeping Things Updated

When TaskFlow AI restarts (especially in Docker), you may need to:

1. Get the new API key
2. Update your Claude Desktop configuration
3. Restart Claude Desktop

Consider using the standalone version for more stability, or script the API key extraction for automated setups.

## 🆘 Getting Help

If you encounter issues:

1. Check the TaskFlow AI logs: `docker logs taskflow-ai`
2. Verify API connectivity: `curl -H "Authorization: Bearer YOUR_KEY" http://localhost:8080/api/tasks`
3. Test MCP server standalone: `node mcp-server-standalone.js`
4. Review Claude Desktop's MCP error logs

The integration should provide seamless task management capabilities directly within Claude Desktop, making your productivity workflow much more efficient!