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
        Schema::create('expense_recurrences', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('price', 10, 2);
            $table->foreignId('category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->enum('frequency', ['monthly', 'yearly']);
            $table->tinyInteger('due_day')->unsigned()->comment('Day of month (1-31)');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->date('last_payment_date')->nullable()->comment('Date of last payment (actual payment day)');
            $table->date('next_payment_date')->nullable()->comment('Next due date; updated on confirm payment and on create');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('category_id');
            $table->index('frequency');
            $table->index('is_active');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('next_payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_recurrences');
    }
};
