# Seed directory conventions

Files are executed in filename order within each directory.
To avoid conflicts across packages, use the following numeric prefix ranges:

- 000–099: davidrubydev/rez
- 100–199: davidrubydev/rez-platform
- 200+:    client repo

Run seeds via `bin/seed.php` or by calling `SeedDatabaseUseCase`
with an ordered array of seed directories.
