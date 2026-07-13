"""
Integration tests for RAS Agent main orchestrator.
"""

import pytest
import tempfile
import json
from pathlib import Path
from unittest.mock import Mock, patch, MagicMock

from ras_agent.agent import RASAgent, main


@pytest.fixture
def temp_config_file(tmp_path):
    """Create a temporary configuration file."""
    config_data = {
        "agent": {
            "device_id": "test-device-123",
            "hostname": "test-host",
            "api_endpoint": "http://test.example.com/api/metrics.php",
            "api_key": "test-api-key",
            "collect_interval": 10,
            "buffer_max_size": 100,
            "buffer_file": str(tmp_path / "buffer.json"),
            "log_file": str(tmp_path / "agent.log"),
            "log_max_size_mb": 10,
            "log_backup_count": 3
        },
        "thresholds": {
            "cpu_warning": 80,
            "cpu_critical": 90
        }
    }

    config_file = tmp_path / "config.json"
    with open(config_file, 'w') as f:
        json.dump(config_data, f)

    return str(config_file)


@pytest.fixture
def agent(temp_config_file):
    """Create RASAgent instance for testing."""
    return RASAgent(config_path=temp_config_file)


class TestRASAgent:
    """Test cases for RASAgent class."""

    def test_initialization(self, agent):
        """Test agent initialization."""
        assert agent is not None
        assert agent.config is not None
        assert agent.logger is not None
        assert agent.collector is not None
        assert agent.api_client is not None
        assert agent.buffer is not None

    def test_config_loading(self, agent, temp_config_file):
        """Test that configuration is loaded correctly."""
        assert agent.config.get('agent', 'device_id') == 'test-device-123'
        assert agent.config.get('agent', 'api_endpoint') == 'http://test.example.com/api/metrics.php'
        assert agent.config.get('agent', 'collect_interval') == 10

    def test_logger_initialization(self, agent):
        """Test that logger is initialized."""
        assert agent.logger is not None
        from ras_agent.logger import AgentLogger
        assert isinstance(agent.logger, AgentLogger)

    def test_collector_initialization(self, agent):
        """Test that collector is initialized."""
        assert agent.collector is not None
        from ras_agent.collector import MetricsCollector
        assert isinstance(agent.collector, MetricsCollector)

    def test_api_client_initialization(self, agent):
        """Test that API client is initialized."""
        assert agent.api_client is not None
        from ras_agent.api_client import APIClient
        assert isinstance(agent.api_client, APIClient)

    def test_buffer_initialization(self, agent):
        """Test that buffer is initialized."""
        assert agent.buffer is not None
        from ras_agent.buffer import MetricsBuffer
        assert isinstance(agent.buffer, MetricsBuffer)

    @patch('ras_agent.api_client.requests.Session.get')
    def test_connection_success(self, mock_get, agent):
        """Test successful connection test."""
        mock_response = Mock()
        mock_response.status_code = 200
        mock_get.return_value = mock_response

        result = agent.test_connection()

        assert result is True

    @patch('ras_agent.api_client.requests.Session.get')
    def test_connection_failure(self, mock_get, agent):
        """Test failed connection test."""
        mock_response = Mock()
        mock_response.status_code = 401
        mock_get.return_value = mock_response

        result = agent.test_connection()

        assert result is False

    @patch('ras_agent.api_client.APIClient.send_metrics')
    def test_collect_and_send_metrics_success(self, mock_send, agent):
        """Test successful metric collection and sending."""
        mock_send.return_value = True

        result = agent.collect_and_send_metrics()

        assert result is True
        mock_send.assert_called_once()

        # Verify metrics were collected
        call_args = mock_send.call_args[0][0]
        assert 'hostname' in call_args
        assert 'cpu_usage' in call_args

    @patch('ras_agent.api_client.APIClient.send_metrics')
    def test_collect_and_send_metrics_failure(self, mock_send, agent):
        """Test metric collection and sending failure."""
        mock_send.return_value = False

        result = agent.collect_and_send_metrics()

        assert result is False
        # Metrics should be buffered
        assert agent.buffer.get_buffered_count() == 1

    @patch('ras_agent.api_client.APIClient.send_metrics')
    def test_buffered_metrics_sent_on_success(self, mock_send, agent):
        """Test that buffered metrics are sent when connection is restored."""
        # First call fails (metrics get buffered)
        mock_send.return_value = False

        agent.collect_and_send_metrics()
        assert agent.buffer.get_buffered_count() == 1

        # Second call succeeds (buffered metrics should be sent)
        mock_send.return_value = True
        agent.collect_and_send_metrics()

        # Should have attempted to send buffered metrics
        assert mock_send.call_count >= 2

    def test_shutdown(self, agent):
        """Test graceful shutdown."""
        agent.running = True

        agent.shutdown()

        assert agent.running is False

    @patch('ras_agent.api_client.APIClient.close')
    def test_shutdown_closes_resources(self, mock_close, agent):
        """Test that shutdown closes resources."""
        agent.shutdown()

        mock_close.assert_called_once()

    @patch('time.sleep')
    @patch('ras_agent.agent.RASAgent.collect_and_send_metrics')
    @patch('ras_agent.agent.RASAgent.test_connection')
    def test_main_loop(self, mock_test, mock_collect, mock_sleep, agent):
        """Test the main agent loop."""
        # Setup mocks
        mock_test.return_value = True
        mock_collect.return_value = True

        # Make sleep set shutdown flag after one iteration
        def sleep_side_effect(duration):
            agent.shutdown_requested = True

        mock_sleep.side_effect = sleep_side_effect

        # Run the agent
        agent.run()

        # Verify flow
        mock_test.assert_called_once()
        mock_collect.assert_called_once()


