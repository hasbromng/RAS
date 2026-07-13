"""
RAS Agent Windows Service

This module provides Windows service functionality for the RAS monitoring agent.
It allows the agent to run as a background Windows service.
"""

import sys
import os
import threading
import time

# Add parent directory to path for imports
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

try:
    import win32service
    import win32serviceutil
    import win32event
    import win32con
    import win32api
    import servicemanager
    WIN32_AVAILABLE = True
except ImportError:
    WIN32_AVAILABLE = False
    print("Warning: pywin32 is not installed. Windows service functionality is not available.")
    print("Install it with: pip install pywin32")

from ras_agent.logger import AgentLogger
from ras_agent.config import get_config


class RASAgentService(win32serviceutil.ServiceFramework):
    """
    Windows service for RAS monitoring agent.
    """

    # Service configuration
    _svc_name_ = "RASAgent"
    _svc_display_name_ = "RAS Monitoring Agent"
    _svc_description_ = "Collects and sends system metrics to RAS dashboard server"

    def __init__(self, args):
        """
        Initialize the Windows service.

        Args:
            args: Service arguments
        """
        if not WIN32_AVAILABLE:
            raise ImportError("pywin32 is required for Windows service functionality")

        win32serviceutil.ServiceFramework.__init__(self, args)
        self.hWaitStop = win32event.CreateEvent(None, 0, 0, None)
        self.is_alive = True
        self.agent_thread = None
        self.config_path = self._get_config_path()

    def _get_config_path(self) -> str:
        """
        Get the configuration file path.

        Returns:
            Path to configuration file
        """
        # Try multiple locations
        possible_paths = [
            "config.json",
            os.path.join(os.path.dirname(__file__), "..", "config.json"),
            os.path.join(os.path.dirname(os.path.dirname(__file__)), "config.json"),
            os.path.expanduser("~/.ras_agent/config.json"),
            "C:/Program Files/RAS Agent/config.json"
        ]

        for path in possible_paths:
            if os.path.exists(path):
                return path

        # Return default path even if doesn't exist
        return "config.json"

    def SvcStop(self):
        """
        Handle service stop request.
        """
        self.is_alive = False
        self.ReportServiceStatus(win32service.SERVICE_STOP_PENDING)
        win32event.SetEvent(self.hWaitStop)
        servicemanager.LogMsg(
            servicemanager.EVENTLOG_INFORMATION_TYPE,
            servicemanager.PYS_SERVICE_STOPPED,
            (self._svc_name_, '')
        )

    def SvcDoRun(self):
        """
        Main service loop.
        """
        servicemanager.LogMsg(
            servicemanager.EVENTLOG_INFORMATION_TYPE,
            servicemanager.PYS_SERVICE_STARTED,
            (self._svc_name_, '')
        )

        self._run_agent()

    def _run_agent(self):
        """
        Run the agent in the service context.
        """
        try:
            # Import agent here to avoid import errors during service installation
            from ras_agent.agent import RASAgent

            # Create agent instance
            agent = RASAgent(config_path=self.config_path)

            # Run agent in a separate thread
            def run_agent_thread():
                try:
                    agent.run()
                except Exception as e:
                    servicemanager.LogErrorMsg(f"Agent error: {e}")

            self.agent_thread = threading.Thread(target=run_agent_thread, daemon=True)
            self.agent_thread.start()

            # Wait for stop signal
            win32event.WaitForSingleObject(self.hWaitStop, win32event.INFINITE)

            # Shutdown agent
            if agent.running:
                agent.shutdown()

            # Wait for agent thread to finish
            if self.agent_thread and self.agent_thread.is_alive():
                self.agent_thread.join(timeout=5)

        except Exception as e:
            servicemanager.LogErrorMsg(f"Service error: {e}")


def install_service(config_path: str = None) -> bool:
    """
    Install the Windows service.

    Args:
        config_path: Optional path to configuration file

    Returns:
        True if successful
    """
    if not WIN32_AVAILABLE:
        print("Error: pywin32 is not installed")
        return False

    try:
        service_class = f"{RASAgentService.__module__}.{RASAgentService.__name__}"

        win32serviceutil.InstallService(
            service_class,
            RASAgentService._svc_name_,
            RASAgentService._svc_display_name_,
            description=RASAgentService._svc_description_,
            startType=win32service.SERVICE_AUTO_START
        )

        print(f"Service '{RASAgentService._svc_name_}' installed successfully")
        print(f"Display name: {RASAgentService._svc_display_name_}")
        print(f"Service will start automatically on system boot")

        return True

    except Exception as e:
        print(f"Error installing service: {e}")
        return False


def remove_service() -> bool:
    """
    Remove the Windows service.

    Returns:
        True if successful
    """
    if not WIN32_AVAILABLE:
        print("Error: pywin32 is not installed")
        return False

    try:
        win32serviceutil.RemoveService(RASAgentService._svc_name_)
        print(f"Service '{RASAgentService._svc_name_}' removed successfully")
        return True
    except Exception as e:
        print(f"Error removing service: {e}")
        return False


def start_service() -> bool:
    """
    Start the Windows service.

    Returns:
        True if successful
    """
    if not WIN32_AVAILABLE:
        print("Error: pywin32 is not installed")
        return False

    try:
        win32serviceutil.StartService(RASAgentService._svc_name_)
        print(f"Service '{RASAgentService._svc_name_}' started successfully")
        return True
    except Exception as e:
        print(f"Error starting service: {e}")
        return False


def stop_service() -> bool:
    """
    Stop the Windows service.

    Returns:
        True if successful
    """
    if not WIN32_AVAILABLE:
        print("Error: pywin32 is not installed")
        return False

    try:
        win32serviceutil.StopService(RASAgentService._svc_name_)
        print(f"Service '{RASAgentService._svc_name_}' stopped successfully")
        return True
    except Exception as e:
        print(f"Error stopping service: {e}")
        return False


def restart_service() -> bool:
    """
    Restart the Windows service.

    Returns:
        True if successful
    """
    if stop_service():
        time.sleep(2)
        return start_service()
    return False


def get_service_status() -> str:
    """
    Get the current service status.

    Returns:
        Service status string
    """
    if not WIN32_AVAILABLE:
        return "Unknown (pywin32 not installed)"

    try:
        status = win32serviceutil.QueryServiceStatus(RASAgentService._svc_name_)[1]
        status_map = {
            win32service.SERVICE_STOPPED: "Stopped",
            win32service.SERVICE_START_PENDING: "Start Pending",
            win32service.SERVICE_STOP_PENDING: "Stop Pending",
            win32service.SERVICE_RUNNING: "Running",
            win32service.SERVICE_CONTINUE_PENDING: "Continue Pending",
            win32service.SERVICE_PAUSE_PENDING: "Pause Pending",
            win32service.SERVICE_PAUSED: "Paused"
        }
        return status_map.get(status, "Unknown")
    except Exception as e:
        return f"Error: {e}"


if __name__ == "__main__":
    if WIN32_AVAILABLE:
        win32serviceutil.HandleCommandLine(RASAgentService)
    else:
        print("Error: pywin32 is not installed. Install it with: pip install pywin32")
        sys.exit(1)
