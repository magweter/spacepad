# Grafana Faro Frontend Monitoring Setup

This document explains how to configure Grafana Faro Real User Monitoring (RUM) for the Spacepad frontend.

## Overview

Grafana Faro collects frontend telemetry data including:
- **Web Vitals** (LCP, FID, CLS, FCP, TTFB)
- **Page load timing** (DOMContentLoaded, Load, etc.)
- **User interactions** (clicks, form submissions)
- **All fetch/XHR requests** with full trace context
- **Frontend → Backend trace correlation**
- **Long tasks** (performance monitoring)
- **Errors and exceptions** (sent to Loki)
- **Console logs** (errors/warnings sent to Loki)
- **Session tracking**

## Configuration

### 1. Enable Faro

Add to your `.env` file:

```env
FARO_ENABLED=true
FARO_COLLECTOR_URL=http://localhost:12347/collect
FARO_API_KEY=faro-secret-key
FARO_APP_NAME=spacepad
FARO_APP_VERSION=1.0.0
FARO_APP_ENV=local
```

### 2. Grafana Alloy Configuration

Ensure Grafana Alloy is running with a FARO receiver configured. The receiver should:
- Listen on port `12347` at `/collect` endpoint
- Use the same `api_key` as configured in `FARO_API_KEY`
- Forward logs to Loki
- Forward traces to Tempo
- Forward metrics to Prometheus

Example Alloy configuration:

```river
faro.receiver "faro_receiver" {
    server {
        listen_address           = "0.0.0.0"
        listen_port              = 12347
        cors_allowed_origins     = ["*"]  // Allow all origins for development
        api_key                  = "faro-secret-key"  // Must match FARO_API_KEY
        max_allowed_payload_size = "10MiB"

        rate_limiting {
            rate = 100
        }
    }

    sourcemaps { }

    output {
        logs   = [loki.process.faro_logs.receiver]
        traces = [otelcol.processor.batch.batch_processor.input]
    }
}
```

### 3. Docker Environment

If running in Docker, use `host.docker.internal` to reach Grafana Alloy on the host:

```env
FARO_COLLECTOR_URL=http://host.docker.internal:12347/collect
```

## How It Works

1. **Frontend Application** → Sends RUM data via FARO SDK → **Grafana Alloy** (port 12347 `/collect`)
2. **Grafana Alloy** → Processes FARO data → Forwards to:
   - **Prometheus** (metrics)
   - **Loki** (logs)
   - **Tempo** (traces)

## Features

The Faro integration automatically captures:

- ✅ **Web Vitals** (LCP, FID, CLS, FCP, TTFB)
- ✅ **Page load timing** (DOMContentLoaded, Load, etc.)
- ✅ **User interactions** (clicks, form submissions)
- ✅ **All fetch/XHR requests** with full trace context
- ✅ **Frontend → Backend trace correlation**
- ✅ **Long tasks** (performance monitoring)
- ✅ **Errors and exceptions** (sent to Loki)
- ✅ **Console logs** (errors/warnings sent to Loki)
- ✅ **Session tracking**

## Viewing Data

### Grafana Dashboard

Import the Grafana Faro Frontend Monitoring dashboard (ID: `17766`):

1. Open Grafana at http://localhost:3000
2. Go to Dashboards → Import
3. Enter dashboard ID: `17766`
4. Select Prometheus as the datasource
5. Click "Import"

### Prometheus Queries

Query FARO metrics in Prometheus:

```promql
# Frontend errors
faro_errors_total

# Page load metrics
faro_page_load_duration_seconds

# Web Vitals
faro_web_vitals_lcp_seconds
faro_web_vitals_fid_seconds
faro_web_vitals_cls
```

### Loki Logs

Search for frontend logs in Loki:

```logql
{service_name="spacepad"} |= "error"
```

### Tempo Traces

View frontend traces in Tempo:
- Search for traces from `spacepad` service
- Filter by route or operation
- View trace details and spans

## Troubleshooting

### Faro Not Initializing

1. **Check browser console** for initialization errors
2. **Verify configuration** in `.env` file
3. **Check network tab** for requests to `/collect` endpoint
4. **Verify CORS** settings in Grafana Alloy config

### No Data in Grafana

1. **Check Grafana Alloy logs:**
   ```bash
   docker logs grafana-alloy
   ```

2. **Verify API key matches:**
   - `FARO_API_KEY` in `.env` must match `api_key` in Alloy config

3. **Check Prometheus targets:**
   - Visit http://localhost:9090/targets
   - Verify `grafana-alloy` target is UP

4. **Verify CORS:**
   - Ensure `cors_allowed_origins` in Alloy config includes your frontend origin

### CORS Errors

If you see CORS errors in the browser console:

1. Add your frontend origin to `cors_allowed_origins` in Alloy config
2. For development: `cors_allowed_origins = ["*"]`
3. For production: `cors_allowed_origins = ["https://yourdomain.com"]`

## Security

**Important:** The default API key `faro-secret-key` is for development only. In production:

1. Generate a secure random API key
2. Update `FARO_API_KEY` in `.env`
3. Update `api_key` in Grafana Alloy config
4. Consider using environment variables or secrets management

## Advanced Configuration

### Custom Instrumentations

To add custom instrumentations, modify `resources/views/components/scripts/faro.blade.php`:

```javascript
import { TracingInstrumentation } from '@grafana/faro-web-tracing';

const faroInstance = initializeFaro({
    // ... existing config
    instrumentations: [
        ...getWebInstrumentations(),
        new TracingInstrumentation(),
    ],
});
```

### Disable Specific Features

You can disable specific features via environment variables:

```env
FARO_PERFORMANCE_ENABLED=false
FARO_ERRORS_ENABLED=false
FARO_CONSOLE_ENABLED=false
FARO_INTERACTIONS_ENABLED=false
FARO_SESSION_TRACKING=false
```

## References

- [Grafana Faro Documentation](https://github.com/grafana/faro-web-sdk)
- [Grafana Faro Quick Start](https://github.com/grafana/faro-web-sdk/blob/main/docs/sources/tutorials/quick-start-browser.md)
- [Grafana Alloy FARO Receiver](https://grafana.com/docs/alloy/latest/reference/components/faro.receiver/)

