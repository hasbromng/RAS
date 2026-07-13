"""
RAS Agent Package

RAS (Remote Assistance Support) Monitoring Agent

A lightweight Python monitoring agent that collects system metrics
and sends them to a RAS backend server.
"""

__version__ = "1.0.0"
__author__ = "RAS Team"

from .agent import RASAgent, main
from .config import Config, get_config
from .collector import MetricsCollector
from .api_client import APIClient
from .buffer import MetricsBuffer, BufferedMetricsSender
from .logger import AgentLogger, setup_logger

__all__ = [
    'RASAgent',
    'main',
    'Config',
    'get_config',
    'MetricsCollector',
    'APIClient',
    'MetricsBuffer',
    'BufferedMetricsSender',
    'AgentLogger',
    'setup_logger'
]
