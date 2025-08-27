# TaskFlow AI MCP Setup for mashlap

This directory contains everything needed to set up the TaskFlow AI MCP server on your laptop (mashlap).

## Quick Setup

1. **Copy this entire directory to your laptop** (mashlap)
2. **Run the setup script:**
   ```bash
   cd laptop-setup
   chmod +x setup-on-mashlap.sh
   ./setup-on-mashlap.sh
   ```
3. **Restart Claude Desktop**
4. **Test in Claude Desktop:**
   - "Show me my tasks for today"
   - "Create a test task"

## Files Included

- `mcp-server-standalone.js` - The MCP server that connects to SilentSteno
- `package.json` - Node.js dependencies
- `setup-on-mashlap.sh` - Automated setup script
- `test-mcp-server.js` - Test script to verify everything works
- `README.md` - This file

## Configuration

The setup script configures Claude Desktop to:
- Connect to TaskFlow AI at `192.168.5.128:8080`
- Use API key: `taskflow_539bb7ab05e67de83e114ba70e68f60e`

## Requirements

- Node.js 18+ on mashlap
- Claude Desktop installed
- Network access to 192.168.5.128

## If Something Goes Wrong

1. **API Key Changed?** 
   - Get new key: `curl -s http://192.168.5.128:8080 | grep -o 'taskflow_[a-f0-9]*'`
   - Edit Claude Desktop config file

2. **Connection Issues?**
   - Test: `curl http://192.168.5.128:8080`
   - Check TaskFlow AI is running on 192.168.5.128

3. **MCP Not Working?**
   - Test manually: `TASKFLOW_API_KEY="key" TASKFLOW_BASE_URL="http://192.168.5.128:8080" node test-mcp-server.js`
   - Check Claude Desktop logs

## Architecture

```
mashlap (laptop)          192.168.5.128 (server)
┌─────────────┐          ┌─────────────┐
│Claude Desktop│ ◄──────► │TaskFlow AI  │
│MCP Server   │   HTTP   │   :8080     │
└─────────────┘          └─────────────┘
```