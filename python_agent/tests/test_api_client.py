"""
Unit tests for RAS Agent API client module.
"""

import pytest
import json
from unittest.mock import Mock, patch, MagicMock
from requests.exceptions import Timeout, ConnectionError

from ras_agent.api_client import APIClient, send_metrics_to_api


@pytest.fixture
def mock_logger():
    """Create a mock logger."""
    return Mock()


@pytest.fixture
def api_client(mock_logger):
    """Create an API client instance for testing."""
    return APIClient(
        api_endpoint="http://test.example.com/api/metrics.php",
        api_key="test-api-key",
        device_id="test-device-123",
        logger=mock_logger
    )


@pytest.fixture
def sample_metrics():
    """Sample metrics data for testing."""
    return {
        "device_id": "test-device-123",
        "hostname": "test-host",
        "ip_address": "192.168.1.100",
        "cpu_usage": 45.5,
        "memory_used": 8589934592,
        "memory_total": 17179869184,
        "disk_used": 536870912000,
        "disk_total": 1073741824000,
        "disk_usage": 50.0,
        "storage_health": "healthy",
        "network_status": "good"
    }


class TestAPIClient:
    """Test cases for APIClient class."""

    def test_initialization(self, api_client):
        """Test client initialization."""
        assert api_client.api_endpoint == "http://test.example.com/api/metrics.php"
        assert api_client.api_key == "test-api-key"
        assert api_client.device_id == "test-device-123"
        assert api_client.logger is not None

    def test_session_headers(self, api_client):
        """Test that session has correct headers."""
        assert 'Content-Type' in api_client.session.headers
        assert api_client.session.headers['Content-Type'] == 'application/json'
        assert 'X-API-Key' in api_client.session.headers
        assert api_client.session.headers['X-API-Key'] == 'test-api-key'
        assert 'User-Agent' in api_client.session.headers
        assert api_client.device_id in api_client.session.headers['User-Agent']

    @patch('ras_agent.api_client.requests.Session.get')
    def test_connection_success(self, mock_get, api_client):
        """Test successful connection test."""
        mock_response = Mock()
        mock_response.status_code = 200
        mock_get.return_value = mock_response

        success, message = api_client.test_connection()

        assert success is True
        assert "successful" in message.lower()

    @patch('ras_agent.api_client.requests.Session.get')
    def test_connection_auth_failure(self, mock_get, api_client):
        """Test connection test with authentication failure."""
        mock_response = Mock()
        mock_response.status_code = 401
        mock_get.return_value = mock_response

        success, message = api_client.test_connection()

        assert success is False
        assert "authentication" in message.lower()

    @patch('ras_agent.api_client.requests.Session.get')
    def test_connection_timeout(self, mock_get, api_client):
        """Test connection test with timeout."""
        mock_get.side_effect = Timeout("Connection timeout")

        success, message = api_client.test_connection()

        assert success is False
        assert "timeout" in message.lower()

    @patch('ras_agent.api_client.requests.Session.post')
    def test_send_metrics_success(self, mock_post, api_client, sample_metrics):
        """Test successful metrics sending."""
        mock_response = Mock()
        mock_response.status_code = 200
        mock_response.json.return_value = {
            "success": True,
            "message": "Metrics received successfully",
            "device_id": "test-device-123",
            "status": "online",
            "alerts_created": 0
        }
        mock_post.return_value = mock_response

        result = api_client.send_metrics(sample_metrics)

        assert result is True
        mock_post.assert_called_once()

        # Verify request data
        call_args = mock_post.call_args
        assert call_args[1]['json']['device_id'] == 'test-device-123'

    @patch('ras_agent.api_client.requests.Session.post')
    def test_send_metrics_api_failure(self, mock_post, api_client, sample_metrics):
        """Test metrics sending when API returns success=false."""
        mock_response = Mock()
        mock_response.status_code = 200
        mock_response.json.return_value = {
            "success": False,
            "message": "Invalid data"
        }
        mock_post.return_value = mock_response

        result = api_client.send_metrics(sample_metrics)

        assert result is False

    @patch('ras_agent.api_client.requests.Session.post')
    def test_send_metrics_auth_failure(self, mock_post, api_client, sample_metrics):
        """Test metrics sending with authentication failure."""
        mock_response = Mock()
        mock_response.status_code = 401
        mock_post.return_value = mock_response

        result = api_client.send_metrics(sample_metrics)

        assert result is False
        # Should not retry auth failures
        assert mock_post.call_count == 1

    @patch('ras_agent.api_client.requests.Session.post')
    def test_send_metrics_server_error_retry(self, mock_post, api_client, sample_metrics):
        """Test retry on server error."""
        # First call fails with 500, second succeeds
        mock_response_error = Mock()
        mock_response_error.status_code = 500

        mock_response_success = Mock()
        mock_response_success.status_code = 200
        mock_response_success.json.return_value = {"success": True}

        mock_post.side_effect = [mock_response_error, mock_response_success]

        result = api_client.send_metrics(sample_metrics)

        assert result is True
        assert mock_post.call_count == 2  # Initial + 1 retry

    @patch('ras_agent.api_client.requests.Session.post')
    def test_send_metrics_timeout_retry(self, mock_post, api_client, sample_metrics):
        """Test retry on timeout."""
        # First call times out, second succeeds
        mock_response = Mock()
        mock_response.status_code = 200
        mock_response.json.return_value = {"success": True}

        mock_post.side_effect = [Timeout("Timeout"), mock_response]

        result = api_client.send_metrics(sample_metrics)

        assert result is True
        assert mock_post.call_count == 2

    @patch('ras_agent.api_client.requests.Session.post')
    def test_send_metrics_max_retries_exceeded(self, mock_post, api_client, sample_metrics):
        """Test that max retries are respected."""
        mock_post.side_effect = Timeout("Timeout")

        result = api_client.send_metrics(sample_metrics, max_retries=2)

        assert result is False
        # Should try initial + max_retries = 3 total attempts
        assert mock_post.call_count == 3

    @patch('ras_agent.api_client.requests.Session.post')
    def test_send_metrics_connection_error_retry(self, mock_post, api_client, sample_metrics):
        """Test retry on connection error."""
        mock_response = Mock()
        mock_response.status_code = 200
        mock_response.json.return_value = {"success": True}

        mock_post.side_effect = [ConnectionError("No connection"), mock_response]

        result = api_client.send_metrics(sample_metrics)

        assert result is True
        assert mock_post.call_count == 2

    @patch('ras_agent.api_client.requests.Session.post')
    def test_send_batch_metrics(self, mock_post, api_client):
        """Test sending multiple metrics in batch."""
        mock_response = Mock()
        mock_response.status_code = 200
        mock_response.json.return_value = {"success": True}
        mock_post.return_value = mock_response

        metrics_list = [
            {"hostname": "host1", "cpu_usage": 50},
            {"hostname": "host2", "cpu_usage": 60},
            {"hostname": "host3", "cpu_usage": 70}
        ]

        successful, failed = api_client.send_batch(metrics_list)

        assert successful == 3
        assert failed == 0
        assert mock_post.call_count == 3

    @patch('ras_agent.api_client.requests.Session.post')
    def test_send_batch_partial_failure(self, mock_post, api_client):
        """Test batch send with some failures."""
        # Create responses: 2 success, 1 failure
        success_response = Mock()
        success_response.status_code = 200
        success_response.json.return_value = {"success": True}

        fail_response = Mock()
        fail_response.status_code = 500
        fail_response.json.return_value = {"success": False}

        mock_post.side_effect = [success_response, fail_response, success_response]

        metrics_list = [
            {"hostname": "host1"},
            {"hostname": "host2"},
            {"hostname": "host3"}
        ]

        successful, failed = api_client.send_batch(metrics_list)

        assert successful == 2
        assert failed == 1

    def test_close_session(self, api_client):
        """Test session cleanup."""
        api_client.close()
        # Verify session is closed (no exceptions should be raised)
        assert True

    @patch('ras_agent.api_client.APIClient')
    def test_send_metrics_to_api_convenience_function(self, mock_client_class, sample_metrics):
        """Test the convenience function for sending metrics."""
        mock_client = Mock()
        mock_client.send_metrics.return_value = True
        mock_client_class.return_value = mock_client

        result = send_metrics_to_api(
            api_endpoint="http://test.com/api",
            api_key="test-key",
            device_id="test-device",
            metrics=sample_metrics,
            logger=Mock()
        )

        assert result is True
        mock_client.send_metrics.assert_called_once()
        mock_client.close.assert_called_once()


