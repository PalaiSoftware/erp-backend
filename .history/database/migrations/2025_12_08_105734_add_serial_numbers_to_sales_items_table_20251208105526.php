// database/migrations/xxxx_add_serial_numbers_to_sales_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('sales_items', function (Blueprint $table) {
            $table->text('serial_numbers')->nullable()->after('gst');
            // Example: "SN001, SN002, SN003" or "IMEI12345"
        });
    }

    public function down()
    {
        Schema::table('sales_items', function (Blueprint $table) {
            $table->dropColumn('serial_numbers');
        });
    }
};