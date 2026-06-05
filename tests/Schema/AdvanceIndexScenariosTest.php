<?php

declare(strict_types=1);

use Hibla\Migrations\Schema\Blueprint;

use function Hibla\await;

beforeEach(function () {
    initializeSchema();
});

afterEach(function () {
    cleanupSchema();
});

describe('Advanced Index Scenarios', function () {
    it('creates multiple indexes on same column', function () {
        await(schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->text('content');
            $table->index('title', 'custom_title_index');
        }));

        $exists = await(schema()->hasTable('posts'));
        expect($exists)->toBeTruthy();
    });

    it('creates composite unique index', function () {
        await(schema()->create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unique(['user_id', 'role_id']);
        }));

        $exists = await(schema()->hasTable('user_roles'));
        expect($exists)->toBeTruthy();

        schema()->dropIfExists('user_roles')->wait();
    });

    it('creates indexes with custom names', function () {
        await(schema()->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index('idx_product_sku');
            $table->string('name')->unique('unq_product_name');
        }));

        $exists = await(schema()->hasTable('products'));
        expect($exists)->toBeTruthy();

        schema()->dropIfExists('products')->wait();
    });
});
