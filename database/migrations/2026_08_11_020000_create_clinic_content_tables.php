<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('image_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_published', 'sort_order']);
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('image_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_published', 'sort_order']);
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->string('public_slug')->nullable()->after('registration_number');
            $table->string('public_title')->nullable()->after('public_slug');
            $table->text('bio')->nullable()->after('public_title');
            $table->string('photo_url')->nullable()->after('bio');
            $table->boolean('show_on_website')->default(false)->after('photo_url');
            $table->unsignedInteger('website_sort_order')->default(0)->after('show_on_website');

            $table->unique(['tenant_id', 'public_slug']);
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'public_slug']);
            $table->dropColumn([
                'public_slug',
                'public_title',
                'bio',
                'photo_url',
                'show_on_website',
                'website_sort_order',
            ]);
        });

        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('departments');
    }
};
