# Changelog - Rental Management System

## [v2.0.0] - 2026-02-04

### 🎉 Yeni Özellikler

#### Rental Management Dashboard
- ✅ Script kiralayan kullanıcılar için kapsamlı yönetim paneli
- ✅ Gerçek zamanlı analytics dashboard
- ✅ 7 günlük trend grafikleri (Chart.js)
- ✅ Türkiye şehir bazlı ziyaretçi analizi
- ✅ Aktif kullanıcı sayısı (son 5 dakika)
- ✅ Para yatırma tutarı takibi (TRY)

#### İBAN Yönetimi
- ✅ Birden fazla İBAN ekleme/silme
- ✅ Banka adı, hesap sahibi, İBAN bilgileri
- ✅ Aktif/Pasif durum yönetimi
- ✅ Sürükle-bırak ile sıralama (SortableJS)
- ✅ Tek tıkla İBAN kopyalama
- ✅ İBAN format validasyonu (TR + 24 rakam)

#### Kripto Cüzdan Yönetimi
- ✅ USDT (TRC20) cüzdan desteği
- ✅ TRX (TRON) cüzdan desteği
- ✅ BTC (Bitcoin) cüzdan desteği
- ✅ Cüzdan ekleme/güncelleme/silme
- ✅ Aktif/Pasif durum kontrolü
- ✅ Görsel cüzdan kartları (her coin için özel renk)
- ✅ Tek tıkla adres kopyalama

#### Script Ayarları
- ✅ Site başlığı ve açıklaması (SEO)
- ✅ Bakım modu aktif/pasif
- ✅ Kayıt sistemi aç/kapa
- ✅ Tawk.to canlı destek entegrasyonu
- ✅ İletişim bilgileri (Email, WhatsApp, Telegram)
- ✅ Ödeme ayarları (Min/Max tutar, Bonus oranı)
- ✅ Günlük çekim limiti
- ✅ Analytics kod snippet

#### Analytics Tracking System
- ✅ Gerçek zamanlı veri toplama JavaScript SDK
- ✅ Otomatik pageview tracking
- ✅ Session bazlı unique visitor takibi
- ✅ Heartbeat sistemi (30 saniye)
- ✅ Para yatırma işlemi tracking
- ✅ Custom event tracking API
- ✅ Beacon API desteği (sayfa kapatılsa bile veri gönderir)
- ✅ SPA (Single Page Application) desteği

### 📦 Yeni Dosyalar

```
/migrations/rental_management_system.sql     # Database migration
/api/analytics/track.php                      # Analytics API endpoint
/assets/js/analytics-tracker.js               # Tracking JavaScript
/modules/rental/manage.php                    # Dashboard
/modules/rental/ibans.php                     # İBAN yönetimi
/modules/rental/wallets.php                   # Kripto cüzdan yönetimi
/modules/rental/settings.php                  # Script ayarları
/RENTAL_MANAGEMENT_README.md                  # Dokümantasyon
```

### 🗄️ Yeni Database Tabloları

1. **rental_analytics** - Gerçek zamanlı ziyaretçi verileri
2. **rental_analytics_summary** - Günlük özet istatistikler
3. **rental_analytics_by_city** - Şehir bazlı analitik
4. **rental_deposits** - Para yatırma işlemleri
5. **rental_crypto_wallets** - Kripto cüzdan bilgileri
6. **rental_ibans** - İBAN bilgileri
7. **rental_settings** - Script ayarları
8. **rental_active_sessions** - Aktif kullanıcı takibi

### 🔧 Güncellemeler

