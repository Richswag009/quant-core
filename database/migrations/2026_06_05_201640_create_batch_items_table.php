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
        Schema::create('batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string("beneficiary_name")->nullable();
            $table->string("account_number")->nullable();
            $table->string("bank_code")->nullable();
            $table->double("amount")->nullable();
            $table->string("narration")->nullable();
            $table->string("external_reference")->nullable();
            $table->enum('status', ['PENDING', 'VALID', 'INVALID', 'PENDING_APPROVAL', 'APPROVED', 'POSTING', 'POSTED', 'REJECTED', "FAILED"])->default('PENDING');
            $table->text("validation_error")->nullable();
            $table->text("posting_error")->nullable();
            $table->timestamp("posted_at")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_items');
    }
};
