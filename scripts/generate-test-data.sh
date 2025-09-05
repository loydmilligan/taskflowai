#!/bin/bash

# TaskFlow AI Test Data Generation Script
# Generates realistic projects, tasks, notes, and scraps for testing workflows

# Configuration
BASE_URL="https://taskflow.mattmariani.com"
API_KEY="taskflow_4c424ebfc3b903a04bedceb4a8fa394d"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Helper function for API calls
call_api() {
    local method=$1
    local endpoint=$2
    local data=$3
    
    echo -e "${BLUE}→ ${method} ${endpoint}${NC}"
    
    if [ "$method" = "POST" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST \
            -H "Content-Type: application/json" \
            -H "Authorization: Bearer ${API_KEY}" \
            -d "$data" \
            "${BASE_URL}${endpoint}")
    else
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X GET \
            -H "Authorization: Bearer ${API_KEY}" \
            "${BASE_URL}${endpoint}")
    fi
    
    http_code=$(echo "$response" | tail -1 | cut -d':' -f2)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "${GREEN}✓ Success (${http_code})${NC}"
        echo "$body" | jq . 2>/dev/null || echo "$body"
    else
        echo -e "${RED}✗ Failed (${http_code})${NC}"
        echo "$body"
    fi
    echo ""
}

echo -e "${YELLOW}🚀 TaskFlow AI Test Data Generator${NC}"
echo "======================================"

# 1. Create Projects
echo -e "${YELLOW}📁 Creating Projects...${NC}"

# TaskFlow AI Project (already exists, but let's ensure it has good data)
call_api "POST" "/api/projects" '{
    "name": "TaskFlow AI Project Management", 
    "description": "Development and enhancement of the TaskFlow AI task management system with roadmap features implementation",
    "status": "active",
    "area": "Development",
    "tags": "ai,productivity,web-app,php"
}'

# FuelWise AI Project  
call_api "POST" "/api/projects" '{
    "name": "FuelWise AI", 
    "description": "Micro-SaaS platform for fuel market analysis and pricing optimization using AI-driven insights",
    "status": "active", 
    "area": "Business",
    "tags": "saas,ai,fuel,pricing,micro-saas"
}'

# UniAdmitHelper Project
call_api "POST" "/api/projects" '{
    "name": "UniAdmitHelper", 
    "description": "College counseling management platform for high school students - streamlining the admission process",
    "status": "active",
    "area": "Education", 
    "tags": "education,counseling,students,college,management"
}'

# 2. Create Tasks for TaskFlow AI Project
echo -e "${YELLOW}📋 Creating TaskFlow AI Tasks...${NC}"

call_api "POST" "/api/tasks" '{
    "project_id": 1,
    "title": "Implement Proactive Workflow Notifications",
    "description": "Add scheduled morning/evening workflow pings with ntfy integration and snooze functionality",
    "due_date": "2025-09-05",
    "priority": "high",
    "status": "pending",
    "area": "Development",
    "tags": "workflow,notifications,ntfy"
}'

call_api "POST" "/api/tasks" '{
    "project_id": 1,
    "title": "Fix PWA Manifest Routing",
    "description": "Ensure manifest.json endpoint works properly for mobile app installation",
    "due_date": "2025-09-04", 
    "priority": "medium",
    "status": "in-progress",
    "area": "Development",
    "tags": "pwa,mobile,manifest"
}'

call_api "POST" "/api/tasks" '{
    "project_id": 1,
    "title": "Create Comprehensive Test Suite",
    "description": "Build automated tests for all roadmap features using Playwright",
    "due_date": "2025-09-10",
    "priority": "medium", 
    "status": "pending",
    "area": "Quality Assurance",
    "tags": "testing,playwright,automation"
}'

call_api "POST" "/api/tasks" '{
    "project_id": 1,
    "title": "Optimize Database Performance",
    "description": "Add indexes and optimize queries for improved response times as user base grows", 
    "due_date": "2025-09-15",
    "priority": "low",
    "status": "pending", 
    "area": "Performance",
    "tags": "database,optimization,performance"
}'

call_api "POST" "/api/tasks" '{
    "project_id": 1,
    "title": "Document API Endpoints",
    "description": "Create comprehensive API documentation for external integrations",
    "due_date": "2025-09-08",
    "priority": "low", 
    "status": "completed",
    "area": "Documentation",
    "tags": "api,documentation,integration"
}'

# 3. Create Tasks for FuelWise AI Project
echo -e "${YELLOW}⛽ Creating FuelWise AI Tasks...${NC}"

