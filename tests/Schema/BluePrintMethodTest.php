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

describe('Blueprint Methods', function () {
    it('gets blueprint properties correctly', function () {
        $blueprint = new Blueprint('test_table');

        expect($blueprint->getTable())->toBe('test_table');
        expect($blueprint->getEngine())->toBe('InnoDB');
        expect($blueprint->getCharset())->toBe('utf8mb4');
        expect($blueprint->getCollation())->toBe('utf8mb4_unicode_ci');
    });

    it('sets blueprint engine correctly', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->engine('MyISAM');
        }));

        $exists = await(schema()->hasTable('users'));
        expect($exists)->toBeTruthy();
    });

    it('sets blueprint charset and collation correctly', function () {
        await(schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->charset('utf8');
            $table->collation('utf8_general_ci');
        }));

        $exists = await(schema()->hasTable('users'));
        expect($exists)->toBeTruthy();
    });
});
