Order Management System
Project Summary

This project is an order management system designed for a business that receives customer orders, prepares quotations or proforma invoices, manages ordered items, handles deliveries through delivery challans, creates final invoices, and records payments.

The main business flow starts with a customer. A customer can place multiple orders over time. Each order contains the details of what the customer requested and can include multiple order items.

An order can have one Proforma Invoice (PI). The PI is a preliminary commercial document that shows the expected products or services, quantities, prices, and total amount before the final invoice is issued.

An order can have multiple delivery challans. This is useful when the full order is not delivered at once. For example, part of an order may be delivered today and the remaining items may be delivered later. Each delivery can therefore have its own delivery challan.

A final invoice can include one or multiple orders. For example, if a customer has Order A and Order B, both orders can be combined into a single invoice when required. Each order belongs to only one final invoice.

An invoice can have multiple payments. This allows the customer to pay the invoice in parts instead of paying the full amount at once.

Main Business Relationships

A customer can have many orders.

An order belongs to one customer.

An order can have many order items.

Each order item belongs to one order.

An order can have one Proforma Invoice (PI).

Each Proforma Invoice belongs to one order.

An order can have many delivery challans.

Each delivery challan belongs to one order.

An invoice can include many orders.

Each order can belong to one final invoice.

An invoice can have many payments.

Each payment belongs to one invoice.

Example Business Flow

A customer contacts the company and places an order.

The company creates the order and adds all requested items to it.

A Proforma Invoice may then be prepared for that order so the customer can review the expected amount before final billing.

When products are delivered, one or more delivery challans can be created. Multiple challans may be necessary when the order is delivered in several parts.

When billing is required, the company creates a final invoice. The invoice may contain a single order or combine multiple orders from the customer.

The customer can then make one full payment or several partial payments against the invoice.

This structure allows the business to follow the complete lifecycle from customer order to delivery, invoicing, and payment



The system follows the complete business lifecycle of an order:

Customer → Order → Order Items → Proforma Invoice → Delivery Challan → Final Invoice → Payment

The main purpose of the system is to keep all business records connected so that a company can easily understand:

Who placed an order
What items were ordered
What price was proposed
What items were delivered
Whether the delivery was partial or complete
Which orders were included in the final invoice
How much the customer paid
How much money is still due
Customer

A customer is the person or company that purchases products or services from the business.

A customer can place multiple orders over time.

For example:

ABC Garments Ltd. may place one order in January, another order in February, and another order in March.

Therefore:

A Customer can have many Orders.

Each Order belongs to one Customer.

Example:

Customer: ABC Garments Ltd.

Orders:

Order ORD-001
Order ORD-002
Order ORD-003
Order

An Order represents a customer's actual request for products or services.

The order is the central part of the system.

An order may contain information such as:

Customer
Order number
Order date
Order status
Delivery status
Total amount
Notes
Ordered items

For example:

ABC Garments Ltd. places an order for:

100 T-Shirts
50 Jackets
200 Caps

The business creates one Order record.

That order then contains multiple Order Items.

An Order can have:

Many Order Items
One Proforma Invoice
Many Delivery Challans
One Final Invoice
Order Item

An Order Item represents an individual product, service, or line inside an order.

One order can contain many items.

For example:

Order ORD-001

Items:

T-Shirt — Quantity: 100
Jacket — Quantity: 50
Cap — Quantity: 200

Each of these is a separate Order Item.

Therefore:

One Order can have many Order Items.

Each Order Item belongs to one Order.

Order Items may contain information such as:

Item name
Description
Quantity
Unit
Unit price
Total price

Example:

T-Shirt

Quantity: 100

Unit Price: 500 BDT

Total: 50,000 BDT

Proforma Invoice (PI)

A Proforma Invoice, commonly called a PI, is a preliminary invoice.

It is created before the final commercial invoice.

A PI is mainly used to show the customer the expected cost of an order before final billing.

It can contain:

Customer information
Order information
Item details
Quantities
Unit prices
Total amount
Taxes
Discounts
Payment terms
Delivery terms

A PI is not the final proof that the customer has been officially billed.

It is more like a formal price proposal or expected invoice based on the order.

For example:

A customer places an order for:

100 T-Shirts
50 Jackets

The company creates a PI showing:

T-Shirts: 50,000 BDT

Jackets: 75,000 BDT

Total: 125,000 BDT

The customer can review the PI before the final invoice is created.

In this project:

One Order can have one Proforma Invoice.

Each Proforma Invoice belongs to one Order.

