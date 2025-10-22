# Simple a very solid self-learning AI pipeline inside your Symfony app.

# Camoo AI - based on Symfony framework

This project is a fullstack Symfony application that demonstrates how to build a self-learning AI pipeline using Symfony 7.x, PHP 8.4, Docker, and various other tools and libraries. 
The application includes features such as AI model training, and data management.
Feel free to explore the codebase and adapt it to your own needs! We hope you find it useful and inspiring. All contributions are welcome.


## How to run the project
### Prerequisites
- Docker
- Docker Compose
- PHP 8.4
- Symfony 7.x
- Symfony CLI (optional, but recommended)
- Composer (optional, but recommended)
- PHPUnit


### Steps to run the project
1. Clone the repository

```bash
# Clone the repository
git clone htts://github.com/camoo/camoo-ai
cd camoo-ai

# Install dependencies
docker-compose exec camoo-ai composer install
# Or if you have composer installed locally
composer install

# Use make to install dependencies
make install

```

# Bring up the project

```bash
docker-compose up -d --build
# Or if you have make installed
make up
```

# Bring down the project

```bash
docker-compose down
# Or if you have make installed
make down
```


# Start Websocket server

```bash
# 8086 is the port number, you can change it as needed
docker-compose exec camoo-ai php bin/console app:websocket:serve 8086

OR on production use nohup
php bin/console app:websocket:serve > /dev/null 2>&1 &

# stop the websocket server
pkill -f "php bin/console app:websocket:serve"

```

# Test Websocket connection
```bash
npx wscat -c ws://localhost:8383/ws

# start sending messages
{"message":"Hello, AI!", "context": {}}
``` 

# Test AI via CLI Command
```bash
# log into the container
docker-compose exec camoo-ai bash
# run the AI command
php bin/console app:chat
# start typing your messages
tell me a joke
```

# Access the application
- Frontend: http://localhost:8383/ai-test.html
- Code Coverage: http://localhost:8383/coverage/
- PHPMyAdmin: http://localhost:8484/
  - Login: `root`
  - Password: `root`

# Access the terminal

```bash
docker-compose exec camoo-ai bash
# Or if you have make installed
make shell
```

# log files

```bash
# Application logs
docker-compose exec camoo-ai tail -f var/log/dev.log
```

# Run migration

```bash
docker-compose exec camoo-ai php bin/console doctrine:migrations:migrate --no-interaction
# Or if you have make installed
make migrate


# For test environment
docker-compose exec camoo-ai php bin/console doctrine:migrations:migrate --env=test --no-interaction
# Or if you have make installed
make migrate-test

# Optionally, you can also run the fixtures to populate the database with initial data
docker-compose exec camoo-ai php bin/console cache:clear --env=test
# Or if you have make installed
make fixtures

```

# Run Unit tests

```bash
docker-compose exec camoo-ai composer test

# Or if you have phpunit installed locally
composer test

# Or if you have make installed
make test
```

# Run PHP Cs Fixer / Linter

```bash
docker-compose exec camoo-ai composer lint

# Or if you have php-cs-fixer installed locally
composer lint
# Or if you have make installed
make lint
```

# Run Deptrac analysis

```bash
docker-compose exec camoo-ai php vendor/bin/deptrac analyse --report-uncovered

# Or if you have deptrac installed locally
composer analyse

# Or if you have make installed
make analyse
```