class TestAPIRetryLogic:
    """Test retry logic behavior."""

    @patch('ras_agent.api_client.time.sleep')  # Mock sleep to speed up tests
    @patch('ras_agent.api_client.requests.Session.post')
    def test_exponential_backoff(self, mock_post, mock_sleep, api_client, sample_metrics):
        """Test that exponential backoff is used for retries."""
        mock_post.side_effect = Timeout("Timeout")

        api_client.send_metrics(sample_metrics, max_retries=3)

        # Check that sleep was called with increasing backoff times
        sleep_calls = [call[0][0] for call in mock_sleep.call_args_list]
        assert len(sleep_calls) >= 2
        # Backoff should increase
        assert sleep_calls[1] >= sleep_calls[0]

    @patch('ras_agent.api_client.requests.Session.post')
    def test_max_backoff_limit(self, mock_post, api_client, sample_metrics):
        """Test that backoff doesn't exceed maximum limit."""
        import time

        mock_post.side_effect = Timeout("Timeout")

        original_sleep = time.sleep
        sleep_times = []

        def mock_sleep(duration):
            sleep_times.append(duration)
            return original_sleep(0.001)  # Small delay for testing

        with patch('time.sleep', side_effect=mock_sleep):
            api_client.send_metrics(sample_metrics, max_retries=10)

        # Verify no sleep time exceeds max backoff
        for sleep_time in sleep_times:
            assert sleep_time <= api_client.MAX_BACKOFF
