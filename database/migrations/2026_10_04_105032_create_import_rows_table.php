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
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('status', 10);
            $table->unsignedInteger('flags')->default(0);
            $table->string('external_id')->nullable();
            $table->string('created_at_raw')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('source')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('product')->nullable();
            $table->string('budget_uah')->nullable();
            $table->string('lead_status')->nullable();
            $table->string('manager')->nullable();
            $table->string('comment')->nullable();
            $table->string('next_contact_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->index(['import_id', 'row_number']);
            $table->index(['import_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
