# CLAUDE.md - TaskFlow AI Project

## Project Overview
TaskFlow AI is a mobile-first project and task management web app where users interact primarily through an AI chat interface powered by Google Gemini. The core philosophy is that all entity management (creating, editing, organizing) happens through natural language conversation rather than traditional forms.

## Project-Specific Context
This is a single-user productivity app built with extreme simplicity in mind:
- **Primary Interface**: AI chat using Google Gemini API
- **Architecture**: Single PHP file with SQLite database
- **Mobile Priority**: Touch-first design with chat as primary input method
- **Entity Model**: Projects, Tasks, Notes, and Scraps with smart conversion workflows

## Key Technical Decisions

### Architecture Choice
- **Single File Design**: `index.php` handles all routing, database operations, and API endpoints
- **No Frameworks**: Vanilla PHP, JavaScript, and CSS only
- **SQLite Database**: Zero-configuration, portable data storage
- **Mobile-First CSS**: Progressive enhancement from mobile to desktop

### API-First Design
Every user action has a corresponding REST endpoint:
- Authentication via API keys for external integrations
- Full CRUD operations accessible programmatically
- Chat interface internally uses same API endpoints as external clients

### Chat-Driven Interaction Model
- **Primary Input**: Natural language through Gemini AI chat
- **Persistent Context**: Chat history maintained across sessions
- **Navigation**: AI can navigate between views and perform actions
- **Entity Management**: Create/edit entities through conversational interface

## Current Project Status

### Development Phases
1. **Phase 1 - Research & Planning**: ✅ Complete
   - Core concept validated
   - Technical stack selected
   - Entity relationships defined

2. **Phase 2 - Design & Architecture**: 🔄 Ready to Begin
   - Mobile-first UI wireframes needed
   - Chat interface design patterns
   - API endpoint structure design

3. **Phase 3 - Frontend Implementation**: ⏳ Planned
   - Mobile-responsive chat interface
   - View switching and navigation
   - Touch-optimized interactions

4. **Phase 4 - Backend & Integration**: ⏳ Planned
   - Google Gemini API integration
   - SQLite schema and operations
   - ntfy.sh notification system

## File Structure
```
taskflow-ai/
├── index.php          # Single-file app with routing, API, and views
├── taskflow.db        # SQLite database (auto-created)
├── uploads/           # File attachments (future feature)
├── CLAUDE.md          # This file
└── README.md          # Project documentation
```

## Core Entities & Relationships

### Entity Types
- **Projects**: Containers with kanban/card views
- **Tasks**: Actionable items with due dates and priorities
- **Notes**: Reference material without due dates
- **Scraps**: Raw input that converts to Tasks or Notes

### Smart Conversion Logic
- **Scrap → Task**: Add due date and priority
- **Scrap → Note**: Keep as reference, optionally date-assign for planning
- **Morning Workflow**: Daily prompt to process unprocessed Scraps

## Mobile-First Design Principles

### Touch Optimization
- Large tap targets (minimum 44px)
- Swipe gestures for navigation
- Bottom-sheet modals for mobile comfort
- Thumb-friendly action placement

### Chat Interface Design
- Fixed bottom chat input
- Auto-expanding text area
- Quick action buttons for common tasks
- Voice input placeholder (future feature)

## AI Integration Architecture

### Google Gemini Setup
- API key stored in settings table
- Chat context maintained for conversation continuity
- Structured prompts for entity creation/modification
- Error handling for API failures with graceful degradation

### Chat Processing Flow
1. User input received
2. Context from chat history added
3. Gemini API called with structured prompt
4. Response parsed for actions (create, edit, navigate)
5. Database updated if needed
6. UI updated with results
7. Chat history stored

## API Design Patterns

### REST Endpoints
```
GET    /api/projects              # List all projects
POST   /api/projects              # Create project
GET    /api/projects/{id}         # Get project details
PUT    /api/projects/{id}         # Update project
DELETE /api/projects/{id}         # Delete project

GET    /api/tasks                 # List tasks (with filters)
POST   /api/tasks                 # Create task
GET    /api/tasks/{id}            # Get task details
PUT    /api/tasks/{id}            # Update task
DELETE /api/tasks/{id}            # Delete task

POST   /api/chat                  # Send chat message
GET    /api/chat/history          # Get chat history

GET    /api/plan                  # Get today's plan
GET    /api/plan/{date}           # Get plan for specific date

POST   /api/scraps/{id}/convert   # Convert scrap to task/note
```