Example flow:

Order ORD-001

↓

PI PI-001

This means the PI is directly connected to that particular order.

Difference Between Order and Proforma Invoice

An Order represents what the customer requested.

A Proforma Invoice represents the proposed financial value of that order.

Example:

The customer says:

"I want 100 T-Shirts."

This becomes an Order.

The company says:

"100 T-Shirts will cost 50,000 BDT."

This becomes the Proforma Invoice.

Therefore:

Order = Customer request

PI = Proposed price and financial details

Delivery Challan

A Delivery Challan is a document used when goods are physically delivered to the customer.

It records what items were delivered.

A Delivery Challan may contain:

Customer information
Order reference
Delivery date
Delivered items
Delivered quantities
Delivery address
Receiver information
Notes

The main purpose of a Delivery Challan is to prove and track the movement of goods from the seller to the customer.

A Delivery Challan is not the same as an Invoice.

An Invoice is mainly a financial document.

A Delivery Challan is mainly a delivery document.

For example:

A customer orders:

100 T-Shirts
50 Jackets

The company may not deliver everything on the same day.

First delivery:

60 T-Shirts
20 Jackets

A Delivery Challan is created for this delivery.

Second delivery:

40 T-Shirts
30 Jackets

Another Delivery Challan is created.

Therefore, one Order can have multiple Delivery Challans.

Example:

Order ORD-001

Delivery Challan DC-001

60 T-Shirts
20 Jackets

Delivery Challan DC-002

40 T-Shirts
30 Jackets

After both deliveries, the entire order is completed.

Therefore:

One Order can have many Delivery Challans.

Each Delivery Challan belongs to one Order.

Why an Order Can Have Multiple Delivery Challans

An order may be delivered in multiple stages.

This is called partial delivery.

For example:

The customer orders 1,000 units.

The company currently has only 400 units ready.

The company delivers:

First delivery:

400 units

Second delivery:

300 units

Third delivery:

300 units

Three separate Delivery Challans can be created.

This allows the company to track exactly:

What was ordered
What was already delivered
What quantity is still pending
Difference Between Delivery Challan and Invoice

A Delivery Challan records the delivery of goods.

An Invoice records the amount the customer must pay.

Example:

Delivery Challan:

"Today we delivered 100 T-Shirts."

Invoice:

"The customer must pay 50,000 BDT for these goods."

Therefore:

Delivery Challan = Delivery record

Invoice = Billing record

Invoice

An Invoice is the final billing document issued to the customer.

It represents the actual amount the customer is expected to pay.

An Invoice may contain:

Invoice number
Customer information
Included orders
Item details
Total amount
Discount
Tax
Paid amount
Due amount
Payment terms
Invoice date
Due date

In this system, one Invoice can include multiple Orders.

For example:

A customer has:

Order ORD-001

Order ORD-002

Order ORD-003

The business may decide to create one invoice containing:

ORD-001
ORD-002

Another invoice may later contain:

ORD-003

Therefore:

One Invoice can contain many Orders.

Each Order belongs to one final Invoice.

Example:

Invoice INV-001

Included Orders:

ORD-001
ORD-002

This means the customer receives one combined invoice for both orders.

Why One Invoice Can Include Multiple Orders

In many businesses, a customer may place several orders during a period.

Instead of creating a separate invoice for every order, the company may combine multiple orders into one invoice.

For example:

Monday:

Customer places Order A.

Wednesday:

Customer places Order B.

Friday:

Customer places Order C.

At the end of the week, the company creates one Invoice containing:

Order A
Order B
Order C

This makes billing easier for both the company and the customer.

Difference Between Proforma Invoice and Final Invoice

A Proforma Invoice is created before final billing.

A Final Invoice is the actual payment request.

Example:

Proforma Invoice:

"Based on your order, the expected total is 100,000 BDT."

Final Invoice:

"Your final payable amount is 98,000 BDT."

The final amount may be different because of:

Quantity changes
Discounts
Taxes
Delivery changes
Price adjustments
Customer negotiations

Therefore:

PI = Preliminary financial document

Invoice = Final billing document

A PI usually helps the customer understand the expected cost.

An Invoice creates the actual payment obligation.

Payment

A Payment represents money received from the customer against an Invoice.

A customer may pay the entire invoice at once or make several partial payments.

For example:

Invoice Total:

100,000 BDT

First Payment:

30,000 BDT

Second Payment:

40,000 BDT

Third Payment:

30,000 BDT

Therefore:

