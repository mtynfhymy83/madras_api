<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
Schema::create('factor_detail', function (Blueprint $table) {

// 1. id
$table->id();

// 2. factor_id
$table->integer('factor_id')
->comment('شناسه فاکتور');

// 3. book_id
$table->integer('book_id')
->comment('شناسه کتاب');

// 4. price
$table->integer('price')
->comment('قیمت');

// 5. discount
$table->integer('discount')
->comment('قیمت با تخفیف');
});
}

public function down(): void
{
Schema::dropIfExists('factor_detail');
}
};
