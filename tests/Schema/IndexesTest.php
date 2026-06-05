<?php

declare(strict_types=1);

use Hibla\Migrations\Schema\Blueprint;
use Hibla\QueryBuilder\DB;

use function Hibla\await;

beforeEach(function () {
    initializeSchema();
});

afterEach(function () {
    cleanupSchema();
});

describe('Indexes', function () {
    it('creates a table with primary key', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        $exists = await(schema()->hasTable('users'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with unique index', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('username');
            $table->unique('username');
        }));

        $exists = await(schema()->hasTable('users'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with regular index', function () {
        await(schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->string('slug');
            $table->index('slug');
        }));

        $exists = await(schema()->hasTable('posts'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with composite index', function () {
        await(schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('slug');
            $table->index(['user_id', 'slug']);
        }));

        $exists = await(schema()->hasTable('posts'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with fulltext index', function () {
        await(schema()->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->fullText();
            $table->fullText(['title', 'content']);
        }));

        $exists = await(schema()->hasTable('articles'));
        expect($exists)->toBeTruthy();
    });

    it('creates table with various spatial types', function () {
        if (driver() === 'pgsql') {
            try {
                await(DB::rawExecute('CREATE EXTENSION IF NOT EXISTS postgis'));
            } catch (\Throwable $e) {
                $this->markTestSkipped('PostGIS extension is not installed or available on this Postgres server.');
            }
        }

        await(schema()->create('geo_test', function (Blueprint $table) {
            $table->id();
            $table->point('location')->spatialIndex();
            $table->lineString('route')->nullable();
            $table->polygon('area')->spatialIndex();
            $table->geometry('shape')->nullable();
        }));

        $exists = await(schema()->hasTable('geo_test'));
        expect($exists)->toBeTruthy();

        await(schema()->dropIfExists('geo_test'));
    });

    it('creates table with SRID specification', function () {
        if (driver() === 'pgsql') {
            try {
                await(Hibla\QueryBuilder\DB::rawExecute('CREATE EXTENSION IF NOT EXISTS postgis'));
            } catch (\Throwable $e) {
                $this->markTestSkipped('PostGIS extension not available.');
            }
        }

        await(schema()->create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->point('location');
            $table->spatialIndex('location');
        }));

        $exists = await(schema()->hasTable('stores'));
        expect($exists)->toBeTruthy();

        await(schema()->dropIfExists('stores'));
    });

    it('creates a table with named indexes', function () {
        await(schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', 'custom_slug_index');
            $table->unique('slug', 'custom_slug_unique');
        }));

        $exists = await(schema()->hasTable('posts'));
        expect($exists)->toBeTruthy();
    });

    it('creates a table with index algorithms', function () {
        await(schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->index('slug', null, 'BTREE');
        }));

        $exists = await(schema()->hasTable('posts'));
        expect($exists)->toBeTruthy();
    });
});