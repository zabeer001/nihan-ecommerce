<?php

namespace App\Services\Orders;

use App\Models\Customer;
use App\Models\Order;

class OrderCustomer
{
    // Called inside the order transaction, after its stock checks succeed.
    public function resolve(Order $order): Customer
    {
        $phone = Customer::normalizePhone($order->phone);
        $customer = Customer::where('normalized_phone', $phone)->orderBy('id')->lockForUpdate()->first();
        return $customer ?? Customer::create([
            'name' => $order->customer_name,
            'phone' => $order->phone,
            'email' => $order->email,
            'address' => $order->address,
        ]);
    }
}
