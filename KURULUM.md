# 🚀 Rental Management System - Hızlı Kurulum Rehberi

## 📋 Gereksinimler

- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Apache/Nginx
- mod_rewrite aktif
- GD kütüphanesi (opsiyonel, QR kod için)

## 🔧 Kurulum Adımları

### 1. Dosyaları Yükleyin

Tüm dosyaları web sunucunuzun root dizinine yükleyin:

```bash
/var/www/html/yoursite/
```

### 2. Database Migration

MySQL'de yeni tabloları oluşturun:

```bash
mysql -u username -p database_name < migrations/rental_management_system.sql
```

**ÖNEMLİ:** Mevcut tablolarınız korunur, sadece yeni tablolar eklenir.

### 3. Dosya İzinleri

Gerekli klasörlere yazma izni verin:

```bash
chmod 755 /var/www/html/yoursite/assets/images
chmod 755 /var/www/html/yoursite/logs
```

### 4. Config Kontrolü

`config.php` dosyasında database bilgilerinizi kontrol edin:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tokatbet_site');
define('DB_USER', 'root');
define('DB_PASS', 'password');
```

### 5. Analytics API URL

`/api/analytics/track.php` dosyasında base URL'i ayarlayın (gerekirse).

### 6. Test Edin

Ana sayfaya gidin ve giriş yapın:
```
https://yoursite.com/
```

## 📍 Yeni Sayfalar

Kullanıcılar için:
- `/rental` - Kiralamalar listesi
- `/rental/manage/[ID]` - Yönetim dashboard'u
- `/rental/manage/[ID]/ibans` - İBAN yönetimi
- `/rental/manage/[ID]/wallets` - Kripto cüzdan
- `/rental/manage/[ID]/settings` - Ayarlar

API:
- `/api/analytics/track.php` - Analytics endpoint

## 🔑 Test Kullanıcıları

Database'inizde şu kullanıcılar var:

**Admin:**
- Username: `admin` / `codex`
- Password: (mevcut şifrelerinizi kullanın)

**Normal Kullanıcı:**
- Username: `kero`
- Password: (mevcut şifrenizi kullanın)

## 📊 Analytics Entegrasyonu

Kiralanan her site için analytics kodunu ekleyin:

1. Rental yönetim paneline girin
2. "Ayarlar" sekmesine gidin
3. "Analytics Entegrasyonu" bölümünden kodu kopyalayın
4. Kiralanan sitenin `<head>` bölümüne yapıştırın

**Kod örneği:**
```html
<!-- ScriptMarket Analytics -->
<script>
  window.ANALYTICS_API_URL = 'https://yoursite.com/api/analytics/track';
  window.RENTAL_ID = 3;
</script>
<script src="https://yoursite.com/assets/js/analytics-tracker.js"></script>
```

## 🧪 Test Senaryosu

1. **Rental Oluştur**: Admin olarak bir script kiralayın
2. **Aktif Et**: Rental'ı aktif duruma getirin
3. **Yönet Butonu**: Rental listesinde "Yönet" butonunu görün
4. **Dashboard**: Analytics dashboard'u açın
5. **İBAN Ekle**: En az 1 İBAN ekleyin
6. **Cüzdan Ekle**: USDT cüzdanı ekleyin
7. **Ayarları Yap**: Tawk.to ID ekleyin
8. **Analytics Test**: Analytics kodunu test sitesine ekleyin
9. **Veri Kontrol**: 5 dakika bekleyin ve dashboard'da veri görün

## 🛠️ Sorun Giderme

### 1. Analytics Veri Gelmiyor

- API endpoint'in erişilebilir olduğunu kontrol edin: `/api/analytics/track.php`
- CORS hatası varsa `track.php`'de `Access-Control-Allow-Origin` kontrol edin
- Browser console'da hata var mı bakın (F12)
- Rental ID'nin doğru olduğunu kontrol edin

### 2. İBAN Sıralaması Çalışmıyor

- `SortableJS` kütüphanesi yüklenmiş mi kontrol edin
- Browser console'da JavaScript hatası var mı bakın

### 3. Grafikler Görünmüyor

- `Chart.js` kütüphanesi yüklenmiş mi kontrol edin
- Database'de `rental_analytics_summary` tablosunda veri var mı kontrol edin

### 4. 404 Hatası

- `.htaccess` dosyası var mı kontrol edin
- `mod_rewrite` aktif mi kontrol edin
- Apache'de `AllowOverride All` ayarlı mı kontrol edin

### 5. Database Hatası

- Migration dosyası başarıyla çalıştırıldı mı kontrol edin
- Database kullanıcısının CREATE, ALTER yetkisi var mı kontrol edin

## 📈 İlk Veri Girişi

Sistemde ilk verileri görmek için:

```sql
-- Test verileri ekle
INSERT INTO rental_analytics_summary 
(rental_id, date, unique_visitors, total_pageviews, active_users_now, total_deposits_try)
VALUES 
(3, CURDATE(), 150, 450, 12, 5000.00),
(3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 120, 380, 0, 3500.00),
(3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 100, 320, 0, 2800.00);

-- Şehir verileri
INSERT INTO rental_analytics_by_city 
(rental_id, city, date, visitor_count, pageview_count, latitude, longitude)
VALUES 
(3, 'İstanbul', CURDATE(), 45, 120, 41.0082, 28.9784),
(3, 'Ankara', CURDATE(), 25, 80, 39.9334, 32.8597),
(3, 'İzmir', CURDATE(), 20, 65, 38.4237, 27.1428);
```

## 🎨 Özelleştirme

### Renkleri Değiştirmek

`assets/css/dashboard.css` dosyasında CSS variables düzenleyin:

```css
:root {
    --primary: #6366f1;
    --accent: #8b5cf6;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
}
```

### Logo Değiştirmek

`assets/images/` klasörüne kendi logonuzu ekleyin ve `header.php`'de günceleyin.

## 🔐 Güvenlik Notları

1. **Production'da:**
   - `config.php`'de debug modunu kapatın
   - Database şifrelerini güçlü yapın
   - HTTPS kullanın
   - CORS ayarlarını sıkılaştırın

2. **Analytics API:**
   - Rate limiting ekleyin (opsiyonel)
   - IP whitelist kullanın (gerekirse)

3. **File Permissions:**
   - PHP dosyalarına 644
   - Klasörlere 755
   - Config dosyasına 640

## 📞 Destek

Sorun yaşarsanız:
1. `RENTAL_MANAGEMENT_README.md` dosyasını okuyun
2. `CHANGELOG.md`'de güncellemeleri kontrol edin
3. Browser console'da hata mesajlarını kontrol edin
4. Apache/Nginx error log'larını inceleyin

## ✅ Kurulum Tamamlandı!

Artık sistemin tüm özellikleri kullanıma hazır:
- ✅ Rental Management Dashboard
- ✅ Analytics Tracking
- ✅ İBAN Yönetimi
- ✅ Kripto Cüzdan Yönetimi
- ✅ Script Ayarları

**Başarılar dileriz! 🎉**

---

**Not:** İlk kurulumda analytics verilerinin gelmesi için kiralanan sitelere tracking kodunun eklenmesi ve birkaç ziyaretçinin gelmesi gerekir. Test için yukarıdaki SQL ile manuel veri ekleyebilirsiniz.
