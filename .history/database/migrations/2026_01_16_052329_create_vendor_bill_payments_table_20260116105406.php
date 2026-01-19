use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vendor_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('purchase_clients')->onDelete('cascade');
            $table->foreignId('bill_id')->constrained('purchase_bills')->onDelete('cascade');
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->date('paid_on')->default(DB::raw('CURRENT_DATE'));
            $table->string('payment_mode', 50)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('vendor_bill_payments');
    }
};