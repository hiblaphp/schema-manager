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

describe('Stress Tests', function () {
    it('handles rapid table creation and deletion', function () {
        for ($i = 1; $i <= 5; $i++) {
            $tableName = "test_table_{$i}";

            await(schema()->create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name');
            }));

            $exists = await(schema()->hasTable($tableName));
            expect($exists)->toBeTruthy();

            await(schema()->drop($tableName));

            $exists = await(schema()->hasTable($tableName));
            expect($exists)->toBeFalsy();
        }
    });

    it('handles multiple alterations in sequence', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        }));

        await(schema()->table('users', function (Blueprint $table) {
            $table->string('email')->nullable();
        }));

        await(schema()->table('users', function (Blueprint $table) {
            $table->index('email');
        }));

        await(schema()->table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        }));

        await(schema()->table('users', function (Blueprint $table) {
            $table->integer('age')->default(0);
        }));

        $exists = await(schema()->hasTable('users'));
        expect($exists)->toBeTruthy();
    });
});
