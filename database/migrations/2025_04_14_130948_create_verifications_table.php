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
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->date('date_of_birth');

            // Address information
            $table->string('street_address');
            $table->string('province');
            $table->string('city');
            $table->string('postal_code', 10);

            // ID verification
            $table->string('id_type');
            $table->string('id_number');
            $table->string('id_front')->nullable();
            $table->string('id_back')->nullable();
            $table->string('selfie')->nullable();

            // Income verification
            $table->string('employment_status');
            $table->decimal('monthly_income', 15, 2);
            $table->string('income_proof')->nullable();

            // Business information (optional)
            $table->boolean('is_business')->default(false);
            $table->string('business_name')->nullable();
            $table->string('business_registration_number')->nullable();
            $table->string('business_type')->nullable();
            $table->string('business_registration')->nullable();
            $table->string('business_permit')->nullable();

            // Verification status
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};
