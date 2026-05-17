FROM php:8.2-cli

# No PDO/MySQL needed — all DB is handled by InfinityFree api.php
# Only need PHP with file_get_contents (built-in) for API calls

WORKDIR /app

COPY helpers.php index.php cart.php payment.php admin.php ./

EXPOSE 10000

# Render sets $PORT automatically
CMD php -S 0.0.0.0:${PORT:-10000} -t /app