call_api "POST" "/api/tasks" '{
    "project_id": 2,
    "title": "Design Fuel Price Prediction Algorithm",
    "description": "Research and implement ML model for predicting fuel price trends based on market data",
    "due_date": "2025-09-12",
    "priority": "high",
    "status": "in-progress",
    "area": "AI/ML",
    "tags": "machine-learning,prediction,algorithm"
}'

call_api "POST" "/api/tasks" '{
    "project_id": 2,
    "title": "Build Data Pipeline for Market Data",
    "description": "Create automated data collection system for fuel prices, news, and market indicators",
    "due_date": "2025-09-07",
    "priority": "high", 
    "status": "pending",
    "area": "Data Engineering", 
    "tags": "data-pipeline,automation,api"
}'

call_api "POST" "/api/tasks" '{
    "project_id": 2,
    "title": "Design SaaS Landing Page",
    "description": "Create compelling landing page highlighting AI-driven fuel insights and pricing value prop",
    "due_date": "2025-09-09",
    "priority": "medium",
    "status": "pending",
    "area": "Marketing",
    "tags": "landing-page,marketing,design"
}'

call_api "POST" "/api/tasks" '{
    "project_id": 2,
    "title": "Implement Stripe Payment Integration", 
    "description": "Add subscription billing for micro-SaaS with tiered pricing plans",
    "due_date": "2025-09-14",
    "priority": "medium",
    "status": "pending",
    "area": "Business", 
    "tags": "payments,stripe,subscription"
}'

call_api "POST" "/api/tasks" '{
    "project_id": 2,
    "title": "MVP Dashboard Development",
    "description": "Build initial dashboard showing fuel price trends, alerts, and basic analytics",
    "due_date": "2025-09-11", 
    "priority": "high",
    "status": "completed",
    "area": "Development",
    "tags": "dashboard,mvp,frontend"
}'

# 4. Create Tasks for UniAdmitHelper Project
echo -e "${YELLOW}🎓 Creating UniAdmitHelper Tasks...${NC}"

call_api "POST" "/api/tasks" '{
    "project_id": 3,
    "title": "Student Profile Management System",
    "description": "Build comprehensive student profiles with academic history, extracurriculars, and goals tracking",
    "due_date": "2025-09-13",
    "priority": "high",
    "status": "in-progress", 
    "area": "Development",
    "tags": "student-profiles,database,management"
}'

call_api "POST" "/api/tasks" '{
    "project_id": 3,
    "title": "College Application Tracking",
    "description": "Create system to track application deadlines, requirements, and submission status per student",
    "due_date": "2025-09-16", 
    "priority": "high",
    "status": "pending",
    "area": "Features",
    "tags": "applications,tracking,deadlines"
}'

call_api "POST" "/api/tasks" '{
    "project_id": 3,
    "title": "Parent Communication Portal",
    "description": "Build secure portal for parents to view student progress and communicate with counselor",
    "due_date": "2025-09-18",
    "priority": "medium",
    "status": "pending",
    "area": "Communication", 
    "tags": "parents,portal,communication"
}'

call_api "POST" "/api/tasks" '{
    "project_id": 3,
    "title": "Essay Review Workflow",
    "description": "Implement system for students to submit essays for review with feedback tracking",
    "due_date": "2025-09-20",
    "priority": "medium", 
    "status": "pending",
    "area": "Workflow",
    "tags": "essays,review,feedback"
}'

call_api "POST" "/api/tasks" '{
    "project_id": 3,
    "title": "Research UI/UX Best Practices",
    "description": "Study education platforms to understand optimal user experience for students and counselors",
    "due_date": "2025-09-06",
    "priority": "low",
    "status": "completed",
    "area": "Research", 
    "tags": "ui-ux,research,education"
}'

# 5. Create Notes
echo -e "${YELLOW}📝 Creating Project Notes...${NC}"

call_api "POST" "/api/notes" '{
    "title": "TaskFlow AI Architecture Decisions",
    "content": "Key architectural decisions made during roadmap implementation:\n\n- Single-file philosophy maintained for simplicity\n- SQLite chosen for zero-config database\n- Integration layer approach for modular features\n- PWA implementation for mobile-first experience\n\nNext considerations:\n- Consider PostgreSQL for multi-user scaling\n- Evaluate Redis for session management\n- Plan for microservices if complexity grows",
    "area": "Development",
    "tags": "architecture,decisions,database"
}'

