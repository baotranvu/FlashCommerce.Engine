# ⚡ FlashCommerce High-Throughput Benchmarking & Stress Testing Harness

This directory houses the out-of-process, high-concurrency load testing suite powered by **k6**.

---

## 📁 Directory Structure

```
benchmarks/
├── README.md                      # Operational guide & runbooks
├── lib/
│   └── html-reporter.js           # Zero-dependency terminal & HTML report generator
├── reports/                       # (.gitignored) Generated HTML & JSON reports
│   └── .gitkeep
└── scenarios/                     # Domain-scoped load scenarios
    └── ordering/
        └── flash_sale_checkout.js # 20-VU concurrent flash sale checkout burst
```

---

## 🚀 Quick Start

### 1. Prerequisite
Ensure `k6` is installed:
```powershell
k6 version
# Or specify exact path: & "C:\Program Files\k6\k6.exe" version
```

### 2. Run Flash Sale Concurrency Test
```powershell
& "C:\Program Files\k6\k6.exe" run benchmarks/scenarios/ordering/flash_sale_checkout.js
```

### 3. Open Standalone HTML Report in Browser
```powershell
start benchmarks/reports/report.html
```

---

## 🛡️ Empirical Verification Protocol

| Metric | Baseline (Without Redis Lua) | Fixed (With Redis Lua Hold) |
| :--- | :--- | :--- |
| **HTTP 201 Created** | 13–20 requests (Lost Update Anomaly) | **Exactly 5 requests** (Hold Acquired) |
| **HTTP 409 Conflict** | 0 requests | **Exactly 15 requests** (Out of Stock) |
| **Physical Stock in DB** | Reported as 0 | Physical stock intact, Redis stock = 0 |
| **Total Orders in DB** | **13–20 orders (OVERSELL)** | **Exactly 5 orders (ZERO OVERSELL)** |
