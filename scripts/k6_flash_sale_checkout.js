import http from 'k6/http';
import { check, sleep } from 'k6';

// Concurrency options: 20 Virtual Users firing 20 requests simultaneously
export const options = {
  scenarios: {
    flash_sale_blast: {
      executor: 'shared-iterations',
      vus: 20,
      iterations: 20,
      maxDuration: '10s',
    },
  },
  thresholds: {
    // Collect all status codes for empirical analysis
    http_req_duration: ['p(95)<2000'],
  },
};

// Target Flash Sale Product ID (Configured via env or default 99999)
const TARGET_PRODUCT_ID = __ENV.PRODUCT_ID ? parseInt(__ENV.PRODUCT_ID) : 99999;
const API_URL = __ENV.API_URL || 'http://localhost:8000/api/v1/orders/checkout';

export default function () {
  const payload = JSON.stringify({
    user_id: 3000 + __VU,
    items: [
      {
        product_id: TARGET_PRODUCT_ID,
        quantity: 1,
      },
    ],
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    timeout: '5s',
  };

  const res = http.post(API_URL, payload, params);

  check(res, {
    '201 Order Created': (r) => r.status === 201,
    '400/409 Out of Stock': (r) => r.status === 400 || r.status === 409,
    '500 Server Error': (r) => r.status >= 500,
  });
}