- ✅ `/index.php` - Yeni routing eklendi (manage, ibans, wallets, settings)
- ✅ `/modules/rental/index.php` - "Yönet" butonu eklendi (aktif rental'lar için)

### 🎨 UI/UX İyileştirmeleri

- Modern gradient kartlar
- Hover efektleri
- Responsive grid layout
- Dark theme uyumlu renkler
- İkon kullanımı (Bootstrap Icons)
- Loading indicators
- Modal dialogs
- Drag & drop sıralama

### 🔒 Güvenlik

- CSRF token koruması
- Prepared statements (SQL injection koruması)
- XSS koruması (htmlspecialchars)
- Session validation
- Input sanitization
- IBAN format validation

### 📊 Analytics Features

#### Frontend (analytics-tracker.js)
- Session ID generation
- Automatic pageview tracking
- User activity tracking (mouse, keyboard, scroll, touch)
- Heartbeat system (30s intervals)
- Page visibility tracking
- SPA navigation detection
- beforeunload handling
- Clipboard API for address copying

#### Backend (track.php)
- CORS headers
- Multiple event types (pageview, heartbeat, deposit, session_end, custom_event)
- IP-based geolocation
- Unique visitor detection
- Daily summary updates
- City-based statistics
- Active session management

### 🌐 API Endpoints

#### POST `/api/analytics/track.php`

**Event Types:**
1. `pageview` - Sayfa görüntüleme
2. `heartbeat` - Aktif kullanıcı ping
3. `deposit` - Para yatırma
4. `session_end` - Oturum sonu
5. `custom_event` - Özel olaylar

**Headers:**
```
Content-Type: application/json
Access-Control-Allow-Origin: *
```

### 📱 Responsive Breakpoints

- Mobile: < 768px
- Tablet: 768px - 1024px
- Desktop: 1024px - 1440px
- Large: > 1440px

### 🎯 Kullanım Akışı

1. **Kullanıcı script kiralar** → Rental oluşturulur
2. **Rental aktif olur** → "Yönet" butonu görünür
3. **Dashboard'a girer** → Analytics verileri görüntülenir
4. **İBAN ekler** → Birden fazla banka hesabı ekleyebilir
5. **Kripto cüzdan ekler** → USDT, TRX, BTC cüzdanları
6. **Ayarları yapar** → Tawk.to, limitler, iletişim bilgileri
7. **Analytics kodu alır** → Kiralanan siteye ekler
8. **Gerçek zamanlı veri görür** → Dashboard üzerinden takip

### 🔄 Veri Akışı

```
Kiralanan Site → Analytics JS → API Endpoint → Database → Dashboard
```

1. Ziyaretçi kiralanan siteye girer
2. Analytics JS çalışır ve veri toplar
3. Veriler API endpoint'e gönderilir
4. Database'e kaydedilir
5. Dashboard'da gerçek zamanlı görüntülenir

### 📈 Metrikler

- **Unique Visitors** - Benzersiz ziyaretçi (IP bazlı, günlük)
- **Pageviews** - Toplam sayfa görüntüleme
- **Active Users** - Son 5 dakikada aktif olanlar
- **Deposits** - Para yatırma tutarı (TRY)
- **City Stats** - Şehir bazlı dağılım

### 🛠️ Geliştirme Notları

#### Analytics JS Optimization
- Beacon API kullanımı (reliability)
- Passive event listeners (performance)
- MutationObserver for SPA (compatibility)
- Session storage (persistence)

#### Database Optimization
- Composite indexes (rental_id, date)
- ON DUPLICATE KEY UPDATE (upsert)
- Date-based partitioning (opsiyonel, büyük veri için)

#### Future Improvements
- GeoIP2 database integration
- Real-time WebSocket updates
- Export functionality (Excel, PDF, CSV)
- Email/SMS notifications
- Multi-language support
- API rate limiting dashboard

### 🐛 Bilinen Sorunlar

- GeoIP şu anda test amaçlı rastgele şehir döndürüyor (production'da GeoIP2 kullanın)
- Türkiye haritası görselleştirmesi henüz Leaflet.js ile yapılmadı (opsiyonel)

### 📝 TODO

- [ ] GeoIP2 database entegrasyonu
- [ ] Leaflet.js ile interaktif harita
- [ ] WebSocket ile real-time updates
- [ ] Email notification system
- [ ] Export to Excel/PDF
- [ ] API documentation (Swagger)
- [ ] Unit tests
- [ ] Performance monitoring

### 🎊 Sonuç

Bu güncelleme ile script kiralayan kullanıcılar:
- Sitelerini profesyonelce yönetebilir
- Gerçek zamanlı analytics görebilir
- Ödeme yöntemlerini kolayca ekleyebilir
- Müşteri destek sistemini kurabilir
- Kapsamlı raporlar alabilir

Sistem tamamen modüler ve genişletilebilir şekilde tasarlanmıştır.

---

**Version:** 2.0.0  
**Release Date:** 2026-02-04  
**Breaking Changes:** Hayır  
**Migration Required:** Evet (SQL dosyası çalıştırılmalı)
