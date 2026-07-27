<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename legacy tenant staff roles to the solo-v1 matrix:
 * tenant_admin / web_developer → admin
 * content_editor → staff
 * (doctor is new and has no legacy equivalent)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'tenant_admin')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'web_developer')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'content_editor')->update(['role' => 'staff']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'admin')->update(['role' => 'tenant_admin']);
        DB::table('users')->where('role', 'staff')->update(['role' => 'content_editor']);
    }
};
