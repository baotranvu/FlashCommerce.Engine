# 🏛️ FLASHCOMMERCE.ENGINE: AGENT OPERATIONAL & ARCHITECTURAL PROTOCOL

This rule file specifies the autonomous engineering boundaries, toolchain allowlist, and architectural invariants for **FlashCommerce.Engine**.

---

## ⚡ 1. DIRECT COMMAND EXECUTION TOPOLOGY (ZERO-INTERRUPTION INVARIANT)
To leverage Antigravity's fine-grained prefix-matching and ensure uninterrupted Deep Work:
- **Direct Binary Invocation**: Always invoke operational binaries directly (`docker exec`, `php`, `composer`, `k6`, `git`).
- **No Outer Subshell Wrapping**: Do not wrap standard operations inside dynamic `powershell -Command "..."` strings that defeat host prefix caching.

```bash
# Whitelisted Direct Command Patterns:
# PHP / Laravel / Testing:
docker exec ecom-api composer test:all
docker exec ecom-api php artisan test
docker exec ecom-api php artisan flash-sale:release-expired-holds
docker exec ecom-api php artisan flash-sale:reconcile-inventory --product_id=1
k6 run benchmarks/scenarios/ordering/flash_sale_checkout.js

# Git Safe Commands (Direct & Path-Targeted):
git status
git add
git add -A
git add .
git commit -m "..."
git diff
git diff --cached
git log
git branch
git checkout
git switch
git restore
git rm -r --cached
git fetch
git pull
git push
git -C ...
```

---

## 🏛️ 2. CORE ARCHITECTURAL INVARIANTS
1. **Zero-Oversell Invariant**: Two-Phase Inventory Hold via atomic Redis Lua CAS (`hold_inventory_atomic.lua`). Physical MySQL stock is only decremented during settlement (`WHERE physical_stock >= :qty`).
2. **Ghost-Payment Defense**: If a payment webhook arrives for an `EXPIRED` order, the settlement engine unconditionally refuses MySQL decrement, sets status to `REFUNDED`, and persists a record in `order_refunds`.
3. **Forensic Reconciliation & Separation of Duties**: `flash-sale:reconcile-inventory` is Dry-Run by default. Applying remediation (`--repair`) strictly requires `--ticket` and `--approved-by`.
4. **Clean Architecture Boundary**: Controllers must contain zero `try-catch` blocks; domain exceptions must self-render Problem Details (RFC 7807).
5. **Git Hygiene**: Pre-commit Guardian hook enforces atomic commits $\le 400$ LOC.
