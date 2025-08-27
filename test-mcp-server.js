#!/usr/bin/env node

/**
 * Test script for TaskFlow AI MCP Server
 * This script tests the MCP server functionality without requiring Claude Desktop
 */

const { spawn } = require('child_process');
const readline = require('readline');

class MCPTester {
  constructor() {
    this.apiKey = process.env.TASKFLOW_API_KEY;
    this.baseUrl = process.env.TASKFLOW_BASE_URL || 'http://localhost:8080';
    
    if (!this.apiKey) {
      console.error('❌ TASKFLOW_API_KEY environment variable is required');
      console.error('Usage: TASKFLOW_API_KEY="your_key" node test-mcp-server.js');
      process.exit(1);
    }
  }

  async testMCPServer() {
    console.log('🧪 Testing TaskFlow AI MCP Server...\n');
    
    // Test cases
    const tests = [
      {
        name: 'List available tools',
        request: {
          jsonrpc: '2.0',
          id: 1,
          method: 'tools/list',
          params: {}
        }
      },
      {
        name: 'Get today\'s plan',
        request: {
          jsonrpc: '2.0',
          id: 2,
          method: 'tools/call',
          params: {
            name: 'get_todays_plan',
            arguments: {}
          }
        }
      },
      {
        name: 'Get all tasks',
        request: {
          jsonrpc: '2.0',
          id: 3,
          method: 'tools/call',
          params: {
            name: 'get_tasks',
            arguments: {}
          }
        }
      },
      {
        name: 'Create a test scrap',
        request: {
          jsonrpc: '2.0',
          id: 4,
          method: 'tools/call',
          params: {
            name: 'create_scrap',
            arguments: {
              content: 'Test scrap created by MCP server test'
            }
          }
        }
      },
      {
        name: 'Create a test task',
        request: {
          jsonrpc: '2.0',
          id: 5,
          method: 'tools/call',
          params: {
            name: 'create_task',
            arguments: {
              title: 'Test Task from MCP',
              description: 'This task was created via MCP server test',
              priority: 'medium',
              due_date: new Date(Date.now() + 24*60*60*1000).toISOString().split('T')[0] // Tomorrow
            }
          }
        }
      }
    ];

    // Start MCP server process
    const mcpProcess = spawn('node', ['mcp-server-standalone.js'], {
      env: {
        ...process.env,
        TASKFLOW_API_KEY: this.apiKey,
        TASKFLOW_BASE_URL: this.baseUrl
      },
      stdio: ['pipe', 'pipe', 'pipe']
    });

    let isServerReady = false;
    
    // Wait for server to be ready
    mcpProcess.stderr.on('data', (data) => {
      const output = data.toString();
      if (output.includes('TaskFlow AI MCP Server running')) {
        isServerReady = true;
        console.log('✅ MCP Server started successfully\n');
        runTests();
      } else {
        console.error('MCP Server Error:', output);
      }
    });

    // Handle server errors
    mcpProcess.on('error', (error) => {
      console.error('❌ Failed to start MCP server:', error.message);
      process.exit(1);
    });

    // Function to run tests sequentially
    async function runTests() {
      for (const test of tests) {
        console.log(`🔍 ${test.name}...`);
        
        try {
          const result = await sendRequest(test.request, mcpProcess);
          console.log('✅ Success:', JSON.stringify(result, null, 2));
        } catch (error) {
          console.log('❌ Error:', error.message);
        }
        
        console.log(''); // Empty line for readability
      }
      
      console.log('🎉 All tests completed!');
      mcpProcess.kill();
      process.exit(0);
    }

    // Function to send a request to the MCP server
    function sendRequest(request, process) {
      return new Promise((resolve, reject) => {
        const requestStr = JSON.stringify(request) + '\n';
        
        // Set up response handler
        const onData = (data) => {
          try {
            const response = JSON.parse(data.toString().trim());
            process.stdout.removeListener('data', onData);
            
            if (response.error) {
              reject(new Error(response.error.message || 'Unknown error'));
            } else {
              resolve(response.result);
            }
          } catch (err) {
            reject(new Error('Invalid JSON response: ' + data.toString()));
          }
        };

        process.stdout.on('data', onData);
        
        // Send request
        process.stdin.write(requestStr);
        
        // Timeout after 10 seconds
        setTimeout(() => {
          process.stdout.removeListener('data', onData);
          reject(new Error('Request timeout'));
        }, 10000);
      });
    }

    // Give server time to start
    setTimeout(() => {
      if (!isServerReady) {
        console.error('❌ MCP server failed to start within timeout');
        mcpProcess.kill();
        process.exit(1);
      }
    }, 5000);
  }

  async verifyTaskFlowAI() {
    console.log('🔍 Verifying TaskFlow AI connectivity...');
    
    try {
      const fetch = (await import('node-fetch')).default;
      const response = await fetch(`${this.baseUrl}/api/tasks`, {
        headers: {
          'Authorization': `Bearer ${this.apiKey}`,
          'Content-Type': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      const tasks = await response.json();
      console.log(`✅ TaskFlow AI is accessible (found ${tasks.length} tasks)\n`);
      return true;
    } catch (error) {
      console.error('❌ Cannot connect to TaskFlow AI:', error.message);
      console.error('Make sure TaskFlow AI is running at:', this.baseUrl);
      return false;
    }
  }

  async run() {
    console.log('🚀 TaskFlow AI MCP Server Test Suite\n');
    
    console.log('Configuration:');
    console.log(`  Base URL: ${this.baseUrl}`);
    console.log(`  API Key: ${this.apiKey.substring(0, 12)}...`);
    console.log('');

    // First verify TaskFlow AI is accessible
    const isAccessible = await this.verifyTaskFlowAI();
    if (!isAccessible) {
      process.exit(1);
    }

    // Then test the MCP server
    await this.testMCPServer();
  }
}

// Run the test if this script is called directly
if (require.main === module) {
  const tester = new MCPTester();
  tester.run().catch(console.error);
}

module.exports = { MCPTester };