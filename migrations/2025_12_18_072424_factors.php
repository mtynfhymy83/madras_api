<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
Schema::create('factors', function (Blueprint $table) {

// 1. id

$table->integer('id')->nullable();

// 2. user_id
$table->integer('user_id')->nullable()
->comment('شماره کاربر');

// 3. status (tinyint در MySQL → smallint در Postgres)
$table->smallInteger('status')->nullable()
->comment('وضعیت پرداخت');

// 4. state
$table->string('state', 1000)->nullable()
->comment('پیام متنی وضعیت سفارش');

// 5. cprice
$table->integer('cprice')->nullable()
->comment('قیمت بدون تخفیف');

// 6. price
$table->integer('price')->nullable()
->comment('قیمت کل قابل پرداخت');

// 7. discount
$table->smallInteger('discount')->default(0)
->comment('مقدار تخفیف لحاظ شده');

// 8. discount_id
$table->integer('discount_id')->nullable()
->comment('شناسه کد تخفیف');

// 9. paid
$table->integer('paid')->default(0)
->comment('مبلغ پرداخت شده');

// 10. ref_id
$table->string('ref_id', 255)->nullable()
->comment('شماره پیگیری برگشتی از بانک');

// 11. cdate (timestamp یونیکس)
$table->integer('cdate')->nullable()
->comment('تاریخ ایجاد صورتحساب');

// 12. pdate (timestamp یونیکس)
$table->integer('pdate')->nullable()
->comment('تاریخ پرداخت صورتحساب');

// 13. owner
$table->integer('owner')
->comment('مالک');

// 14. section
$table->string('section', 255)
->comment('بخش');

// 15. data_id
$table->string('data_id', 255)
->comment('شناسه داده');
});
}

public function down(): void
{
Schema::dropIfExists('factors');
}
};
