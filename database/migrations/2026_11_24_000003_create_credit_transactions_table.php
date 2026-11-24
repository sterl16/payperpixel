<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // credits ledger, we only ever add rows. balance = sum of amount_nanos
    //   purchase  +  credits bought
    //   hold      -  estimate set aside when they submit
    //   release   +  hold given back when it finishes, pass or fail
    //   charge    -  what it actually cost, only on success
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->bigInteger('amount_nanos');
            $table->timestamps();

            // only one hold, release and charge per generation
            $table->unique(['generation_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