call_api "POST" "/api/notes" '{
    "title": "FuelWise AI Market Research",  
    "content": "Market research findings for fuel pricing micro-SaaS:\n\n**Target Customers:**\n- Small fuel retailers (gas stations)\n- Fleet managers\n- Commodity traders\n\n**Pain Points:**\n- Manual price monitoring is time-consuming\n- Missing optimal pricing opportunities\n- Lack of predictive insights\n\n**Competitive Landscape:**\n- GasBuddy (consumer-focused)\n- OPIS (enterprise-level, expensive)\n- Opportunity in SMB market gap\n\n**MVP Features:**\n- Price alerts\n- Basic trend analysis\n- Simple dashboard",
    "area": "Business",
    "tags": "market-research,competition,saas"
}'

call_api "POST" "/api/notes" '{
    "title": "UniAdmitHelper User Personas",
    "content": "Primary user personas for college counseling platform:\n\n**1. High School Counselor (Primary)**\n- Manages 50-200+ students\n- Needs efficient tracking and communication\n- Values time-saving automation\n\n**2. High School Student (Secondary)**\n- Overwhelmed by college process\n- Needs clear guidance and deadlines\n- Mobile-first usage patterns\n\n**3. Parent (Tertiary)**\n- Wants visibility into process\n- Concerned about deadlines\n- Prefers simple communication\n\n**Key Workflows:**\n- Initial student assessment\n- College list development\n- Application tracking\n- Deadline management\n- Progress reporting",
    "area": "Education", 
    "tags": "personas,users,workflow"
}'

call_api "POST" "/api/notes" '{
    "title": "Workflow Notification Ideas",
    "content": "Ideas for proactive workflow notifications:\n\n**Morning Workflow (9 AM default):**\n- AI task selection based on energy/priorities\n- Scrap processing suggestions\n- Daily intention setting\n- Energy level check-in\n\n**Evening Workflow (6 PM default):**\n- Progress review and completion tracking\n- Reflection prompts for learning capture\n- Tomorrow preparation\n- Productivity scoring\n\n**Implementation:**\n- Configurable times per user\n- Multiple ntfy topics for different workflows\n- Snooze functionality (15/30/60 min)\n- Quick action buttons in notifications\n- Cancel today option\n\n**Technical:**\n- Cron jobs or scheduled tasks\n- Database tracking of workflow states\n- Integration with existing ntfy system",
    "area": "Development",
    "tags": "workflows,notifications,features"
}'

# 6. Create Scraps (unprocessed ideas that can become tasks)
echo -e "${YELLOW}💡 Creating Scraps for Processing...${NC}"

call_api "POST" "/api/scraps" '{
    "content": "Add dark mode toggle to TaskFlow AI - users have been requesting this for better night-time usage. Could improve user experience significantly."
}'

call_api "POST" "/api/scraps" '{
    "content": "FuelWise AI needs competitor price tracking - automatically monitor competitor stations within radius and alert when opportunities arise"
}'

call_api "POST" "/api/scraps" '{
    "content": "UniAdmitHelper should have scholarship tracking feature - many students miss opportunities due to poor organization of scholarship deadlines and requirements"
}'

call_api "POST" "/api/scraps" '{
    "content": "Bug: mobile keyboard covers input fields on iOS - need to adjust viewport behavior or add scroll adjustment when keyboard appears"
}'

call_api "POST" "/api/scraps" '{
    "content": "Consider adding time tracking to tasks - would help with better estimation and productivity insights for workflow AI recommendations"
}'

call_api "POST" "/api/scraps" '{
    "content": "FuelWise AI pricing strategy - research shows SMB market willing to pay $29-79/month for good fuel insights. Start with $39 tier?"
}'

call_api "POST" "/api/scraps" '{
    "content": "UniAdmitHelper integration with CommonApp API could save tons of manual work - investigate feasibility and pricing"
}'

call_api "POST" "/api/scraps" '{
    "content": "Add keyboard shortcuts to TaskFlow AI - power users would love quick task creation, navigation between views, etc."
}'

echo -e "${GREEN}✅ Test data generation complete!${NC}"
echo ""
echo -e "${YELLOW}📊 Summary:${NC}"
echo "- 3 Projects created"
echo "- 15 Tasks created (5 per project)"
echo "- 4 Notes created"
echo "- 8 Scraps created for processing"
echo ""
echo -e "${BLUE}🔍 Verifying data with API...${NC}"

# Quick verification
echo -e "${YELLOW}Projects:${NC}"
call_api "GET" "/api/projects"

echo -e "${YELLOW}Tasks Summary:${NC}" 
call_api "GET" "/api/tasks?limit=5"

echo -e "${YELLOW}Notes Summary:${NC}"
call_api "GET" "/api/notes?limit=3"

echo -e "${YELLOW}Scraps Summary:${NC}"
call_api "GET" "/api/scraps?limit=5"

echo -e "${GREEN}🎉 Test data generation script completed successfully!${NC}"