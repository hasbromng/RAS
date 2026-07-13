# RAS Monitoring Agent - Python Client

A lightweight Python monitoring agent that collects system metrics and sends them to a RAS (Remote Assistance Support) dashboard server.

## Features

- **System Metrics Collection**: CPU, memory, disk, network, and storage health monitoring
- **Reliable Data Transmission**: Automatic retry with exponential backoff
- **Offline Buffering**: Local storage when server is unavailable
- **Lightweight Resource Usage**: Minimal CPU and memory footprint
- **Cross-Platform**: Support for Windows (as service) and Linux (as daemon)
- **Configurable**: JSON-based configuration with environment variable overrides
- **Comprehensive Logging**: Rotating log files with multiple log levels
- **Performance Optimized**: Heavy Windows inventory is cached and refreshed less often than core metrics

## Requirements

- Python 3.7 or higher
- Windows 10/11 or Linux (Ubuntu, CentOS, etc.)
- Administrator/root privileges for service installation

## Installation

### Windows Installation

1. **Download and Extract**
   - Extract the `python_agent` directory to your desired location (e.g., `C:\Program Files\RAS Agent\`)

2. **Run as Administrator**
   - Right-click `install.bat` and select "Run as administrator"

3. **Configure Settings**
   - Edit `config.json` with your settings:
     ```json
     {
       "agent": {
         "device_id": "auto-generated-or-manual-id",
         "hostname": "auto-detected-hostname",
         "api_endpoint": "http://your-server/RAS/admin/api/metrics.php",
         "api_key": "your-api-key-from-dashboard",
         "collect_interval": 60,
         "extended_refresh_interval": 300,
         "command_poll_interval": 15,
         "buffer_flush_batch_size": 100
       }
     }
     ```

4. **Start the Service**
   ```cmd
   python service/windows_service.py start
   ```
   Or use Windows Service Manager (`services.msc`)

### Linux Installation

1. **Extract Files**
   ```bash
   sudo mkdir -p /opt/ras_agent
   sudo cp -r python_agent/* /opt/ras_agent/
   cd /opt/ras_agent
   ```

2. **Run Installation Script**
   ```bash
   sudo ./install.sh
   ```

3. **Configure Settings**
   ```bash
   sudo nano config.json
   ```

4. **Start the Service**
   ```bash
   sudo systemctl start ras-agent
   sudo systemctl enable ras-agent
   ```

## Configuration

### Configuration File (config.json)

The agent supports multiple endpoint types:

```json
{
  "agent": {
    "device_id": "",                        // Auto-generated if empty
    "hostname": "",                          // Auto-detected if empty
    "api_endpoint": "http://localhost/RAS/admin/api/metrics.php",
    "api_key": "change-this-to-secure-key",
    "collect_interval": 60,                   // Seconds between fast collections
    "extended_refresh_interval": 300,         // Seconds between heavy Windows inventory refreshes
    "command_poll_interval": 15,              // Seconds between command polls
    "buffer_max_size": 1000,                 // Max buffered metrics
    "buffer_flush_batch_size": 100,          // Buffer items sent per recovery batch
    "buffer_file": "buffer.json",
    "log_file": "ras_agent.log",
    "log_max_size_mb": 10,
    "log_backup_count": 5
  },
  "thresholds": {
    "cpu_warning": 80,
    "cpu_critical": 90,
    "memory_warning": 80,
    "memory_critical": 90,
    "disk_warning": 75,
    "disk_critical": 85
  }
}
```

#### Supported Endpoint Types:

| Type | Example | Use Case |
|------|---------|----------|
| **Localhost** | `http://localhost/RAS/admin/api/metrics.php` | Development on same machine |
| **Local IP** | `http://192.168.1.100/RAS/admin/api/metrics.php` | LAN/Same network |
| **ngrok** | `https://xyz.ngrok-free.dev/RAS/admin/api/metrics.php` | Testing over internet |
| **Domain HTTPS** | `https://monitoring.company.com/RAS/admin/api/metrics.php` | Production |
| **VPS IP** | `http://203.0.113.10/RAS/admin/api/metrics.php` | Production without domain |

#### Configuration Templates Available:

- [`config.json.template`](config.json.template) - Default template
- [`config.ngrok.json`](config.ngrok.json) - ngrok testing configuration
- [`config.local.json.template`](config.local.json.template) - Local network template
- [`config.production.json.template`](config.production.json.template) - Production template

### Environment Variables

You can override configuration using environment variables:

- `RAS_API_ENDPOINT` - API endpoint URL
- `RAS_API_KEY` - Authentication key
- `RAS_DEVICE_ID` - Device identifier
- `RAS_HOSTNAME` - Device hostname
- `RAS_COLLECT_INTERVAL` - Collection interval in seconds
- `RAS_EXTENDED_REFRESH_INTERVAL` - Heavy inventory refresh interval
- `RAS_COMMAND_POLL_INTERVAL` - Server command polling interval
- `RAS_BUFFER_FLUSH_BATCH_SIZE` - Buffered metric batch size when reconnecting

## Usage

### Manual Testing

To test the agent manually without installing as a service:

```bash
# Activate virtual environment (if using)
source venv/bin/activate  # Linux
venv\Scripts\activate     # Windows

# Run agent once
python -m ras_agent.agent

# Or run with specific config
python -m ras_agent.agent --config /path/to/config.json
```

### Service Management

**Windows:**
```cmd
# Install service
python service/windows_service.py install

# Start service
python service/windows_service.py start

# Stop service
python service/windows_service.py stop

# Remove service
python service/windows_service.py remove

# Check status
sc query RASAgent
```

**Linux:**
```bash
# Start service
sudo systemctl start ras-agent

# Stop service
sudo systemctl stop ras-agent

# Restart service
sudo systemctl restart ras-agent

# Check status
sudo systemctl status ras-agent

# View logs
sudo journalctl -u ras-agent -f
```

## Metrics Collected

The agent collects the following metrics:

- **CPU Usage**: Overall percentage and per-core usage
- **Memory**: Total, used, free, and percentage
- **Disk**: Total, used, free space, and usage percentage
- **Storage Health**: Overall storage status (healthy/warning/critical)
- **Network**: Status (good/degraded/down) and primary IP
- **System**: Hostname, OS, uptime
- **Hardware Inventory**: CPU model, memory slots, GPU, SMART data, security status, and active users

## API Integration

The agent sends metrics to the RAS backend API endpoint:

**Endpoint:** `POST /admin/api/metrics.php`

**Request Format:**
```json
{
  "device_id": "unique-device-id",
  "hostname": "server-hostname",
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

**Headers:**
```
X-API-Key: your-api-key
Content-Type: application/json
```

## Development

### Running Tests

```bash
# Install test dependencies
pip install -r requirements-test.txt

# Run tests
pytest tests/

# Run with coverage
pytest tests/ --cov=ras_agent --cov-report=html
```

### Project Structure

```
python_agent/
├── ras_agent/           # Main package
│   ├── __init__.py
│   ├── agent.py         # Main orchestrator
│   ├── api_client.py    # API communication
│   ├── buffer.py        # Local buffering
│   ├── collector.py     # Metrics collection
│   ├── config.py        # Configuration management
│   └── logger.py        # Logging utilities
├── service/             # Service/daemon scripts
│   ├── windows_service.py
│   └── linux_daemon.sh
├── tests/               # Unit tests
├── config.json.template # Configuration template
├── requirements.txt     # Dependencies
├── install.bat         # Windows installer
└── README.md           # This file
```

## Troubleshooting

### Agent Not Connecting

1. **Check API Endpoint**: Verify the URL in `config.json`
2. **Check API Key**: Ensure it matches the dashboard settings
3. **Check Firewall**: Ensure outbound connections are allowed
4. **Check Logs**: Review `ras_agent.log` for error messages

### High Resource Usage

1. **Reduce Collection Interval**: Increase `collect_interval` in config
2. **Check Logging**: Ensure log rotation is working
3. **Monitor Buffer**: Large buffer may cause high memory usage

### Service Won't Start (Windows)

1. **Run as Administrator**: Ensure elevated privileges
2. **Check Dependencies**: Verify pywin32 is installed
3. **Check Event Viewer**: Look for error messages in Windows Event Logs

### Service Won't Start (Linux)

1. **Check Permissions**: Ensure proper file ownership
2. **Check Journal**: `sudo journalctl -u ras-agent -n 50`
3. **Verify Python**: Ensure correct Python version is installed

## Uninstallation

### Windows

```cmd
# Run as Administrator
uninstall.bat
```

### Linux

```bash
sudo ./uninstall.sh
```

## Security Considerations

1. **API Keys**: Store securely and rotate regularly
2. **HTTPS**: Use HTTPS for API endpoints in production
3. **File Permissions**: Restrict access to configuration files
4. **Logs**: Ensure log files don't contain sensitive information

## Performance

Typical resource usage:

- **CPU**: < 1% during collection
- **Memory**: < 50MB
- **Disk**: ~10MB for logs (with rotation)
- **Network**: ~1KB per metric submission

## License

This agent is part of the RAS (Remote Assistance Support) system.

## Support

For issues, questions, or contributions, please refer to the main RAS project documentation.

## Version History

- **1.0.0** - Initial release
  - Core metrics collection
  - Windows and Linux service support
  - Offline buffering
  - Comprehensive logging
  - Unit tests with 80%+ coverage
