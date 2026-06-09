# Hibla Schema Manager

**Asynchronous migrations, seeders, and programmatic schema definition for PHP 8.4+.**

> **Note:** This repository provides the CLI tooling and schema builder for the [Hibla Database Ecosystem](https://github.com/hiblaphp/database). 
> For complete, comprehensive documentation covering all CLI commands, Blueprint definitions, and Query Builder features, please visit the main **[hiblaphp/database meta-package](https://github.com/hiblaphp/database)**.

## Overview

`hiblaphp/schema-manager` is a standalone database lifecycle management toolkit. It equips any PHP application or microframework with Laravel-style migrations, programmatic blueprints, asynchronous database seeders, and advanced production safeguards like "Safe Mode" and native Schema Dumping (Squashing).

## Installation

Install the package via Composer. *(This automatically installs the required `hiblaphp/query-builder` dependency).*

```bash
composer require hiblaphp/schema-manager
```

Run the initialization command to auto-scaffold your configuration files:

```bash
# Places configs in a /config directory for instant auto-discovery
./vendor/bin/hibla-db init --dir=config
```

## Quick Start

### 1. Create a Migration
Generate your first migration using the CLI. The command automatically detects table creation intents based on the name:

```bash
./vendor/bin/hibla-db make:migration create_users_table
```

This generates a safe, anonymous class in your `database/migrations` folder:

```php
<?php

use Hibla\Migrations\Schema\Blueprint;
use Hibla\Migrations\Schema\Migration;
use function Hibla\await;

return new class extends Migration {
    public function up(): void {
        await($this->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        }));
    }

    public function down(): void {
        await($this->dropIfExists('users'));
    }
};
```

### 2. Run the Migrations
Apply your schema to the database safely:

```bash
./vendor/bin/hibla-db migrate
```

### 3. Create a Seeder
Generate a database seeder file:

```bash
./vendor/bin/hibla-db make:seeder UserSeeder
```

Seed your data using highly optimized, asynchronous queries:

```php
<?php

use Hibla\Migrations\Schema\Seeder;
use function Hibla\await;

return new class extends Seeder {
    public function run(): void {
        await($this->db('users')->insertBatch([
            ['name' => 'Alice', 'email' => 'alice@test.com'],
            ['name' => 'Bob', 'email' => 'bob@test.com'],
        ]));
    }
};
```

Run your seeders:

```bash
./vendor/bin/hibla-db db:seed
```

## Testing & Development

Because the Schema Manager tests native table alterations, foreign key bindings, and schema state dumping, the test suite requires real databases to run against. A `docker-compose.yml` file is provided to quickly spin up the necessary environments.

### 1. Start the Database Containers
Start the MySQL 8 and PostgreSQL 15 containers:
```bash
docker compose up -d
```

### 2. Run the Test Suite
The repository uses [Pest PHP](https://pestphp.com/) for testing. 

To run the tests against MySQL:
```bash
composer test:mysql
```

To run the tests against PostgreSQL:
```bash
composer test:pgsql
```

To run the tests against both databases sequentially:
```bash
composer test:all
```

## Documentation

For full documentation on available Blueprint column types, multi-connection migrations, production Safe Mode, and Schema Squashing (`schema:dump`), please read the **[Comprehensive Hibla Documentation](https://github.com/hiblaphp/database#schema-manager)**.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).