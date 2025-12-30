<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::create('customer_types', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique(); // e.g., Retail, Wholesaler, Distributor
        $table->text('description')->nullable();
        $table->unsignedBigInteger('cid');
        $table->unsignedBigInteger('created_by'); // user id
        $table->tinyInteger('created_by_rid'); // 1=admin, 2=superuser
        $table->timestamps();

        $table->foreign('cid')->references('id')->on('clients')->onDelete('cascade');
    });
}
    public function down(): void
    {
        Schema::dropIfExists('customer_types');
    }
};
