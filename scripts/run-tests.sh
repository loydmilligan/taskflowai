#!/bin/bash

# TaskFlow AI - Playwright Test Execution Script
# Comprehensive testing workflow with coordination hooks

set -e

echo "🚀 TaskFlow AI - Playwright Testing Environment Setup"
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Node.js and npm are installed
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed. Please install Node.js 18+ to continue."
    exit 1
fi

if ! command -v npm &> /dev/null; then
    print_error "npm is not installed. Please install npm to continue."
    exit 1
fi

# Install dependencies if needed
print_status "Installing dependencies..."
npm install

# Install Playwright browsers
print_status "Installing Playwright browsers..."
npx playwright install

# Execute coordination hooks
print_status "Executing coordination hooks..."
if command -v npx &> /dev/null && npx claude-flow --help &> /dev/null; then
    npx claude-flow@alpha hooks pre-task --description "setup-playwright-testing" || print_warning "Claude Flow hooks not available"
else
    print_warning "Claude Flow not available - skipping coordination hooks"
fi

# Start PHP server in background
print_status "Starting PHP development server..."
PHP_PID=""
if command -v php &> /dev/null; then
    php -S localhost:8080 index.php &> /dev/null &
    PHP_PID=$!
    sleep 2
    print_success "PHP server started on localhost:8080 (PID: $PHP_PID)"
else
    print_warning "PHP not found - you may need to start the server manually"
fi

# Function to cleanup on exit
cleanup() {
    if [ ! -z "$PHP_PID" ]; then
        print_status "Stopping PHP server (PID: $PHP_PID)..."
        kill $PHP_PID 2>/dev/null || true
    fi
    
    # Execute post-task hooks
    if command -v npx &> /dev/null && npx claude-flow --help &> /dev/null; then
        npx claude-flow@alpha hooks post-task --task-id "playwright-testing" || true
    fi
}

trap cleanup EXIT

# Parse command line arguments
TEST_SUITE="all"
BROWSER="chromium"
HEADED=false
DEBUG=false
UI_MODE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --suite)
            TEST_SUITE="$2"
            shift 2
            ;;
        --browser)
            BROWSER="$2"
            shift 2
            ;;
        --headed)
            HEADED=true
            shift
            ;;
        --debug)
            DEBUG=true
            shift
            ;;
        --ui)
            UI_MODE=true
            shift
            ;;
        --help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --suite SUITE     Test suite to run (all, dashboard, chat, mobile, filtering)"
            echo "  --browser BROWSER Browser to use (chromium, firefox, webkit)"
            echo "  --headed          Run tests in headed mode"
            echo "  --debug           Run tests in debug mode"
            echo "  --ui              Open Playwright UI mode"
            echo "  --help            Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0                         # Run all tests"
            echo "  $0 --suite dashboard       # Run only dashboard tests"
            echo "  $0 --browser firefox       # Run tests in Firefox"
            echo "  $0 --headed --debug        # Run in headed debug mode"
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Build test command
PLAYWRIGHT_CMD="npx playwright test"

if [ "$DEBUG" = true ]; then
    PLAYWRIGHT_CMD="$PLAYWRIGHT_CMD --debug"
elif [ "$UI_MODE" = true ]; then
    PLAYWRIGHT_CMD="$PLAYWRIGHT_CMD --ui"
elif [ "$HEADED" = true ]; then
    PLAYWRIGHT_CMD="$PLAYWRIGHT_CMD --headed"
fi

# Add browser selection
PLAYWRIGHT_CMD="$PLAYWRIGHT_CMD --project=$BROWSER"

# Add test suite selection
case $TEST_SUITE in
    dashboard)
        PLAYWRIGHT_CMD="$PLAYWRIGHT_CMD tests/specs/dashboard/"
        ;;
    chat)
        PLAYWRIGHT_CMD="$PLAYWRIGHT_CMD tests/specs/chat/"
        ;;
    mobile)
        PLAYWRIGHT_CMD="$PLAYWRIGHT_CMD tests/specs/mobile/"
        ;;
    filtering)
        PLAYWRIGHT_CMD="$PLAYWRIGHT_CMD tests/specs/filtering/"
        ;;
    all)
        # Run all tests
        ;;
    *)
        print_error "Unknown test suite: $TEST_SUITE"
        exit 1
        ;;
esac

print_status "Running tests with command: $PLAYWRIGHT_CMD"
echo ""

# Execute tests
if eval $PLAYWRIGHT_CMD; then
    print_success "All tests passed! 🎉"
    
    # Store test results in memory for coordination
    if command -v npx &> /dev/null && npx claude-flow --help &> /dev/null; then
        npx claude-flow@alpha hooks post-edit --memory-key "swarm/testing/results" --file "test-results/results.json" || true
    fi
else
    print_error "Some tests failed. Check the test results for details."
    exit 1
fi

print_status "Opening test report..."
if [ -f "playwright-report/index.html" ]; then
    if command -v xdg-open &> /dev/null; then
        xdg-open playwright-report/index.html
    elif command -v open &> /dev/null; then
        open playwright-report/index.html
    else
        print_status "Test report available at: playwright-report/index.html"
    fi
fi

print_success "Testing complete! Results saved to test-results/"