/**
 * RAS Dashboard - JavaScript
 * Remote Assistance Support System
 */

// API Base URL
const API_BASE_URL = 'api';

// Dashboard state
let dashboardData = {
    refreshInterval: null,
    devices: [],
    alerts: []
};

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Materialize components
    M.AutoInit();

    // Initialize navigation
    initNavigation();

    // Load initial data
    loadDashboardData();

    // Initialize settings forms
    initSettingsForms();

    // Start auto-refresh
    startAutoRefresh();
});

/**
 * Initialize navigation
 */
function initNavigation() {
    const navLinks = document.querySelectorAll('#nav-mobile a, .sidenav a');
    const sections = document.querySelectorAll('main > section');

    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);

            // Update active nav link
            navLinks.forEach(l => l.parentElement.classList.remove('active'));
            if (this.parentElement) {
                this.parentElement.classList.add('active');
            }

            // Show target section
            sections.forEach(section => {
                section.classList.remove('section-active');
            });
            const targetSection = document.getElementById(targetId + '-section');
            if (targetSection) {
                targetSection.classList.add('section-active');

                // Load section-specific data
                loadSectionData(targetId);
            }

            // Close mobile nav
            const sidenav = document.querySelector('.sidenav');
            if (sidenav && M.Sidenav.getInstance(sidenav)) {
                M.Sidenav.getInstance(sidenav).close();
            }
        });
    });
}

/**
 * Load section-specific data
 */
function loadSectionData(sectionId) {
    switch(sectionId) {
        case 'devices':
            loadDevicesData();
            break;
        case 'alerts':
            loadAlertsData();
            break;
        case 'reports':
            loadReportsData();
            break;
        case 'settings':
            loadSettingsData();
            break;
    }
}

/**
 * Load dashboard data
 */
