<!-- ======================================================================= -->
<!-- FLASHCOMMERCE.ENGINE ENTERPRISE PULL REQUEST TEMPLATE                   -->
<!-- Dual-Audience: Human Reviewer & Automated CI/CD / AI Agent Verifier     -->
<!-- ======================================================================= -->

## 🎯 1. Summary & Business Context
- **Business Capability**: <!-- e.g. User registration with email OTP verification -->
- **Subsystem / Package**: `packages/` <!-- e.g. packages/Identity -->
- **Related Spec / Brainstorm**: `docs/03_specs/` <!-- e.g. BS-20261024-verification-architecture... -->

---

## 🏛️ 2. Architectural Invariants Enforced
- [ ] **Dependency Inversion**: Domain layer has zero framework or infrastructure imports.
- [ ] **Repository Isolation**: Persistence and database driver exceptions are translated to Domain Exceptions.
- [ ] **Zero Try-Catch in Controllers**: All domain errors bubble up to RFC 7807 global exception handler.
- [ ] **Concurrency & Idempotency**: Redis atomic primitives or MySQL unique constraints prevent double-action/race conditions.

---

## 🔬 3. Physical Resource & Latency Impact
| Physical Resource | Prior State | Proposed State | Architectural Justification |
| :--- | :--- | :--- | :--- |
| **Network Latency** | | | <!-- e.g. Redis in-memory cache lookup in 0.1ms vs MySQL query in 15ms --> |
| **RAM Footprint** | | | <!-- e.g. Redis key TTL 300s auto-eviction --> |
| **Database I/O** | | | <!-- e.g. Unique index scan on email column --> |

---

## 🧪 4. Empirical Dual Ground Truth (Verification Evidence)
> **MANDATORY**: Paste actual terminal execution output proving 100% Green test pass.

```bash
docker exec ecom-api composer test:all
```

<details>
<summary>📋 Click to expand test execution output</summary>

```text
<!-- Paste output from composer test:all here -->
```

</details>

---

## 🤖 5. CI/CD & AI Agent Automated Gate Checklist
- [ ] **PR Title**: Matches Conventional Commits regex `^(feat|fix|refactor|perf|test|docs|chore)\([a-z0-9_-]+\): .{1,72}$`.
- [ ] **Package Boundary**: Diff does not violate package isolation boundaries.
- [ ] **Test Coverage**: All Unit, Integration, Chaos, and Smoke tests exit with code 0.
- [ ] **RFC Compliance**: Error envelopes conform strictly to RFC 7807 Problem Details and RFC 6585.
