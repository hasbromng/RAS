"""
Test script for ngrok connection to RAS API

This script tests if the ngrok tunnel and API connection are working properly.
"""

import requests
import json
import sys

# Configuration
NGROK_URL = "https://heathered-dortha-unparsed.ngrok-free.dev"
API_ENDPOINT = f"{NGROK_URL}/RAS/admin/api/metrics.php"
API_KEY = "change-this-to-secure-key"  # Change this to your actual API key!

def test_connection():
    """Test basic connection to ngrok endpoint."""
    print("🔍 Testing connection to ngrok endpoint...")
    print(f"   URL: {API_ENDPOINT}")
    print()

    try:
        response = requests.get(API_ENDPOINT, timeout=10)
        print(f"✅ Connection successful!")
        print(f"   Status Code: {response.status_code}")
        print(f"   Response: {response.text[:200]}...")
        return True
    except requests.exceptions.Timeout:
        print(f"❌ Connection timeout!")
        print(f"   Make sure ngrok is running on your server")
        return False
    except requests.exceptions.ConnectionError as e:
        print(f"❌ Connection error: {e}")
        print(f"   Check if ngrok URL is correct: {NGROK_URL}")
        return False
    except Exception as e:
        print(f"❌ Unexpected error: {e}")
        return False

def test_api_authentication():
    """Test API authentication with API key."""
    print("\n🔐 Testing API authentication...")
    print(f"   API Key: {API_KEY[:8]}...{API_KEY[-4:]}")
    print()

    test_data = {
        "device_id": "test-connection",
        "hostname": "test-host",
        "ip_address": "192.168.1.100",
        "cpu_usage": 25.5,
        "memory_used": 4000000000,
        "memory_total": 16000000000,
        "disk_used": 500000000000,
        "disk_total": 1000000000000,
        "disk_usage": 50.0,
        "storage_health": "healthy",
        "network_status": "good"
    }

    try:
        response = requests.post(
            API_ENDPOINT,
            json=test_data,
            headers={
                "X-API-Key": API_KEY,
                "Content-Type": "application/json"
            },
            timeout=10
        )

        print(f"   Status Code: {response.status_code}")

        if response.status_code == 200:
            result = response.json()
            if result.get('success'):
                print(f"✅ API authentication successful!")
                print(f"   Message: {result.get('message')}")
                print(f"   Device ID: {result.get('device_id')}")
                print(f"   Status: {result.get('status')}")
                print(f"   Alerts Created: {result.get('alerts_created', 0)}")
                return True
            else:
                print(f"❌ API returned success=false")
                print(f"   Message: {result.get('message')}")
                return False
        elif response.status_code == 401:
            print(f"❌ Authentication failed!")
            print(f"   Check your API key in config.php")
            return False
        else:
            print(f"❌ Unexpected status code: {response.status_code}")
            print(f"   Response: {response.text}")
            return False

    except Exception as e:
        print(f"❌ Error during API test: {e}")
        return False

def check_api_key():
    """Check if API key needs to be changed."""
    global API_KEY

    if API_KEY == "change-this-to-secure-key":
        print("\n⚠️  WARNING: Using default API key!")
        print("   Please update the API_KEY in this script:")
        print("   1. Open config/config.php on your server")
        print("   2. Look for API_KEY constant")
        print("   3. Update API_KEY in this script with that value")
        print()

        response = input("Do you want to continue with default key? (y/n): ")
        if response.lower() != 'y':
            print("❌ Test cancelled. Please update API key first.")
            return False

    return True

def main():
    """Run all connection tests."""
    print("=" * 60)
    print("RAS Agent - ngrok Connection Test")
    print("=" * 60)
    print()
    print(f"ngrok URL: {NGROK_URL}")
    print(f"API Endpoint: {API_ENDPOINT}")
    print("=" * 60)
    print()

    # Check API key
    if not check_api_key():
        sys.exit(1)

    # Test basic connection
    if not test_connection():
        print("\n❌ Basic connection failed. Please fix connection issues first.")
        sys.exit(1)

    # Test API authentication
    if not test_api_authentication():
        print("\n❌ API authentication failed. Please check API key configuration.")
        sys.exit(1)

    # All tests passed
    print("\n" + "=" * 60)
    print("✅ All tests passed!")
    print("=" * 60)
    print()
    print("Your ngrok tunnel is working properly!")
    print("You can now install and run the Python agent.")
    print()
    print("Next steps:")
    print("1. Copy config.ngrok.json to config.json on client machine")
    print("2. Update device_id and hostname in config.json")
    print("3. Update API_KEY in config.json with actual value")
    print("4. Run: python -m ras_agent.agent")
    print()

if __name__ == "__main__":
    main()
