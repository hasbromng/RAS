#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
RAS Monitoring Agent - Main Entry Point

This is the main entry point for the standalone RAS agent executable.
It can be run directly or compiled with PyInstaller.
"""

import sys
import os
import argparse
import signal

# Add current directory to path for imports
if hasattr(sys, 'frozen'):
    # When running as frozen exe
    sys.path.insert(0, os.path.dirname(sys.executable))
else:
    # When running as script
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from ras_agent.agent import RASAgent
from ras_agent.config import get_config, reset_config
from ras_agent.logger import AgentLogger


def signal_handler(signum, frame):
    """Handle shutdown signals."""
    print("\nShutdown signal received...")
    sys.exit(0)


def install_service(config_path=None):
    """Install agent as Windows service."""
    try:
        from service.windows_service import install_service as service_install
        return service_install(config_path)
    except Exception as e:
        print(f"[ERROR] Error installing service: {e}")
        return False


def remove_service():
    """Remove agent Windows service."""
    try:
        from service.windows_service import remove_service as service_remove
        return service_remove()
    except Exception as e:
        print(f"[ERROR] Error removing service: {e}")
        return False


def start_service():
    """Start agent Windows service."""
    try:
        from service.windows_service import start_service as service_start
        return service_start()
    except Exception as e:
        print(f"[ERROR] Error starting service: {e}")
        return False


def stop_service():
    """Stop agent Windows service."""
    try:
        from service.windows_service import stop_service as service_stop
        return service_stop()
    except Exception as e:
        print(f"[ERROR] Error stopping service: {e}")
        return False


def test_connection(config_path=None):
    """Test connection to API endpoint."""
    client = None
    try:
        from ras_agent.api_client import APIClient

        config = get_config(config_path)
        agent_config = config.get_agent_config()

        client = APIClient(
            api_endpoint=agent_config['api_endpoint'],
            api_key=agent_config['api_key'],
            device_id=agent_config['device_id'],
            logger=None
        )

        success, message = client.test_connection()

        if success:
            print(f"[OK] Connection test successful: {message}")
        else:
            print(f"[ERROR] Connection test failed: {message}")

        return success
    except Exception as e:
        print(f"[ERROR] Error testing connection: {e}")
        return False
    finally:
        if client is not None:
            try:
                client.close()
            except Exception:
                pass


def run_agent(config_path=None):
    """Run the monitoring agent."""
    # Setup signal handlers
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    try:
        # Create and run agent
        agent = RASAgent(config_path)
        agent.run()

        return 0
    except KeyboardInterrupt:
        print("\nShutdown requested by user")
        return 0
    except Exception as e:
        print(f"Fatal error: {e}", file=sys.stderr)
        return 1


def main():
    """Main entry point."""
    parser = argparse.ArgumentParser(
        description='RAS Monitoring Agent - System metrics collection and reporting',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  ras_agent.exe                    # Run as standalone application
  ras_agent.exe install            # Install as Windows service
  ras_agent.exe start              # Start the service
  ras_agent.exe stop               # Stop the service
  ras_agent.exe remove             # Remove the service
  ras_agent.exe test               # Test API connection
  ras_agent.exe --config custom.json  # Use custom config file

For more information, visit: https://github.com/your-repo/RAS
        """
    )

    parser.add_argument(
        '--config', '-c',
        dest='config',
        help='Path to configuration file (default: config.json)'
    )

    subparsers = parser.add_subparsers(dest='command', help='Available commands')

    # Service management commands
    subparsers.add_parser('install', help='Install as Windows service')
    subparsers.add_parser('remove', help='Remove Windows service')
    subparsers.add_parser('start', help='Start Windows service')
    subparsers.add_parser('stop', help='Stop Windows service')
    subparsers.add_parser('restart', help='Restart Windows service')

    # Utility commands
    subparsers.add_parser('test', help='Test API connection')
    subparsers.add_parser('version', help='Show version information')

    args = parser.parse_args()
    config_path = args.config or 'config.json'

    # Handle commands
    if args.command == 'install':
        return 0 if install_service(config_path) else 1
    elif args.command == 'remove':
        return 0 if remove_service() else 1
    elif args.command == 'start':
        return 0 if start_service() else 1
    elif args.command == 'stop':
        return 0 if stop_service() else 1
    elif args.command == 'restart':
        stop_service()
        return 0 if start_service() else 1
    elif args.command == 'test':
        return 0 if test_connection(config_path) else 1
    elif args.command == 'version':
        print("RAS Monitoring Agent v1.0.0")
        print("Python:", sys.version)
        return 0
    else:
        # Default: run agent
        return run_agent(config_path)


if __name__ == '__main__':
    sys.exit(main())
