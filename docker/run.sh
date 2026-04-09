
#!/bin/bash

# One-line Docker deployment script for DCCP Admin Production
# Usage: ./docker/run.sh

echo "🚀 Deploying DCCP Admin Production with Docker..."

# Check if .env file exists in the current directory

# Read variables from .env file
export $(grep -v '^#' .env | xargs)

# Build the Docker image first
echo "📦 Building optimized Docker image..."
docker build \
    --build-arg APP_NAME=DccpAdminV3 \
    --build-arg APP_ENV=production \
    --build-arg APP_DEBUG=false \
    --build-arg APP_URL=https://dccpadmin.yukazaki.com \
    -t yukazaki/dccpadminv3:latest -f docker/Dockerfile .

# Ask to publish
if [[ "$*" == *"--publish"* ]]; then
    PUBLISH="y"
else
    read -p "Do you want to publish the image to Docker Hub? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        PUBLISH="y"
    fi
fi

if [ "$PUBLISH" = "y" ]; then
    echo "📦 Publishing image to Docker Hub..."
    docker push yukazaki/dccpadminv3:latest
fi

# Run the Docker container
echo "🚀 Starting Docker container..."
docker run -d \
    --name dccpadmin-production \
    --restart unless-stopped \
    --env-file .env \
    --network host \
    -v dccp-storage:/var/www/html/storage \
    -v dccp-logs:/var/www/html/storage/logs \
    --health-cmd "curl -f http://localhost:8000 || exit 1" \
    --health-interval 30s \
    --health-timeout 10s \
    --health-retries 3 \
    --health-start-period 60s \
    -e RUN_DOCKER_SCRIPTS=true \
    yukazaki/dccpadminv3:latest

echo "✅ DCCP Admin deployed successfully!"
echo "🌐 Application is available at: http://localhost:8000"
echo "📊 Horizon dashboard: http://localhost:8000/horizon"
echo "💓 Pulse dashboard: http://localhost:8000/pulse"
echo ""
echo "Note: Using host networking mode. The container shares your host's network."
echo "To view logs: docker logs -f dccpadmin-production"
echo "To stop: docker stop dccpadmin-production"
echo "To restart: docker restart dccpadmin-production"