### Authentication
- API key based authentication
- Keys generated and managed in settings
- Header: `Authorization: Bearer {api_key}`

## Database Schema Design

### Key Tables
```sql
-- Core entities
projects (id, name, description, status, created_at, tags, area)
tasks (id, project_id, title, description, due_date, priority, status, tags, area, created_at)
notes (id, title, content, date_assigned, date_range_end, tags, area, created_at)
scraps (id, content, date_assigned, date_range_end, processed, created_at)

-- System tables
chat_history (id, message, response, timestamp, context)
settings (key, value)
api_keys (id, key_hash, name, created_at, last_used)
```

### Indexing Strategy
- Date-based queries (due_date, date_assigned)
- Tag and area filtering
- Text search on content fields

## Development Standards

### Code Quality
- **Single Responsibility**: Each function has one clear purpose
- **API Consistency**: All endpoints follow REST conventions
- **Error Handling**: Graceful degradation for AI and network failures
- **Mobile Testing**: Test on actual mobile devices, not just browser dev tools

### Performance Targets
- **Initial Load**: < 2 seconds on 3G mobile
- **Chat Response**: < 3 seconds for Gemini API calls
- **Navigation**: Instant view switching
- **Database**: < 100ms for local SQLite queries

### Security Considerations
- **Input Sanitization**: All user input cleaned before database storage
- **API Authentication**: Required for all endpoints
- **SQL Injection**: Use prepared statements exclusively
- **XSS Prevention**: Escape all output, especially chat content

## Integration Points

### Google Gemini AI
- **Prompt Engineering**: Structured prompts for consistent entity creation
- **Context Management**: Include relevant project/task context in API calls
- **Fallback Strategy**: Basic functionality works without AI if API fails

### ntfy.sh Notifications
- **Morning Reminders**: Process scraps before configured time
- **Due Date Alerts**: Task deadlines and overdue notifications
- **Configuration**: User sets topic and notification preferences

## Mobile UX Patterns

### Navigation
- **Bottom Tab Bar**: Primary navigation for main views
- **Chat Overlay**: Accessible from all screens via floating action button
- **Swipe Gestures**: Back navigation and view switching
- **Pull-to-Refresh**: Update data in list views

### Input Methods
- **Chat Primary**: Natural language for all entity management
- **Quick Actions**: Common operations as chat shortcuts
- **Touch Fallback**: Basic tap interactions for navigation
- **Voice Future**: Prepared for voice input integration

## Development Workflow

### Phase-Based Development
Each phase should result in a working, demonstrable feature:
- **Research**: Understand requirements and validate approaches
- **Design**: Create working prototypes of key interactions
- **Frontend**: Build complete user interface with mock data
- **Backend**: Integrate real data and AI functionality

### Testing Strategy
- **Mobile Testing**: Primary testing on mobile devices
- **Chat Testing**: Validate AI responses for common scenarios
- **API Testing**: Ensure all endpoints work correctly
- **Edge Cases**: Handle offline scenarios and API failures

## Success Metrics

### User Experience
- **Chat Success Rate**: >90% of user intents correctly interpreted
- **Mobile Performance**: Smooth interactions on mid-range phones
- **Daily Usage**: Users complete morning scrap processing workflow

### Technical Performance
- **Uptime**: Local app always available (no external dependencies for core features)
- **Response Time**: Chat interactions feel responsive
- **Data Integrity**: No data loss during entity conversions

## Future Considerations

### Planned Enhancements
- **Voice Input**: Speech-to-text for chat interface
- **File Attachments**: Associate files with projects/tasks
- **Collaboration**: Multi-user support (major architecture change)
- **Advanced AI**: More sophisticated natural language processing

### Technical Debt Prevention
- **Keep Simple**: Resist feature creep that breaks single-file architecture
- **API First**: All features must work via API for future flexibility
- **Mobile Priority**: Never sacrifice mobile experience for desktop features

## Remember
This project prioritizes **working over perfect**. The goal is a functional productivity tool that proves the chat-driven interaction model, not a feature-complete enterprise solution. Focus on the core workflow: talk to AI, manage tasks, stay organized.

The chat interface is the innovation here - keep everything else as simple as possible to support that core differentiator.