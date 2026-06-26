# Seed directory conventions

Files are executed in filename order within each directory.
To avoid conflicts across packages, use the following numeric prefix ranges:

- 000–099: davidrubydev/rez
- 100–199: davidrubydev/rez-platform
- 200+:    client repo

## Directory structure

```
database/seeds/
  schema/   — DDL only (CREATE TABLE IF NOT EXISTS). Always run.
  data/     — Sample INSERT data for development. Run with --fill only.
```

## Running seeds

Schema only (safe for any environment):
```bash
php bin/seed.php
```

Schema + sample data (development only):
```bash
php bin/seed.php --fill
```

Via Composer:
```bash
composer seed         # schema only
composer seed:fill    # schema + data
```

## Adding seeds from client repos

Pass additional seed directories to `SeedDatabaseUseCase`:

```php
$useCase->execute(new SeedDatabaseRequest([
    MysqlDatabaseSeeder::seedsPath(),   // rez schema
    MysqlDatabaseSeeder::dataPath(),    // rez sample data (optional)
    __DIR__ . '/seeds/schema',          // client schema
    __DIR__ . '/seeds/data',            // client sample data (optional)
]));
```
