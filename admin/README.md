# RAS Admin Dashboard

Remote Assistance Support System - PHP/MySQL Admin Dashboard for monitoring device metrics and managing alerts.

## Features

- **Real-time Dashboard**: Monitor device status, CPU, memory, and disk usage
- **Device Management**: View detailed information for each registered device
- **Alert System**: Automatic alerts for critical conditions with email notifications
- **Performance Reports**: Generate daily, weekly, and custom reports with CSV export
- **Material Design UI**: Clean, responsive interface built with Materialize CSS
- **REST API**: Receive metrics data from Python agents

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL/MariaDB 5.7 or higher
- Web server (Apache/Nginx)
- XAMPP/WAMP (for Windows development)

### Setup Steps

1. **Database Setup**

   Import the database schema:

   ```bash
   mysql -u root -p < database/ras_schema.sql
   ```

   Or use phpMyAdmin to import `database/ras_schema.sql`

2. **Configuration**

   Edit `config/config.php` if needed:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'ras_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

   Update the API key for security:

   ```php
   define('API_KEY', 'change-this-to-secure-key');
   ```

3. **Web Server Configuration**

   Ensure the web server can access the admin directory:

   ```
   http://localhost/RAS/admin/
   ```

4. **Permissions**

   Set write permissions for logs directory:

   ```bash
   chmod 755 logs/
   ```

## Usage

### Access Dashboard

Open your browser and navigate to:

```
http://localhost/RAS/admin/index.php
```

### Python Agent Integration

Send metrics from your Python agent to:

```
POST http://localhost/RAS/admin/api/metrics.php
Headers:
    X-API-Key: your-api-key
    Content-Type: application/json
Body:
    {
        "device_id": "device-001",
        "hostname": "server-01",
        "ip_address": "192.168.1.100",
        "cpu_usage": 45.5,
        "memory_used": 8589934592,
        "memory_total": 17179869184,
        "disk_used": 536870912000,
        "disk_total": 1073741824000,
        "disk_usage": 50.0,
        "storage_health": "healthy",
        "network_status": "good"
    }
```

### API Endpoints

- **Metrics**: `POST /admin/api/metrics.php` - Receive device metrics
- **Devices**: `GET /admin/api/devices.php` - List all devices
- **Device Detail**: `GET /admin/api/devices.php?id={device_id}` - Get specific device
- **Alerts**: `GET /admin/api/alerts.php` - List all alerts
- **Dashboard**: `GET /admin/api/dashboard.php` - Get dashboard summary
- **Settings**: `GET/PUT /admin/api/settings.php` - Manage system settings

### Email Notifications

Configure email settings in the dashboard Settings page:

1. Navigate to Settings
2. Enable email notifications
3. Configure SMTP settings
4. Set recipient email address

### Reports

Generate performance reports:

1. Navigate to Reports
2. Select report type (daily/weekly/custom)
3. Choose device (or all devices)
4. Select date range
5. Click "Apply"
6. Export to CSV if needed

## Default Alert Thresholds

- CPU Critical: 90%
- Memory Critical: 90%
- Disk Critical: 85%
- Device Offline: 5 minutes

These can be adjusted in the Settings page.

## File Structure

```
RAS/
├── admin/
│   ├── api/
│   │   ├── metrics.php       # Receive metrics endpoint
│   │   ├── devices.php       # Device data API
│   │   ├── alerts.php        # Alerts API
│   │   ├── dashboard.php     # Dashboard summary API
│   │   └── settings.php      # Settings API
│   ├── assets/
│   │   ├── css/
│   │   │   └── dashboard.css # Custom styles
│   │   └── js/
│   │       └── dashboard.js  # Dashboard JavaScript
│   ├── includes/
│   │   └── email.php         # Email notification library
│   ├── pages/
│   │   ├── device_detail.php # Device detail page
│   │   └── reports.php       # Reports page
│   ├── index.php             # Main dashboard
│   └── README.md             # This file
├── config/
│   └── config.php            # Configuration file
├── database/
│   └── ras_schema.sql        # Database schema
└── logs/
    └── (auto-generated)      # Application logs
```

## Security Considerations

1. **API Key**: Change the default API key in `config/config.php`
2. **HTTPS**: Use HTTPS in production
3. **Authentication**: Consider adding authentication for the admin panel
4. **Input Validation**: All inputs are validated and sanitized
5. **Prepared Statements**: Database queries use PDO prepared statements
6. **SQL Injection**: Protection via parameterized queries

## Troubleshooting

### Database Connection Error

Check your database credentials in `config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ras_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Permission Issues

Ensure the web server has write permissions:

```bash
chmod 755 logs/
```

### Email Not Sending

Check:
1. Email notifications are enabled in Settings
2. SMTP configuration is correct
3. Recipient email address is set
4. Server allows outbound email connections

## Development

### Adding New Features

1. **New API Endpoint**: Create in `admin/api/`
2. **New Page**: Add to `admin/pages/` or `admin/`
3. **Database Changes**: Update `database/ras_schema.sql`

### Customization

- **CSS**: Edit `admin/assets/css/dashboard.css`
- **JavaScript**: Edit `admin/assets/js/dashboard.js`
- **Templates**: Edit PHP files in `admin/` and `admin/pages/`

## License

This is part of the Remote Assistance Support System project.

## Support

For issues and questions, refer to the main project documentation.
