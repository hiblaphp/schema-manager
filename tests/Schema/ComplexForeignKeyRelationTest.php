<?php

declare(strict_types=1);

use Hibla\SchemaManager\Schema\Blueprint;

use function Hibla\await;

beforeEach(function () {
    initializeSchema();
});

afterEach(function () {
    cleanupSchema();
});

describe('Complex Foreign Key Relationships', function () {
    it('creates multiple foreign keys on single table', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        await(schema()->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        await(schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title');
        }));

        $exists = await(schema()->hasTable('posts'));
        expect($exists)->toBeTruthy();
    });

    it('creates self-referencing foreign key', function () {
        await(schema()->create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        }));

        $exists = await(schema()->hasTable('categories'));
        expect($exists)->toBeTruthy();
    });

    it('creates composite foreign key', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
        }));

        await(schema()->create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('bio');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        }));

        $exists = await(schema()->hasTable('user_profiles'));
        expect($exists)->toBeTruthy();
    });
});
