# k6 Load Testing for Spacepad

k6 script for generating realistic traffic to test the Spacepad application and demonstrate the observability stack.

## Script

### `load-test.js`
A unified script that can run in two modes:

**Load Test Mode (default)**: Variable load with different stages (ramp up, steady state, ramp down). Good for testing under different load conditions.

**Continuous Mode**: Steady, continuous traffic (2 requests/second) indefinitely. Perfect for demonstrating observability in action.

## Usage

### Load Test Mode (Default)
```bash
# Run with default settings
k6 run k6/load-test.js

# Or with custom backend URL
BACKEND_URL=http://localhost:8000 k6 run k6/load-test.js

# Via docker
docker run --rm -i --network spacepad-dev_app \
  -v $(pwd)/k6:/scripts \
  -e BACKEND_URL=http://app:8080 \
  -e CONNECT_CODE=100001 \
  grafana/k6 run /scripts/load-test.js
```

### Continuous Mode
```bash
# Set CONTINUOUS=true to enable continuous mode
CONTINUOUS=true BACKEND_URL=http://localhost:8000 k6 run k6/load-test.js

# Via docker
docker run --rm -i --network spacepad-dev_app \
  -v $(pwd)/k6:/scripts \
  -e BACKEND_URL=http://app:8080 \
  -e CONNECT_CODE=100001 \
  -e CONTINUOUS=true \
  grafana/k6 run /scripts/load-test.js
```

## Configuration

The script can be configured via environment variables:

- `BACKEND_URL`: Backend API URL (default: `http://localhost:8000`)
- `CONNECT_CODE`: Connect code for authentication (default: `100001`)
- `CONTINUOUS`: Set to `true` or `1` to enable continuous mode (default: `false`)

## Authentication

The script automatically authenticates using the connect code system:
1. Each Virtual User (VU) authenticates once during setup using `/api/auth/login` with connect code `100001`
2. The authentication token is stored and reused for all API requests
3. The scripts fetch available displays and use the first display for testing
4. Includes retry logic (up to 3 attempts) for robust authentication
5. Automatic recovery if authentication is lost during execution

## Traffic Patterns

The script simulates realistic user behavior:

- **Display Data API** (`/api/displays/{display}/data`): 70% of requests
  - Most frequently used endpoint
  - Requires authentication token
  - Returns display calendar data and events

- **Dashboard Page** (`/`): 30% of requests
  - Web dashboard page
  - Simulates user browsing

Each request includes:
- Proper authentication headers (for API endpoints)
- Random think time (simulates user reading/thinking)
- Proper headers (User-Agent, Accept)
- OpenTelemetry trace correlation

## Load Test Mode Stages

When running in load test mode (default), the script follows these stages:
- Ramp up to 5 users (30s)
- Ramp up to 10 users (2m)
- Stay at 10 users (5m)
- Ramp up to 20 users (2m)
- Stay at 20 users (5m)
- Ramp down to 10 users (2m)
- Stay at 10 users (5m)

## Continuous Mode

When running in continuous mode (`CONTINUOUS=true`):
- Generates 2 requests per second
- Runs for 24 hours (effectively continuous)
- Pre-allocates 5 VUs, scales up to 20 VUs if needed
- Lower think time (0.5-2s) for higher throughput

## Observing Traffic

With k6 running, you can observe:

1. **Grafana** (http://localhost:3000):
   - Traces in Tempo showing request flows
   - Metrics in Prometheus showing request rates, latencies
   - Service maps showing service dependencies
   - Logs in Loki showing application logs

2. **Prometheus** (http://localhost:9090):
   - Query: `rate(http_requests_total[1m])`
   - Query: `histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))`

3. **Tempo** (via Grafana):
   - Search for traces from `spacepad-app`
   - View trace details and spans
   - Filter by route: `/api/displays/{display}/data` or `/`

4. **Loki** (via Grafana):
   - Search for logs from the application
   - View log entries with trace context

## Troubleshooting

### Authentication Failures
- Ensure connect code `100001` exists and is valid
- Check that the user associated with the connect code has displays configured
- Verify the `BACKEND_URL` is correct
- The script includes retry logic, but check logs for persistent failures

### No Displays Available
- The script will skip API calls if no displays are found
- Ensure the authenticated user has at least one display configured
- Check display status (should be READY or ACTIVE)

### High Error Rates
- Check application logs for errors
- Verify database connectivity
- Ensure all required services are running
- Check network connectivity between k6 and the backend

### VUs Failing Authentication
- The script retries authentication up to 3 times
- Check backend logs for authentication errors
- Verify the connect code is valid and not expired
- Ensure the backend is accessible from k6

## Customization

Edit the script to:
- Change request rates (modify `rate` in continuous mode or `stages` in load test mode)
- Modify the endpoint distribution (currently 70% API, 30% dashboard)
- Add more endpoints
- Modify user behavior patterns
- Add custom metrics
- Change load patterns
