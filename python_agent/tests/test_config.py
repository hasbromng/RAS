"""
Unit tests for RAS Agent configuration module.
"""

import os
import json
import tempfile
import pytest
from pathlib import Path

from ras_agent.config import Config, get_config, reset_config


class TestConfig:
    """Test cases for Config class."""

    def setup_method(self):
        """Reset global config before each test."""
        reset_config()

    def test_default_config_loading(self):
        """Test that default configuration is loaded correctly."""
        config = Config()

        # Check default values
        assert config.get('agent', 'api_endpoint') == 'http://localhost/RAS/admin/api/metrics.php'
        assert config.get('agent', 'collect_interval') == 60
        assert config.get('agent', 'extended_refresh_interval') == 300
        assert config.get('agent', 'command_poll_interval') == 15
        assert config.get('agent', 'buffer_max_size') == 1000

        # Check threshold defaults
        assert config.get('thresholds', 'cpu_warning') == 80
        assert config.get('thresholds', 'cpu_critical') == 90

    def test_device_id_auto_generation(self):
        """Test that device_id is auto-generated when empty."""
        config = Config()
        device_id = config.get('agent', 'device_id')

        assert device_id is not None
        assert len(device_id) > 0
        # Should be a UUID-like format
        assert '-' in device_id or len(device_id) == 36

    def test_hostname_auto_detection(self):
        """Test that hostname is auto-detected when empty."""
        config = Config()
        hostname = config.get('agent', 'hostname')

        assert hostname is not None
        assert len(hostname) > 0

    def test_config_file_loading(self, tmp_path):
        """Test loading configuration from a file."""
        # Create test config file
        config_data = {
            "agent": {
                "device_id": "test-device-123",
                "hostname": "test-host",
                "api_endpoint": "http://test.example.com/api/metrics.php",
                "api_key": "test-api-key",
                "collect_interval": 120,
                "extended_refresh_interval": 600,
                "command_poll_interval": 20,
                "buffer_max_size": 500,
                "buffer_flush_batch_size": 50,
                "buffer_file": "test_buffer.json",
                "log_file": "test.log",
                "log_max_size_mb": 20,
                "log_backup_count": 3
            },
            "thresholds": {
                "cpu_warning": 70,
                "cpu_critical": 85
            }
        }

        config_file = tmp_path / "test_config.json"
        with open(config_file, 'w') as f:
            json.dump(config_data, f)

        # Load config
        config = Config(str(config_file))

        # Check loaded values
        assert config.get('agent', 'device_id') == 'test-device-123'
        assert config.get('agent', 'hostname') == 'test-host'
        assert config.get('agent', 'api_endpoint') == 'http://test.example.com/api/metrics.php'
        assert config.get('agent', 'collect_interval') == 120
        assert config.get('agent', 'extended_refresh_interval') == 600
        assert config.get('agent', 'command_poll_interval') == 20
        assert config.get('agent', 'buffer_max_size') == 500
        assert config.get('thresholds', 'cpu_warning') == 70
        assert config.get('thresholds', 'cpu_critical') == 85

    def test_environment_variable_override(self, monkeypatch):
        """Test that environment variables override config values."""
        # Set environment variables
        monkeypatch.setenv('RAS_API_ENDPOINT', 'http://env.example.com/api/metrics.php')
        monkeypatch.setenv('RAS_API_KEY', 'env-api-key')
        monkeypatch.setenv('RAS_COLLECT_INTERVAL', '180')

        config = Config()

        # Check environment overrides
        assert config.get('agent', 'api_endpoint') == 'http://env.example.com/api/metrics.php'
        assert config.get('agent', 'api_key') == 'env-api-key'
        assert config.get('agent', 'collect_interval') == 180

    def test_config_validation_success(self, tmp_path):
        """Test successful configuration validation."""
        config_data = {
            "agent": {
                "device_id": "test-device",
                "hostname": "test-host",
                "api_endpoint": "http://localhost/RAS/admin/api/metrics.php",
                "api_key": "test-key",
                "collect_interval": 60,
                "extended_refresh_interval": 300,
                "command_poll_interval": 15,
                "buffer_max_size": 1000,
                "buffer_flush_batch_size": 100,
                "buffer_file": "buffer.json",
                "log_file": "agent.log",
                "log_max_size_mb": 10,
                "log_backup_count": 5
            },
            "thresholds": {
                "cpu_warning": 80,
                "cpu_critical": 90
            }
        }

        config_file = tmp_path / "test_config.json"
        with open(config_file, 'w') as f:
            json.dump(config_data, f)

        config = Config(str(config_file))
        assert config.validate() is True

    def test_config_validation_missing_required_field(self, tmp_path):
        """Test validation fails with missing required field."""
        config_data = {
            "agent": {
                "device_id": "",  # Empty should fail
                "hostname": "test-host",
                "api_endpoint": "http://localhost/RAS/admin/api/metrics.php",
                "api_key": "test-key"
            },
            "thresholds": {}
        }

        config_file = tmp_path / "test_config.json"
        with open(config_file, 'w') as f:
            json.dump(config_data, f)

        config = Config(str(config_file))
        with pytest.raises(ValueError, match="Required field"):
            config.validate()

    def test_config_validation_invalid_api_endpoint(self, tmp_path):
        """Test validation fails with invalid API endpoint."""
        config_data = {
            "agent": {
                "device_id": "test-device",
                "hostname": "test-host",
                "api_endpoint": "invalid-url",  # Missing http:// or https://
                "api_key": "test-key",
                "collect_interval": 60,
                "extended_refresh_interval": 300,
                "command_poll_interval": 15,
                "buffer_max_size": 1000,
                "buffer_flush_batch_size": 100,
                "buffer_file": "buffer.json",
                "log_file": "agent.log",
                "log_max_size_mb": 10
            },
            "thresholds": {}
        }

        config_file = tmp_path / "test_config.json"
        with open(config_file, 'w') as f:
            json.dump(config_data, f)

        config = Config(str(config_file))
        with pytest.raises(ValueError, match="API endpoint"):
            config.validate()

    def test_config_validation_invalid_numeric_values(self, tmp_path):
        """Test validation fails with invalid numeric values."""
        config_data = {
            "agent": {
                "device_id": "test-device",
                "hostname": "test-host",
                "api_endpoint": "http://localhost/RAS/admin/api/metrics.php",
                "api_key": "test-key",
                "collect_interval": 5,  # Below minimum (10)
                "extended_refresh_interval": 30,  # Below minimum (60)
                "command_poll_interval": 2,  # Below minimum (5)
                "buffer_max_size": 20000,  # Above maximum (10000)
                "buffer_flush_batch_size": 0,  # Below minimum (1)
                "buffer_file": "buffer.json",
                "log_file": "agent.log",
                "log_max_size_mb": 10
            },
            "thresholds": {}
        }

        config_file = tmp_path / "test_config.json"
        with open(config_file, 'w') as f:
            json.dump(config_data, f)

        config = Config(str(config_file))
        with pytest.raises(ValueError, match="collect_interval"):
            config.validate()

    def test_get_agent_config(self):
        """Test getting agent configuration section."""
        config = Config()
        agent_config = config.get_agent_config()

        assert isinstance(agent_config, dict)
        assert 'device_id' in agent_config
        assert 'api_endpoint' in agent_config
        assert 'collect_interval' in agent_config
        assert 'extended_refresh_interval' in agent_config
        assert 'command_poll_interval' in agent_config
        assert 'buffer_flush_batch_size' in agent_config

    def test_get_thresholds(self):
        """Test getting thresholds configuration section."""
        config = Config()
        thresholds = config.get_thresholds()

        assert isinstance(thresholds, dict)
        assert 'cpu_warning' in thresholds
        assert 'cpu_critical' in thresholds
        assert 'memory_warning' in thresholds
        assert 'disk_critical' in thresholds

    def test_get_all_config(self):
        """Test getting entire configuration."""
        config = Config()
        all_config = config.get_all()

        assert isinstance(all_config, dict)
        assert 'agent' in all_config
        assert 'thresholds' in all_config

    def test_global_config_singleton(self):
        """Test that get_config returns singleton instance."""
        config1 = get_config()
        config2 = get_config()

        assert config1 is config2

    def test_config_repr_masks_api_key(self):
        """Test that config repr masks API key for security."""
        config = Config()
        config_str = repr(config)

        # API key should be masked
        assert '****' in config_str
        # Full API key should not be visible
        assert config.get('agent', 'api_key') not in config_str

    def test_save_template(self, tmp_path):
        """Test saving configuration template."""
        config = Config()
        template_path = tmp_path / "config_template.json"

        config.save_template(str(template_path))

        assert template_path.exists()

        with open(template_path, 'r') as f:
            template = json.load(f)

        # Template should have empty device_id and hostname
        assert template['agent']['device_id'] == ''
        assert template['agent']['hostname'] == ''
        # Other values should have defaults
        assert template['agent']['collect_interval'] == 60
        assert template['agent']['extended_refresh_interval'] == 300
        assert template['agent']['command_poll_interval'] == 15
