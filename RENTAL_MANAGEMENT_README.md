# Script Rental Management System

Script kiralayan kullanıcılar için kapsamlı yönetim paneli ve analytics sistemi.

## 🚀 Özellikler

### 1. Analytics Dashboard
- **Gerçek Zamanlı İstatistikler**
  - Bugünkü ziyaretçi sayısı
  - Sayfa görüntüleme sayısı
  - Aktif kullanıcı sayısı (son 5 dakika)
  - Toplam para yatırma tutarı (TRY)

- **7 Günlük Grafikler**
  - Ziyaretçi trendi
  - Sayfa görüntüleme trendi
  - Para yatırma trendi

- **Türkiye Şehir Haritası**
  - Şehir bazlı ziyaretçi dağılımı
  - En çok ziyaret edilen şehirler
  - Coğrafi koordinatlar ile görselleştirme

### 2. İBAN Yönetimi
- Birden fazla İBAN ekleme/silme
- İBAN'ları aktif/pasif yapma
- Sürükle-bırak ile sıralama
- Banka adı, hesap sahibi ve İBAN bilgileri
- Tek tıkla kopyalama

### 3. Kripto Cüzdan Yönetimi
- **3 Tip Kripto Cüzdan:**
  - USDT (TRC20)
  - TRX (TRON)
  - BTC (Bitcoin)
- Cüzdan ekleme/güncelleme/silme
- Aktif/pasif durumu
- Kolay kopyalama özelliği

### 4. Script Ayarları
- **Genel Ayarlar:**
  - Site başlığı ve açıklaması
  - Bakım modu
  - Kayıt açık/kapalı

- **Canlı Destek:**
  - Tawk.to entegrasyonu
  - Property ID yönetimi

- **İletişim:**
  - E-posta adresi
  - WhatsApp numarası
  - Telegram kullanıcı adı

- **Ödeme Ayarları:**
  - Minimum/Maksimum yatırım tutarı
  - Bonus oranı (%)
  - Günlük çekim limiti

## 📦 Kurulum

### 1. Database Migration

```bash
mysql -u username -p database_name < migrations/rental_management_system.sql
```

### 2. Dosya Yapısı

```
SaaS/
├── api/
│   └── analytics/
│       └── track.php          # Analytics veri toplama endpoint
├── assets/
│   └── js/
│       └── analytics-tracker.js  # Analytics tracking JS
├── modules/
│   └── rental/
│       ├── index.php          # Kiralamalar listesi
│       ├── manage.php         # Analytics dashboard
│       ├── ibans.php          # İBAN yönetimi
│       ├── wallets.php        # Kripto cüzdan yönetimi
│       └── settings.php       # Script ayarları
└── migrations/
    └── rental_management_system.sql  # Database tabloları
```

### 3. Analytics Entegrasyonu

Kiralanan sitelere şu kodu ekleyin (Ayarlar sayfasından alabilirsiniz):

```html
<!-- ScriptMarket Analytics -->
<script>
  window.ANALYTICS_API_URL = 'https://yoursite.com/api/analytics/track';
  window.RENTAL_ID = [RENTAL_ID];
</script>
<script src="https://yoursite.com/assets/js/analytics-tracker.js"></script>
```

## 🗄️ Database Tabloları

### rental_analytics
Gerçek zamanlı ziyaretçi verileri
- visitor_ip, city, region, country
- page_url, user_agent
- session_id, visit_date, visit_time

### rental_analytics_summary
Günlük özet veriler
- unique_visitors, total_pageviews
- active_users_now
- total_deposits_try, deposit_count

### rental_analytics_by_city
Şehir bazlı istatistikler
- city, visitor_count, pageview_count
- latitude, longitude (harita için)

### rental_deposits
Para yatırma işlemleri
- amount_try, payment_method
- transaction_id, status

### rental_crypto_wallets
Kripto cüzdan bilgileri
- wallet_type (USDT_TRC20, TRX_TRON, BTC)
- wallet_address
- status (active/inactive)

### rental_ibans
İBAN bilgileri
- bank_name, account_holder, iban
- status, display_order

### rental_settings
Script ayarları
- setting_key, setting_value
- Örnek: tawkto_id, withdrawal_limit, site_title

### rental_active_sessions
Aktif kullanıcı takibi (son 5 dakika)
- session_id, visitor_ip
- last_activity

## 📊 Analytics API

### Endpoint: `/api/analytics/track.php`

#### Pageview Tracking
```javascript
{
  "type": "pageview",
  "data": {
    "session_id": "sm_xxx",
    "rental_id": 3,
    "page_url": "https://example.com",
    "user_agent": "...",
    "timestamp": "2026-02-04T12:00:00Z"
  }
}
```

#### Deposit Tracking
```javascript
ScriptMarketAnalytics.trackDeposit(500, 'bank_transfer', 'TXN123');
```

#### Custom Event
```javascript
ScriptMarketAnalytics.trackEvent('signup', { source: 'homepage' });
```

## 🎨 Kullanıcı Arayüzü

### Rental Management Dashboard
- `/rental/manage/[RENTAL_ID]` - Ana dashboard
- `/rental/manage/[RENTAL_ID]/ibans` - İBAN yönetimi
- `/rental/manage/[RENTAL_ID]/wallets` - Kripto cüzdan yönetimi
- `/rental/manage/[RENTAL_ID]/settings` - Script ayarları

### Özellikler
- Modern, responsive tasarım
- Dark theme uyumlu
- Sürükle-bırak ile sıralama
- Gerçek zamanlı veri güncelleme
- Chart.js ile grafikler
- SortableJS ile sıralama

## 🔒 Güvenlik

- Session bazlı authentication
- CSRF token koruması
- SQL injection koruması (prepared statements)
- XSS koruması (htmlspecialchars)
- IP tabanlı rate limiting

## 🌐 GeoIP Entegrasyonu

### Öneri: GeoIP2 Database Kullanımı

```php
// composer require geoip2/geoip2
use GeoIp2\Database\Reader;

$reader = new Reader('/path/to/GeoLite2-City.mmdb');
$record = $reader->city($ip);
$city = $record->city->name;
$lat = $record->location->latitude;
$lng = $record->location->longitude;
```

## 📱 Responsive Tasarım

Tüm sayfalar mobil uyumlu:
- Tablet: 768px+
- Desktop: 1024px+
- Large Desktop: 1440px+

## 🎯 Gelecek Özellikler

- [ ] Leaflet.js ile interaktif Türkiye haritası
- [ ] Gerçek zamanlı bildirimler (WebSocket)
- [ ] Export to Excel/PDF
- [ ] Email/SMS bildirimleri
- [ ] Çoklu dil desteği
- [ ] API rate limiting dashboard
- [ ] Webhook entegrasyonları

## 📝 Notlar

- Analytics JS dosyası her 30 saniyede bir heartbeat gönderir
- Aktif kullanıcılar son 5 dakika içinde aktivite gösterenlerdir
- Şehir bazlı veriler günlük olarak toplanır
- Session bazlı unique visitor takibi yapılır

## 🤝 Destek

Sorularınız için:
- GitHub Issues
- Email: support@example.com
- Telegram: @support

## 📄 Lisans

Tüm hakları saklıdır © 2026
