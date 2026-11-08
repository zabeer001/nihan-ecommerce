# Order Show Modal Frontend Test Harness

This document explains how the frontend-only modal test was run for:

`resources/js/backend/pages/order/orderShow/OrderShowPage.jsx`

## Problem Summary

The issue was seen on the Order Show page, for example:

`/dashboard/order/1`

When using the raw material quick actions such as **Add Material** and especially **Purchase / Import**, Chrome could show:

`Aw, Snap! Something went wrong while displaying this webpage. Error code: SIGILL`

The backend was not the focus of this investigation. The problem area was the frontend behavior of the Order Show page and its modals.

## Main Frontend Problem Found

The biggest frontend problem was that the **Purchase / Import Raw Materials** modal stored its form state inside the global Order Show Zustand store:

`resources/js/backend/pages/order/orderShow/_store/useOrderShowStore.js`

Before the fix, that store contained modal-specific state and actions such as:

- `purchaseModalForm`
- `purchaseModalError`
- `purchaseModalSupplierSearch`
- `purchaseModalSelectedSupplier`
- `setError`
- `setSupplierSearch`
- `updateField`
- `updateItem`
- `addItem`
- `removeItem`
- `selectSupplier`
- `clearSupplierSelection`
- `resetPurchaseModal`

That meant a small action inside the modal could update the same global store used by the whole Order Show page. Examples:

- typing supplier mobile
- supplier search debounce
- selecting supplier
- changing purchase fields
- adding/removing material items
- changing material item fields
- closing/resetting the modal

The Order Show page is already large. It contains order summary, order items, raw materials, invoices, payments, delivery challans, and multiple detail/edit modals. Updating global page state from inside a modal created unnecessary page-wide render pressure.

This was risky because the modal did not need to control the whole page. It only needed local form state.

## Why This Could Cause the Browser Crash

The Chrome `SIGILL` screen is a browser renderer crash, not a normal React error message. A normal frontend exception usually shows a console error or a blank React section, not the whole browser tab crashing.

The likely frontend trigger was excessive rendering or unstable state updates while the Order Show page was already heavy.

The suspicious pattern was:

1. User opens **Purchase / Import** from the Order Show page.
2. Modal mounts and starts fetching raw materials/suppliers.
3. Supplier mobile debounce updates global store state.
4. Modal field components subscribe to the same global store.
5. Store updates can cause many order-show sections and modal children to re-evaluate.
6. Repeated updates during typing/searching/opening can put pressure on the browser tab.

So the main suspected crash cause was not an API validation issue. It was the frontend modal state being connected too broadly to the Order Show page state.

## Fixes Applied

### 1. Purchase / Import Modal State Was Moved Local

The **Purchase / Import Raw Materials** modal now owns its own React state with `useState`.

File:

`resources/js/backend/pages/order/orderShow/_components/AddRawMaterialPurchaseModal/AddRawMaterialPurchaseModal.jsx`

The modal now keeps these locally:

- form values
- supplier search text
- selected supplier
- modal error
- material item add/remove/update state

This means typing or changing fields inside the modal no longer updates the global Order Show store.

### 2. Purchase Modal Child Components Were Made Prop-Based

These components no longer subscribe directly to the global store:

- `PurchaseFields.jsx`
- `RawMaterialItemsFields.jsx`
- `SupplierFields.jsx`

They now receive data and update functions as props from the modal.

This keeps the render path small and predictable:

`AddRawMaterialPurchaseModal -> child fields`

instead of:

`global Order Show store -> whole page/subscribers -> modal fields`

### 3. Dead Modal State Was Removed from the Global Store

After moving the modal to local state, the old `purchaseModal*` state/actions were removed from:

`resources/js/backend/pages/order/orderShow/_store/useOrderShowStore.js`

The store now keeps only actual Order Show page data:

- order
- sections
- order items
- invoices
- payments
- delivery challans
- raw material purchases
- summary values

This prevents the old risky pattern from being reused accidentally.

### 4. Add Modals Now Reset on Close

Several add modals previously kept typed values when closed/cancelled. That could make repeated actions confusing and leave stale form state alive.

The following modals now reset form/error state when closed:

- Add Order Item
- Add Proforma Invoice
- Add Invoice
- Add Payment
- Add Delivery Challan
- Add Raw Material
- Add Supplier
- Purchase / Import Raw Materials

### 5. Supplier Modal Timeout Cleanup Was Added

`AddSupplierModal.jsx` used a delayed close:

`setTimeout(onClose, 350)`

The timeout is now stored in a ref and cleared on unmount/close. This prevents a delayed callback from firing after the modal has already disappeared.

### 6. Add Raw Material Requires a Name

The quick **Add Raw Material** modal now requires the material name field on the frontend.

This avoids submitting an empty material from the Order Show quick action.

## What Was Not Changed

The backend APIs were not changed for this fix.

The modal submit endpoints remain the same:

- order items
- proforma invoices
- invoices
- payments
- delivery challans
- raw materials
- suppliers
- raw material purchases

The test harness mocks those APIs only to verify the frontend behavior.

The test uses a temporary browser harness kept at the project root:

- `order-show-modal-harness.html`
- `order-show-modal-harness.jsx`

## What It Tests

The harness imports the real React modal components from the Order Show page and runs them in a local Vite page with mocked API responses.

It tests create flows for:

- Add Order Item
- Add Proforma Invoice
- Add Invoice
- Add Payment
- Add Delivery Challan
- Add Raw Material
- Add Supplier
- Purchase / Import Raw Materials

It tests update flows for:

- Edit Order Item
- Edit Proforma Invoice
- Edit Invoice
- Edit Delivery Challan
- Edit Raw Material Purchase

## How It Works

The harness:

1. Mounts each real modal component in React.
2. Mocks `window.fetch`, so no backend/database is required.
3. Fills the minimum required fields.
4. Clicks create/update buttons.
5. Records every request made by the modal.
6. Fails if the expected API request is not made.
7. Prints JSON into the page showing either `"status": "PASS"` or `"status": "FAIL"`.

## Commands Used

Start the Vite dev server:

```bash
npm run dev -- --host 127.0.0.1 --port 5173
```

Run the browser harness with Chromium:

```bash
/snap/bin/chromium --headless --disable-gpu --no-sandbox --dump-dom --virtual-time-budget=15000 http://127.0.0.1:5173/order-show-modal-harness.html
```

A passing run includes:

```json
{
  "status": "PASS"
}
```

After testing, stop Vite with `Ctrl+C`.

Run the production frontend build:

```bash
npm run build
```

## Expected Requests

The passing harness confirms these frontend requests are triggered:

- `POST /api/order-items`
- `POST /api/proforma-invoices`
- `POST /api/invoices`
- `POST /api/payments`
- `POST /api/delivery-challans`
- `POST /api/raw-materials`
- `POST /api/suppliers`
- `POST /api/raw-material-purchases`
- `PUT /api/order-items/{id}`
- `PUT /api/proforma-invoices/{id}`
- `PUT /api/invoices/{id}`
- `PUT /api/delivery-challans/{id}`
- `PUT /api/raw-material-purchases/{id}`

## Notes

- This is a frontend smoke test, not a backend integration test.
- It does not require MySQL or Laravel API responses.
- It verifies modal rendering, input handling, submit handling, and request payload creation.
- Keep the harness out of production routing. It is only loaded manually through Vite during local testing.
