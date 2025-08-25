README.md
TaskFlow AI
A mobile-first task management web app where you manage your projects, tasks, and daily planning by chatting with AI instead of filling out forms.
Overview
TaskFlow AI reimagines productivity software by making the primary interface a persistent chat conversation. Instead of clicking through forms and menus, you simply tell the AI what you need to do, and it handles the organization for you.
Key Features:

🤖 AI-First Interface: Create and manage everything through natural conversation
📱 Mobile Optimized: Designed for thumb-friendly mobile interaction
📋 Smart Planning: Automatic daily agenda with task due dates and assigned notes
🔄 Scrap Processing: Convert random thoughts into actionable tasks or reference notes
🎯 Morning Workflow: Daily prompts to organize your thoughts before work begins
🔗 API Complete: Full REST API for integrations and automation
🚀 Zero Setup: Single PHP file with SQLite - runs anywhere

Quick Start
Requirements

PHP 7.4+ with SQLite support
Google Gemini API key
Web server (Apache, Nginx, or php -S)

Installation

Clone or download this repository
Set up your environment:
bash# If using PHP built-in server
php -S localhost:8000 index.php

# Or copy to your web server directory
cp index.php /var/www/html/taskflow/

Configure Gemini API:

Get your API key from Google AI Studio
Open the app and go to Settings
Enter your Gemini API key
Optionally configure ntfy.sh topic for notifications


Start using:

Open the app on your phone or desktop
Start chatting: "Add a task to call the dentist next week"
The AI will create, organize, and help you manage your tasks



First Time Setup

Visit the app and go to Settings
Add your Gemini API Key (required for chat features)
Set your Morning Time (when you want to process daily scraps)
Optional: Add ntfy.sh topic for mobile notifications
Start chatting to create your first project or task!

How It Works
Core Concepts
Projects → Containers for related work, with card and kanban views
Tasks → Things that need doing, with due dates and priorities
Notes → Reference information, can be assigned to dates for planning
Scraps → Raw thoughts that get processed into tasks or notes
Daily Workflow

Morning Processing: Before your set morning time, process accumulated "scraps" (random thoughts) into actionable tasks or reference notes
Plan View: See today's agenda with due tasks automatically included, plus any notes or scraps you've assigned to today
Chat Throughout Day: "Add lunch meeting with Sarah tomorrow" or "Remember that the new client prefers email communication" - just talk naturally
Evening Review: Check tomorrow's plan, adjust priorities, add any final thoughts as scraps

Chat Examples
You: "Add a project for planning the company retreat"
AI: "Created project 'Company Retreat Planning'. Want to add any initial tasks?"

You: "Yes, add tasks for booking venue and sending invites"  
AI: "Added two tasks: 'Book venue' and 'Send invites'. Any due dates?"

You: "Venue by next Friday, invites by Monday"
AI: "Perfect! 'Book venue' due next Friday, 'Send invites' due Monday. Both will appear in your Plan view on their due dates."

You: "Show me my projects"
AI: "Switching to Projects view... You have 3 active projects: Company Retreat Planning, Website Redesign, and Q4 Budget Review."
API Usage
TaskFlow AI provides a complete REST API for automation and integrations.
Authentication
bash# Generate API key in Settings, then use in headers
curl -H "Authorization: Bearer your-api-key" http://localhost:8000/api/tasks
Key Endpoints
bash# List all tasks
GET /api/tasks

# Create a task
POST /api/tasks
{"title": "Call dentist", "due_date": "2024-01-15", "priority": "high"}

# Get today's plan  
GET /api/plan

# Send chat message
POST /api/chat
{"message": "What tasks do I have due this week?"}

# Convert scrap to task
POST /api/scraps/123/convert
{"type": "task", "due_date": "2024-01-20", "priority": "medium"}
See full API documentation at /api/docs when running the app.
Architecture
Single File Design
Everything runs from index.php:

Routing: Simple URL parsing for different views and API endpoints
Database: SQLite with schema auto-creation on first run
Chat: Gemini API integration with conversation context
Views: Inline HTML with mobile-first CSS
API: RESTful endpoints for all functionality

Mobile-First Approach

Touch-optimized interface with large tap targets
Bottom-fixed chat input always accessible
Swipe navigation between views
Responsive design from 375px mobile to desktop
Progressive web app capabilities

Data Storage
SQLite database with tables for projects, tasks, notes, scraps, chat history, and settings. No external dependencies or complex setup required.
Development
File Structure
taskflow-ai/
├── index.php          # Complete application
├── taskflow.db        # SQLite database (auto-created)
├── README.md          # This file
├── CLAUDE.md          # Claude AI development context
└── gemini.md          # Gemini AI development context
Development Workflow

Research & Planning: Validate concepts and technical approaches
Design & Architecture: Create mobile-first wireframes and API structure
Frontend Implementation: Build chat interface and responsive views
Backend Integration: Connect Gemini API and complete database operations

Contributing
This project follows a "working over perfect" philosophy:

Keep the single-file architecture
Mobile experience is always priority #1
Every feature must work via API
Chat interface is the primary innovation - keep everything else simple

Notifications
ntfy.sh Integration
Optional push notifications for:

Morning reminders to process scraps
Task due date alerts
Overdue task notifications

Configure your ntfy.sh topic in Settings to enable.
Privacy & Data

Local Storage: All data stored in local SQLite database
No Cloud Sync: Data stays on your device/server
API Keys: Gemini API key stored locally, only used for chat features
Minimal Data: Only collects what's needed for functionality

Troubleshooting
Common Issues
Chat not working?

Check Gemini API key in Settings
Verify internet connection
Check browser console for errors

App won't load?

Ensure PHP 7.4+ with SQLite support
Check file permissions on directory
Verify web server configuration

Mobile issues?

Clear browser cache and reload
Check if JavaScript is enabled
Try different mobile browser

Support
This is a minimal, single-file application designed for simplicity. For issues:

Check the browser console for error messages
Verify your PHP and SQLite setup
Test the Gemini API key independently
Review the inline code comments in index.php

License
MIT License - feel free to modify and adapt for your needs.

Philosophy: TaskFlow AI believes that task management should feel like having a conversation with a smart assistant, not filling out digital paperwork. The goal is to prove that chat-driven productivity tools can be more natural and effective than traditional form-based interfaces.