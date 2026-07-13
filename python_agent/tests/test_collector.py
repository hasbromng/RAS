"""
Unit tests for RAS Agent metrics collector module.
"""

import pytest
from unittest.mock import Mock, patch

from ras_agent.collector import MetricsCollector, collect_metrics


class TestMetricsCollector:
    """Test cases for MetricsCollector class."""

    def test_collector_initialization(self):
        """Test collector initialization."""
        logger = Mock()
        collector = MetricsCollector(logger=logger)

        assert collector.logger is logger

    def test_get_cpu_usage(self):
        """Test CPU usage collection."""
        logger = Mock()
        collector = MetricsCollector(logger=logger)

        cpu_metrics = collector.get_cpu_usage()

        # Check structure
        assert 'cpu_usage' in cpu_metrics
        assert 'cpu_per_core' in cpu_metrics
        assert 'cpu_count_physical' in cpu_metrics
        assert 'cpu_count_logical' in cpu_metrics

        # Check data types
        assert isinstance(cpu_metrics['cpu_usage'], (int, float))
        assert 0 <= cpu_metrics['cpu_usage'] <= 100
        assert isinstance(cpu_metrics['cpu_per_core'], list)
        assert isinstance(cpu_metrics['cpu_count_physical'], int)
        assert isinstance(cpu_metrics['cpu_count_logical'], int)

    def test_get_memory_info(self):
        """Test memory information collection."""
        logger = Mock()
        collector = MetricsCollector(logger=logger)

        memory_info = collector.get_memory_info()

        # Check structure
        assert 'memory_total' in memory_info
        assert 'memory_used' in memory_info
        assert 'memory_free' in memory_info
        assert 'memory_available' in memory_info
        assert 'memory_percent' in memory_info
        assert 'swap_total' in memory_info
        assert 'swap_used' in memory_info

        # Check data types and ranges
        assert isinstance(memory_info['memory_total'], int)
        assert memory_info['memory_total'] > 0
        assert isinstance(memory_info['memory_used'], int)
        assert memory_info['memory_used'] >= 0
        assert 0 <= memory_info['memory_percent'] <= 100

    def test_get_disk_info(self):
        """Test disk information collection."""
        logger = Mock()
        collector = MetricsCollector(logger=logger)

        disk_info = collector.get_disk_info()

        # Check structure
        assert 'disk_total' in disk_info
        assert 'disk_used' in disk_info
        assert 'disk_free' in disk_info
        assert 'disk_usage' in disk_info
        assert 'all_disks' in disk_info

        # Check data types and ranges
        assert isinstance(disk_info['disk_total'], int)
        assert disk_info['disk_total'] >= 0
        assert isinstance(disk_info['disk_used'], int)
        assert disk_info['disk_used'] >= 0
        assert 0 <= disk_info['disk_usage'] <= 100

    def test_get_storage_health(self):
        """Test storage health determination."""
        logger = Mock()
        collector = MetricsCollector(logger=logger)

        health = collector.get_storage_health()

        # Should be one of the expected values
        assert health in ['healthy', 'warning', 'critical', 'unknown']

    def test_get_network_info(self):
        """Test network information collection."""
        logger = Mock()
        collector = MetricsCollector(logger=logger)

        network_info = collector.get_network_info()

        # Check structure
        assert 'network_status' in network_info
        assert 'primary_ip' in network_info
        assert 'bytes_sent' in network_info
        assert 'bytes_recv' in network_info
        assert 'interfaces' in network_info

        # Check data types
        assert network_info['network_status'] in ['good', 'degraded', 'down', 'unknown']
        assert isinstance(network_info['primary_ip'], str)
        assert isinstance(network_info['bytes_sent'], int)
        assert isinstance(network_info['bytes_recv'], int)

    def test_get_system_info(self):
        """Test system information collection."""
        logger = Mock()
        collector = MetricsCollector(logger=logger)

        system_info = collector.get_system_info()

        # Check structure
        assert 'hostname' in system_info
        assert 'system' in system_info
        assert 'release' in system_info
        assert 'machine' in system_info
        assert 'uptime_seconds' in system_info

        # Check data types
        assert isinstance(system_info['hostname'], str)
        assert len(system_info['hostname']) > 0
        assert isinstance(system_info['uptime_seconds'], (int, float))
        assert system_info['uptime_seconds'] > 0

    def test_collect_all_metrics(self):
        """Test collecting all metrics together."""
        logger = Mock()
        collector = MetricsCollector(logger=logger)

        metrics = collector.collect_all_metrics()

        # Check API format structure
        required_fields = [
            'cpu_usage',
            'memory_used',
            'memory_total',
            'disk_used',
            'disk_total',
            'disk_usage',
            'storage_health',
            'network_status',
            'ip_address',
            'hostname',
            'additional_info'
        ]

        for field in required_fields:
            assert field in metrics, f"Missing required field: {field}"

        # Check data types
        assert isinstance(metrics['cpu_usage'], (int, float))
        assert isinstance(metrics['memory_used'], int)
        assert isinstance(metrics['memory_total'], int)
        assert isinstance(metrics['disk_used'], int)
        assert isinstance(metrics['disk_total'], int)
        assert isinstance(metrics['disk_usage'], (int, float))

        # Check ranges
        assert 0 <= metrics['cpu_usage'] <= 100
        assert metrics['memory_used'] >= 0
        assert metrics['memory_total'] > 0
        assert metrics['disk_used'] >= 0
        assert metrics['disk_total'] > 0
        assert 0 <= metrics['disk_usage'] <= 100

        # Check enum values
        assert metrics['storage_health'] in ['healthy', 'warning', 'critical', 'unknown']
        assert metrics['network_status'] in ['good', 'degraded', 'down', 'unknown']

        # Check additional_info structure
        assert isinstance(metrics['additional_info'], dict)

    def test_collect_metrics_convenience_function(self):
        """Test the convenience function for collecting metrics."""
        logger = Mock()
        metrics = collect_metrics(logger=logger)

        # Should return the same structure as collect_all_metrics
        assert 'cpu_usage' in metrics
        assert 'memory_used' in metrics
        assert 'hostname' in metrics

    @patch('ras_agent.collector.psutil')
    def test_get_cpu_usage_error_handling(self, mock_psutil):
        """Test error handling in CPU usage collection."""
        # Simulate psutil error
        mock_psutil.cpu_percent.side_effect = Exception("Test error")

        logger = Mock()
        collector = MetricsCollector(logger=logger)

        cpu_metrics = collector.get_cpu_usage()

        # Should return default values on error
        assert cpu_metrics['cpu_usage'] == 0
        assert cpu_metrics['cpu_per_core'] == []
        assert cpu_metrics['cpu_count_physical'] == 0

        # Error should be logged
        logger.error.assert_called()

    @patch('ras_agent.collector.psutil')
    def test_get_memory_info_error_handling(self, mock_psutil):
        """Test error handling in memory info collection."""
        # Simulate psutil error
        mock_psutil.virtual_memory.side_effect = Exception("Test error")
        mock_psutil.swap_memory.return_value = Mock(total=0, used=0, free=0, percent=0)

        logger = Mock()
        collector = MetricsCollector(logger=logger)

        memory_info = collector.get_memory_info()

        # Should return default values on error
        assert memory_info['memory_total'] == 0
        assert memory_info['memory_used'] == 0
        assert memory_info['memory_percent'] == 0

        # Error should be logged
        logger.error.assert_called()

    def test_additional_info_contains_detailed_metrics(self):
        """Test that additional_info contains detailed metrics."""
        logger = Mock()
        collector = MetricsCollector(logger=logger)

        metrics = collector.collect_all_metrics()
        additional_info = metrics['additional_info']

        # Check detailed CPU metrics
        assert 'cpu_per_core' in additional_info
        assert 'cpu_count_physical' in additional_info
        assert 'cpu_count_logical' in additional_info

        # Check detailed memory metrics
        assert 'memory_percent' in additional_info

        # Check disk metrics
        assert 'all_disks' in additional_info

        # Check system info
        assert 'system' in additional_info
        assert 'uptime_seconds' in additional_info
