"""
Unit tests for RAS Agent buffer module.
"""

import pytest
import json
import os
import tempfile
from pathlib import Path
from unittest.mock import Mock, patch

from ras_agent.buffer import MetricsBuffer, BufferedMetricsSender


@pytest.fixture
def temp_buffer_file(tmp_path):
    """Create a temporary buffer file path."""
    return str(tmp_path / "test_buffer.json")


@pytest.fixture
def buffer(temp_buffer_file):
    """Create a MetricsBuffer instance for testing."""
    return MetricsBuffer(buffer_file=temp_buffer_file, max_size=100, logger=Mock())


@pytest.fixture
def sample_metrics():
    """Sample metrics for testing."""
    return {
        "device_id": "test-device",
        "hostname": "test-host",
        "cpu_usage": 50.0,
        "memory_used": 8000000000,
        "memory_total": 16000000000
    }


class TestMetricsBuffer:
    """Test cases for MetricsBuffer class."""

    def test_initialization(self, temp_buffer_file):
        """Test buffer initialization."""
        logger = Mock()
        buffer = MetricsBuffer(buffer_file=temp_buffer_file, max_size=100, logger=logger)

        assert buffer.buffer_file == temp_buffer_file
        assert buffer.max_size == 100
        assert buffer.logger is logger
        assert buffer.is_empty()

    def test_save_to_buffer(self, buffer, sample_metrics):
        """Test saving metrics to buffer."""
        result = buffer.save_to_buffer(sample_metrics)

        assert result is True
        assert buffer.get_buffered_count() == 1
        assert not buffer.is_empty()

    def test_save_to_buffer_adds_timestamp(self, buffer, sample_metrics):
        """Test that buffering adds timestamp."""
        buffer.save_to_buffer(sample_metrics)

        all_metrics = buffer.get_all_metrics()
        assert len(all_metrics) == 1

        buffered_metric = all_metrics[0]
        assert '_buffered_at' in buffered_metric
        assert isinstance(buffered_metric['_buffered_at'], float)

    def test_max_size_limit(self, temp_buffer_file):
        """Test that buffer respects max size limit."""
        buffer = MetricsBuffer(buffer_file=temp_buffer_file, max_size=5, logger=Mock())

        # Add more metrics than max_size
        for i in range(10):
            buffer.save_to_buffer({"test": f"metric-{i}"})

        # Should only keep max_size metrics
        assert buffer.get_buffered_count() == 5

    def test_get_all_metrics(self, buffer, sample_metrics):
        """Test getting all buffered metrics."""
        buffer.save_to_buffer(sample_metrics)
        buffer.save_to_buffer({"test": "metric-2"})

        all_metrics = buffer.get_all_metrics()

        assert len(all_metrics) == 2
        assert all_metrics[0]['device_id'] == 'test-device'

    def test_remove_metric(self, buffer, sample_metrics):
        """Test removing a specific metric."""
        buffer.save_to_buffer(sample_metrics)

        all_metrics = buffer.get_all_metrics()
        metric_to_remove = all_metrics[0]

        result = buffer.remove_metric(metric_to_remove)

        assert result is True
        assert buffer.is_empty()

    def test_remove_metric_not_found(self, buffer, sample_metrics):
        """Test removing a metric that doesn't exist."""
        buffer.save_to_buffer(sample_metrics)

        fake_metric = {"_buffered_at": 123456789}
        result = buffer.remove_metric(fake_metric)

        assert result is False
        assert buffer.get_buffered_count() == 1

    def test_clear_all(self, buffer, sample_metrics):
        """Test clearing all buffered metrics."""
        buffer.save_to_buffer(sample_metrics)
        buffer.save_to_buffer({"test": "metric-2"})

        result = buffer.clear_all()

        assert result is True
        assert buffer.is_empty()

    def test_clear_old_metrics(self, buffer):
        """Test clearing metrics older than specified age."""
        import time

        # Add a metric that will be considered old
        old_metric = {"device": "test", "_buffered_at": time.time() - 100000}  # Very old
        buffer._buffer.append(old_metric)

        # Add a current metric
        current_metric = {"device": "test", "_buffered_at": time.time()}
        buffer.save_to_buffer(current_metric)

        # Clear old metrics (with max_age of 1 hour)
        removed = buffer.clear_old_metrics(max_age_seconds=3600)

        assert removed == 1
        assert buffer.get_buffered_count() == 1

    def test_buffer_persistence(self, temp_buffer_file, sample_metrics):
        """Test that buffer persists to disk."""
        # Create buffer and add metrics
        buffer1 = MetricsBuffer(buffer_file=temp_buffer_file, max_size=10, logger=Mock())
        buffer1.save_to_buffer(sample_metrics)
        buffer1.save_to_buffer({"test": "metric-2"})

        # Create new buffer instance (should load from file)
        buffer2 = MetricsBuffer(buffer_file=temp_buffer_file, max_size=10, logger=Mock())

        # Should have loaded the metrics
        assert buffer2.get_buffered_count() == 2

    def test_sync_on_save(self, temp_buffer_file):
        """Test that buffer syncs to disk automatically."""
        buffer = MetricsBuffer(buffer_file=temp_buffer_file, max_size=10, logger=Mock())
        buffer.save_to_buffer({"test": "metric"})

        # Check that file was created
        assert os.path.exists(temp_buffer_file)

        # Load and verify content
        with open(temp_buffer_file, 'r') as f:
            data = json.load(f)

        assert 'metrics' in data
        assert len(data['metrics']) == 1

    def test_sync_force(self, buffer, sample_metrics):
        """Test forced sync to disk."""
        buffer.save_to_buffer(sample_metrics)

        result = buffer.sync()

        assert result is True

    def test_get_buffer_info(self, buffer):
        """Test getting buffer information."""
        buffer.save_to_buffer({"test": "metric"})

        info = buffer.get_buffer_info()

        assert 'buffer_file' in info
        assert 'current_size' in info
        assert 'max_size' in info
        assert 'is_dirty' in info
        assert info['current_size'] == 1
        assert info['max_size'] == 100

    def test_len_dunder(self, buffer, sample_metrics):
        """Test __len__ method."""
        assert len(buffer) == 0

        buffer.save_to_buffer(sample_metrics)
        assert len(buffer) == 2  # metric + _buffered_at field

    def test_repr_dunder(self, buffer):
        """Test __repr__ method."""
        repr_str = repr(buffer)

        assert 'MetricsBuffer' in repr_str
        assert '0/100' in repr_str  # current/max size

    def test_thread_safety(self, buffer, sample_metrics):
        """Test that buffer operations are thread-safe."""
        import threading

        def add_metrics():
            for i in range(10):
                buffer.save_to_buffer({"test": i})

        # Create multiple threads
        threads = [threading.Thread(target=add_metrics) for _ in range(5)]

        for thread in threads:
            thread.start()

        for thread in threads:
            thread.join()

        # Should have all metrics (up to max_size)
        assert buffer.get_buffered_count() == 100  # max_size

    def test_load_from_corrupted_file(self, temp_buffer_file):
        """Test handling corrupted buffer file."""
        # Create corrupted file
        with open(temp_buffer_file, 'w') as f:
            f.write("invalid json content")

        logger = Mock()
        buffer = MetricsBuffer(buffer_file=temp_buffer_file, max_size=10, logger=logger)

        # Should start with empty buffer
        assert buffer.is_empty()
        # Error should be logged
        logger.error.assert_called()

    def test_clear_old_metrics_removes_correct_ones(self, buffer):
        """Test that clear_old_metrics only removes old metrics."""
        import time

        current_time = time.time()

        # Add metrics with different ages
        for i in range(5):
            age = i * 1000  # Different ages
            metric = {"test": i, "_buffered_at": current_time - age}
            buffer._buffer.append(metric)

        # Clear metrics older than 2500 time units
        removed = buffer.clear_old_metrics(max_age_seconds=2500)

        # Should remove 3 metrics (ages 3000, 2000, 1000)
        assert removed == 3
        # Should keep 2 most recent metrics
        assert buffer.get_buffered_count() == 2