async function loadDashboardData() {
    try {
        const response = await fetch(`${API_BASE_URL}/dashboard.php`);
        const result = await response.json();

        if (result.success) {
            updateDashboard(result.data);
        } else {
            showNotification('Failed to load dashboard data', 'error');
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showNotification('Error loading dashboard data', 'error');
    }
}

/**
 * Update dashboard UI
 */
function updateDashboard(data) {
    // Update summary cards
    updateSummaryCards(data.device_stats, data.alert_stats);

    // Update average metrics
    updateAverageMetrics(data.average_metrics);

    // Update recent devices table
    updateRecentDevices(data.recent_devices);

    // Update alerts list
    updateAlertsList(data.critical_alerts);

    // Update refresh interval
    if (data.refresh_interval) {
        dashboardData.refreshInterval = data.refresh_interval * 1000;
    }
}

/**
 * Update summary cards
 */
function updateSummaryCards(deviceStats, alertStats) {
    document.getElementById('online-count').textContent = deviceStats.online || 0;
    document.getElementById('offline-count').textContent = deviceStats.offline || 0;
    document.getElementById('warning-count').textContent = (deviceStats.warning || 0) + (alertStats.warning || 0);
    document.getElementById('critical-count').textContent = (deviceStats.critical || 0) + (alertStats.critical || 0);
}

/**
 * Update average metrics
 */
function updateAverageMetrics(metrics) {
    const cpuValue = document.getElementById('avg-cpu-value');
    const cpuBar = document.getElementById('avg-cpu-bar');
    const memValue = document.getElementById('avg-memory-value');
    const memBar = document.getElementById('avg-memory-bar');
    const diskValue = document.getElementById('avg-disk-value');
    const diskBar = document.getElementById('avg-disk-bar');

    if (cpuValue) {
        cpuValue.textContent = metrics.cpu + '%';
        cpuBar.style.width = metrics.cpu + '%';
        cpuBar.style.background = getMetricColor(metrics.cpu);
    }

    if (memValue) {
        memValue.textContent = metrics.memory + '%';
        memBar.style.width = metrics.memory + '%';
        memBar.style.background = getMetricColor(metrics.memory);
    }

    if (diskValue) {
        diskValue.textContent = metrics.disk + '%';
        diskBar.style.width = metrics.disk + '%';
        diskBar.style.background = getMetricColor(metrics.disk);
    }
}

/**
 * Get metric color based on value
 */
function getMetricColor(value) {
    if (value >= 90) return '#f44336';
    if (value >= 75) return '#ff9800';
    if (value >= 50) return '#ffeb3b';
    return '#4caf50';
}

/**
 * Update recent devices table
 */
function updateRecentDevices(devices) {
    const tbody = document.getElementById('recent-devices-body');

    if (!devices || devices.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="center-align">No devices found</td></tr>';
        return;
    }

    tbody.innerHTML = devices.map(device => `
        <tr>
            <td>${device.hostname}</td>
            <td>${device.ip_address}</td>
            <td>${getStatusBadge(device.status)}</td>
            <td>${formatPercentage(device.cpu_usage)}</td>
            <td>${formatMemory(device.memory_used, device.memory_total)}</td>
            <td>${formatPercentage(device.disk_usage)}</td>
            <td>${device.open_alerts || 0}</td>
            <td>${formatDateTime(device.last_seen)}</td>
        </tr>
    `).join('');
}

/**
 * Update alerts list
 */
function updateAlertsList(alerts) {
    const alertsContainer = document.getElementById('alerts-list');

    if (!alerts || alerts.length === 0) {
        alertsContainer.innerHTML = '<p class="grey-text">No active alerts</p>';
        return;
    }

    alertsContainer.innerHTML = alerts.map(alert => `
        <div class="alert-item alert-${alert.severity}">
            <div class="alert-header">
                <div class="alert-title">
                    <i class="material-icons">${getAlertIcon(alert.severity)}</i>
                    ${alert.hostname} - ${alert.alert_type.toUpperCase()}
                </div>
                <div class="alert-time">${formatDateTime(alert.timestamp)}</div>
            </div>
            <div class="alert-message">${alert.message}</div>
            <div class="alert-actions">
                <button class="btn-small waves-effect waves-light" onclick="acknowledgeAlert(${alert.id})">
                    Acknowledge
                </button>
                <button class="btn-small waves-effect waves-light btn-flat" onclick="viewDevice('${alert.device_id}')">
                    View Device
                </button>
            </div>
        </div>
    `).join('');
}

/**
 * Load devices data
 */
async function loadDevicesData() {
    try {
        const response = await fetch(`${API_BASE_URL}/devices.php`);
        const result = await response.json();

        if (result.success) {
            updateDevicesList(result.data);
        }
    } catch (error) {
        console.error('Error loading devices:', error);
    }
}

/**
 * Update devices list
 */
function updateDevicesList(devices) {
    const tbody = document.getElementById('devices-list-body');

    if (!devices || devices.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="center-align">No devices found</td></tr>';
        return;
    }

    tbody.innerHTML = devices.map(device => `
        <tr>
            <td>${device.hostname}</td>
            <td>${device.ip_address}</td>
            <td>${getStatusBadge(device.status)}</td>
            <td>${formatPercentage(device.cpu_usage)}</td>
            <td>${formatMemory(device.memory_used, device.memory_total)}</td>
            <td>${formatPercentage(device.disk_usage)}</td>
            <td>${formatDateTime(device.last_seen)}</td>
            <td>
                <button class="btn-small waves-effect waves-light" onclick="viewDevice('${device.device_id}')">
                    <i class="material-icons">visibility</i>
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Load alerts data
 */
async function loadAlertsData() {
    try {
        const response = await fetch(`${API_BASE_URL}/alerts.php?limit=100`);
        const result = await response.json();

        if (result.success) {
            updateAlertsHistory(result.data);
        }
    } catch (error) {
        console.error('Error loading alerts:', error);
    }
}

/**
 * Update alerts history
 */
function updateAlertsHistory(alerts) {
    const container = document.getElementById('alerts-history-list');

    if (!alerts || alerts.length === 0) {
        container.innerHTML = '<p class="grey-text">No alerts found</p>';
        return;
    }

    container.innerHTML = alerts.map(alert => `
        <div class="alert-item alert-${alert.severity}">
            <div class="alert-header">
                <div class="alert-title">
                    <i class="material-icons">${getAlertIcon(alert.severity)}</i>
                    ${alert.hostname} - ${alert.alert_type.toUpperCase()}
                </div>
                <div class="alert-time">${formatDateTime(alert.timestamp)}</div>
            </div>
            <div class="alert-message">${alert.message}</div>
            <div class="alert-actions">
                <span class="status-badge status-${alert.status}">${alert.status}</span>
                ${alert.status === 'open' ? `
                    <button class="btn-small waves-effect waves-light" onclick="acknowledgeAlert(${alert.id})">
                        Acknowledge
                    </button>
                    <button class="btn-small waves-effect waves-light red" onclick="resolveAlert(${alert.id})">
                        Resolve
                    </button>
                ` : ''}
            </div>
        </div>
    `).join('');
}

/**
 * Load reports data
 */
async function loadReportsData() {
    // Load device list for report generation
    try {
        const response = await fetch(`${API_BASE_URL}/devices.php`);
        const result = await response.json();

        if (result.success) {
            const deviceSelect = document.getElementById('report-device');
            deviceSelect.innerHTML = '<option value="all">All Devices</option>' +
                result.data.map(device =>
                    `<option value="${device.device_id}">${device.hostname}</option>`
                ).join('');
            M.FormSelect.init(deviceSelect);
        }
    } catch (error) {
        console.error('Error loading devices:', error);
    }
}

/**
 * Generate report
 */
async function generateReport() {
    const reportType = document.getElementById('report-type').value;
    const deviceId = document.getElementById('report-device').value;

    showNotification('Generating report...', 'info');

    // This would generate a report view
    // For MVP, we'll show a chart
    showNotification(`Report generation for ${reportType} - ${deviceId} not yet implemented`, 'warning');
}

/**
 * Export to CSV
 */
function exportToCSV() {
    showNotification('CSV export not yet implemented', 'warning');
}

/**
 * Load settings data
 */
async function loadSettingsData() {
    try {
        const response = await fetch(`${API_BASE_URL}/settings.php`);
        const result = await response.json();

        if (result.success) {
            updateSettingsUI(result.data);
        }
    } catch (error) {
        console.error('Error loading settings:', error);
    }
}

/**
 * Update settings UI
 */
function updateSettingsUI(settings) {
    // Thresholds
    if (settings.alert_threshold_cpu) {
        document.getElementById('cpu-threshold').value = settings.alert_threshold_cpu.value;
    }
    if (settings.alert_threshold_memory) {
        document.getElementById('memory-threshold').value = settings.alert_threshold_memory.value;
    }
    if (settings.alert_threshold_disk) {
        document.getElementById('disk-threshold').value = settings.alert_threshold_disk.value;
    }

    // Email settings
    if (settings.email_enabled) {
        document.getElementById('email-enabled').checked = settings.email_enabled.value;
    }
    if (settings.email_smtp_host) {
        document.getElementById('smtp-host').value = settings.email_smtp_host.value;
    }
    if (settings.email_smtp_port) {
        document.getElementById('smtp-port').value = settings.email_smtp_port.value;
    }
    if (settings.email_smtp_secure) {
        document.getElementById('smtp-secure').value = settings.email_smtp_secure.value;
    }
    if (settings.email_from_address) {
        document.getElementById('email-from').value = settings.email_from_address.value;
    }
    if (settings.email_to_address) {
        document.getElementById('email-to').value = settings.email_to_address.value;
    }
}

/**
 * Initialize settings forms
 */
function initSettingsForms() {
    // Thresholds form
    const thresholdsForm = document.getElementById('thresholds-form');
    if (thresholdsForm) {
        thresholdsForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const data = {
                alert_threshold_cpu: parseInt(document.getElementById('cpu-threshold').value),
                alert_threshold_memory: parseInt(document.getElementById('memory-threshold').value),
                alert_threshold_disk: parseInt(document.getElementById('disk-threshold').value)
            };

            await saveSettings('Alert thresholds', data);
        });
    }

    // Email form
    const emailForm = document.getElementById('email-form');
    if (emailForm) {
        emailForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const data = {
                email_enabled: document.getElementById('email-enabled').checked,
                email_smtp_host: document.getElementById('smtp-host').value,
                email_smtp_port: parseInt(document.getElementById('smtp-port').value),
                email_smtp_secure: document.getElementById('smtp-secure').value,
                email_from_address: document.getElementById('email-from').value,
                email_to_address: document.getElementById('email-to').value
            };

            await saveSettings('Email settings', data);
        });
    }
}

/**
 * Save settings
 */
async function saveSettings(settingName, data) {
    try {
        const response = await fetch(`${API_BASE_URL}/settings.php`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showNotification(`${settingName} saved successfully`, 'success');
        } else {
            showNotification(`Failed to save ${settingName.toLowerCase()}`, 'error');
        }
    } catch (error) {
        console.error('Error saving settings:', error);
        showNotification('Error saving settings', 'error');
    }
}

/**
 * Acknowledge alert
 */
async function acknowledgeAlert(alertId) {
    try {
        const response = await fetch(`${API_BASE_URL}/alerts.php`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                alert_id: alertId,
                status: 'acknowledged',
                acknowledged_by: 'Admin'
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Alert acknowledged', 'success');
            // Reload current section
            loadSectionData('alerts');
        } else {
            showNotification('Failed to acknowledge alert', 'error');
        }
    } catch (error) {
        console.error('Error acknowledging alert:', error);
        showNotification('Error acknowledging alert', 'error');
    }
}

/**
 * Resolve alert
 */
async function resolveAlert(alertId) {
    try {
        const response = await fetch(`${API_BASE_URL}/alerts.php`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                alert_id: alertId,
                status: 'resolved',
                acknowledged_by: 'Admin'
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Alert resolved', 'success');
            loadSectionData('alerts');
        } else {
            showNotification('Failed to resolve alert', 'error');
        }
    } catch (error) {
        console.error('Error resolving alert:', error);
        showNotification('Error resolving alert', 'error');
    }
}

/**
 * View device details
 */
async function viewDevice(deviceId) {
    try {
        const response = await fetch(`${API_BASE_URL}/devices.php?id=${deviceId}`);
        const result = await response.json();

        if (result.success) {
            showDeviceDetails(result.data);
        }
    } catch (error) {
        console.error('Error loading device details:', error);
    }
}

/**
 * Show device details (simplified for MVP)
 */
function showDeviceDetails(device) {
    // Navigate to devices section and show details
    const devicesLink = document.querySelector('a[href="#devices"]');
    if (devicesLink) {
        devicesLink.click();
    }

    showNotification(`Device details: ${device.hostname}`, 'info');
}

/**
 * Start auto-refresh
 */
function startAutoRefresh() {
    // Default refresh interval (30 seconds)
    const interval = dashboardData.refreshInterval || 30000;

    // Clear existing interval
    if (dashboardData.refreshTimer) {
        clearInterval(dashboardData.refreshTimer);
    }

    // Set up auto-refresh
    dashboardData.refreshTimer = setInterval(() => {
        const activeSection = document.querySelector('main > section.section-active');
        if (activeSection) {
            const sectionId = activeSection.id.replace('-section', '');
            loadSectionData(sectionId);
        }
    }, interval);
}

/**
 * Show notification
 */
function showNotification(message, type = 'info') {
    if (typeof M !== 'undefined' && M.toast) {
        M.toast({
            html: message,
            classes: type
        });
    } else {
        alert(message);
    }
}

/**
 * Format percentage
 */
function formatPercentage(value) {
    if (value === null || value === undefined) return '-';
    return value.toFixed(1) + '%';
}

/**
 * Format memory
 */
function formatMemory(used, total) {
    if (!used || !total) return '-';

    const usedGB = (used / (1024 ** 3)).toFixed(1);
    const totalGB = (total / (1024 ** 3)).toFixed(1);
    const percent = ((used / total) * 100).toFixed(1);

    return `${usedGB}GB / ${totalGB}GB (${percent}%)`;
}

/**
 * Format date time
 */
function formatDateTime(dateString) {
    if (!dateString) return '-';

    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;

    // If less than 24 hours, show relative time
    if (diff < 86400000) {
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return Math.floor(diff / 60000) + ' min ago';
        return Math.floor(diff / 3600000) + ' hr ago';
    }

    // Otherwise show formatted date
    return date.toLocaleString();
}

/**
 * Get status badge HTML
 */
function getStatusBadge(status) {
    const statusMap = {
        'online': 'status-online',
        'offline': 'status-offline',
        'warning': 'status-warning',
        'critical': 'status-critical'
    };

    const className = statusMap[status] || 'status-offline';
    return `<span class="status-badge ${className}">${status || 'Unknown'}</span>`;
}

/**
 * Get alert icon
 */
function getAlertIcon(severity) {
    const iconMap = {
        'critical': 'error',
        'warning': 'warning',
        'info': 'info'
    };

    return iconMap[severity] || 'info';
}
