<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVerificationCodeColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function(Blueprint $table)
        {
            $table->string('email_verification_code',50)->nullable()->after('status');
            $table->tinyInteger('is_email_verified')->nullable()->after('email_verification_code');
            $table->string('reset_password_otp',6)->nullable()->after('is_email_verified');
            $table->dateTime('reset_password_expires_at')->nullable()->after('reset_password_otp');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
