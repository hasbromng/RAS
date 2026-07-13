"""
RAS Agent Buffer Module

This module handles local buffering of metrics when the server is unavailable.
It stores failed requests in a local JSON file and retries them when connection is restored.
"""

import json
import os
import threading
import time
from typing import Dict, Any, List, Optional
from collections import deque


class MetricsBuffer:
    """
    Local buffer for storing metrics when server is unavailable.
    """

    def __init__(self, buffer_file: str = "buffer.json",
                 max_size: int = 1000, logger=None):
        """
        Initialize metrics buffer.

        Args:
            buffer_file: Path to buffer file
            max_size: Maximum number of metrics to buffer
            logger: Optional logger instance
        """
        self.buffer_file = buffer_file
        self.max_size = max_size
        self.logger = logger
        self._lock = threading.Lock()
        self._buffer: deque = deque(maxlen=max_size)
        self._dirty = False  # Track if buffer has unsaved changes

        # Create buffer directory if needed
        buffer_dir = os.path.dirname(buffer_file)
        if buffer_dir and not os.path.exists(buffer_dir):
            try:
                os.makedirs(buffer_dir, exist_ok=True)
            except OSError as e:
                if self.logger:
                    self.logger.error(f"Could not create buffer directory: {e}")

        # Load existing buffer
        self._load_buffer()

    def _load_buffer(self) -> None:
        """Load buffer from file."""
        if not os.path.exists(self.buffer_file):
            if self.logger:
                self.logger.debug(f"Buffer file {self.buffer_file} does not exist, starting fresh")
            return

        try:
            with open(self.buffer_file, 'r', encoding='utf-8') as f:
                data = json.load(f)

                # Load metrics into buffer (respecting max_size)
                metrics = data.get('metrics', [])
                for metric in metrics[-self.max_size:]:
                    self._buffer.append(metric)

            if self.logger:
                self.logger.info(f"Loaded {len(self._buffer)} metrics from buffer")

        except json.JSONDecodeError as e:
            if self.logger:
                self.logger.error(f"Invalid JSON in buffer file: {e}")
            self._clear_buffer_file()
        except Exception as e:
            if self.logger:
                self.logger.error(f"Error loading buffer: {e}")

    def _save_buffer(self) -> bool:
        """
        Save buffer to file.

        Returns:
            True if successful, False otherwise
        """
        try:
            with self._lock:
                data = {
                    'version': 1,
                    'created_at': time.time(),
                    'metrics': list(self._buffer)
                }

                # Write to temp file first, then rename (atomic operation)
                temp_file = f"{self.buffer_file}.tmp"
                with open(temp_file, 'w', encoding='utf-8') as f:
                    json.dump(data, f, indent=2)

                # Atomically replace the actual buffer file
                os.replace(temp_file, self.buffer_file)

                self._dirty = False
                return True

        except Exception as e:
            if self.logger:
                self.logger.error(f"Error saving buffer: {e}")
            return False

    def _clear_buffer_file(self) -> None:
        """Clear the buffer file."""
        try:
            if os.path.exists(self.buffer_file):
                os.remove(self.buffer_file)
        except Exception as e:
            if self.logger:
                self.logger.error(f"Error clearing buffer file: {e}")

    def save_to_buffer(self, metrics: Dict[str, Any]) -> bool:
        """
        Save metrics to buffer.

        Args:
            metrics: Dictionary containing metrics to buffer

        Returns:
            True if successful, False otherwise
        """
        try:
            with self._lock:
                # Add timestamp to metrics
                buffered_metric = metrics.copy()
                buffered_metric['_buffered_at'] = time.time()

                self._buffer.append(buffered_metric)
                self._dirty = True

            if self.logger:
                self.logger.debug(f"Saved metrics to buffer (buffer size: {len(self._buffer)})")

            # Auto-save after adding
            return self._save_buffer()

        except Exception as e:
            if self.logger:
                self.logger.error(f"Error saving to buffer: {e}")
            return False

    def get_buffered_count(self) -> int:
        """
        Get number of buffered metrics.

        Returns:
            Number of buffered metrics
        """
        return len(self._buffer)

    def is_empty(self) -> bool:
        """
        Check if buffer is empty.

        Returns:
            True if buffer is empty
        """
        return len(self._buffer) == 0

    def get_all_metrics(self) -> List[Dict[str, Any]]:
        """
        Get all buffered metrics.

        Returns:
            List of buffered metrics
        """
        with self._lock:
            return list(self._buffer)

    def remove_metric(self, metric: Dict[str, Any]) -> bool:
        """
        Remove a specific metric from buffer.

        Args:
            metric: Metric dictionary to remove

        Returns:
            True if removed, False if not found
        """
        try:
            with self._lock:
                # Remove by comparing buffered_at timestamp
                if '_buffered_at' in metric:
                    for i, m in enumerate(self._buffer):
                        if m.get('_buffered_at') == metric.get('_buffered_at'):
                            del self._buffer[i]
                            self._dirty = True
                            return True
            return False
        except Exception as e:
            if self.logger:
                self.logger.error(f"Error removing metric: {e}")
            return False

    def clear_old_metrics(self, max_age_seconds: int = 86400) -> int:
        """
        Clear metrics older than specified age.

        Args:
            max_age_seconds: Maximum age in seconds (default: 24 hours)

        Returns:
            Number of metrics removed
        """
        current_time = time.time()
        removed_count = 0

        try:
            with self._lock:
                # Filter out old metrics
                new_buffer = deque(maxlen=self.max_size)
                for metric in self._buffer:
                    buffered_at = metric.get('_buffered_at', 0)
                    if current_time - buffered_at <= max_age_seconds:
                        new_buffer.append(metric)
                    else:
                        removed_count += 1

                removed_count = len(self._buffer) - len(new_buffer)
                self._buffer = new_buffer
                self._dirty = True

            if removed_count > 0 and self.logger:
                self.logger.info(f"Cleared {removed_count} old metrics from buffer")

            # Save after clearing
            if removed_count > 0:
                self._save_buffer()

            return removed_count

        except Exception as e:
            if self.logger:
                self.logger.error(f"Error clearing old metrics: {e}")
            return 0

    def clear_all(self) -> bool:
        """
        Clear all buffered metrics.

        Returns:
            True if successful
        """
        try:
            with self._lock:
                self._buffer.clear()
                self._dirty = True

            if self.logger:
                self.logger.info("Cleared all buffered metrics")

            return self._save_buffer()

        except Exception as e:
            if self.logger:
                self.logger.error(f"Error clearing buffer: {e}")
            return False

    def replace_all(self, metrics: List[Dict[str, Any]]) -> bool:
        """
        Replace the entire buffer contents in one operation.

        Args:
            metrics: New list of metrics to store

        Returns:
            True if successful
        """
        try:
            with self._lock:
                self._buffer = deque(metrics[-self.max_size:], maxlen=self.max_size)
                self._dirty = True

            return self._save_buffer()
        except Exception as e:
            if self.logger:
                self.logger.error(f"Error replacing buffer contents: {e}")
            return False

    def sync(self) -> bool:
        """
        Force sync buffer to disk.

        Returns:
            True if successful
        """
        if self._dirty:
            return self._save_buffer()
        return True

    def get_buffer_info(self) -> Dict[str, Any]:
        """
        Get information about the buffer.

        Returns:
            Dictionary with buffer information
        """
        return {
            'buffer_file': self.buffer_file,
            'current_size': len(self._buffer),
            'max_size': self.max_size,
            'is_dirty': self._dirty,
            'file_exists': os.path.exists(self.buffer_file),
            'file_size_bytes': os.path.getsize(self.buffer_file) if os.path.exists(self.buffer_file) else 0
        }

    def __len__(self) -> int:
        """Return buffer size."""
        return len(self._buffer)

    def __repr__(self) -> str:
        """String representation of buffer."""
        return f"MetricsBuffer(size={len(self._buffer)}/{self.max_size}, file={self.buffer_file})"