class TestBufferedMetricsSender:
    """Test cases for BufferedMetricsSender class."""

    @pytest.fixture
    def mock_api_client(self):
        """Create mock API client."""
        client = Mock()
        client.send_metrics.return_value = True
        return client

    @pytest.fixture
    def buffered_sender(self, buffer, mock_api_client):
        """Create BufferedMetricsSender instance."""
        return BufferedMetricsSender(buffer=buffer, api_client=mock_api_client, logger=Mock())

    def test_initialization(self, buffered_sender, buffer, mock_api_client):
        """Test sender initialization."""
        assert buffered_sender.buffer is buffer
        assert buffered_sender.api_client is mock_api_client

    def test_send_buffered_metrics_empty(self, buffered_sender):
        """Test sending when buffer is empty."""
        result = buffered_sender.send_buffered_metrics()

        assert result['successful'] == 0
        assert result['failed'] == 0
        assert result['remaining'] == 0

    def test_send_buffered_metrics_success(self, buffered_sender, sample_metrics):
        """Test successful sending of buffered metrics."""
        # Add metrics to buffer
        for i in range(3):
            metric = sample_metrics.copy()
            metric['test'] = i
            buffered_sender.buffer.save_to_buffer(metric)

        # API client returns success
        buffered_sender.api_client.send_metrics.return_value = True

        result = buffered_sender.send_buffered_metrics()

        assert result['successful'] == 3
        assert result['failed'] == 0
        assert result['remaining'] == 0

    def test_send_buffered_metrics_partial_failure(self, buffered_sender, sample_metrics):
        """Test sending with some failures."""
        # Add 3 metrics
        for i in range(3):
            metric = sample_metrics.copy()
            metric['test'] = i
            buffered_sender.buffer.save_to_buffer(metric)

        # First 2 succeed, 3rd fails
        call_count = [0]

        def mock_send(metrics):
            call_count[0] += 1
            return call_count[0] < 3  # Fail on 3rd call

        buffered_sender.api_client.send_metrics.side_effect = mock_send

        result = buffered_sender.send_buffered_metrics()

        assert result['successful'] == 2
        assert result['failed'] == 1
        assert result['remaining'] == 1

    def test_send_buffered_metrics_max_batch(self, buffered_sender, sample_metrics):
        """Test batch size limit."""
        # Add more metrics than max_batch
        for i in range(10):
            metric = sample_metrics.copy()
            metric['test'] = i
            buffered_sender.buffer.save_to_buffer(metric)

        result = buffered_sender.send_buffered_metrics(max_batch=5)

        # Should only process 5 at a time
        assert result['successful'] == 5
        assert result['remaining'] == 5

    def test_send_buffered_metrics_stops_on_failure(self, buffered_sender, sample_metrics):
        """Test that sending stops on first failure."""
        # Add 5 metrics
        for i in range(5):
            metric = sample_metrics.copy()
            metric['test'] = i
            buffered_sender.buffer.save_to_buffer(metric)

        # First succeeds, then fail
        call_count = [0]

        def mock_send(metrics):
            call_count[0] += 1
            return call_count[0] == 1

        buffered_sender.api_client.send_metrics.side_effect = mock_send

        result = buffered_sender.send_buffered_metrics()

        # Should stop after first failure
        assert result['successful'] == 1
        assert result['failed'] == 1
        assert result['remaining'] == 4

    def test_send_buffered_metrics_removes_internal_fields(self, buffered_sender, sample_metrics):
        """Test that internal fields are removed before sending."""
        buffered_sender.buffer.save_to_buffer(sample_metrics)

        def check_fields(metrics):
            # Should not have internal fields
            assert '_buffered_at' not in metrics
            return True

        buffered_sender.api_client.send_metrics.side_effect = check_fields

        buffered_sender.send_buffered_metrics()

        # Check that the mock was called
        assert buffered_sender.api_client.send_metrics.called

    def test_send_buffered_metrics_syncs_buffer(self, buffered_sender, sample_metrics):
        """Test that buffer is synced after sending."""
        buffered_sender.buffer.save_to_buffer(sample_metrics)
        buffered_sender.api_client.send_metrics.return_value = True

        result = buffered_sender.send_buffered_metrics()

        # Buffer should be synced (empty after successful send)
        assert buffered_sender.buffer.is_empty()