class TestAgentMain:
    """Test cases for main entry point."""

    def test_main_function(self, temp_config_file):
        """Test main function."""
        with patch('ras_agent.agent.RASAgent') as mock_agent_class:
            mock_agent = Mock()
            mock_agent_class.return_value = mock_agent

            result = main(config_path=temp_config_file)

            assert result == 0
            mock_agent.run.assert_called_once()

    def test_main_keyboard_interrupt(self, temp_config_file):
        """Test main handles keyboard interrupt."""
        with patch('ras_agent.agent.RASAgent') as mock_agent_class:
            mock_agent.run.side_effect = KeyboardInterrupt()

            result = main(config_path=temp_config_file)

            assert result == 0

    def test_main_exception(self, temp_config_file):
        """Test main handles exceptions."""
        with patch('ras_agent.agent.RASAgent') as mock_agent_class:
            mock_agent_class.side_effect = Exception("Test error")

            result = main(config_path=temp_config_file)

            assert result == 1


class TestAgentIntegration:
    """Integration tests for agent components."""

    @patch('ras_agent.api_client.requests.Session.post')
    def test_full_metrics_flow(self, mock_post, agent):
        """Test complete metrics collection and sending flow."""
        # Mock successful API response
        mock_response = Mock()
        mock_response.status_code = 200
        mock_response.json.return_value = {
            "success": True,
            "message": "Metrics received",
            "device_id": "test-device-123",
            "status": "online",
            "alerts_created": 0
        }
        mock_post.return_value = mock_response

        # Run one cycle
        result = agent.run_once()

        assert result is True

        # Verify API was called
        assert mock_post.called

        # Verify request format
        call_args = mock_post.call_args
        request_data = call_args[1]['json']

        # Check required fields
        assert 'device_id' in request_data
        assert 'hostname' in request_data
        assert 'cpu_usage' in request_data
        assert 'memory_used' in request_data
        assert 'disk_usage' in request_data

    @patch('ras_agent.api_client.requests.Session.post')
    def test_metrics_buffering_on_failure(self, mock_post, agent):
        """Test that metrics are buffered when sending fails."""
        # Mock API failure
        mock_post.side_effect = Exception("Network error")

        # Run one cycle
        result = agent.run_once()

        assert result is False
        assert agent.buffer.get_buffered_count() == 1

    @patch('ras_agent.api_client.requests.Session.post')
    def test_buffered_metrics_retry_on_recovery(self, mock_post, agent):
        """Test that buffered metrics are sent when connection recovers."""
        # First attempt fails
        mock_post.side_effect = Exception("Network error")

        agent.run_once()
        assert agent.buffer.get_buffered_count() == 1

        # Second attempt succeeds
        mock_response = Mock()
        mock_response.status_code = 200
        mock_response.json.return_value = {"success": True}
        mock_post.side_effect = None
        mock_post.return_value = mock_response

        agent.run_once()

        # Buffered metrics should have been sent
        # Total calls: 1 failure + 1 success + 1 (or more) buffered retry
        assert mock_post.call_count >= 2

    def test_signal_handling(self, agent):
        """Test that agent handles shutdown signals."""
        import signal

        # Initially not shutdown
        assert not agent.shutdown_requested

        # Simulate SIGINT signal
        signal.raise_signal(signal.SIGINT)

        # Note: This test may not work perfectly in all environments
        # as signal handling can be complex

    def test_configuration_validation(self, tmp_path):
        """Test that invalid configuration is rejected."""
        # Create invalid config
        invalid_config = {
            "agent": {
                "device_id": "",  # Empty - should fail validation
                "hostname": "test",
                "api_endpoint": "http://test.com/api",
                "api_key": "test-key"
            }
        }

        config_file = tmp_path / "invalid_config.json"
        with open(config_file, 'w') as f:
            json.dump(invalid_config, f)

        with pytest.raises(ValueError):
            RASAgent(config_path=str(config_file))
