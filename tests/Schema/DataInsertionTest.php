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

describe('Data Insertion and Verification', function () {
    it('creates table and inserts data', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->integer('age')->default(0);
        }));

        await(DB::rawExecute(
            'INSERT INTO users (name, email, age) VALUES (?, ?, ?)',
            ['John Doe', 'john@example.com', 30]
        ));

        $user = await(DB::rawFirst('SELECT * FROM users WHERE email = ?', ['john@example.com']));

        expect($user)->not->toBeNull();
        expect($user->name)->toBe('John Doe');
        expect($user->email)->toBe('john@example.com');
        expect((int) $user->age)->toBe(30);
    });

    it('respects default values', function () {
        await(schema()->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0.00);
            $table->integer('stock')->default(0);
            $table->boolean('active')->default(true);
        }));

        await(DB::rawExecute(
            'INSERT INTO products (name) VALUES (?)',
            ['Test Product']
        ));

        $product = await(DB::rawFirst('SELECT * FROM products WHERE name = ?', ['Test Product']));

        expect($product)->not->toBeNull();
        expect((float) $product->price)->toBe(0.00);
        expect((int) $product->stock)->toBe(0);
        expect((int) $product->active)->toBe(1);

        await(schema()->dropIfExists('products'));
    });

    it('respects nullable constraints', function () {
        await(schema()->create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('bio')->nullable();
            $table->string('website')->nullable();
        }));

        await(DB::rawExecute(
            'INSERT INTO profiles (bio) VALUES (?)',
            [null]
        ));

        $profile = await(DB::rawFirst('SELECT * FROM profiles ORDER BY id DESC LIMIT 1', []));

        expect($profile)->not->toBeNull();
        expect($profile->bio)->toBeNull();
        expect($profile->website)->toBeNull();

        await(schema()->dropIfExists('profiles'));
    });

    it('enforces unique constraints', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
        }));

        await(DB::rawExecute(
            'INSERT INTO users (email) VALUES (?)',
            ['test@example.com']
        ));

        expect(function () {
            await(DB::rawExecute(
                'INSERT INTO users (email) VALUES (?)',
                ['test@example.com']
            ));
        })->toThrow(Exception::class);
    });

    it('enforces foreign key constraints', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        await(schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        }));

        await(DB::rawExecute(
            'INSERT INTO users (name) VALUES (?)',
            ['John Doe']
        ));

        $userId = await(DB::rawValue('SELECT id FROM users WHERE name = ?', ['John Doe']));

        await(DB::rawExecute(
            'INSERT INTO posts (user_id, title) VALUES (?, ?)',
            [$userId, 'Test Post']
        ));

        $post = await(DB::rawFirst('SELECT * FROM posts WHERE title = ?', ['Test Post']));
        expect($post)->not->toBeNull();
        expect((int) $post->user_id)->toBe((int) $userId);

        expect(function () {
            await(DB::rawExecute(
                'INSERT INTO posts (user_id, title) VALUES (?, ?)',
                [99999, 'Invalid Post']
            ));
        })->toThrow(Exception::class);
    });

    it('cascades deletes correctly', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        await(schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
        }));

        await(DB::rawExecute(
            'INSERT INTO users (name) VALUES (?)',
            ['Jane Doe']
        ));

        $userId = await(DB::rawValue('SELECT id FROM users WHERE name = ?', ['Jane Doe']));

        await(DB::rawExecute(
            'INSERT INTO posts (user_id, title) VALUES (?, ?)',
            [$userId, 'Post 1']
        ));

        await(DB::rawExecute(
            'INSERT INTO posts (user_id, title) VALUES (?, ?)',
            [$userId, 'Post 2']
        ));

        $postCount = await(DB::rawValue('SELECT COUNT(*) FROM posts WHERE user_id = ?', [$userId]));
        expect((int) $postCount)->toBe(2);

        await(DB::rawExecute('DELETE FROM users WHERE id = ?', [$userId]));

        $postCount = await(DB::rawValue('SELECT COUNT(*) FROM posts WHERE user_id = ?', [$userId]));
        expect((int) $postCount)->toBe(0);
    });
});