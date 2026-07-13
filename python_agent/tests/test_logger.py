"""
Unit tests for RAS Agent logger module.
"""

import pytest
import os
import tempfile
from pathlib import Path
from unittest.mock import Mock, patch

from ras_agent.logger import AgentLogger, setup_logger, get_logger, reset_logger


@pytest.fixture
def temp_log_file(tmp_path):
    """Create a temporary log file path."""
    return str(tmp_path / "test_agent.log")


@pytest.fixture(autouse=True)
def reset_logger_state():
    """Reset logger state before each test."""
    reset_logger()


class TestAgentLogger:
    """Test cases for AgentLogger class."""

    def test_singleton_pattern(self):
        """Test that logger follows singleton pattern."""
        logger1 = AgentLogger(log_file="test1.log")
        logger2 = AgentLogger(log_file="test2.log")

        # Should be the same instance
        assert logger1 is logger2

    def test_initialization(self, temp_log_file):
        """Test logger initialization."""
        logger = AgentLogger(log_file=temp_log_file)

        assert logger is not None
        assert AgentLogger._logger is not None

    def test_log_file_creation(self, temp_log_file):
        """Test that log file is created."""
        AgentLogger(log_file=temp_log_file)

        # Log file should be created
        assert os.path.exists(temp_log_file)

    def test_info_logging(self, temp_log_file):
        """Test info level logging."""
        logger = AgentLogger(log_file=temp_log_file)

        logger.info("Test info message")

        # Check message was written to file
        with open(temp_log_file, 'r') as f:
            content = f.read()

        assert "Test info message" in content
        assert "INFO" in content

    def test_debug_logging(self, temp_log_file):
        """Test debug level logging."""
        logger = AgentLogger(log_file=temp_log_file)

        logger.debug("Test debug message")

        # Check message was written to file
        with open(temp_log_file, 'r') as f:
            content = f.read()

        assert "Test debug message" in content
        assert "DEBUG" in content

    def test_warning_logging(self, temp_log_file):
        """Test warning level logging."""
        logger = AgentLogger(log_file=temp_log_file)

        logger.warning("Test warning message")

        # Check message was written to file
        with open(temp_log_file, 'r') as f:
            content = f.read()

        assert "Test warning message" in content
        assert "WARNING" in content

    def test_error_logging(self, temp_log_file):
        """Test error level logging."""
        logger = AgentLogger(log_file=temp_log_file)

        logger.error("Test error message")

        # Check message was written to file
        with open(temp_log_file, 'r') as f:
            content = f.read()

        assert "Test error message" in content
        assert "ERROR" in content

    def test_critical_logging(self, temp_log_file):
        """Test critical level logging."""
        logger = AgentLogger(log_file=temp_log_file)

        logger.critical("Test critical message")

        # Check message was written to file
        with open(temp_log_file, 'r') as f:
            content = f.read()

        assert "Test critical message" in content
        assert "CRITICAL" in content

    def test_exception_logging(self, temp_log_file):
        """Test exception logging with traceback."""
        logger = AgentLogger(log_file=temp_log_file)

        try:
            raise ValueError("Test exception")
        except ValueError as e:
            logger.exception("An error occurred")

        # Check message was written to file
        with open(temp_log_file, 'r') as f:
            content = f.read()

        assert "An error occurred" in content
        assert "ValueError" in content
        assert "Test exception" in content

    def test_log_rotation(self, temp_log_file):
        """Test log file rotation when size limit is reached."""
        # Create logger with very small size limit
        logger = AgentLogger(log_file=temp_log_file, max_size_mb=1)

        # Write enough data to trigger rotation (more than 1MB)
        for i in range(10000):
            logger.info(f"Test log message {i}: " + "x" * 100)

        # Check that backup files were created
        log_dir = os.path.dirname(temp_log_file)
        log_basename = os.path.basename(temp_log_file)

        backup_files = [f for f in os.listdir(log_dir) if f.startswith(log_basename) and f != log_basename]

        # Should have created backup files
        assert len(backup_files) > 0

    def test_different_log_levels(self, temp_log_file):
        """Test logging with different log levels."""
        # Create logger with WARNING level
        logger = AgentLogger(log_file=temp_log_file, level='WARNING')

        logger.debug("Debug message")  # Should not appear
        logger.info("Info message")    # Should not appear
        logger.warning("Warning message")  # Should appear
        logger.error("Error message")  # Should appear

        with open(temp_log_file, 'r') as f:
            content = f.read()

        assert "Warning message" in content
        assert "Error message" in content
        assert "Debug message" not in content
        assert "Info message" not in content

    def test_log_format(self, temp_log_file):
        """Test that log entries have correct format."""
        logger = AgentLogger(log_file=temp_log_file)

        logger.info("Test message")

        with open(temp_log_file, 'r') as f:
            content = f.read()

        # Check format includes timestamp, level, logger name
        assert "[" in content  # Timestamp brackets
        assert "]" in content  # Closing brackets
        assert "ras_agent" in content  # Logger name

    def test_convenience_functions(self, temp_log_file):
        """Test convenience logging functions."""
        setup_logger(log_file=temp_log_file)

        info("Test info")
        warning("Test warning")
        error("Test error")

        with open(temp_log_file, 'r') as f:
            content = f.read()

        assert "Test info" in content
        assert "Test warning" in content
        assert "Test error" in content

    def test_get_logger_before_setup(self):
        """Test get_logger when not yet initialized."""
        logger = get_logger()

        # Should return None if not initialized
        assert logger is None

    def test_get_logger_after_setup(self, temp_log_file):
        """Test get_logger after initialization."""
        setup_logger(log_file=temp_log_file)

        logger = get_logger()

        # Should return the logger instance
        assert logger is not None
        assert isinstance(logger, AgentLogger)

    def test_reset_logger(self, temp_log_file):
        """Test resetting logger instance."""
        logger1 = setup_logger(log_file=temp_log_file)
        reset_logger()

        logger2 = setup_logger(log_file=temp_log_file)

        # Should be a new instance after reset
        assert logger1 is not logger2


class TestLoggerConfiguration:
    """Test logger configuration options."""

    def test_backup_count(self, temp_log_file):
        """Test backup count configuration."""
        logger = AgentLogger(
            log_file=temp_log_file,
            max_size_mb=1,
            backup_count=3
        )

        # Write enough data to trigger rotation
        for i in range(10000):
            logger.info(f"Test log message {i}: " + "x" * 100)

        log_dir = os.path.dirname(temp_log_file)
        log_basename = os.path.basename(temp_log_file)

        backup_files = [f for f in os.listdir(log_dir)
                       if f.startswith(log_basename) and f != log_basename]

        # Should not exceed backup_count
        assert len(backup_files) <= 3

    def test_log_directory_creation(self, tmp_path):
        """Test that log directory is created if it doesn't exist."""
        log_dir = tmp_path / "logs" / "subdir"
        log_file = log_dir / "agent.log"

        # Directory doesn't exist yet
        assert not log_dir.exists()

        logger = AgentLogger(log_file=str(log_file))

        # Directory should be created
        assert log_dir.exists()
        assert log_file.exists()
