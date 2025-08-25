# 🐳 TaskFlow AI - Docker Deployment Guide

## Quick Start

```bash
# Clone the repository
git clone https://github.com/loydmilligan/taskflowai.git
cd taskflowai

# Start with Docker Compose
docker-compose up -d

# Access at http://localhost:8080
```

## Deployment Options

### 1. Production Deployment
```bash
# Build and start
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f
```

### 2. Development Mode (Live Editing)
```bash
# Use development compose file
docker-compose -f docker-compose.dev.yml up -d

# This mounts your source code for live editing
```

### 3. Custom Port
```bash
# Edit docker-compose.yml to change ports:
# ports:
#   - "3000:80"  # Custom port 3000

docker-compose up -d
```

## Data Persistence

The Docker setup automatically persists:
- **SQLite Database**: `./data/` directory
- **File Uploads**: `./uploads/` directory (future feature)

## Environment Variables

You can customize the deployment:

```yaml
# docker-compose.override.yml
services:
  taskflow-ai:
    environment:
      - APACHE_DOCUMENT_ROOT=/var/www/html
      - PHP_INI_SCAN_DIR=/usr/local/etc/php/conf.d
```

## Backup & Restore

### Backup
```bash
# Backup database and uploads
tar -czf taskflow-backup.tar.gz data/ uploads/
```

### Restore
```bash
# Restore from backup
tar -xzf taskflow-backup.tar.gz
docker-compose restart
```

## Troubleshooting

### Container won't start
```bash
# Check logs
docker-compose logs taskflow-ai

# Rebuild if needed
docker-compose build --no-cache
```

### Permission Issues
```bash
# Fix data directory permissions
sudo chown -R www-data:www-data data/
sudo chown -R www-data:www-data uploads/
```

### Port Already in Use
```bash
# Change port in docker-compose.yml
ports:
  - "8081:80"  # Use port 8081 instead
```

## Production Considerations

1. **Reverse Proxy**: Use nginx/Traefik for SSL termination
2. **Backup Strategy**: Regular database backups
3. **Monitoring**: Add health checks and logging
4. **Security**: Keep containers updated

## Docker Commands Reference

```bash
# Start services
docker-compose up -d

# Stop services
docker-compose down

# View logs
docker-compose logs -f

# Rebuild
docker-compose build

# Access container shell
docker-compose exec taskflow-ai bash

# Check status
docker-compose ps
```

Your TaskFlow AI is now containerized and ready for deployment anywhere Docker runs! 🚀