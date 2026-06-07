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

describe('Column Helper Methods', function () {
    it('uses foreignId helper correctly', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        await(schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
        }));

        $exists = await(schema()->hasTable('posts'));
        expect($exists)->toBeTruthy();
    });

    it('creates timestamps helper correctly', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        }));

        await(DB::table('users')->insert([
            'name' => 'Test User',
        ]));

        $user = await(DB::rawFirst('SELECT * FROM users WHERE name = ?', ['Test User']));

        expect($user->created_at)->not->toBeNull();
        expect($user->updated_at)->not->toBeNull();
    });

    it('creates softDeletes helper correctly', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->softDeletes();
        }));

        await(DB::table('users')->insert([
            'name' => 'Deleted User',
        ]));

        $user = await(DB::rawFirst('SELECT * FROM users WHERE name = ?', ['Deleted User']));

        expect($user->deleted_at)->toBeNull();
    });
});
