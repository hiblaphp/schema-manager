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

describe('Table Creation', function () {
    it('creates a basic table', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        }));

        $exists = await(schema()->hasTable('users'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with various column types', function () {
        await(schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('content');
            $table->integer('views')->default(0);
            $table->boolean('is_published')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        }));

        $exists = await(schema()->hasTable('posts'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with auto-increment columns', function () {
        await(schema()->create('categories', function (Blueprint $table) {
            $table->id();
            $table->increments('legacy_id');
            $table->bigIncrements('big_id');
            $table->smallIncrements('small_id');
            $table->string('name');
        }));

        $exists = await(schema()->hasTable('categories'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with integer variations', function () {
        await(schema()->create('stats', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('tiny_num');
            $table->smallInteger('small_num');
            $table->mediumInteger('medium_num');
            $table->integer('regular_num');
            $table->bigInteger('big_num');
            $table->unsignedTinyInteger('unsigned_tiny');
            $table->unsignedBigInteger('unsigned_big');
        }));

        $exists = await(schema()->hasTable('stats'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with text variations', function () {
        await(schema()->create('documents', function (Blueprint $table) {
            $table->id();
            $table->text('short_text');
            $table->mediumText('medium_text');
            $table->longText('long_text');
            $table->string('title', 100);
        }));

        $exists = await(schema()->hasTable('documents'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with decimal variations', function () {
        await(schema()->create('financials', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 10, 2);
            $table->float('rate', 5, 2);
            $table->double('precise_value', 15, 8);
            $table->unsignedDecimal('positive_amount', 8, 2);
        }));

        $exists = await(schema()->hasTable('financials'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with date/time columns', function () {
        await(schema()->create('events', function (Blueprint $table) {
            $table->id();
            $table->date('event_date');
            $table->dateTime('event_datetime');
            $table->timestamp('event_timestamp');
            $table->timestamps();
        }));

        $exists = await(schema()->hasTable('events'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with enum column', function () {
        await(schema()->create('orders', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['pending', 'processing', 'completed', 'cancelled']);
            $table->timestamps();
        }));

        $exists = await(schema()->hasTable('orders'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with soft deletes', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        }));

        $exists = await(schema()->hasTable('users'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with comments', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('User full name');
            $table->string('email')->comment('User email address')->unique();
        }));

        $exists = await(schema()->hasTable('users'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with after positioning', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->after('name');
            $table->timestamps();
        }));

        $exists = await(schema()->hasTable('users'));
        expect($exists)->toBeTruthy();
    });
});