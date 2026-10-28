/**
 * FlashCommerce Standalone Offline HTML & Terminal Reporter for k6
 *
 * 100% Zero-dependency, offline-ready. Generates:
 * 1. Clean ANSI-colored ASCII summary for terminal stdout.
 * 2. Standalone Dark-Mode HTML report for offline browser viewing.
 */

export function generateHtmlReport(data) {
  const metrics = data.metrics || {};
  const duration = metrics.http_req_duration ? metrics.http_req_duration.values : {};
  const reqs = metrics.http_reqs ? metrics.http_reqs.values : { count: 0, rate: 0 };
  const checks = metrics.checks ? metrics.checks.values : { passes: 0, fails: 0, value: 0 };

  const passRate = checks.passes + checks.fails > 0
    ? ((checks.passes / (checks.passes + checks.fails)) * 100).toFixed(1)
    : '100.0';

  const checkItems = (data.root_group && data.root_group.checks) ? data.root_group.checks : [];

  let checksHtml = '';
  for (const c of checkItems) {
    const isPass = c.fails === 0;
    const badgeClass = isPass ? 'badge-pass' : 'badge-fail';
    const statusText = isPass ? 'PASSED' : 'VIOLATED';
    checksHtml += `
      <tr>
        <td style="font-weight: 500;">${c.name}</td>
        <td><span class="badge ${badgeClass}">${statusText}</span></td>
        <td>${c.passes}</td>
        <td>${c.fails}</td>
      </tr>
    `;
  }

  const generatedAt = new Date().toISOString();

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>FlashCommerce - Load Test Empirical Report</title>
  <style>
    :root {
      --bg: #0f172a;
      --card-bg: #1e293b;
      --border: #334155;
      --text: #f8fafc;
      --text-muted: #94a3b8;
      --emerald: #10b981;
      --crimson: #ef4444;
      --amber: #f59e0b;
      --blue: #38bdf8;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
      padding: 32px 24px;
      line-height: 1.5;
    }
    .container { max-width: 960px; margin: 0 auto; }
    .header { margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 16px; }
    .header h1 { font-size: 24px; color: var(--text); display: flex; align-items: center; gap: 10px; }
    .header .timestamp { color: var(--text-muted); font-size: 13px; margin-top: 4px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 16px;
    }
    .card .title { font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
    .card .value { font-size: 26px; font-weight: 700; margin-top: 6px; }
    .card.pass .value { color: var(--emerald); }
    .card.warn .value { color: var(--amber); }
    .card.blue .value { color: var(--blue); }
    .table-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 24px;
    }
    .table-card h2 { font-size: 16px; margin-bottom: 16px; color: var(--blue); }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); color: var(--text-muted); font-weight: 600; }
    td { padding: 10px 12px; border-bottom: 1px solid var(--border); }
    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.05em;
    }
    .badge-pass { background: rgba(16, 185, 129, 0.2); color: var(--emerald); }
    .badge-fail { background: rgba(239, 68, 68, 0.2); color: var(--crimson); }
    .latency-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; text-align: center; }
    .latency-box { background: rgba(15, 23, 42, 0.6); padding: 10px; border-radius: 6px; border: 1px solid var(--border); }
    .latency-box .lbl { font-size: 11px; color: var(--text-muted); text-transform: uppercase; }
    .latency-box .val { font-size: 16px; font-weight: 700; margin-top: 4px; color: var(--amber); }
    .footer { text-align: center; color: var(--text-muted); font-size: 12px; margin-top: 32px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>⚡ FlashCommerce Empirical Load Test Report</h1>
      <div class="timestamp">Generated: ${generatedAt} | Runtime: k6 High-Throughput Harness</div>
    </div>

    <div class="grid">
      <div class="card blue">
        <div class="title">Total HTTP Requests</div>
        <div class="value">${reqs.count || 0}</div>
      </div>
      <div class="card ${passRate >= 99 ? 'pass' : 'warn'}">
        <div class="title">Check Assertion Rate</div>
        <div class="value">${passRate}%</div>
      </div>
      <div class="card pass">
        <div class="title">Avg Latency</div>
        <div class="value">${duration.avg ? duration.avg.toFixed(1) : 0} ms</div>
      </div>
      <div class="card warn">
        <div class="title">p95 Latency</div>
        <div class="value">${duration['p(95)'] ? duration['p(95)'].toFixed(1) : 0} ms</div>
      </div>
    </div>

    <div class="table-card">
      <h2>🛡️ Business & Concurrency Assertions</h2>
      <table>
        <thead>
          <tr>
            <th>Assertion Check</th>
            <th>Status</th>
            <th>Passes</th>
            <th>Fails</th>
          </tr>
        </thead>
        <tbody>
          ${checksHtml || '<tr><td colspan="4">No explicit checks defined.</td></tr>'}
        </tbody>
      </table>
    </div>

    <div class="table-card">
      <h2>⏱️ Request Latency Distribution (ms)</h2>
      <div class="latency-grid">
        <div class="latency-box">
          <div class="lbl">Min</div>
          <div class="val">${duration.min ? duration.min.toFixed(1) : 0}</div>
        </div>
        <div class="latency-box">
          <div class="lbl">Avg</div>
          <div class="val">${duration.avg ? duration.avg.toFixed(1) : 0}</div>
        </div>
        <div class="latency-box">
          <div class="lbl">Median (p50)</div>
          <div class="val">${duration.med ? duration.med.toFixed(1) : 0}</div>
        </div>
        <div class="latency-box">
          <div class="lbl">p90</div>
          <div class="val">${duration['p(90)'] ? duration['p(90)'].toFixed(1) : 0}</div>
        </div>
        <div class="latency-box">
          <div class="lbl">p95</div>
          <div class="val">${duration['p(95)'] ? duration['p(95)'].toFixed(1) : 0}</div>
        </div>
        <div class="latency-box">
          <div class="lbl">Max</div>
          <div class="val">${duration.max ? duration.max.toFixed(1) : 0}</div>
        </div>
      </div>
    </div>

    <div class="footer">
      FlashCommerce.Engine • Zero-Oversell Empirical Verification • Ground Truth in MySQL & Redis
    </div>
  </div>
</body>
</html>`;
}

export function generateTerminalSummary(data) {
  const metrics = data.metrics || {};
  const duration = metrics.http_req_duration ? metrics.http_req_duration.values : {};
  const reqs = metrics.http_reqs ? metrics.http_reqs.values : { count: 0, rate: 0 };
  const checkItems = (data.root_group && data.root_group.checks) ? data.root_group.checks : [];

  let out = '\n' + '='.repeat(60) + '\n';
  out += '  ⚡ FLASHCOMMERCE CONCURRENCY TEST SUMMARY\n';
  out += '='.repeat(60) + '\n\n';

  out += `  Total Requests Fired : ${reqs.count} requests (${(reqs.rate || 0).toFixed(1)} req/s)\n`;
  out += `  Latency (p50 / p95)  : ${duration.med ? duration.med.toFixed(1) : 0}ms / ${duration['p(95)'] ? duration['p(95)'].toFixed(1) : 0}ms\n`;
  out += `  Min / Max Duration   : ${duration.min ? duration.min.toFixed(1) : 0}ms / ${duration.max ? duration.max.toFixed(1) : 0}ms\n\n`;

  out += '  Assertions & Status Checks:\n';
  for (const c of checkItems) {
    const symbol = c.fails === 0 ? '✓' : '✗';
    out += `    ${symbol} ${c.name.padEnd(30)} : ${c.passes} passed, ${c.fails} failed\n`;
  }

  out += '\n' + '-'.repeat(60) + '\n';
  out += '  📄 HTML Report saved to: benchmarks/reports/report.html\n';
  out += '  💡 Open in browser     : start benchmarks/reports/report.html\n';
  out += '='.repeat(60) + '\n';

  return out;
}
