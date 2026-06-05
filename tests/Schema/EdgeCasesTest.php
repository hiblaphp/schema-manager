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

describe('Edge Cases', function () {
    it('handles empty table creation', function () {
        await(schema()->create('empty_table', function (Blueprint $table) {
            $table->id();
        }));

        $exists = await(schema()->hasTable('empty_table'));
        expect($exists)->toBeTruthy();

        await(schema()->dropIfExists('empty_table'));
    });

    it('handles table with many columns', function () {
        await(schema()->create('wide_table', function (Blueprint $table) {
            $table->id();
            for ($i = 1; $i <= 20; $i++) {
                $table->string("column_{$i}")->nullable();
            }
        }));

        $exists = await(schema()->hasTable('wide_table'));
        expect($exists)->toBeTruthy();

        await(schema()->dropIfExists('wide_table'));
    });

    it('handles dropping non-existent table gracefully', function () {
        $result = await(schema()->dropIfExists('this_table_does_not_exist'));
        expect($result)->not->toThrow(Exception::class);
    });
});