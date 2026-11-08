A **delivery challan** is basically a **delivery proof document**.

It says:

> “We delivered these goods, in this quantity, to this customer, on this date.”

It is **not the invoice** and **not the payment receipt**.

Think of it like this:

```text
Order
  ↓
Goods are delivered
  ↓
Delivery Challan is created
  ↓
Customer receives and checks goods
```

Example:

Customer ordered:

```text
10,000 zippers
```

But today you deliver only:

```text
3,000 zippers
```

Then you create a challan:

```text
Challan No: CH-001
Customer: ABC Garments
Order No: ORD-101
Item: Nylon Zipper
Ordered Qty: 10,000
This Delivery Qty: 3,000
Previously Delivered: 0
Remaining Qty: 7,000
Date: 5 July 2026
```

Later you deliver another 4,000:

```text
Challan No: CH-002
This Delivery Qty: 4,000
Previously Delivered: 3,000
Remaining Qty: 3,000
```

Then final 3,000:

```text
Challan No: CH-003
This Delivery Qty: 3,000
Previously Delivered: 7,000
Remaining Qty: 0
```

So the challan helps track:

```text
How much was ordered
How much was delivered before
How much is delivered now
How much is still remaining
```

That is exactly why your proposal needs **partial delivery tracking**. 

The easiest mental model is:

```text
Order = what customer requested

Delivery Challan = what you physically delivered

Invoice = how much money customer must pay

Payment = how much money customer actually paid
```

So for your system:

```text
Customer orders 10,000 pcs
        ↓
Order created
        ↓
Deliver 3,000 pcs
        ↓
Challan created
        ↓
Deliver 4,000 pcs
        ↓
Another challan created
        ↓
Deliver final 3,000 pcs
        ↓
Final challan created
```

That is the delivery challan.
