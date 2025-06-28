#!/bin/sh

echo "=== RUND-API Post-Installation Setup ==="

# Create necessary directories if they don't exist
mkdir -p /var/www/html/logs
mkdir -p /var/www/html/tmp
mkdir -p /var/www/html/app/data

# Set proper permissions
chown -R www-data:www-data /var/www/html/logs
chown -R www-data:www-data /var/www/html/tmp
chmod 755 /var/www/html/logs
chmod 755 /var/www/html/tmp

# Create log files if they don't exist
touch /var/www/html/logs/access.log
touch /var/www/html/logs/error.log
touch /var/www/html/logs/php_error.log

# Set log file permissions
chown www-data:www-data /var/www/html/logs/*.log
chmod 644 /var/www/html/logs/*.log

# Test LibreOffice installation
echo "Testing LibreOffice installation..."
if command -v libreoffice >/dev/null 2>&1; then
  echo "✓ LibreOffice installed successfully"
  libreoffice --version
else
  echo "✗ LibreOffice installation failed"
  exit 1
fi

# Test PHP extensions
echo "Testing PHP extensions..."
php -m | grep -E "(gd|zip|intl|xml|mbstring|fileinfo|dom)" && echo "✓ Required PHP extensions loaded" || echo "✗ Missing required PHP extensions"

# List all loaded extensions for debugging
echo "All loaded PHP extensions:"
php -m

# Test PHPOffice installation (if composer already ran)
if [ -f "/var/www/html/vendor/autoload.php" ]; then
  echo "Testing PHPOffice installation..."
  php -r "
    require_once '/var/www/html/vendor/autoload.php';
    try {
        new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        echo '✓ PHPSpreadsheet works\n';
    } catch (Exception \$e) {
        echo '✗ PHPSpreadsheet error: ' . \$e->getMessage() . '\n';
    }
    try {
        new \PhpOffice\PhpWord\PhpWord();
        echo '✓ PHPWord works\n';
    } catch (Exception \$e) {
        echo '✗ PHPWord error: ' . \$e->getMessage() . '\n';
    }
    "
else
  echo "⚠ Composer dependencies not found, run 'composer install' first"
fi

# Warm up opcache if enabled
if [ "$PHP_OPCACHE_ENABLE" = "1" ]; then
  echo "Warming up OPcache..."
  find /var/www/html/app -name "*.php" -exec php -l {} \; >/dev/null 2>&1
  echo "✓ OPcache warmed up"
fi

# Create a simple health check endpoint test
echo "Testing API endpoint..."
if curl -f http://localhost:3000/app/ >/dev/null 2>&1; then
  echo "✓ API endpoint is responding"
else
  echo "⚠ API endpoint not responding (this is normal during startup)"
fi

echo "=== Setup completed ==="
echo "API should be available at:"
echo "  - Development: http://localhost:3000"
echo "  - Production:  http://172.16.234.52:3000"
echo ""
echo "Logs are available in: /var/www/html/logs/"
echo "Temporary files in: /var/www/html/tmp/"
