/**
 * k6 Load Test Script for Spacepad
 * 
 * Tests the dashboard page and the /api/displays/{display}/data endpoint
 * Uses connect code 100001 for authentication
 * 
 * Can run in two modes:
 * - Load test mode (default): Variable load with stages
 * - Continuous mode: Steady continuous load (set CONTINUOUS=true)
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');
const requestDuration = new Trend('request_duration');
const requestsCounter = new Counter('requests_total');

// Configuration
const CONTINUOUS_MODE = __ENV.CONTINUOUS === 'true' || __ENV.CONTINUOUS === '1';
const BASE_URL = __ENV.BACKEND_URL || 'http://localhost:8000';
const CONNECT_CODE = __ENV.CONNECT_CODE || '100001';

// Different execution patterns based on mode
export const options = CONTINUOUS_MODE ? {
  scenarios: {
    continuous_load: {
      executor: 'constant-arrival-rate',
      rate: 2,                    // 2 requests per second
      timeUnit: '1s',
      duration: '24h',            // Run for 24 hours (effectively continuous)
      preAllocatedVUs: 5,         // Pre-allocate 5 VUs
      maxVUs: 20,                 // Max 20 VUs if needed
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<1000'],
    http_req_failed: ['rate<0.1'],
  },
} : {
  stages: [
    { duration: '30s', target: 5 },   // Ramp up to 5 users
    { duration: '2m', target: 10 },    // Ramp up to 10 users
    { duration: '5m', target: 10 },    // Stay at 10 users
    { duration: '2m', target: 20 },    // Ramp up to 20 users
    { duration: '5m', target: 20 },    // Stay at 20 users
    { duration: '2m', target: 10 },    // Ramp down to 10 users
    { duration: '5m', target: 10 },    // Stay at 10 users
  ],
  thresholds: {
    http_req_duration: ['p(95)<500', 'p(99)<1000'], // 95% of requests under 500ms, 99% under 1s
    http_req_failed: ['rate<0.05'],                   // Less than 5% errors
    errors: ['rate<0.05'],
  },
};

// Shared state for VU - stores auth token and display ID
let authToken = null;
let displayId = null;

// Setup function - runs once per VU to authenticate
export function setup() {
  if (!CONTINUOUS_MODE) {
    console.log(`Starting k6 load test against ${BASE_URL}`);
  }
  
  // Authenticate once per VU with retry logic
  const deviceUid = `k6-device-${__VU}-${Date.now()}`;
  const deviceName = CONTINUOUS_MODE ? `k6-continuous-${__VU}` : `k6-load-test-${__VU}`;
  
  let token = null;
  let displayIdToUse = null;
  
  // Retry authentication up to 3 times
  for (let attempt = 1; attempt <= 3; attempt++) {
    const loginResponse = http.post(
      `${BASE_URL}/api/auth/login`,
      JSON.stringify({
        code: CONNECT_CODE,
        uid: deviceUid,
        name: deviceName,
      }),
      {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        tags: {
          endpoint: '/api/auth/login',
          test_type: 'setup',
        },
        timeout: '10s',
      }
    );

    const loginSuccess = check(loginResponse, {
      'login status is 200': (r) => r.status === 200,
      'login has token': (r) => {
        try {
          const body = JSON.parse(r.body);
          return body.data && body.data.token !== undefined;
        } catch {
          return false;
        }
      },
    });

    if (loginSuccess) {
      try {
        const loginBody = JSON.parse(loginResponse.body);
        token = loginBody.data.token;
        if (!CONTINUOUS_MODE) {
          console.log(`VU ${__VU} authenticated successfully on attempt ${attempt}`);
        }
        break;
      } catch (e) {
        console.error(`VU ${__VU} failed to parse login response on attempt ${attempt}: ${e}`);
      }
    } else {
      console.error(`VU ${__VU} authentication failed on attempt ${attempt}: ${loginResponse.status} - ${loginResponse.body}`);
      if (attempt < 3) {
        sleep(1); // Wait before retry
      }
    }
  }

  if (!token) {
    console.error(`VU ${__VU} failed to authenticate after 3 attempts`);
    return { baseUrl: BASE_URL, token: null, displayId: null };
  }

  // Get displays list to find a display ID with retry
  for (let attempt = 1; attempt <= 3; attempt++) {
    const displaysResponse = http.get(
      `${BASE_URL}/api/displays`,
      {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
        },
        tags: {
          endpoint: '/api/displays',
          test_type: 'setup',
        },
        timeout: '10s',
      }
    );

    const displaysSuccess = check(displaysResponse, {
      'displays status is 200': (r) => r.status === 200,
      'displays has data': (r) => {
        try {
          const body = JSON.parse(r.body);
          return body.data && Array.isArray(body.data) && body.data.length > 0;
        } catch {
          return false;
        }
      },
    });

    if (displaysSuccess) {
      try {
        const displaysBody = JSON.parse(displaysResponse.body);
        if (displaysBody.data && displaysBody.data.length > 0) {
          displayIdToUse = displaysBody.data[0].id;
          if (!CONTINUOUS_MODE) {
            console.log(`VU ${__VU} found display ID: ${displayIdToUse}`);
          }
          break;
        } else {
          console.warn(`VU ${__VU} authenticated but no displays available`);
        }
      } catch (e) {
        console.error(`VU ${__VU} failed to parse displays response on attempt ${attempt}: ${e}`);
      }
    } else {
      console.error(`VU ${__VU} failed to get displays on attempt ${attempt}: ${displaysResponse.status} - ${displaysResponse.body}`);
      if (attempt < 3) {
        sleep(1); // Wait before retry
      }
    }
  }

  return {
    baseUrl: BASE_URL,
    token: token,
    displayId: displayIdToUse,
  };
}

// Main test function
export default function (data) {
  // Use token and displayId from setup
  authToken = data.token;
  displayId = data.displayId;

  if (!authToken) {
    // If no token, try to re-authenticate (might be a transient issue)
    const deviceUid = `k6-device-${__VU}-${Date.now()}`;
    const deviceName = CONTINUOUS_MODE ? `k6-continuous-${__VU}` : `k6-load-test-${__VU}`;
    
    const loginResponse = http.post(
      `${BASE_URL}/api/auth/login`,
      JSON.stringify({
        code: CONNECT_CODE,
        uid: deviceUid,
        name: deviceName,
      }),
      {
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        tags: {
          endpoint: '/api/auth/login',
          test_type: 'recovery',
        },
        timeout: '10s',
      }
    );

    if (loginResponse.status === 200) {
      try {
        const loginBody = JSON.parse(loginResponse.body);
        authToken = loginBody.data.token;
        data.token = authToken; // Update data for future iterations
        if (!CONTINUOUS_MODE) {
          console.log(`VU ${__VU} recovered authentication`);
        }
      } catch (e) {
        console.error(`VU ${__VU} failed to parse recovery login response: ${e}`);
      }
    }

    if (!authToken) {
      console.error(`VU ${__VU} has no auth token, skipping iteration`);
      sleep(1);
      return;
    }
  }

  // Weighted endpoint selection
  // 70% of requests go to the display data endpoint (most used)
  // 30% go to dashboard page
  const useDisplayData = Math.random() < 0.7;

  let url, params, endpoint;

  if (useDisplayData && displayId) {
    // Call the display data endpoint
    endpoint = `/api/displays/${displayId}/data`;
    url = `${BASE_URL}${endpoint}`;
    params = {
      headers: {
        'Authorization': `Bearer ${authToken}`,
        'Accept': 'application/json',
        'User-Agent': CONTINUOUS_MODE ? `k6-continuous/${__VU}` : `k6-load-test/${__VU}`,
      },
      tags: {
        endpoint: endpoint,
        test_type: 'api',
        load_type: CONTINUOUS_MODE ? 'continuous' : 'load_test',
      },
    };
  } else {
    // Call the dashboard page
    endpoint = '/';
    url = `${BASE_URL}${endpoint}`;
    params = {
      headers: {
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'User-Agent': CONTINUOUS_MODE ? `k6-continuous/${__VU}` : `k6-load-test/${__VU}`,
      },
      tags: {
        endpoint: endpoint,
        test_type: 'web',
        load_type: CONTINUOUS_MODE ? 'continuous' : 'load_test',
      },
    };
  }

  const startTime = Date.now();
  const response = http.get(url, params);
  const duration = Date.now() - startTime;

  requestsCounter.add(1, { endpoint: endpoint });
  requestDuration.add(duration, { endpoint: endpoint });

  const success = check(response, {
    'status is 200 or 302': (r) => r.status === 200 || r.status === 302,
    'response time < 2000ms': (r) => r.timings.duration < 2000,
  });

  // For API endpoints, also check for valid JSON
  if (useDisplayData && displayId) {
    const jsonCheck = check(response, {
      'has valid JSON': (r) => {
        try {
          JSON.parse(r.body);
          return true;
        } catch {
          return false;
        }
      },
      'has display data': (r) => {
        try {
          const body = JSON.parse(r.body);
          return body.data !== undefined;
        } catch {
          return false;
        }
      },
    });
    errorRate.add(!success || !jsonCheck);
  } else {
    errorRate.add(!success);
  }

  // Simulate user think time
  // Continuous mode: 0.5-2 seconds, Load test mode: 1-3 seconds
  const thinkTime = CONTINUOUS_MODE 
    ? Math.random() * 1.5 + 0.5 
    : Math.random() * 2 + 1;
  sleep(thinkTime);
}

// Teardown function - runs once after all VUs finish
export function teardown(data) {
  const mode = CONTINUOUS_MODE ? 'continuous load test' : 'load test';
  console.log(`${mode} completed for ${data.baseUrl}`);
  if (data.displayId) {
    console.log(`Tested display ID: ${data.displayId}`);
  }
}
