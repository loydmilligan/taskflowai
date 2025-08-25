# TaskFlow AI - One Shot Spec

A mobile-first project and task management web app where users interact primarily through an AI chat interface powered by Google Gemini to manage projects, tasks, notes, and daily planning.

## Core Features

* **AI Chat Interface**: Primary interaction method for creating, editing, and navigating through all app functions using Google Gemini
* **Entity Management**: Create and manage Projects, Tasks, Notes, and Scraps with tags, areas, and due dates
* **Daily Planning Workflow**: Plan view shows today's agenda with auto-assigned due tasks and space for notes/scraps
* **Morning Processing**: Visual prompts before a set morning time to process Scraps into Tasks or Notes via the Scratchpad
* **Multiple Views**: Scratchpad, Projects (cards/kanban), Tasks, Plan, Notes, Calendar, and Settings
* **Smart Conversion**: Convert Scraps to Tasks (with due dates/priority) or Notes (reference material)
* **Date Assignment**: Assign Notes and Scraps to specific dates or date ranges for Plan view visibility
* **Mobile-First Design**: Touch-optimized interface with chat as primary input method
* **REST API**: Full API access with API key authentication for external integrations
* **Notifications**: ntfy.sh integration for task reminders and daily processing prompts

## Implementation Requirements

* **Single User System**: No multi-user complexity, focus on personal productivity workflow
* **Chat-Driven Editing**: All entity creation/modification happens through AI chat, minimal forms
* **Persistent Chat**: Chat history maintained across sessions for context and navigation
* **Smart Auto-Assignment**: Tasks due today automatically appear in today's Plan view
* **Morning Workflow**: Time-based UI prompts to encourage daily Scrap processing before work begins
* **Flexible Entity Model**: Scraps can become Tasks (actionable, due dates) or Notes (reference, no due dates)
* **Date Range Support**: Notes and Scraps can be assigned to date ranges for planning visibility
* **Kanban Projects**: Projects display as both card lists and kanban boards for visual management
* **API-First Design**: All app functions accessible via REST endpoints with API key security

## Technical Implementation

* Use **PHP + SQLite + Vanilla JavaScript** (single index.php file with basic routing)
* Google Gemini API integration for AI chat functionality  
* Mobile-first responsive CSS with touch-optimized interface
* Simple session management for user preferences and chat history
* SQLite database with tables for: projects, tasks, notes, scraps, tags, areas, chat_history, settings
* AJAX for chat interface and real-time updates without page refreshes
* Basic API key generation and validation for REST endpoints
* ntfy.sh HTTP POST integration for notifications
* Minimal JavaScript for chat UI, view switching, and drag-and-drop kanban

## Database Schema

* **projects** (id, name, description, status, created_at, tags, area)
* **tasks** (id, project_id, title, description, due_date, priority, status, tags, area, created_at)
* **notes** (id, title, content, date_assigned, date_range_end, tags, area, created_at)
* **scraps** (id, content, date_assigned, date_range_end, processed, created_at)
* **chat_history** (id, message, response, timestamp, context)
* **settings** (key, value) - for morning_time, gemini_api_key, ntfy_topic, etc.
* **api_keys** (id, key_hash, name, created_at, last_used)

## Success Criteria

* **Core Chat Workflow**: Users can create, edit, and manage all entities through natural language chat
* **Daily Planning**: Plan view shows relevant tasks, notes, and scraps for effective daily workflow
* **Morning Processing**: Scratchpad effectively guides users to process scraps into actionable items
* **Mobile Experience**: App works smoothly on mobile devices with touch navigation and chat input
* **API Functionality**: External tools can integrate via REST API for automation and extensions
* **AI Integration**: Google Gemini provides intelligent responses for task management and planning

## View Structure

* **Scratchpad**: Morning processing interface for converting scraps to tasks/notes
* **Plan**: Today's agenda with due tasks, assigned notes/scraps, and quick add via chat  
* **Projects**: Card view and kanban board toggle for visual project management
* **Tasks**: List view with filtering by due date, priority, tags, areas
* **Notes**: Reference material organized by tags, areas, and date assignments
* **Calendar**: Month/week view showing tasks, notes, and scrap assignments
* **Settings**: Gemini API key, morning time, ntfy topic, API key management

## Claude Code Integration Note

This project leverages Claude Code's 4-phase workflow (Research → Design → Frontend → Backend) and can utilize specialized subagents for code review, debugging, and testing. The AI chat functionality will require careful prompt engineering and API integration testing.

## Constraints

* **Single File Architecture**: Keep core app in one PHP file with simple routing
* **No Complex Frameworks**: Vanilla JavaScript/CSS only, no build processes
* **API-First Design**: Every user action should have a corresponding API endpoint
* **Mobile Priority**: Design for mobile first, desktop is secondary
* **Simple AI Integration**: Start with basic Gemini text completion, avoid complex AI features initially
* **Local Storage**: SQLite only, no external databases or cloud dependencies
* **Minimal Dependencies**: Google Gemini API and ntfy.sh only, everything else vanilla

## Simplified Initial Version

For rapid prototyping, implement core chat interface with basic CRUD operations first. Mock the morning workflow and advanced filtering initially. Focus on proving the chat-driven interaction model works before adding complex features like kanban boards or date range assignments.
