<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile', 15)->nullable()->unique()->after('email');
            $table->timestamp('mobile_verified_at')->nullable()->after('mobile');

            $table->string('national_code', 10)->nullable()->after('mobile_verified_at');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('national_code');
            $table->date('birth_date')->nullable()->after('gender');
            $table->string('avatar')->nullable()->after('birth_date');

            // اطلاعات حقوقی (فاکتور رسمی)
            $table->boolean('is_legal')->default(false)->after('avatar');
            $table->string('company_name')->nullable()->after('is_legal');
            $table->string('economic_code')->nullable()->after('company_name');
            $table->string('registration_number')->nullable()->after('economic_code');

            $table->enum('status', ['active', 'inactive', 'banned'])
                ->default('active')->after('registration_number');

            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'mobile',
                'mobile_verified_at',
                'national_code',
                'gender',
                'birth_date',
                'avatar',
                'is_legal',
                'company_name',
                'economic_code',
                'registration_number',
                'status',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
