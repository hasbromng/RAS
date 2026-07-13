"""
RAS Agent API Client Module

This module handles communication with the RAS backend API.
It sends metrics data and handles retries and errors.
"""

import json
import time
from typing import Dict, Any, Optional, Tuple

try:
    import requests
    REQUESTS_AVAILABLE = True
except ImportError:
    REQUESTS_AVAILABLE = False


class APIClient:
    """
    API client for communicating with RAS backend.
    """

    # Default timeout values
    DEFAULT_TIMEOUT = 30  # seconds
    CONNECT_TIMEOUT = 10   # seconds

    # Retry configuration
    MAX_RETRIES = 3
    INITIAL_BACKOFF = 1    # seconds
    MAX_BACKOFF = 60       # seconds

    def __init__(self, api_endpoint: str, api_key: str, device_id: str, logger=None):
        """
        Initialize API client.

        Args:
            api_endpoint: URL of the API endpoint
            api_key: API key for authentication
            device_id: Unique device identifier
            logger: Optional logger instance
        """
        if not REQUESTS_AVAILABLE:
            raise ImportError("requests library is required but not installed. "
                            "Install it with: pip install requests")

        self.api_endpoint = api_endpoint
        self.api_key = api_key
        self.device_id = device_id
        self.logger = logger
        self.session = requests.Session()

        # Set default headers
        self.session.headers.update({
            'Content-Type': 'application/json',
            'X-API-Key': self.api_key,
            'User-Agent': f'RAS-Agent/{self.device_id}'
        })

    def test_connection(self) -> Tuple[bool, str]:
        """
        Test connection to API endpoint.

        Returns:
            Tuple of (success, message)
        """
        try:
            # Send a simple GET request to test connectivity
            response = self.session.get(
                self.api_endpoint.replace('metrics.php', 'dashboard.php'),
                timeout=(self.CONNECT_TIMEOUT, self.DEFAULT_TIMEOUT)
            )

            if response.status_code == 200:
                return True, "Connection successful"
            elif response.status_code == 401:
                return False, "Authentication failed - check API key"
            else:
                return False, f"Unexpected status code: {response.status_code}"

        except requests.exceptions.Timeout:
            return False, "Connection timeout"
        except requests.exceptions.ConnectionError as e:
            return False, f"Connection error: {e}"
        except Exception as e:
            return False, f"Connection test failed: {e}"

    def send_metrics(self, metrics: Dict[str, Any], max_retries: int = MAX_RETRIES) -> bool:
        """
        Send metrics to the API endpoint.

        Args:
            metrics: Dictionary containing system metrics
            max_retries: Maximum number of retry attempts

        Returns:
            True if successful, False otherwise
        """
        if self.logger:
            self.logger.debug(f"Sending metrics to {self.api_endpoint}")

        # Add device_id to metrics
        metrics_with_id = metrics.copy()
        metrics_with_id['device_id'] = self.device_id

        return self._send_with_retry(metrics_with_id, max_retries)

    def _send_with_retry(self, data: Dict[str, Any], max_retries: int) -> bool:
        """
        Send data with exponential backoff retry.

        Args:
            data: Data to send
            max_retries: Maximum retry attempts

        Returns:
            True if successful, False otherwise
        """
        backoff = self.INITIAL_BACKOFF

        for attempt in range(max_retries + 1):
            try:
                response = self.session.post(
                    self.api_endpoint,
                    json=data,
                    timeout=(self.CONNECT_TIMEOUT, self.DEFAULT_TIMEOUT)
                )

                # Check for successful response
                if response.status_code == 200:
                    try:
                        result = response.json()
                        if result.get('success'):
                            if self.logger:
                                self.logger.info(f"Metrics sent successfully. "
                                              f"Device status: {result.get('status')}, "
                                              f"Alerts created: {result.get('alerts_created', 0)}")
                            return True
                        else:
                            if self.logger:
                                self.logger.warning(f"API returned success=false: "
                                                   f"{result.get('message', 'Unknown error')}")
                            return False
                    except json.JSONDecodeError:
                        if self.logger:
                            self.logger.warning("API response was not valid JSON")
                        return False

                elif response.status_code == 401:
                    if self.logger:
                        self.logger.error("Authentication failed - check API key")
                    return False  # Don't retry auth failures

                elif response.status_code == 400:
                    if self.logger:
                        self.logger.error(f"Bad request - {response.text}")
                    return False  # Don't retry client errors

                elif response.status_code >= 500:
                    # Server error - retry
                    if attempt < max_retries:
                        if self.logger:
                            self.logger.warning(f"Server error {response.status_code}, "
                                              f"retrying in {backoff}s... (attempt {attempt + 1}/{max_retries})")
                        time.sleep(backoff)
                        backoff = min(backoff * 2, self.MAX_BACKOFF)
                    else:
                        if self.logger:
                            self.logger.error(f"Server error after {max_retries} retries")
                        return False

                else:
                    if self.logger:
                        self.logger.warning(f"Unexpected status code: {response.status_code}")
                    return False

            except requests.exceptions.Timeout:
                if attempt < max_retries:
                    if self.logger:
                        self.logger.warning(f"Request timeout, retrying in {backoff}s... "
                                          f"(attempt {attempt + 1}/{max_retries})")
                    time.sleep(backoff)
                    backoff = min(backoff * 2, self.MAX_BACKOFF)
                else:
                    if self.logger:
                        self.logger.error("Request timeout after max retries")
                    return False

            except requests.exceptions.ConnectionError as e:
                if attempt < max_retries:
                    if self.logger:
                        self.logger.warning(f"Connection error, retrying in {backoff}s... "
                                          f"(attempt {attempt + 1}/{max_retries})")
                    time.sleep(backoff)
                    backoff = min(backoff * 2, self.MAX_BACKOFF)
                else:
                    if self.logger:
                        self.logger.error(f"Connection error after max retries: {e}")
                    return False

            except Exception as e:
                if self.logger:
                    self.logger.error(f"Unexpected error sending metrics: {e}")
                return False

        return False

    def send_batch(self, metrics_list: list) -> Tuple[int, int]:
        """
        Send multiple metric batches.

        Args:
            metrics_list: List of metric dictionaries

        Returns:
            Tuple of (successful_count, failed_count)
        """
        successful = 0
        failed = 0

        for metrics in metrics_list:
            if self.send_metrics(metrics):
                successful += 1
            else:
                failed += 1

        if self.logger:
            self.logger.info(f"Batch send complete: {successful} successful, {failed} failed")

        return successful, failed

    def close(self) -> None:
        """Close the session and cleanup resources."""
        if self.session:
            self.session.close()


# Convenience function for standalone usage
def send_metrics_to_api(api_endpoint: str, api_key: str, device_id: str,
                       metrics: Dict[str, Any], logger=None) -> bool:
    """
    Send metrics to the API endpoint.

    Args:
        api_endpoint: URL of the API endpoint
        api_key: API key for authentication
        device_id: Unique device identifier
        metrics: Dictionary containing system metrics
        logger: Optional logger instance

    Returns:
        True if successful, False otherwise
    """
    client = APIClient(api_endpoint, api_key, device_id, logger)
    try:
        return client.send_metrics(metrics)
    finally:
        client.close()
