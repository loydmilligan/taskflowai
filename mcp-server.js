#!/usr/bin/env node

/**
 * TaskFlow AI MCP Server
 * Provides Model Context Protocol interface for TaskFlow AI
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';

class TaskFlowMCPServer {
  constructor() {
    this.server = new Server(
      {
        name: 'taskflow-ai-mcp',
        version: '0.1.0',
      },
      {
        capabilities: {
          tools: {},
        },
      }
    );

    // Default configuration - can be overridden by environment variables
    this.config = {
      baseUrl: process.env.TASKFLOW_BASE_URL || 'http://localhost:8080',
      apiKey: process.env.TASKFLOW_API_KEY || '',
    };

    this.setupToolHandlers();
    this.setupErrorHandling();
  }

  setupErrorHandling() {
    this.server.onerror = (error) => console.error('[MCP Error]', error);
    process.on('SIGINT', async () => {
      await this.server.close();
      process.exit(0);
    });
  }

  async makeApiCall(endpoint, options = {}) {
    const url = `${this.config.baseUrl}${endpoint}`;
    const config = {
      headers: {
        'Authorization': `Bearer ${this.config.apiKey}`,
        'Content-Type': 'application/json',
        ...options.headers,
      },
      ...options,
    };

    try {
      const response = await fetch(url, config);
      
      if (!response.ok) {
        throw new Error(`API Error: ${response.status} - ${response.statusText}`);
      }

      const data = await response.json();
      return data;
    } catch (error) {
      throw new Error(`Failed to call TaskFlow AI API: ${error.message}`);
    }
  }

  setupToolHandlers() {
    // List available tools
    this.server.setRequestHandler(ListToolsRequestSchema, async () => {
      return {
        tools: [
          {
            name: 'get_tasks',
            description: 'Get all tasks or filter by status, priority, project, or date',
            inputSchema: {
              type: 'object',
              properties: {
                status: {
                  type: 'string',
                  enum: ['pending', 'in_progress', 'completed'],
                  description: 'Filter by task status',
                },
                priority: {
                  type: 'string',
                  enum: ['low', 'medium', 'high'],
                  description: 'Filter by priority level',
                },
                project_id: {
                  type: 'number',
                  description: 'Filter by project ID',
                },
                due_date: {
                  type: 'string',
                  format: 'date',
                  description: 'Filter by due date (YYYY-MM-DD)',
                },
                overdue: {
                  type: 'boolean',
                  description: 'Show only overdue tasks',
                },
              },
              additionalProperties: false,
            },
          },
          {
            name: 'create_task',
            description: 'Create a new task',
            inputSchema: {
              type: 'object',
              properties: {
                title: {
                  type: 'string',
                  description: 'Task title (required)',
                },
                description: {
                  type: 'string',
                  description: 'Task description',
                },
                due_date: {
                  type: 'string',
                  format: 'date',
                  description: 'Due date (YYYY-MM-DD)',
                },
                priority: {
                  type: 'string',
                  enum: ['low', 'medium', 'high'],
                  description: 'Priority level',
                  default: 'medium',
                },
                status: {
                  type: 'string',
                  enum: ['pending', 'in_progress', 'completed'],
                  description: 'Task status',
                  default: 'pending',
                },
                project_id: {
                  type: 'number',
                  description: 'Project ID to assign task to',
                },
                tags: {
                  type: 'array',
                  items: { type: 'string' },
                  description: 'Tags for the task',
                },
                area: {
                  type: 'string',
                  description: 'Area or category for the task',
                },
              },
              required: ['title'],
              additionalProperties: false,
            },
          },
          {
            name: 'update_task',
            description: 'Update an existing task',
            inputSchema: {
              type: 'object',
              properties: {
                id: {
                  type: 'number',
                  description: 'Task ID (required)',
                },
                title: {
                  type: 'string',
                  description: 'Task title',
                },
                description: {
                  type: 'string',
                  description: 'Task description',
                },
                due_date: {
                  type: 'string',
                  format: 'date',
                  description: 'Due date (YYYY-MM-DD)',
                },
                priority: {
                  type: 'string',
                  enum: ['low', 'medium', 'high'],
                  description: 'Priority level',
                },
                status: {
                  type: 'string',
                  enum: ['pending', 'in_progress', 'completed'],
                  description: 'Task status',
                },
                project_id: {
                  type: 'number',
                  description: 'Project ID to assign task to',
                },
                tags: {
                  type: 'array',
                  items: { type: 'string' },
                  description: 'Tags for the task',
                },
                area: {
                  type: 'string',
                  description: 'Area or category for the task',
                },
              },
              required: ['id'],
              additionalProperties: false,
            },
          },
          {
            name: 'delete_task',
            description: 'Delete a task',
            inputSchema: {
              type: 'object',
              properties: {
                id: {
                  type: 'number',
                  description: 'Task ID (required)',
                },
              },
              required: ['id'],
              additionalProperties: false,
            },
          },
          {
            name: 'get_projects',
            description: 'Get all projects or filter by status and area',
            inputSchema: {
              type: 'object',
              properties: {
                status: {
                  type: 'string',
                  enum: ['active', 'completed', 'archived'],
                  description: 'Filter by project status',
                },
                area: {
                  type: 'string',
                  description: 'Filter by area',
                },
              },
              additionalProperties: false,
            },
          },
          {
            name: 'create_project',
            description: 'Create a new project',
            inputSchema: {
              type: 'object',
              properties: {
                name: {
                  type: 'string',
                  description: 'Project name (required)',
                },
                description: {
                  type: 'string',
                  description: 'Project description',
                },
                status: {
                  type: 'string',
                  enum: ['active', 'completed', 'archived'],
                  description: 'Project status',
                  default: 'active',
                },
                tags: {
                  type: 'array',
                  items: { type: 'string' },
                  description: 'Tags for the project',
                },
                area: {
                  type: 'string',
                  description: 'Area or category for the project',
                },
              },
              required: ['name'],
              additionalProperties: false,
            },
          },
          {
            name: 'update_project',
            description: 'Update an existing project',
            inputSchema: {
              type: 'object',
              properties: {
                id: {
                  type: 'number',
                  description: 'Project ID (required)',
                },
                name: {
                  type: 'string',
                  description: 'Project name',
                },
                description: {
                  type: 'string',
                  description: 'Project description',
                },
                status: {
                  type: 'string',
                  enum: ['active', 'completed', 'archived'],
                  description: 'Project status',
                },
                tags: {
                  type: 'array',
                  items: { type: 'string' },
                  description: 'Tags for the project',
                },
                area: {
                  type: 'string',
                  description: 'Area or category for the project',
                },
              },
              required: ['id'],
              additionalProperties: false,
            },
          },
          {
            name: 'delete_project',
            description: 'Delete a project',
            inputSchema: {
              type: 'object',
              properties: {
                id: {
                  type: 'number',
                  description: 'Project ID (required)',
                },
              },
              required: ['id'],
              additionalProperties: false,
            },
          },
          {
            name: 'get_notes',
            description: 'Get all notes or filter by area and date',
            inputSchema: {
              type: 'object',
              properties: {
                area: {
                  type: 'string',
                  description: 'Filter by area',
                },
                date_assigned: {
                  type: 'string',
                  format: 'date',
                  description: 'Filter by assigned date (YYYY-MM-DD)',
                },
              },
              additionalProperties: false,
            },
          },
          {
            name: 'create_note',
            description: 'Create a new note',
            inputSchema: {
              type: 'object',
              properties: {
                title: {
                  type: 'string',
                  description: 'Note title (required)',
                },
                content: {
                  type: 'string',
                  description: 'Note content (required)',
                },
                date_assigned: {
                  type: 'string',
                  format: 'date',
                  description: 'Date assigned (YYYY-MM-DD)',
                },
                date_range_end: {
                  type: 'string',
                  format: 'date',
                  description: 'End date for date range (YYYY-MM-DD)',
                },
                tags: {
                  type: 'array',
                  items: { type: 'string' },
                  description: 'Tags for the note',
                },
                area: {
                  type: 'string',
                  description: 'Area or category for the note',
                },
              },
              required: ['title', 'content'],
              additionalProperties: false,
            },
          },
          {
            name: 'update_note',
            description: 'Update an existing note',
            inputSchema: {
              type: 'object',
              properties: {
                id: {
                  type: 'number',
                  description: 'Note ID (required)',
                },
                title: {
                  type: 'string',
                  description: 'Note title',
                },
                content: {
                  type: 'string',
                  description: 'Note content',
                },
                date_assigned: {
                  type: 'string',
                  format: 'date',
                  description: 'Date assigned (YYYY-MM-DD)',
                },
                date_range_end: {
                  type: 'string',
                  format: 'date',
                  description: 'End date for date range (YYYY-MM-DD)',
                },
                tags: {
                  type: 'array',
                  items: { type: 'string' },
                  description: 'Tags for the note',
                },
                area: {
                  type: 'string',
                  description: 'Area or category for the note',
                },
              },
              required: ['id'],
              additionalProperties: false,
            },
          },
          {
            name: 'delete_note',
            description: 'Delete a note',
            inputSchema: {
              type: 'object',
              properties: {
                id: {
                  type: 'number',
                  description: 'Note ID (required)',
                },
              },
              required: ['id'],
              additionalProperties: false,
            },
          },
          {
            name: 'get_scraps',
            description: 'Get all scraps or filter by processed status',
            inputSchema: {
              type: 'object',
              properties: {
                processed: {
                  type: 'boolean',
                  description: 'Filter by processed status',
                },
              },
              additionalProperties: false,
            },
          },
          {
            name: 'create_scrap',
            description: 'Create a new scrap (raw thought/idea)',
            inputSchema: {
              type: 'object',
              properties: {
                content: {
                  type: 'string',
                  description: 'Scrap content (required)',
                },
                date_assigned: {
                  type: 'string',
                  format: 'date',
                  description: 'Date assigned (YYYY-MM-DD)',
                },
                date_range_end: {
                  type: 'string',
                  format: 'date',
                  description: 'End date for date range (YYYY-MM-DD)',
                },
              },
              required: ['content'],
              additionalProperties: false,
            },
          },
          {
            name: 'convert_scrap',
            description: 'Convert a scrap to a task or note',
            inputSchema: {
              type: 'object',
              properties: {
                scrap_id: {
                  type: 'number',
                  description: 'Scrap ID to convert (required)',
                },
                to: {
                  type: 'string',
                  enum: ['task', 'note'],
                  description: 'Convert to task or note (required)',
                },
                data: {
                  type: 'object',
                  description: 'Task or note data for the conversion',
                  properties: {
                    title: { type: 'string' },
                    description: { type: 'string' },
                    content: { type: 'string' },
                    due_date: { type: 'string', format: 'date' },
                    priority: { type: 'string', enum: ['low', 'medium', 'high'] },
                    tags: { type: 'array', items: { type: 'string' } },
                    area: { type: 'string' },
                  },
                },
              },
              required: ['scrap_id', 'to', 'data'],
              additionalProperties: false,
            },
          },
          {
            name: 'get_todays_plan',
            description: 'Get today\'s plan with tasks due today and overdue tasks',
            inputSchema: {
              type: 'object',
              properties: {},
              additionalProperties: false,
            },
          },
          {
            name: 'get_plan_for_date',
            description: 'Get plan for a specific date',
            inputSchema: {
              type: 'object',
              properties: {
                date: {
                  type: 'string',
                  format: 'date',
                  description: 'Date to get plan for (YYYY-MM-DD, required)',
                },
              },
              required: ['date'],
              additionalProperties: false,
            },
          },
          {
            name: 'chat_with_ai',
            description: 'Send a message to TaskFlow AI\'s internal chat system (requires Gemini API key)',
            inputSchema: {
              type: 'object',
              properties: {
                message: {
                  type: 'string',
                  description: 'Message to send to the AI (required)',
                },
              },
              required: ['message'],
              additionalProperties: false,
            },
          },
        ],
      };
    });

    // Handle tool calls
    this.server.setRequestHandler(CallToolRequestSchema, async (request) => {
      const { name, arguments: args } = request.params;

      try {
        switch (name) {
          case 'get_tasks':
            return await this.getTasks(args);
          case 'create_task':
            return await this.createTask(args);
          case 'update_task':
            return await this.updateTask(args);
          case 'delete_task':
            return await this.deleteTask(args);
          case 'get_projects':
            return await this.getProjects(args);
          case 'create_project':
            return await this.createProject(args);
          case 'update_project':
            return await this.updateProject(args);
          case 'delete_project':
            return await this.deleteProject(args);
          case 'get_notes':
            return await this.getNotes(args);
          case 'create_note':
            return await this.createNote(args);
          case 'update_note':
            return await this.updateNote(args);
          case 'delete_note':
            return await this.deleteNote(args);
          case 'get_scraps':
            return await this.getScraps(args);
          case 'create_scrap':
            return await this.createScrap(args);
          case 'convert_scrap':
            return await this.convertScrap(args);
          case 'get_todays_plan':
            return await this.getTodaysPlan();
          case 'get_plan_for_date':
            return await this.getPlanForDate(args);
          case 'chat_with_ai':
            return await this.chatWithAI(args);
          default:
            throw new Error(`Unknown tool: ${name}`);
        }
      } catch (error) {
        return {
          content: [
            {
              type: 'text',
              text: `Error: ${error.message}`,
            },
          ],
          isError: true,
        };
      }
    });
  }

  // Task operations
  async getTasks(args) {
    const params = new URLSearchParams();
    Object.entries(args).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        params.append(key, value.toString());
      }
    });
    
    const queryString = params.toString();
    const endpoint = `/api/tasks${queryString ? '?' + queryString : ''}`;
    const data = await this.makeApiCall(endpoint);
    
    return {
      content: [
        {
          type: 'text',
          text: `Found ${data.length} tasks:\n${JSON.stringify(data, null, 2)}`,
        },
      ],
    };
  }

  async createTask(args) {
    const data = await this.makeApiCall('/api/tasks', {
      method: 'POST',
      body: JSON.stringify(args),
    });
    
    return {
      content: [
        {
          type: 'text',
          text: `Task created successfully:\n${JSON.stringify(data, null, 2)}`,
        },
      ],
    };
  }

  async updateTask(args) {
    const { id, ...updates } = args;
    const data = await this.makeApiCall(`/api/tasks/${id}`, {
      method: 'PUT',
      body: JSON.stringify(updates),
    });
    
    return {
      content: [
        {
          type: 'text',
          text: `Task updated successfully:\n${JSON.stringify(data, null, 2)}`,
        },
      ],
    };
  }

  async deleteTask(args) {
    const data = await this.makeApiCall(`/api/tasks/${args.id}`, {
      method: 'DELETE',
    });
    
    return {
      content: [
        {
          type: 'text',
          text: `Task deleted successfully`,
        },
      ],
    };
  }

  // Project operations
  async getProjects(args) {
    const params = new URLSearchParams();
    Object.entries(args).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        params.append(key, value.toString());
      }
    });
    
    const queryString = params.toString();
    const endpoint = `/api/projects${queryString ? '?' + queryString : ''}`;
    const data = await this.makeApiCall(endpoint);
    
    return {
      content: [
        {
          type: 'text',
          text: `Found ${data.length} projects:\n${JSON.stringify(data, null, 2)}`,
        },
      ],
    };
  }

  async createProject(args) {
    const data = await this.makeApiCall('/api/projects', {
      method: 'POST',
      body: JSON.stringify(args),
    });
    
    return {
      content: [
        {
          type: 'text',
          text: `Project created successfully:\n${JSON.stringify(data, null, 2)}`,
        },
      ],
    };
  }

  async updateProject(args) {
    const { id, ...updates } = args;
    const data = await this.makeApiCall(`/api/projects/${id}`, {
      method: 'PUT',
      body: JSON.stringify(updates),
    });
    
    return {
      content: [
        {
          type: 'text',
          text: `Project updated successfully:\n${JSON.stringify(data, null, 2)}`,
        },
      ],
    };
  }

  async deleteProject(args) {
    const data = await this.makeApiCall(`/api/projects/${args.id}`, {
      method: 'DELETE',
    });
    
    return {
      content: [
        {
          type: 'text',
          text: `Project deleted successfully`,
        },
      ],
    };
  }

  // Note operations
  async getNotes(args) {
    const params = new URLSearchParams();
    Object.entries(args).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        params.append(key, value.toString());
      }
    });
    
    const queryString = params.toString();
    const endpoint = `/api/notes${queryString ? '?' + queryString : ''}`;
    const data = await this.makeApiCall(endpoint);
    
    return {
      content: [
        {
          type: 'text',
          text: `Found ${data.length} notes:\n${JSON.stringify(data, null, 2)}`,
        },
      ],
    };
  }

  async createNote(args) {
    const data = await this.makeApiCall('/api/notes', {
      method: 'POST',
      body: JSON.stringify(args),
    });
    
    return {
      content: [
        {
          type: 'text',
          text: `Note created successfully:\n${JSON.stringify(data, null, 2)}`,
        },
      ],
    };
  }

  async updateNote(args) {
    const { id, ...updates } = args;
    const data = await this.makeApiCall(`/api/notes/${id}`, {
      method: 'PUT',
      body: JSON.stringify(updates),
    });
    
    return {
      content: [
        {
          type: 'text',
          text: `Note updated successfully:\n${JSON.stringify(data, null, 2)}`,
        },
      ],
    };
  }

  async deleteNote(args) {
    const data = await this.makeApiCall(`/api/notes/${args.id}`, {
      method: 'DELETE',
    });
    
    return {
      content: [
        {
          type: 'text',
          text: `Note deleted successfully`,
        },
      ],
    };
  }

  // Scrap operations
  async getScraps(args) {
    const params = new URLSearchParams();
    if (args.processed !== undefined) {
      params.append('processed', args.processed.toString());
    }
    
    const queryString = params.toString();
    const endpoint = `/api/scraps${queryString ? '?' + queryString : ''}`;
    const data = await this.makeApiCall(endpoint);
    
    return {
      content: [
        {
          type: 'text',
          text: `Found ${data.length} scraps:\n${JSON.stringify(data, null, 2)}`,
        },
      ],
    };
  }

  async createScrap(args) {
    const data = await this.makeApiCall('/api/scraps', {
      method: 'POST',
      body: JSON.stringify(args),
    });
    
    return {
      content: [
        {
          type: 'text',
          text: `Scrap created successfully:\n${JSON.stringify(data, null, 2)}`,
        },
      ],
    };
  }

  async convertScrap(args) {
    const data = await this.makeApiCall(`/api/scraps/${args.scrap_id}/convert`, {
      method: 'POST',
      body: JSON.stringify({ to: args.to, data: args.data }),
    });
    
    return {
      content: [
        {
          type: 'text',
          text: `Scrap converted successfully:\n${JSON.stringify(data, null, 2)}`,
        },
      ],
    };
  }

  // Planning operations
  async getTodaysPlan() {
    const data = await this.makeApiCall('/api/plan');
    
    const summary = `Today's Plan (${data.date}):
- Tasks due today: ${data.today?.length || 0}
- Overdue tasks: ${data.overdue?.length || 0}

${JSON.stringify(data, null, 2)}`;
    
    return {
      content: [
        {
          type: 'text',
          text: summary,
        },
      ],
    };
  }

  async getPlanForDate(args) {
    const data = await this.makeApiCall(`/api/plan/${args.date}`);
    
    return {
      content: [
        {
          type: 'text',
          text: `Plan for ${args.date}:\n${JSON.stringify(data, null, 2)}`,
        },
      ],
    };
  }

  // AI Chat operation
  async chatWithAI(args) {
    const data = await this.makeApiCall('/api/chat', {
      method: 'POST',
      body: JSON.stringify({ message: args.message }),
    });
    
    let result = `AI Response: ${data.response}`;
    
    if (data.action_results && data.action_results.length > 0) {
      result += `\n\nActions performed: ${data.action_results.length}`;
      result += `\nAction results:\n${JSON.stringify(data.action_results, null, 2)}`;
    }
    
    return {
      content: [
        {
          type: 'text',
          text: result,
        },
      ],
    };
  }

  async run() {
    // Check if API key is provided
    if (!this.config.apiKey) {
      console.error('Error: TASKFLOW_API_KEY environment variable is required');
      process.exit(1);
    }

    const transport = new StdioServerTransport();
    await this.server.connect(transport);
    console.error('TaskFlow AI MCP Server running on stdio');
  }
}

// Check if this is being run directly
if (import.meta.url === `file://${process.argv[1]}`) {
  const server = new TaskFlowMCPServer();
  server.run().catch(console.error);
}

export { TaskFlowMCPServer };