class BufferedMetricsSender:
    """
    Helper class to send buffered metrics to the API.
    """

    def __init__(self, buffer: MetricsBuffer, api_client, logger=None):
        """
        Initialize buffered sender.

        Args:
            buffer: MetricsBuffer instance
            api_client: APIClient instance
            logger: Optional logger instance
        """
        self.buffer = buffer
        self.api_client = api_client
        self.logger = logger

    def send_buffered_metrics(self, max_batch: int = 100) -> Dict[str, int]:
        """
        Attempt to send buffered metrics.

        Args:
            max_batch: Maximum number of metrics to send in one batch

        Returns:
            Dictionary with send results (successful, failed, remaining)
        """
        if self.buffer.is_empty():
            return {'successful': 0, 'failed': 0, 'remaining': 0}

        if self.logger:
            self.logger.info(f"Attempting to send {self.buffer.get_buffered_count()} buffered metrics")

        successful = 0
        failed = 0

        try:
            all_metrics = self.buffer.get_all_metrics()
            metrics_to_send = all_metrics[:max_batch]
            remaining_metrics = all_metrics[max_batch:]

            for idx, metric in enumerate(metrics_to_send):
                # Remove internal fields before sending
                clean_metric = {k: v for k, v in metric.items()
                                if not k.startswith('_')}

                if self.api_client.send_metrics(clean_metric):
                    successful += 1
                    continue

                failed += 1
                # Stop on first failure to avoid excessive retries.
                remaining_metrics = metrics_to_send[idx:] + remaining_metrics
                break

            # Save buffer state once after processing
            self.buffer.replace_all(remaining_metrics)

            result = {
                'successful': successful,
                'failed': failed,
                'remaining': len(remaining_metrics)
            }

            if self.logger:
                if successful > 0:
                    self.logger.info(f"Sent {successful} buffered metrics, {failed} failed, "
                                   f"{result['remaining']} remaining")
                elif failed > 0:
                    self.logger.warning(f"Failed to send buffered metrics, "
                                       f"{result['remaining']} still buffered")

            return result

        except Exception as e:
            if self.logger:
                self.logger.error(f"Error sending buffered metrics: {e}")
            return {'successful': successful, 'failed': failed, 'remaining': self.buffer.get_buffered_count()}
