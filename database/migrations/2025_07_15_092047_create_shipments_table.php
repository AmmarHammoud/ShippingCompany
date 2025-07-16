<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');

            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');

            $table->decimal('sender_lat', 10, 7);
            $table->decimal('sender_lng', 10, 7);

            $table->decimal('recipient_lat', 10, 7);
            $table->decimal('recipient_lng', 10, 7);

            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->string('recipient_location')->nullable();

            $table->string('shipment_type');
            $table->unsignedInteger('number_of_pieces');
            $table->decimal('weight', 8, 2);
            $table->decimal('delivery_price', 8, 2)->default(0);
            $table->decimal('total_amount', 8, 2);

            $table->string('invoice_number')->unique();
            $table->string('barcode')->unique();

            $table->string('qr_code_url')->nullable();
            // حالة الشحنة
            $table->enum('status', [
                'pending',
                'offered_to_drivers',
                'assigned',
                'in_transit',
                'delivered',
                'cancelled'
            ])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
