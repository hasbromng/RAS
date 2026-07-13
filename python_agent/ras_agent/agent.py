"""
RAS Agent Main Module

This module orchestrates all components of the RAS monitoring agent.
It runs the main loop for collecting and sending metrics.
"""

import signal
import sys
import time
import threading
from typing import Optional

# Import agent modules
from .config import Config, get_config
from .logger import AgentLogger, get_logger
from .collector import MetricsCollector
from .api_client import APIClient
from .buffer import MetricsBuffer, BufferedMetricsSender


class RASAgent:
    """
    Main RAS monitoring agent orchestrator.
    """

    def __init__(self, config_path: Optional[str] = None):
        """
        Initialize the RAS agent.

        Args:
            config_path: Optional path to configuration file
        """
        # Load configuration
        self.config = get_config(config_path)
        self.config.validate()

        # Setup logger
        agent_config = self.config.get_agent_config()
        self.logger = AgentLogger(
            log_file=agent_config['log_file'],
            max_size_mb=agent_config['log_max_size_mb'],
            backup_count=agent_config.get('log_backup_count', 5),
            level='INFO'
        )

        self.logger.info("=" * 60)
        self.logger.info("RAS Monitoring Agent Starting")
        self.logger.info(f"Device ID: {agent_config['device_id']}")
        self.logger.info(f"Hostname: {agent_config['hostname']}")
        self.logger.info(f"API Endpoint: {agent_config['api_endpoint']}")
        self.logger.info("=" * 60)

        # Initialize components
        self.collector = MetricsCollector(logger=self.logger)
        self.buffer = MetricsBuffer(
            buffer_file=agent_config['buffer_file'],
            max_size=agent_config['buffer_max_size'],
            logger=self.logger
        )

        self.api_client = APIClient(
            api_endpoint=agent_config['api_endpoint'],
            api_key=agent_config['api_key'],
            device_id=agent_config['device_id'],
            logger=self.logger
        )

        self.buffered_sender = BufferedMetricsSender(
            buffer=self.buffer,
            api_client=self.api_client,
            logger=self.logger
        )

        # Control flags
        self.running = False
        self.shutdown_requested = False
        self.wake_event = threading.Event()
        self.command_thread = None

        # Setup signal handlers
        self._setup_signal_handlers()

        # Store interval
        self.collect_interval = agent_config['collect_interval']
        self.extended_refresh_interval = agent_config.get('extended_refresh_interval', max(300, self.collect_interval * 5))
        self._last_extended_refresh = 0.0
        self.command_poll_interval = agent_config.get('command_poll_interval', max(10, min(30, self.collect_interval // 2 or 10)))
        self.buffer_flush_batch_size = agent_config.get('buffer_flush_batch_size', 100)

    def _setup_signal_handlers(self) -> None:
        """Setup signal handlers for graceful shutdown."""
        def signal_handler(signum, frame):
            """Handle shutdown signals."""
            signal_name = signal.Signals(signum).name
            self.logger.info(f"Received signal {signal_name}, initiating graceful shutdown...")
            self.shutdown_requested = True

        # Register signal handlers
        signal.signal(signal.SIGINT, signal_handler)
        signal.signal(signal.SIGTERM, signal_handler)

        # Windows doesn't have SIGBREAK, ignore if not available
        if hasattr(signal, 'SIGBREAK'):
            signal.signal(signal.SIGBREAK, signal_handler)

    def test_connection(self) -> bool:
        """
        Test connection to the API endpoint.

        Returns:
            True if connection successful, False otherwise
        """
        self.logger.info("Testing connection to API endpoint...")

        success, message = self.api_client.test_connection()

        if success:
            self.logger.info(f"Connection test successful: {message}")
            return True
        else:
            self.logger.error(f"Connection test failed: {message}")
            return False

    def collect_and_send_metrics(self) -> bool:
        """
        Collect and send metrics to the API.

        Returns:
            True if successful, False otherwise
        """
        try:
            now = time.time()
            refresh_extended = (now - self._last_extended_refresh) >= self.extended_refresh_interval

            # Collect metrics
            self.logger.debug("Collecting system metrics...")
            metrics = self.collector.collect_all_metrics(
                include_extended=refresh_extended,
                force_refresh=refresh_extended
            )

            if refresh_extended:
                self._last_extended_refresh = now

            # Add hostname from config
            metrics['hostname'] = self.config.get('agent', 'hostname')

            # Try to send metrics
            if self.api_client.send_metrics(metrics):
                # Success - try to send any buffered metrics
                if not self.buffer.is_empty():
                    self.logger.info(f"Connection restored, sending {self.buffer.get_buffered_count()} buffered metrics")
                    self.buffered_sender.send_buffered_metrics(max_batch=self.buffer_flush_batch_size)

                return True
            else:
                # Failed - buffer the metrics
                self.logger.warning("Failed to send metrics, buffering locally")
                self.buffer.save_to_buffer(metrics)
                return False

        except Exception as e:
            self.logger.error(f"Error in collect_and_send_metrics: {e}")
            return False

    def run_once(self) -> bool:
        """
        Run a single collection and send cycle.

        Returns:
            True if successful, False otherwise
        """
        return self.collect_and_send_metrics()

    def _command_listener_loop(self) -> None:
        """Background thread to poll for commands from server."""
        self.logger.info("Command listener thread started.")
        while self.running and not self.shutdown_requested:
            try:
                command = self.api_client.fetch_pending_command()
                if command:
                    cmd_id = command.get('id')
                    cmd_action = command.get('command')
                    self.logger.info(f"Received command '{cmd_action}' (ID: {cmd_id})")
                    if cmd_action == 'audit':
                        self.logger.info("Executing immediate audit...")
                        self.wake_event.set()  # Wake up main loop
                        self.api_client.update_command_status(cmd_id, 'completed')
            except Exception as e:
                self.logger.debug(f"Command listener error: {e}")
            
            # Poll at a moderate cadence to reduce request overhead
            for _ in range(self.command_poll_interval):
                if not self.running or self.shutdown_requested:
                    break
                time.sleep(1)

    def run(self) -> None:
        """
        Run the main agent loop.

        This method blocks until shutdown is requested.
        """
        self.logger.info("Starting main agent loop...")
        self.running = True

        # Start command listener thread
        self.command_thread = threading.Thread(target=self._command_listener_loop, daemon=True)
        self.command_thread.start()

        # Test initial connection
        if not self.test_connection():
            self.logger.warning("Initial connection test failed, will retry during operation")

        cycle_count = 0

        try:
            while not self.shutdown_requested:
                cycle_start_time = time.time()

                cycle_count += 1
                self.logger.debug(f"Starting collection cycle #{cycle_count}")

                # Collect and send metrics
                success = self.collect_and_send_metrics()

                # Calculate sleep time
                elapsed = time.time() - cycle_start_time
                sleep_time = max(0, self.collect_interval - elapsed)

                # Sleep with interrupt check
                if sleep_time > 0:
                    self.logger.debug(f"Sleeping for {sleep_time:.1f} seconds...")
                    # Wait using event, will wake up immediately if set
                    self.wake_event.wait(timeout=sleep_time)
                    self.wake_event.clear()

        except Exception as e:
            self.logger.error(f"Error in main loop: {e}")
        finally:
            self.shutdown()

    def shutdown(self) -> None:
        """
        Perform graceful shutdown.
        """
        if not self.running:
            return

        self.logger.info("Shutting down RAS Agent...")
        self.running = False
        
        # Wake up main loop so it exits quickly
        if hasattr(self, 'wake_event'):
            self.wake_event.set()

        # Sync buffer to disk
        buffer_info = self.buffer.get_buffer_info()
        if buffer_info['current_size'] > 0:
            self.logger.info(f"Saving {buffer_info['current_size']} buffered metrics to disk")
            self.buffer.sync()

        # Close API client
        try:
            self.api_client.close()
        except Exception as e:
            self.logger.error(f"Error closing API client: {e}")

        self.logger.info("RAS Agent shutdown complete")
        self.logger.info("=" * 60)


def main(config_path: Optional[str] = None) -> int:
    """
    Main entry point for the RAS agent.

    Args:
        config_path: Optional path to configuration file

    Returns:
        Exit code (0 for success, non-zero for error)
    """
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


if __name__ == "__main__":
    sys.exit(main())
