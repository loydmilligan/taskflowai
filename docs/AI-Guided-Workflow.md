# AI-Guided Daily Workflow Feature

## Overview

The AI-Guided Daily Workflow feature transforms TaskFlow AI into an intelligent daily companion that helps users start their day with focused task selection and end with meaningful reflection. This feature leverages the existing Gemini AI system to provide personalized morning and evening rituals.

## Key Features

### 🌅 Morning Workflow
- **AI Task Selection**: Intelligently selects 3-5 optimal tasks based on priority, due dates, and estimated effort
- **Scrap Processing**: Analyzes unprocessed scraps for actionable items with confidence scoring
- **Energy Check-in**: Records daily energy levels to optimize task recommendations
- **Daily Intention Setting**: Captures user's primary intention for focused execution
- **Conversation Context**: Maintains natural dialogue throughout the planning process

### 🌙 Evening Workflow
- **Completion Analytics**: Tracks tasks completed, created, and notes generated
- **Productivity Scoring**: Calculates daily productivity score (0-10) based on activity
- **Intelligent Reflection**: Generates personalized reflection prompts based on day's performance
- **Tomorrow Planning**: Suggests follow-up actions and tomorrow's preparation steps
- **Progress Insights**: Identifies patterns and provides actionable insights

## Architecture

### Database Schema
```sql
-- Workflow sessions
CREATE TABLE daily_workflows (
    id INTEGER PRIMARY KEY,
    date DATE NOT NULL,
    type TEXT CHECK (type IN ('morning', 'evening')),
    status TEXT DEFAULT 'pending',
    data JSON,
    created_at TIMESTAMP,
    completed_at TIMESTAMP
);

-- Workflow conversations
CREATE TABLE workflow_conversations (
    id INTEGER PRIMARY KEY,
    workflow_id INTEGER NOT NULL,
    message_type TEXT CHECK (message_type IN ('user', 'assistant', 'system')),
    content TEXT NOT NULL,
    metadata JSON,
    created_at TIMESTAMP
);
```

### Core Components

1. **AIWorkflowSystem** - Core workflow logic and AI task selection
2. **WorkflowAwareGeminiAI** - Enhanced Gemini integration with workflow context
3. **WorkflowUIGenerator** - Mobile-first responsive UI components
4. **WorkflowRoutes** - RESTful API endpoints for workflow operations
5. **TaskFlowFeatureIntegrator** - Unified integration layer

## API Endpoints

### Workflow Management
```
POST /api/workflow/morning         # Start morning workflow
POST /api/workflow/evening         # Start evening workflow
GET  /api/workflow/status          # Get today's workflow status
POST /api/workflow/complete        # Complete workflow session
POST /api/workflow/message         # Add conversation message
GET  /api/workflow/ui              # Get workflow UI components
POST /api/workflow/convert-scrap   # Convert scrap to task/note
```

### Integration Endpoints
```
GET  /api/integration/dashboard       # Unified dashboard data
GET  /api/integration/smart-suggestions # Context-aware suggestions
GET  /api/integration/unified-search  # Search across all entities
GET  /api/integration/ai-context      # Enhanced AI context
GET  /api/integration/feature-status  # Health check
```

## Mobile-First Design

### Responsive Breakpoints
- **Mobile**: < 768px - Stacked layouts, full-width components
- **Tablet**: 768px - 1024px - Grid layouts with 2-column fallbacks
- **Desktop**: > 1024px - Full grid layouts with optimal spacing

### Key UI Components

#### Morning Workflow Interface
- Energy level slider (1-10 scale)
- AI-selected task cards with reasoning
- Scrap processing suggestions
- Daily intention text area
- Action buttons (Start Day, Adjust Tasks)

#### Evening Workflow Interface
- Productivity statistics grid
- Reflection prompt sections
- Tomorrow's follow-up checklist
- Completion analytics visualization
- Action buttons (End Day, Plan Tomorrow)

## AI Integration Features

### Workflow Trigger Detection
The enhanced GeminiAI automatically detects workflow intents:

**Morning Triggers:**
- "Good morning", "start day", "daily planning"
- "What should I focus", "task selection", "daily goals"

**Evening Triggers:**
- "Good evening", "end day", "evening reflection"
- "What did I accomplish", "daily review", "productivity"

### Enhanced Context Awareness
- Current workflow status and conversation history
- Task completion patterns and productivity trends
- Energy levels and daily intention alignment
- Cross-session memory for continuous improvement

### Intelligent Task Selection Algorithm
```php
// Factors considered:
- Priority weighting (high: 1.5x, medium: 1x, low: 0.7x)
- Due date urgency (today > tomorrow > future)
- Estimated effort vs. daily capacity (8 hours max)
- Task dependencies and project context
- Historical completion patterns
```

## Installation & Setup

### 1. File Structure
```
src/
├── features/
│   ├── ai-workflow.php          # Core workflow system
│   ├── workflow-integration.php  # Gemini AI enhancement
│   ├── workflow-ui.php          # UI components
│   └── integration-layer.php    # Feature coordinator
├── tests/
│   └── ai-workflow-test.php     # Comprehensive test suite
└── docs/
    └── AI-Guided-Workflow.md    # This documentation
```

