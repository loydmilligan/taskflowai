# TaskFlow AI 🤖📱

**Mobile-first task management with AI chat interface**

TaskFlow AI is a revolutionary task management app where users interact primarily through an **AI chat interface** powered by Google Gemini. Create, manage, and organize projects through natural language conversation instead of traditional forms.

## ✨ Key Features

- 💬 **Chat-Driven Interface**: Manage everything through natural conversation
- 📱 **Mobile-First Design**: Sliding sidebar chat optimized for touch devices  
- 🏗️ **Single-File Architecture**: Everything in one PHP file - zero configuration
- 💾 **SQLite Database**: No database setup required
- 📝 **Smart Entity Management**: Projects, Tasks, Notes, and Scraps with AI conversion
- 🔌 **API-First**: Full REST API for integrations
- ⚡ **Docker Ready**: One-command deployment

## 🚀 Quick Start

### Docker Compose (Recommended)

```bash
# Clone and start
git clone https://github.com/loydmilligan/taskflowai.git
cd taskflowai
docker-compose up -d

# Access at http://localhost:8080
```

### PHP Development Server

```bash
# Clone and start
git clone https://github.com/loydmilligan/taskflowai.git
cd taskflowai
php -S localhost:8080

# Access at http://localhost:8080
```

### Traditional Web Hosting

Simply upload `index.php` to any PHP-enabled web host. The app auto-initializes on first access.

## 🔧 Configuration

1. **Access the app** in your web browser
2. **Tap the chat button** (💬) to open the AI assistant
3. **Go to Settings** and add your Google Gemini API key
4. **Start chatting**: "Create a task to review the mobile UI due tomorrow"

## 📱 Mobile Experience

- **Floating Chat Button**: Always accessible 💬 button
- **Sliding Sidebar**: Chat slides in smoothly from the right
- **Touch Optimized**: 44px+ touch targets, thumb-friendly navigation
- **Scraps Workflow**: 
  - Quickly capture: "Remember to fix the scrolling issue"
  - AI converts to structured tasks or notes
  - Perfect for on-the-go idea capture

## 🌟 Chat-Driven Examples

**Create Tasks:**
```
"Add a high priority task to implement user authentication due Friday"
"Create a task to review the mobile UI for the design project"
```

**Manage Projects:**
```
"Start a new project called Website Redesign in the marketing area"
"Show me all tasks for the Backend Development project"
```

**Quick Notes:**
```
"Save a note about the client meeting: they want dark mode and better mobile nav"
"Add a note with the API documentation links for the development area"
```

**Process Ideas:**
```
"I need to add push notifications and fix the login bug" (creates scrap)
AI: "I can convert this to two separate tasks. Would you like me to...?"
```

## 🐳 Docker Deployment Options

### Production
```bash
docker-compose up -d
```

### Development (with live editing)
```bash
docker-compose -f docker-compose.dev.yml up -d
```

### Custom Configuration
```yaml
# docker-compose.override.yml
services:
  taskflow-ai:
    ports:
      - "3000:80"  # Custom port
    environment:
      - CUSTOM_SETTING=value
```

## 🔒 Security & API

- **API Key Authentication**: Auto-generated secure keys
- **SQLite Security**: File-based, no network database exposure  
- **Input Sanitization**: All user input properly escaped
- **CORS Ready**: Configured for cross-origin API access

## 📊 Architecture

- **Single PHP File**: `index.php` contains everything
- **Zero Dependencies**: No frameworks, just vanilla PHP/JS/CSS
- **Progressive Enhancement**: Works on any device/browser
- **API-First**: Every feature accessible via REST endpoints

## 🎯 Perfect For

- **Personal Productivity**: Single-user focused design
- **Mobile-First Users**: Designed for thumb-friendly interaction  
- **AI Enthusiasts**: Natural language task management
- **Quick Deployment**: Docker one-liner or simple file upload
- **Developers**: Full API access for integrations

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch
3. Test on mobile devices
4. Submit a pull request

## 📄 License

MIT License - feel free to use, modify, and distribute.

---

**Experience the future of task management - where AI understands your intent and mobile-first design puts control at your fingertips.**