# Тестовое задание
#### Статус: выполнено

### Развертка проекта
1) Разверните данное laravel приложение стандартным образом. Вам понадобится php:8.0.2, MariaDB или MySQL. 
2) Выполните миграции.
3) Протестируйте выполнение консольной команды:
```shell
php artisan customers-migration-from-csv migration='task/random.csv' errors='task/migration-errors.xlsx' --debug
```
