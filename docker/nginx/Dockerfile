FROM nginx:alpine

WORKDIR /var/www/html

# Copy Laravel's public assets into the Nginx image.
COPY --chown=nginx:nginx public/ /var/www/html/public/

EXPOSE 80
