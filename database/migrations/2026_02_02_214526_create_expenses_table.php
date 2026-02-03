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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->date('expense_date');
            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->boolean('is_recurring')->default(false);
            $table->foreignId('recurring_id')->nullable()->constrained('expense_recurrences')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('quantity')->nullable()->comment('For product expenses: quantity added to stock');
            $table->enum('status', ['paid', 'unpaid'])->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('expense_number');
            $table->index('title');
            $table->index('expense_date');
            $table->index('category_id');
            $table->index('recurring_id');
            $table->index('product_id');
            $table->index('status');
            $table->index('is_recurring');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
