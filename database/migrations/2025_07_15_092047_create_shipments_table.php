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


            $table->foreignId('center_from_id')->nullable()->constrained('centers')->nullOnDelete();
            $table->foreignId('center_to_id')->nullable()->constrained('centers')->nullOnDelete();

            // السائق الذي يستلم من العميل
            $table->foreignId('pickup_driver_id')->nullable()->constrained('users')->nullOnDelete();

            // السائق الذي يوصّل للمستلم
            $table->foreignId('delivery_driver_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('trailer_id')->nullable()->constrained('trailers')->nullOnDelete();

            $table->decimal('sender_lat', 10, 7);
            $table->decimal('sender_lng', 10, 7);

            $table->decimal('recipient_lat', 10, 7);
            $table->decimal('recipient_lng', 10, 7);
            $table->foreignId('recipient_id')->constrained('users')->onDelete('cascade');

            $table->string('recipient_location')->nullable();

            $table->string('shipment_type');
            $table->unsignedInteger('number_of_pieces');
            $table->decimal('weight', 8, 2);
            $table->decimal('size')->nullable();//new attribute
            $table->decimal('delivery_price', 20, 2)->default(0);
            $table->decimal('product_value', 10, 2);
            $table->decimal('total_amount', 8, 2);

            $table->string('invoice_number')->unique();
            $table->string('barcode')->unique();

            $table->string('qr_code_url')->nullable();
            $table->enum('status', [
                'pending',               // تم إنشاؤها
                'offered_pickup_driver', // بانتظار قبول أول سائق
                'picked_up',             // السائق الأول أخذها
                'in_transit_between_centers', // بين مركزين
                'assigned_to_trailer',
                'arrived_at_destination_center', // وصلت للمركز الثاني
                'offered_delivery_driver', // بانتظار قبول سائق التسليم
                'out_for_delivery',      // خرجت للمستلم
                'delivered',             // تم التسليم
                'cancelled',
            ])->default('pending');
            $table->timestamp('delivered_at')->nullable();
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
