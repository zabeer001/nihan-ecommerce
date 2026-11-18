<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->string('normalized_phone')->nullable()->index());
        Schema::table('orders', fn (Blueprint $table) => $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete());
        DB::transaction(function () {
            foreach (DB::table('customers')->orderBy('id')->cursor() as $customer) {
                DB::table('customers')->where('id', $customer->id)->update(['normalized_phone' => $this->phone($customer->phone)]);
            }
            // Use the latest order's details when creating a previously unsaved customer.
            foreach (DB::table('orders')->orderByDesc('created_at')->orderByDesc('id')->cursor() as $order) {
                $phone = $this->phone($order->phone);
                if ($phone === '') continue;
                $id = DB::table('customers')->where('normalized_phone', $phone)->orderBy('id')->value('id');
                if (! $id) {
                    $id = DB::table('customers')->insertGetId([
                        'name' => $order->customer_name, 'phone' => $order->phone,
                        'normalized_phone' => $phone, 'email' => $order->email, 'address' => $order->address,
                        'created_at' => $order->created_at, 'updated_at' => $order->created_at,
                    ]);
                }
                DB::table('orders')->where('id', $order->id)->update(['customer_id' => $id]);
            }
        });
    }

    private function phone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($digits, '00880')) $digits = substr($digits, 2);
        if (preg_match('/^8801[3-9][0-9]{8}$/', $digits)) $digits = '0'.substr($digits, 3);
        return $digits !== '' ? $digits : mb_strtolower(trim($phone));
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['normalized_phone']);
            $table->dropColumn('normalized_phone');
        });
    }
};
