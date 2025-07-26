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
        Schema::create('ratings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('shipment_id')->constrained()->cascadeOnDelete()->casacadeOnUpdate();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete()->casacadeOnUpdate(); 
        $table->Float('rating')->comment('1-5');
        $table->text('comment')->nullable();
        $table->timestamps();
        
        // Ensure one rating per client per shipment
        $table->unique(['shipment_id', 'user_id']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
