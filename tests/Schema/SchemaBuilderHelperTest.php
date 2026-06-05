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

describe('SchemaBuilder Helper Methods', function () {
    it('uses dropColumn helper method', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        }));

        await(schema()->dropColumn('users', 'email'));

        $exists = await(schema()->hasTable('users'));
        expect($exists)->toBeTruthy();
    });

    it('uses renameColumn helper method', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        await(schema()->renameColumn('users', 'name', 'full_name'));

        $exists = await(schema()->hasTable('users'));
        expect($exists)->toBeTruthy();
    });

    it('uses dropIndex helper method', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
        }));

        await(schema()->dropIndex('users', 'users_email_index'));

        $exists = await(schema()->hasTable('users'));
        expect($exists)->toBeTruthy();
    });

    it('uses dropForeign helper method', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        await(schema()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
        }));

        await(schema()->dropForeign('posts', 'posts_user_id_foreign'));

        $exists = await(schema()->hasTable('posts'));
        expect($exists)->toBeTruthy();
    });
});