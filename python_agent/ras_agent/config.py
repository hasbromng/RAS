"""
RAS Agent Configuration Module

This module handles configuration management for the RAS monitoring agent.
It loads configuration from JSON files and environment variables.
"""

import os
import json
import platform
import uuid
from pathlib import Path
from typing import Dict, Any, Optional


class Config:
    """Configuration manager for RAS monitoring agent."""

    # Default configuration values
    DEFAULT_CONFIG = {
        "agent": {
            "device_id": "",  # Empty means auto-generate
            "hostname": "",  # Empty means auto-detect
            "api_endpoint": "http://localhost/RAS/admin/api/metrics.php",
            "api_key": "change-this-to-secure-key",
            "collect_interval": 60,
            "extended_refresh_interval": 300,
            "command_poll_interval": 15,
            "buffer_max_size": 1000,
            "buffer_flush_batch_size": 100,
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

    # Environment variable mappings
    ENV_MAPPINGS = {
        "RAS_DEVICE_ID": ("agent", "device_id"),
        "RAS_HOSTNAME": ("agent", "hostname"),
        "RAS_API_ENDPOINT": ("agent", "api_endpoint"),
        "RAS_API_KEY": ("agent", "api_key"),
        "RAS_COLLECT_INTERVAL": ("agent", "collect_interval"),
        "RAS_EXTENDED_REFRESH_INTERVAL": ("agent", "extended_refresh_interval"),
        "RAS_COMMAND_POLL_INTERVAL": ("agent", "command_poll_interval"),
        "RAS_BUFFER_MAX_SIZE": ("agent", "buffer_max_size"),
        "RAS_BUFFER_FLUSH_BATCH_SIZE": ("agent", "buffer_flush_batch_size"),
        "RAS_BUFFER_FILE": ("agent", "buffer_file"),
        "RAS_LOG_FILE": ("agent", "log_file"),
        "RAS_LOG_MAX_SIZE_MB": ("agent", "log_max_size_mb")
    }

    def __init__(self, config_path: Optional[str] = None):
        """
        Initialize configuration manager.

        Args:
            config_path: Path to configuration file. If None, uses default path.
        """
        self.config_path = config_path or self._get_default_config_path()
        self.config = self._load_config()

    def _get_default_config_path(self) -> str:
        """Get default configuration file path."""
        # Try multiple locations for config file
        possible_paths = [
            "config.json",  # Current directory
            "config.json.template",  # Template fallback
            os.path.join(os.path.dirname(__file__), "config.json"),  # Module directory
            os.path.join(os.path.dirname(__file__), "..", "config.json"),  # Parent directory
            os.path.expanduser("~/.ras_agent/config.json"),  # User home
            "/etc/ras_agent/config.json"  # System-wide (Linux)
        ]

        for path in possible_paths:
            if os.path.exists(path):
                return path

        # Return default path even if it doesn't exist yet
        return "config.json"

    def _load_config(self) -> Dict[str, Any]:
        """Load and merge configuration from defaults, file, and environment."""
        config = self._deep_copy_dict(self.DEFAULT_CONFIG)

        # Load from file if exists
        if os.path.exists(self.config_path):
            try:
                with open(self.config_path, 'r', encoding='utf-8') as f:
                    file_config = json.load(f)
                    config = self._deep_merge(config, file_config)
            except (json.JSONDecodeError, IOError) as e:
                print(f"Warning: Could not load config file {self.config_path}: {e}")
                print("Using default configuration.")

        # Apply environment variable overrides
        config = self._apply_env_overrides(config)

        # Auto-generate device_id if empty
        needs_save = False
        if not config["agent"]["device_id"]:
            config["agent"]["device_id"] = self._generate_device_id()
            needs_save = True

        # Auto-detect hostname if empty
        if not config["agent"]["hostname"]:
            config["agent"]["hostname"] = platform.node()
            needs_save = True

        # Save back to file if auto-generated
        if needs_save:
            try:
                # Only save the agent and thresholds sections
                save_config = {
                    "agent": config["agent"],
                    "thresholds": config["thresholds"]
                }
                with open(self.config_path, 'w', encoding='utf-8') as f:
                    json.dump(save_config, f, indent=4)
            except Exception as e:
                print(f"Warning: Could not save auto-generated config to {self.config_path}: {e}")

        # Resolve paths relative to config file directory
        config = self._resolve_paths(config)

        return config

    def _deep_copy_dict(self, d: Dict[str, Any]) -> Dict[str, Any]:
        """Create a deep copy of a dictionary."""
        return json.loads(json.dumps(d))

    def _deep_merge(self, base: Dict[str, Any], override: Dict[str, Any]) -> Dict[str, Any]:
        """
        Deep merge two dictionaries.

        Args:
            base: Base dictionary
            override: Dictionary with overrides

        Returns:
            Merged dictionary
        """
        result = self._deep_copy_dict(base)

        for key, value in override.items():
            if key in result and isinstance(result[key], dict) and isinstance(value, dict):
                result[key] = self._deep_merge(result[key], value)
            else:
                result[key] = value

        return result

    def _apply_env_overrides(self, config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Apply environment variable overrides to configuration.

        Args:
            config: Current configuration dictionary

        Returns:
            Configuration with environment overrides applied
        """
        for env_var, (section, key) in self.ENV_MAPPINGS.items():
            value = os.environ.get(env_var)
            if value is not None:
                # Convert to appropriate type
                if key in [
                    "collect_interval",
                    "extended_refresh_interval",
                    "command_poll_interval",
                    "buffer_max_size",
                    "buffer_flush_batch_size",
                    "log_max_size_mb"
                ]:
                    try:
                        value = int(value)
                    except ValueError:
                        continue

                config[section][key] = value

        return config

    def _resolve_paths(self, config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Resolve file paths relative to config file directory.

        Args:
            config: Current configuration dictionary

        Returns:
            Configuration with resolved paths
        """
        agent_config = config["agent"]
        config_dir = os.path.dirname(os.path.abspath(self.config_path))

        # Resolve buffer file path
        buffer_file = agent_config["buffer_file"]
        if not os.path.isabs(buffer_file):
            agent_config["buffer_file"] = os.path.join(config_dir, buffer_file)

        # Resolve log file path
        log_file = agent_config["log_file"]
        if not os.path.isabs(log_file):
            agent_config["log_file"] = os.path.join(config_dir, log_file)

        return config

    def _generate_device_id(self) -> str:
        """
        Generate a unique device ID.

        Returns:
            Unique device identifier
        """
        # Use MAC address-based UUID for consistency across reboots
        try:
            mac = uuid.getnode()
            return str(uuid.uuid5(uuid.NAMESPACE_DNS, f"ras-{mac}"))
        except Exception:
            # Fallback to random UUID
            return str(uuid.uuid4())

    def get(self, section: str, key: str, default: Any = None) -> Any:
        """
        Get configuration value.

        Args:
            section: Configuration section
            key: Configuration key
            default: Default value if not found

        Returns:
            Configuration value or default
        """
        try:
            return self.config[section][key]
        except KeyError:
            return default

    def get_agent_config(self) -> Dict[str, Any]:
        """Get agent configuration section."""
        return self.config["agent"]

    def get_thresholds(self) -> Dict[str, Any]:
        """Get thresholds configuration section."""
        return self.config["thresholds"]

    def get_all(self) -> Dict[str, Any]:
        """Get entire configuration dictionary."""
        return self._deep_copy_dict(self.config)

    def validate(self) -> bool:
        """
        Validate configuration.

        Returns:
            True if configuration is valid

        Raises:
            ValueError: If configuration is invalid
        """
        agent_config = self.config["agent"]

        # Validate required fields
        required_fields = ["device_id", "hostname", "api_endpoint", "api_key"]
        for field in required_fields:
            if not agent_config.get(field):
                raise ValueError(f"Required field '{field}' is missing or empty")

        # Validate API endpoint format
        api_endpoint = agent_config["api_endpoint"]
        if not api_endpoint.startswith(("http://", "https://")):
            raise ValueError("API endpoint must start with http:// or https://")

        # Validate numeric values
        numeric_fields = {
            "collect_interval": (10, 3600),  # 10 seconds to 1 hour
            "extended_refresh_interval": (60, 86400),  # 1 minute to 24 hours
            "command_poll_interval": (5, 300),  # 5 seconds to 5 minutes
            "buffer_max_size": (1, 10000),  # 1 to 10000 entries
            "buffer_flush_batch_size": (1, 1000),  # 1 to 1000 entries
            "log_max_size_mb": (1, 1000)  # 1MB to 1GB
        }

        for field, (min_val, max_val) in numeric_fields.items():
            value = agent_config.get(field, 0)
            if not isinstance(value, int) or value < min_val or value > max_val:
                raise ValueError(f"'{field}' must be between {min_val} and {max_val}")

        return True

    def save_template(self, path: str = "config.json.template") -> None:
        """
        Save configuration template file.

        Args:
            path: Path to save template
        """
        template = self._deep_copy_dict(self.DEFAULT_CONFIG)
        template["agent"]["device_id"] = ""
        template["agent"]["hostname"] = ""

        with open(path, 'w', encoding='utf-8') as f:
            json.dump(template, f, indent=4)

    def __repr__(self) -> str:
        """String representation of configuration (with sensitive data masked)."""
        config_copy = self._deep_copy_dict(self.config)
        # Mask API key for security
        if "api_key" in config_copy["agent"]:
            api_key = config_copy["agent"]["api_key"]
            if len(api_key) > 8:
                config_copy["agent"]["api_key"] = api_key[:4] + "****" + api_key[-4:]
        return f"Config({json.dumps(config_copy, indent=2)})"


# Global configuration instance
_global_config: Optional[Config] = None


def get_config(config_path: Optional[str] = None) -> Config:
    """
    Get global configuration instance.

    Args:
        config_path: Optional path to configuration file

    Returns:
        Configuration instance
    """
    global _global_config
    if _global_config is None:
        _global_config = Config(config_path)
    return _global_config


def reset_config() -> None:
    """Reset global configuration instance (mainly for testing)."""
    global _global_config
    _global_config = None
