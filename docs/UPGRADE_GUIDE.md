# Upgrade Guide

## Database Tables Missing

Due to incorrect mounting of a volume in pre v1.3.0 docker-compose files, changes need to be made in order to upgrade to a new self hosted Spacepad version.

Edit docker compose volumes section (for both app and scheduler), and change the database volume to a file path mount:

```yml
volumes:
    - storage_data:/var/www/html/storage
    - ./database.sqlite:/var/www/html/storage/database.sqlite
    - .env:/var/www/html/.env:ro
```

Then, execute the following commands to make your database writeable for the application:

```bash
sudo chmod -R 775 database.sqlite
sudo chown -R 33:33 database.sqlite
```

After having made these changes, execute the following commands:
```bash
docker compose down
docker compose up -d
```

The migrations should now be able to be updated by the image, thus errors regarding the missing of database tables should be fixed.