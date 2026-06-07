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

describe('Table Operations', function () {
    it('drops a table', function () {
        await(schema()->create('temp_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        await(schema()->drop('temp_table'));

        $exists = await(schema()->hasTable('temp_table'));
        expect($exists)->toBeFalsy();
    });

    it('drops a table if exists', function () {
        await(schema()->dropIfExists('nonexistent_table'));

        $exists = await(schema()->hasTable('nonexistent_table'));
        expect($exists)->toBeFalsy();
    });

    it('renames a table', function () {
        await(schema()->create('old_name', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        await(schema()->rename('old_name', 'new_name'));

        $oldExists = await(schema()->hasTable('old_name'));
        $newExists = await(schema()->hasTable('new_name'));

        expect($oldExists)->toBeFalsy();
        expect($newExists)->toBeTruthy();

        await(schema()->dropIfExists('new_name'));
    });

    it('checks if table exists', function () {
        $exists = await(schema()->hasTable('nonexistent'));
        expect($exists)->toBeFalsy();

        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
        }));

        $exists = await(schema()->hasTable('users'));
        expect($exists)->toBeTruthy();
    });
});
