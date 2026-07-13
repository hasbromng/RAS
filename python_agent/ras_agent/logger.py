"""
RAS Agent Logger Module

This module provides logging functionality for the RAS monitoring agent.
It uses rotating file handlers to manage log file sizes.
"""

import logging
import logging.handlers
import os
import sys
from pathlib import Path
from typing import Optional


class AgentLogger:
    """
    Logger for RAS monitoring agent with rotating file support.
    """

    # Log format
    LOG_FORMAT = '%(asctime)s [%(levelname)s] %(name)s - %(message)s'
    DATE_FORMAT = '%Y-%m-%d %H:%M:%S'

    # Singleton instance
    _instance: Optional['AgentLogger'] = None
    _logger: Optional[logging.Logger] = None

    def __new__(cls, *args, **kwargs):
        """Ensure singleton pattern."""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    def __init__(self, log_file: str = 'ras_agent.log',
                 max_size_mb: int = 10,
                 backup_count: int = 5,
                 level: str = 'INFO'):
        """
        Initialize the logger (only once due to singleton).

        Args:
            log_file: Path to log file
            max_size_mb: Maximum size of log file in MB before rotation
            backup_count: Number of backup files to keep
            level: Logging level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
        """
        if self._logger is not None:
            return

        self._setup_logger(log_file, max_size_mb, backup_count, level)

    def _setup_logger(self, log_file: str, max_size_mb: int,
                     backup_count: int, level: str) -> None:
        """
        Set up the logger with file and console handlers.

        Args:
            log_file: Path to log file
            max_size_mb: Maximum size of log file in MB before rotation
            backup_count: Number of backup files to keep
            level: Logging level
        """
        # Create logger
        self._logger = logging.getLogger('ras_agent')
        self._logger.setLevel(getattr(logging, level.upper(), logging.INFO))

        # Prevent propagation to root logger
        self._logger.propagate = False

        # Clear existing handlers
        self._logger.handlers.clear()

        # Create formatter
        formatter = logging.Formatter(
            fmt=self.LOG_FORMAT,
            datefmt=self.DATE_FORMAT
        )

        # File handler with rotation
        try:
            # Create log directory if it doesn't exist
            log_dir = os.path.dirname(log_file)
            if log_dir and not os.path.exists(log_dir):
                os.makedirs(log_dir, exist_ok=True)

            file_handler = logging.handlers.RotatingFileHandler(
                filename=log_file,
                maxBytes=max_size_mb * 1024 * 1024,  # Convert MB to bytes
                backupCount=backup_count,
                encoding='utf-8'
            )
            file_handler.setFormatter(formatter)
            file_handler.setLevel(getattr(logging, level.upper(), logging.INFO))
            self._logger.addHandler(file_handler)
        except (IOError, OSError) as e:
            # Fallback to stderr if file handler fails
            print(f"Warning: Could not create file handler: {e}", file=sys.stderr)

        # Console handler for service mode
        console_handler = logging.StreamHandler(sys.stderr)
        console_handler.setFormatter(formatter)
        console_handler.setLevel(logging.WARNING)  # Only warnings and errors to console
        self._logger.addHandler(console_handler)

    def debug(self, message: str) -> None:
        """Log debug message."""
        self._logger.debug(message)

    def info(self, message: str) -> None:
        """Log info message."""
        self._logger.info(message)

    def warning(self, message: str) -> None:
        """Log warning message."""
        self._logger.warning(message)

    def error(self, message: str) -> None:
        """Log error message."""
        self._logger.error(message)

    def critical(self, message: str) -> None:
        """Log critical message."""
        self._logger.critical(message)

    def exception(self, message: str) -> None:
        """Log exception with traceback."""
        self._logger.exception(message)

    @staticmethod
    def reset() -> None:
        """Reset logger instance (mainly for testing)."""
        AgentLogger._instance = None
        AgentLogger._logger = None


# Convenience functions
def setup_logger(log_file: str = 'ras_agent.log',
                 max_size_mb: int = 10,
                 backup_count: int = 5,
                 level: str = 'INFO') -> AgentLogger:
    """
    Set up and return the logger instance.

    Args:
        log_file: Path to log file
        max_size_mb: Maximum size of log file in MB before rotation
        backup_count: Number of backup files to keep
        level: Logging level

    Returns:
        AgentLogger instance
    """
    return AgentLogger(log_file, max_size_mb, backup_count, level)


def get_logger() -> Optional[AgentLogger]:
    """
    Get existing logger instance.

    Returns:
        AgentLogger instance or None if not initialized
    """
    if AgentLogger._instance is None:
        return None
    return AgentLogger._instance


def debug(message: str) -> None:
    """Log debug message."""
    logger = get_logger()
    if logger:
        logger.debug(message)


def info(message: str) -> None:
    """Log info message."""
    logger = get_logger()
    if logger:
        logger.info(message)


def warning(message: str) -> None:
    """Log warning message."""
    logger = get_logger()
    if logger:
        logger.warning(message)


def error(message: str) -> None:
    """Log error message."""
    logger = get_logger()
    if logger:
        logger.error(message)


def critical(message: str) -> None:
    """Log critical message."""
    logger = get_logger()
    if logger:
        logger.critical(message)


def exception(message: str) -> None:
    """Log exception with traceback."""
    logger = get_logger()
    if logger:
        logger.exception(message)
