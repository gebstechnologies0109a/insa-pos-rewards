<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qualification_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->string('month');
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['member_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qualification_progress');
    }
};
