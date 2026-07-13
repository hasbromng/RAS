"""
RAS Agent Metrics Collector Module

This module collects system metrics using the psutil library.
It provides functions to gather CPU, memory, disk, and network information.
"""

import socket
import platform
import time
import json
import subprocess
from typing import Dict, Any, List, Optional

try:
    import psutil
    PSUTIL_AVAILABLE = True
except ImportError:
    PSUTIL_AVAILABLE = False


class MetricsCollector:
    """
    System metrics collector for RAS monitoring agent.
    """

    def __init__(self, logger=None):
        """
        Initialize metrics collector.

        Args:
            logger: Optional logger instance for logging
        """
        if not PSUTIL_AVAILABLE:
            raise ImportError("psutil library is required but not installed. "
                            "Install it with: pip install psutil")

        self.logger = logger
        self.boot_time = psutil.boot_time()
        
        # Initialize psutil cpu percent counters so subsequent non-blocking calls (interval=None)
        # return the average usage since this initialization or previous calls.
        try:
            psutil.cpu_percent(interval=None)
            psutil.cpu_percent(interval=None, percpu=True)
        except Exception as e:
            if self.logger:
                self.logger.error(f"Error initializing CPU usage counters: {e}")

    def get_cpu_usage(self) -> Dict[str, Any]:
        """
        Get CPU usage metrics.

        Returns:
            Dictionary with CPU usage information
        """
        try:
            # CPU usage percentage since last call (non-blocking, interval=None)
            cpu_percent = psutil.cpu_percent(interval=None)

            # Per-CPU usage since last call (non-blocking, interval=None)
            cpu_per_core = psutil.cpu_percent(interval=None, percpu=True)

            # CPU count
            cpu_count = psutil.cpu_count()
            cpu_count_logical = psutil.cpu_count(logical=True)

            return {
                "cpu_usage": round(cpu_percent, 2),
                "cpu_per_core": [round(percent, 2) for percent in cpu_per_core],
                "cpu_count_physical": cpu_count,
                "cpu_count_logical": cpu_count_logical
            }
        except Exception as e:
            if self.logger:
                self.logger.error(f"Error getting CPU usage: {e}")
            return {"cpu_usage": 0, "cpu_per_core": [], "cpu_count_physical": 0, "cpu_count_logical": 0}

    def get_memory_info(self) -> Dict[str, Any]:
        """
        Get memory usage metrics.

        Returns:
            Dictionary with memory information
        """
        try:
            # Virtual memory (physical RAM)
            mem = psutil.virtual_memory()

            # Swap memory
            swap = psutil.swap_memory()

            return {
                "memory_total": mem.total,
                "memory_used": mem.used,
                "memory_free": mem.free,
                "memory_available": mem.available,
                "memory_percent": round(mem.percent, 2),
                "swap_total": swap.total,
                "swap_used": swap.used,
                "swap_free": swap.free,
                "swap_percent": round(swap.percent, 2)
            }
        except Exception as e:
            if self.logger:
                self.logger.error(f"Error getting memory info: {e}")
            return {
                "memory_total": 0, "memory_used": 0, "memory_free": 0,
                "memory_available": 0, "memory_percent": 0,
                "swap_total": 0, "swap_used": 0, "swap_free": 0, "swap_percent": 0
            }

    def get_disk_info(self) -> Dict[str, Any]:
        """
        Get disk usage metrics.

        Returns:
            Dictionary with disk usage information
        """
        try:
            disk_info = {}
            disk_partitions = psutil.disk_partitions(all=False)

            for partition in disk_partitions:
                try:
                    usage = psutil.disk_usage(partition.mountpoint)

                    # Skip small or special filesystems
                    if usage.total < 1024 * 1024:  # Less than 1MB
                        continue

                    disk_key = partition.mountpoint.replace('/', '_').replace('\\', '_').replace(':', '')
                    if not disk_key or disk_key == '_':
                        disk_key = 'root'

                    disk_info[disk_key] = {
                        "device": partition.device,
                        "mountpoint": partition.mountpoint,
                        "fstype": partition.fstype,
                        "total": usage.total,
                        "used": usage.used,
                        "free": usage.free,
                        "percent": round(usage.percent, 2)
                    }
                except (PermissionError, OSError) as e:
                    # Skip partitions we can't access
                    if self.logger:
                        self.logger.debug(f"Skipping partition {partition.mountpoint}: {e}")
                    continue

            storage_layout = self._get_windows_storage_layout()
            volume_map = {
                item["drive_letter"].upper() + ":": item
                for item in storage_layout
                if item.get("drive_letter")
            }

            # Associate each visible volume with its physical disk when Windows
            # supplies that information (Disk 0, Disk 1, model, serial number).
            for info in disk_info.values():
                mountpoint = str(info.get("mountpoint", "")).rstrip("\\/").upper()
                if mountpoint in volume_map:
                    info["physical_disk"] = volume_map[mountpoint]

            # Return the first/main partition for API compatibility
            # but also include all partitions in additional_info
            main_disk = None
            if disk_info:
                # Try to find the main system disk (usually C: on Windows, / on Linux)
                for key, info in disk_info.items():
                    if info.get('mountpoint') in ['C:\\', '/', '/']:
                        main_disk = info
                        break

                # Fallback to first available disk
                if main_disk is None:
                    main_disk = next(iter(disk_info.values()))

            if main_disk:
                return {
                    "disk_total": main_disk["total"],
                    "disk_used": main_disk["used"],
                    "disk_free": main_disk["free"],
                    "disk_usage": main_disk["percent"],
                    "all_disks": disk_info,
                    "storage_layout": storage_layout
                }
            else:
                return {
                    "disk_total": 0, "disk_used": 0, "disk_free": 0,
                    "disk_usage": 0, "all_disks": {}, "storage_layout": []
                }

        except Exception as e:
            if self.logger:
                self.logger.error(f"Error getting disk info: {e}")
            return {
                "disk_total": 0, "disk_used": 0, "disk_free": 0,
                "disk_usage": 0, "all_disks": {}, "storage_layout": []
            }

    def _get_windows_storage_layout(self) -> List[Dict[str, Any]]:
        """Return Windows physical-disk to drive-letter mappings, when available."""
        if platform.system().lower() != "windows":
            return []

        command = (
            "Get-Disk | ForEach-Object { "
            "$disk = $_; Get-Partition -DiskNumber $disk.Number | "
            "Where-Object { $_.DriveLetter } | ForEach-Object { "
            "[PSCustomObject]@{disk_number=$disk.Number; model=$disk.FriendlyName; "
            "serial_number=$disk.SerialNumber; drive_letter=$_.DriveLetter; size=$_.Size} "
            "} } | ConvertTo-Json -Compress"
        )
        try:
            result = subprocess.run(
                ["powershell", "-NoProfile", "-NonInteractive", "-Command", command],
                capture_output=True, text=True, timeout=10,
                creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0)
            )
            if result.returncode != 0 or not result.stdout.strip():
                return []
            data = json.loads(result.stdout)
            return data if isinstance(data, list) else [data]
        except (OSError, subprocess.SubprocessError, json.JSONDecodeError) as e:
            if self.logger:
                self.logger.debug(f"Unable to read Windows disk layout: {e}")
            return []

    def get_storage_health(self) -> str:
        """
        Get storage health status.

        Returns:
            Storage health status: 'healthy', 'warning', 'critical', 'unknown'
        """
        try:
            disk_info = self.get_disk_info()
            disk_usage = disk_info.get("disk_usage", 0)

            if disk_usage >= 90:
                return "critical"
            elif disk_usage >= 75:
                return "warning"
            else:
                return "healthy"

        except Exception as e:
            if self.logger:
                self.logger.error(f"Error getting storage health: {e}")
            return "unknown"

    def get_network_info(self) -> Dict[str, Any]:
        """
        Get network information and status.

        Returns:
            Dictionary with network information
        """
        try:
            # Get network IO statistics
            net_io = psutil.net_io_counters()

            # Get network interfaces
            net_if_addrs = psutil.net_if_addrs()
            net_if_stats = psutil.net_if_stats()

            interfaces = {}
            for interface_name, addrs in net_if_addrs.items():
                # Get interface stats
                stats = net_if_stats.get(interface_name)

                interface_info = {
                    "isup": stats.isup if stats else False,
                    "speed": stats.speed if stats else 0,
                    "addresses": []
                }

                # Get addresses for this interface
                for addr in addrs:
                    interface_info["addresses"].append({
                        "family": str(addr.family),
                        "address": addr.address,
                        "netmask": addr.netmask,
                        "broadcast": addr.broadcast
                    })

                interfaces[interface_name] = interface_info

            # Determine primary IP address
            primary_ip = self._get_primary_ip()

            # Determine network status
            network_status = self._determine_network_status(interfaces, primary_ip)

            return {
                "network_status": network_status,
                "primary_ip": primary_ip,
                "bytes_sent": net_io.bytes_sent,
                "bytes_recv": net_io.bytes_recv,
                "packets_sent": net_io.packets_sent,
                "packets_recv": net_io.packets_recv,
                "interfaces": interfaces
            }

        except Exception as e:
            if self.logger:
                self.logger.error(f"Error getting network info: {e}")
            return {
                "network_status": "unknown",
                "primary_ip": "0.0.0.0",
                "bytes_sent": 0, "bytes_recv": 0,
                "packets_sent": 0, "packets_recv": 0,
                "interfaces": {}
            }

    def _get_primary_ip(self) -> str:
        """
        Get the primary IP address of the machine.

        Returns:
            Primary IP address as string
        """
        try:
            # Try to connect to an external address (doesn't actually send data)
            # This gives us the local IP used for outgoing connections
            with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as s:
                # Use Google's DNS server (doesn't actually connect)
                s.connect(("8.8.8.8", 80))
                ip = s.getsockname()[0]
                return ip
        except Exception:
            # Fallback to localhost
            return "127.0.0.1"

    def _determine_network_status(self, interfaces: Dict, primary_ip: str) -> str:
        """
        Determine overall network status.

        Args:
            interfaces: Network interfaces information
            primary_ip: Primary IP address

        Returns:
            Network status: 'good', 'degraded', 'down', 'unknown'
        """
        try:
            # Check if we have a valid IP (not localhost)
            if primary_ip in ["127.0.0.1", "::1", "0.0.0.0"]:
                return "down"

            # Check if any interface is up
            up_interfaces = sum(1 for iface in interfaces.values() if iface.get("isup"))
            if up_interfaces == 0:
                return "down"
            elif up_interfaces == 1:
                return "good"
            else:
                return "good"

        except Exception:
            return "unknown"

    def get_system_info(self) -> Dict[str, Any]:
        """
        Get system information.

        Returns:
            Dictionary with system information
        """
        try:
            return {
                "hostname": platform.node(),
                "system": platform.system(),
                "release": platform.release(),
                "version": platform.version(),
                "machine": platform.machine(),
                "processor": platform.processor(),
                "uptime_seconds": time.time() - self.boot_time
            }
        except Exception as e:
            if self.logger:
                self.logger.error(f"Error getting system info: {e}")
            return {
                "hostname": "unknown",
                "system": "unknown",
                "release": "unknown",
                "version": "unknown",
                "machine": "unknown",
                "processor": "unknown",
                "uptime_seconds": 0
            }

    def get_windows_device_details(self) -> Dict[str, Any]:
        """Collect Windows edition, build, BIOS and motherboard details when permitted."""
        if platform.system().lower() != "windows":
            return {}

        command = (
            "$os=Get-ItemProperty 'HKLM:\\SOFTWARE\\Microsoft\\Windows NT\\CurrentVersion'; "
            "try{$board=Get-CimInstance Win32_BaseBoard -ErrorAction Stop}catch{$board=$null}; "
            "try{$bios=Get-CimInstance Win32_BIOS -ErrorAction Stop}catch{$bios=$null}; "
            "[PSCustomObject]@{edition=$os.EditionID; display_version=$os.DisplayVersion; "
            "release_id=$os.ReleaseId; os_build=($os.CurrentBuildNumber + '.' + $os.UBR); "
            "installed_on=$os.InstallDate; motherboard_manufacturer=if($board){$board.Manufacturer}; "
            "motherboard_product=if($board){$board.Product}; motherboard_serial=if($board){$board.SerialNumber}; "
            "bios_version=if($bios){$bios.SMBIOSBIOSVersion}; bios_date=if($bios){$bios.ReleaseDate}} | ConvertTo-Json -Compress"
        )
        try:
            result = subprocess.run(
                ["powershell", "-NoProfile", "-NonInteractive", "-Command", command],
                capture_output=True, text=True, timeout=10,
                creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0)
            )
            return json.loads(result.stdout) if result.returncode == 0 and result.stdout.strip() else {}
        except (OSError, subprocess.SubprocessError, json.JSONDecodeError) as e:
            if self.logger:
                self.logger.debug(f"Unable to read Windows device details: {e}")
            return {}

    def get_cpu_model(self) -> str:
        """Get CPU model name string."""
        try:
            if platform.system().lower() == "windows":
                result = subprocess.run(
                    ["powershell", "-NoProfile", "-NonInteractive", "-Command",
                     "(Get-CimInstance Win32_Processor | Select-Object -First 1).Name"],
                    capture_output=True, text=True, timeout=8,
                    creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0)
                )
                if result.returncode == 0 and result.stdout.strip():
                    return result.stdout.strip()
            # Fallback: platform.processor()
            return platform.processor() or "Unknown"
        except Exception as e:
            if self.logger:
                self.logger.debug(f"Unable to get CPU model: {e}")
            return platform.processor() or "Unknown"

    def get_memory_slots(self) -> List[Dict[str, Any]]:
        """Get physical memory slot details (Windows only via WMI)."""
        if platform.system().lower() != "windows":
            return []
        command = (
            "Get-CimInstance Win32_PhysicalMemory | "
            "Select-Object BankLabel, Manufacturer, Capacity, Speed, MemoryType, SMBIOSMemoryType | "
            "ConvertTo-Json -Compress"
        )
        try:
            result = subprocess.run(
                ["powershell", "-NoProfile", "-NonInteractive", "-Command", command],
                capture_output=True, text=True, timeout=10,
                creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0)
            )
            if result.returncode != 0 or not result.stdout.strip():
                return []
            data = json.loads(result.stdout)
            slots = data if isinstance(data, list) else [data]
            out = []
            for s in slots:
                out.append({
                    "slot": s.get("BankLabel", ""),
                    "manufacturer": s.get("Manufacturer", ""),
                    "capacity_bytes": s.get("Capacity", 0),
                    "speed_mhz": s.get("Speed", 0),
                })
            return out
        except (OSError, subprocess.SubprocessError, json.JSONDecodeError) as e:
            if self.logger:
                self.logger.debug(f"Unable to read memory slots: {e}")
            return []

    def get_gpu_info(self) -> List[Dict[str, Any]]:
        """Get GPU details (Windows only via WMI)."""
        if platform.system().lower() != "windows":
            return []
        command = (
            "Get-CimInstance Win32_VideoController | "
            "Select-Object Name, AdapterRAM, DriverVersion, VideoProcessor | "
            "ConvertTo-Json -Compress"
        )
        try:
            result = subprocess.run(
                ["powershell", "-NoProfile", "-NonInteractive", "-Command", command],
                capture_output=True, text=True, timeout=10,
                creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0)
            )
            if result.returncode != 0 or not result.stdout.strip():
                return []
            data = json.loads(result.stdout)
            gpus = data if isinstance(data, list) else [data]
            out = []
            for g in gpus:
                out.append({
                    "name": g.get("Name", ""),
                    "vram_bytes": g.get("AdapterRAM", 0),
                    "driver_version": g.get("DriverVersion", ""),
                    "video_processor": g.get("VideoProcessor", ""),
                })
            return out
        except (OSError, subprocess.SubprocessError, json.JSONDecodeError) as e:
            if self.logger:
                self.logger.debug(f"Unable to read GPU info: {e}")
            return []

    def get_storage_smart(self) -> List[Dict[str, Any]]:
        """Get basic SMART/health data via Get-PhysicalDisk and reliability counters (Windows)."""
        if platform.system().lower() != "windows":
            return []
        command = (
            "Get-PhysicalDisk | ForEach-Object { "
            "$d = $_; "
            "try { $r = Get-StorageReliabilityCounter -PhysicalDisk $d -ErrorAction Stop } catch { $r = $null }; "
            "[PSCustomObject]@{ "
            "FriendlyName=$d.FriendlyName; MediaType=$d.MediaType; HealthStatus=$d.HealthStatus; "
            "OperationalStatus=$d.OperationalStatus; Size=$d.Size; "
            "Temperature=if($r){$r.Temperature}else{$null}; "
            "Wear=if($r){$r.Wear}else{$null}; "
            "ReadErrorsTotal=if($r){$r.ReadErrorsTotal}else{$null} "
            "} } | ConvertTo-Json -Compress"
        )
        try:
            result = subprocess.run(
                ["powershell", "-NoProfile", "-NonInteractive", "-Command", command],
                capture_output=True, text=True, timeout=15,
                creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0)
            )
            if result.returncode != 0 or not result.stdout.strip():
                return []
            data = json.loads(result.stdout)
            disks = data if isinstance(data, list) else [data]
            out = []
            for d in disks:
                out.append({
                    "name": d.get("FriendlyName", ""),
                    "media_type": d.get("MediaType", ""),
                    "health_status": d.get("HealthStatus", ""),
                    "operational_status": d.get("OperationalStatus", ""),
                    "size_bytes": d.get("Size", 0),
                    "temperature_celsius": d.get("Temperature"),
                    "wear_percent": d.get("Wear"),
                    "read_errors_total": d.get("ReadErrorsTotal"),
                })
            return out
        except (OSError, subprocess.SubprocessError, json.JSONDecodeError) as e:
            if self.logger:
                self.logger.debug(f"Unable to read SMART data: {e}")
            return []

    def get_security_info(self) -> Dict[str, Any]:
        """Get Windows security status: Defender/antivirus, firewall, BitLocker, Windows Update."""
        if platform.system().lower() != "windows":
            return {}
        command = (
            "$fw = try{(Get-NetFirewallProfile -ErrorAction Stop | "
            "Where-Object Enabled -eq True | Measure-Object).Count}catch{$null}; "
            "$mp = try{Get-MpComputerStatus -ErrorAction Stop}catch{$null}; "
            "$wu = try{(New-Object -ComObject Microsoft.Update.Session).CreateUpdateSearcher().QueryHistory(0,1) | "
            "Select-Object -First 1 -ExpandProperty Date}catch{$null}; "
            "$bl = try{manage-bde -status C: 2>$null | Select-String 'Protection Status' | "
            "ForEach-Object{$_ -replace '.*Protection Status:\\s*',''}}catch{$null}; "
            "[PSCustomObject]@{ "
            "firewall_profiles_enabled=$fw; "
            "antivirus_enabled=if($mp){$mp.AntivirusEnabled}else{$null}; "
            "antivirus_up_to_date=if($mp){$mp.AntivirusSignatureAge -le 7}else{$null}; "
            "antivirus_signature_age=if($mp){$mp.AntivirusSignatureAge}else{$null}; "
            "real_time_protection=if($mp){$mp.RealTimeProtectionEnabled}else{$null}; "
            "last_windows_update=$wu; "
            "bitlocker_status=if($bl){$bl.Trim()}else{'Unknown'} "
            "} } | ConvertTo-Json -Compress"
        )
        try:
            result = subprocess.run(
                ["powershell", "-NoProfile", "-NonInteractive", "-Command", command],
                capture_output=True, text=True, timeout=20,
                creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0)
            )
            if result.returncode != 0 or not result.stdout.strip():
                return {}
            # result may be array wrapped, take first
            raw = json.loads(result.stdout)
            return raw[0] if isinstance(raw, list) else raw
        except (OSError, subprocess.SubprocessError, json.JSONDecodeError) as e:
            if self.logger:
                self.logger.debug(f"Unable to read security info: {e}")
            return {}

    def get_active_users(self) -> List[str]:
        """Get list of currently logged-on users (Windows via query user, fallback via psutil)."""
        users = []
        try:
            if platform.system().lower() == "windows":
                result = subprocess.run(
                    ["query", "user"],
                    capture_output=True, text=True, timeout=8,
                    creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0)
                )
                if result.returncode == 0:
                    lines = result.stdout.strip().splitlines()
                    for line in lines[1:]:  # skip header
                        parts = line.split()
                        if parts:
                            name = parts[0].lstrip(">")
                            if name:
                                users.append(name)
            if not users:
                for u in psutil.users():
                    if u.name and u.name not in users:
                        users.append(u.name)
        except Exception as e:
            if self.logger:
                self.logger.debug(f"Unable to get active users: {e}")
        return users

    def collect_all_metrics(self) -> Dict[str, Any]:
        """
        Collect all system metrics.

        Returns:
            Dictionary with all metrics in the format expected by the API
        """
        if self.logger:
            self.logger.debug("Collecting system metrics...")

        # Collect all metrics
        cpu_metrics = self.get_cpu_usage()
        memory_metrics = self.get_memory_info()
        disk_metrics = self.get_disk_info()
        network_metrics = self.get_network_info()
        system_info = self.get_system_info()
        windows_details = self.get_windows_device_details()
        storage_health = self.get_storage_health()

        # Extended hardware/security data (Windows)
        cpu_model = self.get_cpu_model()
        memory_slots = self.get_memory_slots()
        gpu_info = self.get_gpu_info()
        storage_smart = self.get_storage_smart()
        security_info = self.get_security_info()
        active_users = self.get_active_users()

        # Build metrics dictionary in API format
        metrics = {
            "cpu_usage": cpu_metrics.get("cpu_usage", 0),
            "memory_used": memory_metrics.get("memory_used", 0),
            "memory_total": memory_metrics.get("memory_total", 0),
            "disk_used": disk_metrics.get("disk_used", 0),
            "disk_total": disk_metrics.get("disk_total", 0),
            "disk_usage": disk_metrics.get("disk_usage", 0),
            "storage_health": storage_health,
            "network_status": network_metrics.get("network_status", "unknown"),
            "ip_address": network_metrics.get("primary_ip", "0.0.0.0"),
            "hostname": system_info.get("hostname", "unknown"),
            "additional_info": {
                # CPU
                "cpu_model": cpu_model,
                "cpu_per_core": cpu_metrics.get("cpu_per_core", []),
                "cpu_count_physical": cpu_metrics.get("cpu_count_physical", 0),
                "cpu_count_logical": cpu_metrics.get("cpu_count_logical", 0),
                # Memory
                "memory_percent": memory_metrics.get("memory_percent", 0),
                "memory_slots": memory_slots,
                # GPU
                "gpu": gpu_info,
                # Storage
                "all_disks": disk_metrics.get("all_disks", {}),
                "storage_layout": disk_metrics.get("storage_layout", []),
                "storage_smart": storage_smart,
                # Network
                "network_interfaces": network_metrics.get("interfaces", {}),
                # System
                "system": system_info.get("system", "unknown"),
                "system_release": system_info.get("release", ""),
                "system_machine": system_info.get("machine", ""),
                "system_details": windows_details,
                "uptime_seconds": system_info.get("uptime_seconds", 0),
                # Security
                "security": security_info,
                # Users
                "active_users": active_users,
            }
        }

        if self.logger:
            self.logger.debug(f"Metrics collected: CPU={metrics['cpu_usage']}%, "
                            f"Memory={memory_metrics.get('memory_percent', 0)}%, "
                            f"Disk={metrics['disk_usage']}%")

        return metrics


# Convenience function for standalone usage
def collect_metrics(logger=None) -> Dict[str, Any]:
    """
    Collect all system metrics.

    Args:
        logger: Optional logger instance

    Returns:
        Dictionary with all metrics
    """
    collector = MetricsCollector(logger=logger)
    return collector.collect_all_metrics()
