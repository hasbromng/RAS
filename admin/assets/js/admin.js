/**
 * RAS Admin Panel JavaScript
 * Clean dashboard functionality
 */

// Global state
const AdminPanel = {
    currentPage: null,
    refreshInterval: null,
    sidebarCollapsed: false,

    // Initialize
    init() {
        this.bindEvents();
        this.loadPageData();
        this.startAutoRefresh();
    },

    // Bind event listeners
    bindEvents() {
        // Menu toggle in top bar
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('mobile-open');
                } else {
                    sidebar.classList.toggle('collapsed');
                    this.sidebarCollapsed = !this.sidebarCollapsed;
                    this.savePreferences();
                }
            });
        }

        // Close sidebar on mobile when clicking outside
        if (sidebar) {
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target) && menuToggle && !menuToggle.contains(e.target)) {
                        sidebar.classList.remove('mobile-open');
                    }
                }
            });
        }

        // Refresh button
        const refreshBtn = document.getElementById('refreshBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.refreshData();
            });
        }
    },

    // Load current page data
    loadPageData() {
        const urlParams = new URLSearchParams(window.location.search);
        this.currentPage = urlParams.get('page') || 'dashboard';

        switch(this.currentPage) {
    case 'dashboard':
        this.loadDashboardData();
        break;
    case 'alerts':
        this.loadAlertsData();
        break;
    case 'reports':
        this.loadReportsData();
        break;
    case 'settings':
        this.loadSettingsData();
        break;
    case 'users':
        this.loadUsersData();
        break;
    case 'logs':
        this.loadLogsData();
        break;
        }
    },

    // Load dashboard data
    async loadDashboardData() {
        try {
            const response = await fetch('api/dashboard.php');
            const result = await response.json();

            if (result.success) {
                this.updateDashboardUI(result.data);
            }
        } catch (error) {
            console.error('Error loading dashboard:', error);
        }
    },

    // Update dashboard UI
    updateDashboardUI(data) {
        // Update stat cards with animation
        this.animateValue('total-devices', data.device_stats?.total || 0);
        this.animateValue('online-devices', data.device_stats?.online || 0);
        this.animateValue('offline-devices', data.device_stats?.offline || 0);
        this.animateValue('critical-alerts', (data.device_stats?.critical || 0) + (data.alert_stats?.critical || 0));

        // Update charts if they exist
        this.updateMetricsChart(data.average_metrics);
        this.updateStatusChart(data.device_stats);

        // Update tables and lists
        this.updateRecentDevices(data.recent_devices);
        this.updateAlertsList(data.critical_alerts);
    },

    // Animate value change
    animateValue(elementId, newValue) {
        const element = document.getElementById(elementId);
        if (!element) return;

        const currentValue = parseInt(element.textContent) || 0;
        if (currentValue === newValue) return;

        // Simple animation
        element.style.transform = 'scale(1.1)';
        element.textContent = newValue;

        setTimeout(() => {
            element.style.transform = 'scale(1)';
        }, 200);
    },

    // Update metrics chart
    updateMetricsChart(metrics) {
        const ctx = document.getElementById('metricsChart');
        if (!ctx || !metrics) return;

        if (this.metricsChartInstance) {
            this.metricsChartInstance.destroy();
        }

        this.metricsChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['CPU', 'Memory', 'Disk'],
                datasets: [{
                    label: 'Penggunaan Rata-rata (%)',
                    data: [metrics.cpu || 0, metrics.memory || 0, metrics.disk || 0],
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(0, 200, 81, 0.8)',
                        'rgba(255, 171, 0, 0.8)'
                    ],
                    borderColor: [
                        '#667eea',
                        '#00c851',
                        '#ffab00'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                size: 13,
                                weight: 500
                            }
                        }
                    }
                }
            }
        });
    },

    // Update status chart
    updateStatusChart(deviceStats) {
        const ctx = document.getElementById('statusChart');
        if (!ctx || !deviceStats) return;

        if (this.statusChartInstance) {
            this.statusChartInstance.destroy();
        }

        this.statusChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Online', 'Offline', 'Warning', 'Critical'],
                datasets: [{
                    data: [
                        deviceStats.online || 0,
                        deviceStats.offline || 0,
                        deviceStats.warning || 0,
                        deviceStats.critical || 0
                    ],
                    backgroundColor: [
                        '#00c851',
                        '#33b5e5',
                        '#ffab00',
                        '#ff5252'
                    ],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 16,
                            font: {
                                size: 13
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    },

    // Update recent devices
    updateRecentDevices(devices) {
        const tbody = document.getElementById('recent-devices-body');
        if (!tbody || !devices) return;

        if (devices.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="material-icons">devices</i>
                            <p>Belum ada perangkat terdaftar</p>
                            <small>Kirim data dari agent Python untuk memulai monitoring</small>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = devices.map(device => this.createRecentDeviceRow(device)).join('');
    },

    // Create recent device table row (for dashboard, 7 columns)
    createRecentDeviceRow(device) {
        const memPercent = device.memory_total > 0 ? 
            ((device.memory_used / device.memory_total) * 100).toFixed(1) : 0;

        return `
            <tr>
                <td>
                    <div class="table-cell-title">${this.escapeHtml(device.hostname)}</div>
                    <div class="table-cell-subtitle">${this.escapeHtml(device.device_id)}</div>
                </td>
                <td>
                    <span class="status-badge ${device.status}">${device.status}</span>
                </td>
                <td>${this.formatPercentage(device.cpu_usage)}</td>
                <td>${memPercent}%</td>
                <td>${this.formatPercentage(device.disk_usage)}</td>
                <td>
                    ${device.open_alerts > 0 ? 
                        \`<span class="status-badge critical">\${device.open_alerts}</span>\` : 
                        '<span class="text-muted">-</span>'}
                </td>
                <td>
                    <a href="?page=devices&device_id=${encodeURIComponent(device.device_id)}"
                       class="btn btn-sm btn-primary">
                        <i class="material-icons tiny">visibility</i>
                    </a>
                </td>
            </tr>
        `;
    },

    // Create device table row
    createDeviceRow(device) {
        const deviceIdShort = device.device_id ? device.device_id.substring(0, 8) + '...' : '';
        const ipAddress = device.ip_address || 'N/A';
        const lastSeen = device.last_seen ? new Date(device.last_seen).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }) : 'N/A';
        
        const memPercent = device.memory_total > 0 ? 
            ((device.memory_used / device.memory_total) * 100).toFixed(1) : 0;

        return `
            <tr data-status="${device.status}">
                <td>
                    <div style="font-weight: 500;">${this.escapeHtml(device.hostname)}</div>
                    <small style="color: #636e72; font-size: 0.75rem;">ID: ${this.escapeHtml(deviceIdShort)}</small>
                </td>
                <td style="text-align: center;">
                    <span style="font-family: monospace; background: #f5f5f5; padding: 4px 8px; border-radius: 4px;">
                        ${this.escapeHtml(ipAddress)}
                    </span>
                </td>
                <td style="text-align: center;">
                    <span class="status-badge ${device.status}">${device.status.charAt(0).toUpperCase() + device.status.slice(1)}</span>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; align-items: center; gap: 8px; justify-content: center;">
                        <div style="flex: 1; max-width: 60px; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                            <div style="width: ${Math.min(device.cpu_usage || 0, 100)}%; height: 100%; background: ${this.getMetricColor(device.cpu_usage || 0)};"></div>
                        </div>
                        <span style="font-size: 0.85rem;">${this.formatPercentage(device.cpu_usage)}</span>
                    </div>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; align-items: center; gap: 8px; justify-content: center;">
                        <div style="flex: 1; max-width: 60px; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                            <div style="width: ${Math.min(memPercent, 100)}%; height: 100%; background: ${this.getMetricColor(memPercent)};"></div>
                        </div>
                        <span style="font-size: 0.85rem;">${memPercent}%</span>
                    </div>
                </td>
                <td style="text-align: left;">
                    ${this.formatDiskDetails(device)}
                </td>
                <td style="text-align: center;">
                    <span class="status-badge ${device.storage_health === 'healthy' ? 'online' : (device.storage_health === 'warning' ? 'warning' : 'critical')}">
                        ${device.storage_health ? device.storage_health.charAt(0).toUpperCase() + device.storage_health.slice(1) : 'Unknown'}
                    </span>
                </td>
                <td style="text-align: center;">
                    <small style="color: #636e72; font-size: 0.85rem;">${lastSeen}</small>
                </td>
                <td style="text-align: center;">
                    ${device.open_alerts > 0 ?
                        `<span class="status-badge critical">${device.open_alerts}</span>` :
                        '<span style="color: #bdc3c7;">-</span>'}
                </td>
                <td style="text-align: center;">
                    <a href="?page=devices&device_id=${this.escapeHtml(device.device_id)}"
                       class="btn btn-sm btn-primary" title="Lihat Detail">
                        <i class="material-icons tiny">visibility</i>
                    </a>
                </td>
            </tr>
        `;
    },

    // Update alerts list
    updateAlertsList(alerts) {
        const container = document.getElementById('alerts-list');
        if (!container || !alerts) return;

        if (alerts.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="material-icons">check_circle</i>
                    <p>Tidak ada alert aktif</p>
                </div>
            `;
            return;
        }

        container.innerHTML = alerts.map(alert => `
            <div class="alert-item alert-${alert.severity}">
                <div class="alert-header">
                    <div>
                        <strong>${this.escapeHtml(alert.hostname)}</strong>
                        <span class="status-badge ${alert.severity}">${alert.alert_type}</span>
                    </div>
                    <small>${this.formatDateTime(alert.timestamp)}</small>
                </div>
                <div class="alert-message">${this.escapeHtml(alert.message)}</div>
                <div class="alert-actions">
                    <button class="btn btn-sm" onclick="AdminPanel.acknowledgeAlert(${alert.id})">
                        <i class="material-icons" style="font-size: 14px;">check</i> Acknowledge
                    </button>
                </div>
            </div>
        `).join('');
    },

    // Load devices data
    async loadDevicesData() {
        try {
            const response = await fetch('api/devices.php');
            const result = await response.json();

            if (result.success) {
                // Only update if the table exists and is empty (PHP rendering failed)
                const tbody = document.getElementById('devices-table-body');
                if (tbody && tbody.children.length === 0) {
                    this.updateDevicesTable(result.data);
                }
            }
        } catch (error) {
            console.error('Error loading devices:', error);
        }
    },

    // Update devices table
    updateDevicesTable(devices) {
        const tbody = document.getElementById('devices-table-body');
        if (!tbody || !devices) return;

        if (devices.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <i class="material-icons">devices</i>
                            <p>Belum ada perangkat terdaftar</p>
                            <small>
                                Gunakan agent Python untuk mengirim data metrik.<br>
                                <a href="../test_client.php" target="_blank" style="color: #667eea;">Test API Client</a>
                            </small>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = devices.map(device => this.createDeviceRow(device)).join('');
    },

    // Load alerts data
    async loadAlertsData() {
        try {
            const response = await fetch('api/alerts.php?limit=100');
            const result = await response.json();

            if (result.success) {
                this.updateAlertsTable(result.data);
            }
        } catch (error) {
            console.error('Error loading alerts:', error);
        }
    },

    // Update alerts table
    updateAlertsTable(alerts) {
        const tbody = document.getElementById('alerts-table-body');
        if (!tbody || !alerts) return;

        if (alerts.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="material-icons">notifications</i>
                            <p>Belum ada alert tercatat</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = alerts.map(alert => {
            let snapshotHtml = '<span style="color: #ccc; font-size: 0.75rem;">-</span>';
            let snapshotRow = '';
            
            if (alert.snapshot_data) {
                try {
                    const snap = JSON.parse(alert.snapshot_data);
                    if (snap) {
                        const snapId = 'list-' + alert.id;
                        
                        // Default content structure
                        let contentHtml = '';
                        let hasContent = false;
                        
                        if (alert.alert_type === 'disk' || alert.alert_type === 'storage') {
                            // Disk snapshot is a dictionary of disks
                            const diskKeys = Object.keys(snap);
                            if (diskKeys.length > 0) {
                                hasContent = true;
                                contentHtml = `
                                    <div style="flex: 1;">
                                        <strong style="font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Storage Layout</strong>
                                        <table style="width: 100%; font-size: 0.75rem; margin-top: 4px; border-collapse: collapse;">
                                            ${diskKeys.map(k => {
                                                const d = snap[k];
                                                const pct = d.percent || 0;
                                                const color = pct >= 90 ? '#ef4444' : (pct >= 80 ? '#f59e0b' : '#3b82f6');
                                                return `
                                                <tr>
                                                    <td style="padding: 3px 0; border-bottom: 1px solid #f1f5f9; font-weight: bold;">${this.escapeHtml(d.mountpoint || k)}</td>
                                                    <td style="padding: 3px 0; border-bottom: 1px solid #f1f5f9;">${d.fstype || ''}</td>
                                                    <td style="padding: 3px 0; border-bottom: 1px solid #f1f5f9; text-align: right;">
                                                        <div style="width: 50px; height: 4px; background: #e2e8f0; display: inline-block; vertical-align: middle; margin-right: 4px;">
                                                            <div style="width: ${Math.min(pct, 100)}%; height: 100%; background: ${color};"></div>
                                                        </div>
                                                        <span style="color: ${color}; font-family: monospace; font-weight: 600;">${pct}%</span>
                                                    </td>
                                                </tr>
                                                `;
                                            }).join('')}
                                        </table>
                                    </div>
                                `;
                            }
                        } else if (snap.top_cpu?.length > 0 || snap.top_memory?.length > 0) {
                            hasContent = true;
                            let cpuHtml = '';
                            if (snap.top_cpu && snap.top_cpu.length > 0) {
                                cpuHtml = `
                                    <div style="flex: 1;">
                                        <strong style="font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Top CPU</strong>
                                        <table style="width: 100%; font-size: 0.75rem; margin-top: 4px; border-collapse: collapse;">
                                            ${snap.top_cpu.map(p => `
                                                <tr>
                                                    <td style="padding: 3px 0; border-bottom: 1px solid #f1f5f9;">${this.escapeHtml(p.name)}</td>
                                                    <td style="padding: 3px 0; border-bottom: 1px solid #f1f5f9; text-align: right; color: #ef4444; font-family: monospace; font-weight: 600;">${p.cpu_percent}%</td>
                                                </tr>
                                            `).join('')}
                                        </table>
                                    </div>
                                `;
                            }
                            
                            let memHtml = '';
                            if (snap.top_memory && snap.top_memory.length > 0) {
                                memHtml = `
                                    <div style="flex: 1;">
                                        <strong style="font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Top Memory</strong>
                                        <table style="width: 100%; font-size: 0.75rem; margin-top: 4px; border-collapse: collapse;">
                                            ${snap.top_memory.map(p => `
                                                <tr>
                                                    <td style="padding: 3px 0; border-bottom: 1px solid #f1f5f9;">${this.escapeHtml(p.name)}</td>
                                                    <td style="padding: 3px 0; border-bottom: 1px solid #f1f5f9; text-align: right; color: #3b82f6; font-family: monospace; font-weight: 600;">
                                                        ${p.memory_mb !== undefined ? p.memory_mb + ' MB' : p.memory_percent + '%'}
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </table>
                                    </div>
                                `;
                            }
                            
                            contentHtml = cpuHtml + memHtml;
                        }
                        
                        if (hasContent) {
                            snapshotHtml = `
                                <button type="button" class="btn btn-tiny" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; padding: 1px 5px; font-size: 0.7rem; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 3px; height: 24px; line-height: 24px;" onclick="document.getElementById('snap-row-${snapId}').style.display = document.getElementById('snap-row-${snapId}').style.display === 'none' ? 'table-row' : 'none'; event.stopPropagation();">
                                    <i class="material-icons" style="font-size: 13px;">camera_alt</i>
                                    <span>Lihat</span>
                                </button>
                            `;
                            
                            snapshotRow = `
                                <tr id="snap-row-${snapId}" style="display: none; background: #fafafa;">
                                    <td colspan="6" style="padding: 0;">
                                        <div style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <div style="display: flex; gap: 16px; text-align: left;">
                                                ${contentHtml}
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }
                    }
                } catch(e) {}
            }

            return `
                <tr data-status="${alert.status}" data-severity="${alert.severity}">
                    <td style="padding: 6px 8px; white-space: nowrap;">
                        <div style="font-weight: 500;">${this.formatDateTime(alert.timestamp).split(' ')[0]}</div>
                        <small style="color: #7f8c8d;">${this.formatDateTime(alert.timestamp).split(' ')[1]}</small>
                    </td>
                    <td style="padding: 6px 8px;">
                        <div style="font-weight: 500;">${this.escapeHtml(alert.hostname)}</div>
                        <small style="color: #636e72;">${this.escapeHtml(alert.ip_address)}</small>
                    </td>
                    <td style="padding: 6px 8px;">
                        <span class="status-badge" style="background: #e3f2fd; color: #0277bd; padding: 1px 4px; font-size: 0.7rem;">
                            ${alert.alert_type.toUpperCase()}
                        </span>
                    </td>
                    <td style="padding: 6px 8px;">
                        <span class="status-badge ${alert.severity}" style="padding: 1px 4px; font-size: 0.7rem;">
                            ${alert.severity.charAt(0).toUpperCase() + alert.severity.slice(1)}
                        </span>
                    </td>
                    <td style="padding: 6px 8px;">
                        <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${this.escapeHtml(alert.message)}">
                            ${this.escapeHtml(alert.message)}
                        </div>
                    </td>
                    <td style="padding: 6px 8px; text-align: center;">
                        ${snapshotHtml}
                    </td>
                </tr>
                ${snapshotRow}
            `;
        }).join('');
    },

    // Acknowledge alert
    async acknowledgeAlert(alertId) {
        try {
            const response = await fetch('api/alerts.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    alert_id: alertId,
                    status: 'acknowledged',
                    acknowledged_by: 'Admin'
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Alert berhasil di-acknowledge', 'success');
                this.loadPageData();
            } else {
                this.showNotification('Gagal acknowledge alert', 'error');
            }
        } catch (error) {
            console.error('Error acknowledging alert:', error);
            this.showNotification('Terjadi kesalahan', 'error');
        }
    },

    // Resolve alert
    async resolveAlert(alertId) {
        try {
            const response = await fetch('api/alerts.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    alert_id: alertId,
                    status: 'resolved',
                    acknowledged_by: 'Admin'
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Alert berhasil di-resolve', 'success');
                this.loadPageData();
            } else {
                this.showNotification('Gagal resolve alert', 'error');
            }
        } catch (error) {
            console.error('Error resolving alert:', error);
            this.showNotification('Terjadi kesalahan', 'error');
        }
    },

    // Load reports data (placeholder)
    loadReportsData() {
        // Charts are loaded inline in the page
    },

    // Load settings data (placeholder)
    loadSettingsData() {
        // Form is handled inline
    },

    // Load users data (placeholder)
    loadUsersData() {
        // Static content
    },

    // Load logs data (placeholder)
    loadLogsData() {
        // Logs are loaded inline
    },

    // Refresh data
    refreshData() {
        const refreshBtn = document.getElementById('refreshBtn');
        if (refreshBtn) {
            refreshBtn.classList.add('spinning');
        }

        this.loadPageData();

        setTimeout(() => {
            if (refreshBtn) {
                refreshBtn.classList.remove('spinning');
            }
        }, 1000);
    },

    // Start auto-refresh
    startAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }

        this.refreshInterval = setInterval(() => {
            this.loadPageData();
        }, 30000);
    },

    // Save preferences
    savePreferences() {
        localStorage.setItem('ras_admin_sidebar', this.sidebarCollapsed ? 'collapsed' : 'expanded');
    },

    // Load preferences
    loadPreferences() {
        const sidebarState = localStorage.getItem('ras_admin_sidebar');
        if (sidebarState === 'collapsed') {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.add('collapsed');
                this.sidebarCollapsed = true;
            }
        }
    },

    // Show notification
    showNotification(message, type = 'info') {
        // Remove existing notifications
        const existing = document.querySelectorAll('.temp-notification');
        existing.forEach(n => n.remove());

        // Create notification
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} temp-notification`;
        notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; animation: slideIn 0.3s ease;';
        notification.innerHTML = `
            <i class="material-icons" style="font-size: 18px;">
                ${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}
            </i>
            ${this.escapeHtml(message)}
        `;

        // Add animation
        if (!document.getElementById('notificationStyles')) {
            const style = document.createElement('style');
            style.id = 'notificationStyles';
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(notification);

        // Auto-remove
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    },

    // Helper functions
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    formatPercentage(value) {
        if (value === null || value === undefined) return '-';
        return parseFloat(value).toFixed(1) + '%';
    },

    getMetricColor(value) {
        if (value >= 90) return '#ff5252';
        if (value >= 75) return '#ffab00';
        if (value >= 50) return '#ffca28';
        return '#00c851';
    },

    formatDiskDetails(device) {
        // Try to get all_disks from additional_info
        if (device.additional_info) {
            try {
                const additionalInfo = typeof device.additional_info === 'string' 
                    ? JSON.parse(device.additional_info) 
                    : device.additional_info;
                
                if (additionalInfo.all_disks && Object.keys(additionalInfo.all_disks).length > 0) {
                    let html = '<div style="display: flex; align-items: center; gap: 6px; font-size: 0.75rem;">';
                    
                    let counter = 0;
                    for (const [diskKey, disk] of Object.entries(additionalInfo.all_disks)) {
                        if (counter >= 2) break; // Show max 2 disks
                        
                        const diskName = diskKey.replace(/_/g, ':').toUpperCase();
                        const usedGB = (disk.used / (1024 ** 3)).toFixed(2);
                        const freeGB = (disk.free / (1024 ** 3)).toFixed(2);
                        const totalGB = (disk.total / (1024 ** 3)).toFixed(2);
                        const percent = parseFloat(disk.percent).toFixed(1);
                        const color = this.getMetricColor(parseFloat(percent));
                        
                        html += `
                            <div style="display: flex; align-items: center; gap: 4px;">
                                <span style="width: 30px; text-align: right;"><strong>${diskName}</strong></span>
                                <span style="width: 6px; height: 6px; border-radius: 50%; background: ${color};"></span>
                                <span style="color: #636e72; font-family: monospace;">${percent}%</span>
                            </div>
                        `;
                        counter++;
                    }
                    
                    if (Object.keys(additionalInfo.all_disks).length > 2) {
                        html += `<span style="color: #636e72; font-style: italic;">+${Object.keys(additionalInfo.all_disks).length - 2} more</span>`;
                    }
                    
                    html += '</div>';
                    return html;
                }
            } catch (e) {
                console.error('Error parsing additional_info:', e);
            }
        }
        
        // Fallback to simple disk_usage
        return `
            <div style="display: flex; align-items: center; gap: 8px; justify-content: center;">
                <div style="flex: 1; max-width: 60px; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                    <div style="width: ${Math.min(device.disk_usage || 0, 100)}%; height: 100%; background: ${this.getMetricColor(device.disk_usage || 0)};"></div>
                </div>
                <span style="font-size: 0.85rem;">${this.formatPercentage(device.disk_usage)}</span>
            </div>
        `;
    },

    formatMemory(used, total) {
        if (!used || !total) return '-';
        const percent = ((used / total) * 100).toFixed(1);
        return percent + '%';
    },

    formatDateTime(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleString('id-ID', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        AdminPanel.init();
        AdminPanel.loadPreferences();
    });
} else {
    AdminPanel.init();
    AdminPanel.loadPreferences();
}

// Make available globally
window.AdminPanel = AdminPanel;
