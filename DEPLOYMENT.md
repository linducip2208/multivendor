# Deployment Guide — MultiVendor E-Commerce

## Requirements
- PHP 8.3+
- MySQL 8.0+
- Composer 2.x
- Node.js 18+ (for frontend build)
- Nginx or Apache

## Quick Deploy

```bash
# 1. Clone & install
cd /var/www
git clone [repo-url] multivendor
cd multivendor

# 2. Install dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# 3. Environment
cp .env.example .env
php artisan key:generate

# Edit .env:
# - DB_DATABASE, DB_USERNAME, DB_PASSWORD
# - APP_URL=https://domainanda.com
# - MAIL_* settings

# 4. Database
mysql -u root -p -e "CREATE DATABASE multivendor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --force
php artisan db:seed --force

# 5. Permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 6. Storage symlink
php artisan storage:link

# 7. Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Queue worker + Scheduler (via Supervisor)
cp deploy/supervisor.conf /etc/supervisor/conf.d/multivendor.conf
supervisorctl reread
supervisorctl update
supervisorctl start multivendor-worker:*
supervisorctl start multivendor-scheduler

# 9. Nginx
cp deploy/nginx.conf /etc/nginx/sites-available/multivendor
ln -s /etc/nginx/sites-available/multivendor /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

## Post-Deploy Setup

1. **Create Admin**: Login at `/admin` — seeder creates `admin@multivendor.test / password`
2. **Payment Gateway**: Admin → Integrasi → Tambah Provider → Pilih preset → Isi API key
3. **Shipping**: Admin → Integrasi → Tambah Provider → Pilih preset → Isi API key
4. **AI Analytics**: Admin → Integrasi → Tambah Provider → Tipe AI → Pilih preset → Isi API key
5. **Submit Sitemap**: Buka `https://domainanda.com/sitemap.xml` — submit ke Google Search Console

## Cron Jobs (if not using Supervisor scheduler)

```
* * * * * cd /var/www/multivendor && php artisan schedule:run >> /dev/null 2>&1
```

## IndexNow

Service auto-submits new URLs to Bing, Yandex, Seznam, Naver.
- Key file: `public/indexnow-key.txt` (auto-generated)
- Command: `php artisan seo:indexnow`
- Scheduler: daily at 02:45

## Security

- All API keys encrypted at rest (payment, shipping, AI)
- CSRF protection enabled
- Input validation on all forms
- SQL injection prevention via Eloquent ORM
- XSS protection via Blade auto-escaping
