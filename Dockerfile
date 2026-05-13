FROM php:8.3-cli

WORKDIR /app

# Copy all project files
COPY . .

# Railway provides PORT dynamically
CMD sh -c "php -S 0.0.0.0:${PORT:-8080}"
