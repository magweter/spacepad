/**
 * k6 StatsD Tags Script
 * Adds custom tags to metrics for better observability
 */

export function tags(data) {
  return {
    test_type: 'continuous_load',
    service: 'spacepad-app',
  };
}

