FROM php:8.2-apache
# ติดตั้ง Extension สำหรับต่อฐานข้อมูล
RUN docker-php-ext-install mysqli pdo pdo_mysql
COPY . /var/www/html/