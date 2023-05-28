# Установка Docker и docker-compose

## Установка в Ubuntu Linux

Официальные инструкции:

- Docker CE для Ubuntu: https://docs.docker.com/engine/install/ubuntu/#install-docker-ce
- Docker Compose V1: https://docs.docker.com/compose/install/other/

Рекомендуется добавить текущего пользователя в группы docker и www-data, чтобы:

1. Использовать команду `docker` без `sudo`
2. Предоставлять доступ к файлам проекта пользователю `www-data` в docker-контейнере

Добавление в группы:

```bash
sudo usermod -a -G docker $USER
sudo usermod -a -G www-data $USER
```

Эффект наступит после Sign Out / Sign In либо перезагрузки.

## Использование

```bash
# Собрать образ (выполняется один раз)
docker-compose build

# Запустить контейнеры в фоновом режиме
docker-compose up -d

# Проверить состояние контейнеров
docker-compose ps

# Смотреть логи контейнеров (Ctrl+C для остановки)
docker-compose logs -f

# Открыть сессию bash в контейнере
docker/bin/tree-of-life-app-bash

# Остановить контейнеры
docker-compose down --remove-orphans
```

Чистка данных:

```bash
# УДАЛИТЬ ВСЕ ДАННЫЕ локальной базы данных (находятся в docker volume)
docker volume rm treeoflife_tree_of_life_db_data
```
