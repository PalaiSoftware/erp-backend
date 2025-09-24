
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('mobile');
            $table->string('country');
            $table->string('password');   // store hashed password
            $table->integer('rid');

            // Client fields
            $table->string('client_name');
            $table->string('client_address')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('gst_no')->nullable();
            $table->string('pan')->nullable();

            $table->boolean('approved')->default(false); // admin flag
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