One Invoice can have many Payments.

Each Payment belongs to one Invoice.

The system can calculate:

Invoice Total: 100,000 BDT

Paid Amount: 70,000 BDT

Due Amount: 30,000 BDT

Why an Invoice Can Have Multiple Payments

Customers do not always pay the full invoice amount at once.

They may pay:

Advance payment
Partial payment
Installment payment
Final payment

Example:

Invoice INV-001

Total: 200,000 BDT

Payment 1:

50,000 BDT

Payment 2:

100,000 BDT

Payment 3:

50,000 BDT

After the third payment, the invoice becomes fully paid.

Complete Business Relationship Summary
Customer and Orders

One Customer can have many Orders.

Each Order belongs to one Customer.

Example:

Customer ABC Ltd.

Order 1
Order 2
Order 3
Order and Order Items

One Order can have many Order Items.

Each Order Item belongs to one Order.

Example:

Order ORD-001

T-Shirt
Jacket
Cap
Order and Proforma Invoice

One Order can have one Proforma Invoice.

Each Proforma Invoice belongs to one Order.

Example:

Order ORD-001

↓

PI PI-001

Order and Delivery Challans

One Order can have many Delivery Challans.

Each Delivery Challan belongs to one Order.

Example:

Order ORD-001

↓

Delivery Challan DC-001

Delivery Challan DC-002

Delivery Challan DC-003

Invoice and Orders

One Invoice can include many Orders.

Each Order belongs to one final Invoice.

Example:

Invoice INV-001

↓

Order ORD-001

Order ORD-002

Order ORD-003

Invoice and Payments

One Invoice can have many Payments.

Each Payment belongs to one Invoice.

Example:

Invoice INV-001

↓

Payment 1

Payment 2

Payment 3

Complete Business Lifecycle

The complete business lifecycle can be understood through the following example.

A customer named ABC Garments Ltd. contacts the company.

The customer wants:

1,000 T-Shirts
500 Jackets

The company creates:

Order ORD-001

The order contains two Order Items:

T-Shirt — 1,000 units
Jacket — 500 units

The company then prepares:

PI PI-001

The PI shows the expected total price of the order.

The customer reviews and accepts the proposed amount.

The company starts preparing the goods.

The entire quantity is not ready at the same time.

First Delivery:

500 T-Shirts
200 Jackets

The company creates:

Delivery Challan DC-001

Second Delivery:

500 T-Shirts
300 Jackets

The company creates:

Delivery Challan DC-002

Now the complete order has been delivered.

The company later creates:

Invoice INV-001

The invoice includes:

Order ORD-001

The total invoice amount is:

500,000 BDT

The customer first pays:

200,000 BDT

Payment PAY-001 is recorded.

The customer later pays:

200,000 BDT

Payment PAY-002 is recorded.

Remaining Due:

100,000 BDT

Finally, the customer pays:

100,000 BDT

Payment PAY-003 is recorded.

Now the invoice is fully paid.

Another Example With Multiple Orders in One Invoice

Customer ABC Garments Ltd. places:

Order ORD-001

Total: 100,000 BDT

Later the customer places:

Order ORD-002

Total: 150,000 BDT

The company decides to combine both orders into one final invoice.

Invoice INV-001

Included Orders:

ORD-001
ORD-002

Invoice Total:

250,000 BDT

The customer makes:

Payment 1:

100,000 BDT

Payment 2:

150,000 BDT

The invoice is now fully paid.

Main Business Flow

The normal business flow is:

A Customer is created.
The Customer places an Order.
Order Items are added to the Order.
A Proforma Invoice may be prepared for the Order.
Goods are delivered through one or more Delivery Challans.
One or multiple Orders are included in a Final Invoice.
The Customer makes one or multiple Payments.
The Invoice becomes partially paid or fully paid.
Final Relationship Overview

Customer

↓

Many Orders

Each Order

↓

Many Order Items

Each Order

↓

One Proforma Invoice

Each Order

↓

Many Delivery Challans

One Invoice

↓

Many Orders

One Invoice

↓

Many Payments

Business Meaning in Simple Words

Customer means who buys from the company.

Order means what the customer requested.

Order Item means each individual item inside the order.

Proforma Invoice or PI means the expected or proposed price before final billing.

Delivery Challan means the record of what goods were physically delivered.

Invoice means the final amount the customer must pay.

Payment means the money received from the customer against the invoice.

Together, these records allow the business to follow the complete journey from the customer's initial order to final payment.# nihan-ecommerce
