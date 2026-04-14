# استخدام نسخة PHP حديثة مع مخدم Apache
FROM php:8.2-apache

# تثبيت الحزم الأساسية التي يحتاجها Laravel
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libpq-dev \
    zip \
    unzip \
    git

# تثبيت إضافات PHP للتعامل مع قواعد البيانات والصور
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql gd

# تفعيل ميزة إعادة توجيه الروابط في Apache
RUN a2enmod rewrite

# تحديد مجلد العمل داخل السيرفر
WORKDIR /var/www/html

# نسخ جميع ملفات المشروع إلى السيرفر
COPY . .

# تثبيت بيئة Composer لتحميل حزم Laravel
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# إنشاء اختصار مجلد الصور الخاص بـ Laravel
RUN php artisan storage:link

# إعطاء الصلاحيات اللازمة لملفات التخزين لتجنب أخطاء السيرفر
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# توجيه المخدم ليقرأ من مجلد public (وهي خطوة أمان أساسية في Laravel)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# فتح المنفذ 80 للإنترنت
EXPOSE 80