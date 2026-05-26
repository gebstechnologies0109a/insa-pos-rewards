<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('store_name')->default('');
            $table->string('contact_number')->default('');
            $table->text('store_address')->nullable();
            $table->text('invoice_header')->nullable();
            $table->text('invoice_footer')->nullable();
            $table->string('tax_id')->default('');
            $table->timestamps();

            $table->unique('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_settings');
    }
};