### 2. Integration Steps

1. **Include Integration Layer**:
```php
require_once __DIR__ . '/src/integration-layer.php';
```

2. **Initialize Feature System**:
```php
$featureIntegrator = new TaskFlowFeatureIntegrator(Database::getInstance());
$featureIntegrator->initializeFeatures();
```

3. **Handle Workflow Requests**:
```php
$workflowResponse = $featureIntegrator->handleRequest($method, $uri, $data);
if ($workflowResponse !== null) {
    header('Content-Type: application/json');
    echo json_encode($workflowResponse);
    exit;
}
```

### 3. Frontend Integration

```html
<!-- Include workflow UI -->
<div id="workflow-container"></div>

<script>
// Start morning workflow
fetch('/api/workflow/morning', { method: 'POST' })
    .then(response => response.json())
    .then(data => displayWorkflow(data));

// Get UI components
fetch('/api/workflow/ui?type=morning')
    .then(response => response.json())
    .then(ui => {
        document.head.insertAdjacentHTML('beforeend', ui.css);
        document.getElementById('workflow-container').innerHTML = ui.html;
        document.body.insertAdjacentHTML('beforeend', ui.js);
    });
</script>
```

## Testing

### Comprehensive Test Suite
Run the complete test suite to verify all functionality:

```bash
php tests/ai-workflow-test.php
```

### Test Coverage
- ✅ Morning workflow creation and completion
- ✅ Evening workflow generation and reflection
- ✅ AI task selection algorithm validation
- ✅ Scrap processing with confidence scoring
- ✅ Workflow conversation threading
- ✅ Gemini AI trigger detection
- ✅ Mobile-responsive UI generation
- ✅ API endpoint functionality
- ✅ Integration layer health checks
- ✅ Database schema validation

## Performance Considerations

### Optimization Features
- **Caching**: Workflow data cached for same-day requests
- **Lazy Loading**: UI components loaded on-demand
- **Database Indexes**: Optimized queries for workflow retrieval
- **Token Management**: Efficient Gemini API usage with context limiting

### Scalability
- **Session Memory**: Conversation context limited to last 5 messages
- **Data Retention**: Automatic cleanup of old workflow sessions
- **Resource Limits**: Maximum 8-hour daily effort allocation
- **Rate Limiting**: Gemini API calls throttled appropriately

## Configuration Options

### Workflow Settings
```php
// Customize in AIWorkflowSystem
private $maxDailyEffort = 8;        // Maximum hours per day
private $maxSelectedTasks = 5;      // Maximum tasks selected
private $scrapAnalysisLimit = 5;    // Maximum scraps to analyze
private $conversationContextLimit = 5; // Messages to retain
```

### UI Customization
- Modify CSS variables in `WorkflowUIGenerator::generateWorkflowCSS()`
- Customize prompts in `generateReflectionPrompts()` and `generateMorningGreeting()`
- Adjust energy level ranges and productivity scoring

## Troubleshooting

### Common Issues

1. **Workflow not triggering**
   - Verify Gemini API key configuration
   - Check trigger word detection in `analyzeWorkflowIntent()`

2. **Tasks not being selected**
   - Ensure tasks exist in database with proper status
   - Verify task selection algorithm parameters

3. **UI not rendering**
   - Check CSS/JS inclusion in frontend
   - Validate HTML structure in UI generator

4. **API endpoints failing**
   - Verify route handling in integration layer
   - Check database connection and schema

### Debug Mode
Enable detailed logging by setting:
```php
error_reporting(E_ALL);
ini_set('log_errors', 1);
```

## Future Enhancements

### Planned Features
- **Smart Scheduling**: Time-based task recommendations
- **Habit Tracking**: Daily routine optimization
- **Team Workflows**: Collaborative morning/evening sessions
- **Analytics Dashboard**: Weekly/monthly productivity insights
- **Voice Integration**: Hands-free workflow interactions
- **Calendar Sync**: Integration with external calendar systems

### AI Improvements
- **Learning Algorithms**: Personalized task selection based on history
- **Mood Analysis**: Sentiment-based reflection prompts
- **Predictive Planning**: AI-suggested tomorrow tasks
- **Context Awareness**: Meeting/event-aware scheduling

## Contributing

When contributing to the AI-Guided Daily Workflow feature:

1. **Follow SPARC Methodology**: Specification → Pseudocode → Architecture → Refinement → Completion
2. **Test-Driven Development**: Write tests before implementation
3. **Mobile-First Design**: Ensure responsive behavior
4. **API-First Approach**: Design endpoints before frontend
5. **Coordination Hooks**: Use Claude Flow hooks for concurrent development

### Code Standards
- Single-file PHP architecture for core features
- Mobile-first responsive CSS
- RESTful API design principles
- Comprehensive error handling
- Detailed inline documentation

---

*The AI-Guided Daily Workflow feature represents a significant evolution in personal productivity management, leveraging AI to create meaningful daily rituals that enhance focus, reflection, and continuous improvement.*