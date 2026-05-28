# INSA POS v3 — Master Directive

Single source of truth for core + optional modules. Twelve modules, no overlap.

---

## 1. System Architecture

**Target:** Android POS runs 100% offline on SQLite. Cloud is used only for login, license validation, and sync push/pull.

| Layer | Role |
|-------|------|
| WebView | UI only |
| Local POS Engine (`posengine/`) | Business logic |
| NanoHTTPD (`PosLocalServer`) | Local API |
| Sync Engine | Background worker |

**Hierarchy**

```
Company → Branch → Device → POS Session → Sales / Inventory / Shifts
```

**Mandatory entities:** Company, Branch, Device, POS Session, Product, Inventory Batch, Stock Movement, Sale, Sale Item, Shift, Customer, Settings.

---

## 2. Database Migrations

| Table | Key columns |
|-------|-------------|
| `companies` | id, name, status |
| `branches` | id, company_id, name, status |
| `devices` | id, branch_id, device_name, device_fingerprint, status |
| `pos_terminal_sessions` | id, device_id, branch_id, user_id, started_at, ended_at |
| `inventory_batches` | id, product_id, branch_id, quantity, expiry_date, cost_price, updated_at, synced_at |
| `stock_movements` | id, batch_id, product_id, movement_type, quantity, created_at, synced_at |
| `pos_sales` | local_id, branch_id, total, payment_type, created_at, synced_at |
| `pos_sale_items` | id, sale_local_id, product_id, quantity, price |
| `shifts` | id, user_id, opened_at, closed_at, synced_at |
| `sync_queue` | id, type, payload, created_at, retry_count *(device SQLite only)* |

Migrations must be **idempotent** (`hasTable` / `hasColumn` guards).

---

## 3. Seeders

**`CompanyBranchDeviceSeeder`**

- Company: **GEBS**
- Branch: **INSAPOS**
- Attach two existing devices to INSAPOS (fingerprints from `pos_terminal_sessions`)

---

## 4. Android POS Engine

```
posengine/
    PosEngine.kt
    PosCart.kt
    PosSaleProcessor.kt
    PosInventoryManager.kt
    PosShiftManager.kt
    PosReceiptGenerator.kt
    PromotionEngine.kt      # optional stub until promotions ship
    LoyaltyEngine.kt        # optional stub until device loyalty ships
    FefoDeduction.kt
```

**Responsibilities:** cart, sale creation, FEFO deduction, shift management, receipt generation, promotions, loyalty, sync queue.

---

## 5. Android SQLite

**Entities (Room or equivalent tables):** Product, Inventory Batch, Stock Movement, Pos Sale, Pos Sale Item, Shift, Sync Queue, Customer, Setting.

**DAOs:** Product, Inventory, Sale, Shift, Movement, Sync Queue, Customer, Settings.

---

## 6. Sync Engine

**Push:** sales, sale_items, inventory_batches, stock_movements, shifts, expiry_alerts.

**Pull:** products, categories, customers, inventory_batches, expiry_alerts, settings.

**Conflict types:** `price_mismatch`, `inventory_mismatch`, `expiry_mismatch`.

**Components:** `SyncEngine.kt`, `SyncPayloadBuilder.kt`, `LocalSyncMerger.kt`, `SyncConflictResolver.kt`.

---

## 7. JS Bridge (`INSAPOS`)

| Function | Purpose |
|----------|---------|
| `getLocalProducts()` | Catalog from SQLite |
| `getLocalInventory()` | Stock / batches |
| `getLocalCustomers()` | Member cache |
| `createLocalSale()` | Offline checkout |
| `openLocalShift()` / `closeLocalShift()` | Shift lifecycle |
| `getLocalShiftStatus()` | Active shift |
| `getLocalReceipt()` | Receipt payload |
| `triggerLocalSync()` | Force sync |
| `getSyncStatus()` | Queue / unsynced counts |
| `getDeviceFingerprint()` | Device identity |

---

## 8. Local Server (`PosLocalServer`)

| Endpoint | Method |
|----------|--------|
| `/local/products` | GET |
| `/local/inventory` | GET |
| `/local/customers` | GET |
| `/local/sale` | POST |
| `/local/shift/open` | POST |
| `/local/shift/close` | POST |
| `/local/receipt` | GET |
| `/local/sync/status` | GET |

Port **18182** (INSAPOS v3).

---

## 9. Laravel Backend

**Controllers:** Company, Branch, Device, License (validate), Session (terminal), Sync, Settings, Loyalty (update).

**Routes**

```
super-admin/companies
super-admin/branches
super-admin/devices

api/pos/license/validate
api/pos/session/start
api/pos/session/end
api/pos/sync/push
api/pos/sync/pull
api/pos/settings
api/pos/loyalty/update
```

**Models:** Company, Branch, Device, PosTerminalSession, PosSale, PosSaleItem, InventoryBatch, StockMovement, Shift, Customer, Setting.

---

## 10. Super Admin Panel

**Modules:** Companies, Branches, Devices, Licenses.

**Views:** `companies/index|create|edit`, `branches/index|create`, `devices/index|create`.

---

## 11. Optional Enterprise Modules

Not required for offline POS engine v1.

| Module | Scope |
|--------|--------|
| Forecasting | Daily rate, days left, reorder point, suggested order |
| Expiry dashboard | Expiring soon, expired, slow moving |
| Batch management | List, edit, adjust |
| Owner dashboards | Company / branch / device / sales overview |
| Analytics | Hourly heatmap, top cashiers, device performance |
| Promotions | Buy 2 get 1, high-value discount |
| Loyalty | Points earn / redeem on device |
| Crash logging | ErrorLogger, CrashReporter |

---

## 12. Deliverables

- This directive (`docs/INSAPOS_V3_MASTER_DIRECTIVE.md`)
- Implementation status (`docs/INSAPOS_V3_IMPLEMENTATION_STATUS.md`)
- Audit matrix vs `deploy/insa`
- Critical gap fixes only (modules 1–10); optional modules deferred unless trivial
