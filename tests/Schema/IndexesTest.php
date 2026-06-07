<?php

declare(strict_types=1);

use Hibla\SchemaManager\Schema\Blueprint;
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
            } catch (Throwable $e) {
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
                await(DB::rawExecute('CREATE EXTENSION IF NOT EXISTS postgis'));
            } catch (Throwable $e) {
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

    it('creates table with vector column and index (PostgreSQL only)', function () {
        if (driver() !== 'pgsql') {
            $this->markTestSkipped('Vector columns and indexes are only supported on PostgreSQL.');
        }

        try {
            await(DB::rawExecute('CREATE EXTENSION IF NOT EXISTS vector'));
        } catch (Throwable $e) {
            $this->markTestSkipped('The pgvector extension is not installed or available on this PostgreSQL server.');
        }

        await(schema()->create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->vector('embedding', 1536);
            $table->vectorIndex('embedding', 'embeddings_vector_index', 'COSINE');
        }));

        $exists = await(schema()->hasTable('embeddings'));
        expect($exists)->toBeTruthy();

        await(schema()->dropIfExists('embeddings'));
    });

    it('creates a table with a raw index expression', function () {
        await(schema()->create('stats', function (Blueprint $table) {
            $table->id();
            $table->integer('score');

            $expression = driver() === 'pgsql'
                ? 'CONSTRAINT idx_score_raw UNIQUE (score)'
                : 'UNIQUE KEY idx_score_raw (score)';

            $table->rawIndex($expression, 'idx_score_raw');
        }));

        $exists = await(schema()->hasTable('stats'));
        expect($exists)->toBeTruthy();
    });
});
