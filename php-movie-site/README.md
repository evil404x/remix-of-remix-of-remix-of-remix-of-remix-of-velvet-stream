# 🎬 CineGold - وێبسایتی فیلمی پێشکەوتوو

## ڕێنمایی دامەزراندن (Setup Guide)

### پێداویستییەکان:
- XAMPP (PHP 8.2 + MySQL)
- وێبگەڕ (Chrome, Firefox, etc.)

### هەنگاوەکان:

1. **فۆڵدەری پڕۆژەکە بکە بۆ XAMPP:**
   ```
   کۆپی بکە بۆ: C:\xampp\htdocs\php-movie-site\
   ```

2. **داتابەیسەکە دروست بکە:**
   - XAMPP بکەرەوە (Apache + MySQL)
   - بڕۆ بۆ: http://localhost/phpmyadmin
   - فایلی `setup.sql` لە Import ـەوە ڕەن بکە

3. **فۆڵدەری ئەپلۆد دروست بکە:**
   ```
   php-movie-site/uploads/posters/
   php-movie-site/uploads/backdrops/
   ```

4. **سایتەکە بکەرەوە:**
   ```
   http://localhost/php-movie-site/
   ```

5. **چوونەژوورەوەی ئادمین:**
   ```
   Username: admin
   Password: admin123
   ```
   ⚠️ وشەی نهێنی بگۆڕە!

### پێکهاتەی فایلەکان:
```
php-movie-site/
├── config.php              # ڕێکخستنی داتابەیس
├── setup.sql               # خشتەکانی داتابەیس
├── index.php               # سەرەتا
├── movie.php               # لاپەڕەی فیلم
├── search.php              # گەڕان و فلتەر
├── login.php               # چوونەژوورەوە
├── register.php            # تۆمارکردن
├── favorites.php           # دڵخوازەکان
├── request.php             # داواکردنی فیلم
├── logout.php
├── includes/
│   ├── header.php
│   ├── footer.php
│   └── functions.php
├── admin/
│   ├── index.php           # داشبۆرد
│   ├── add-movie.php       # زیادکردنی فیلم
│   ├── ads.php             # بەڕێوەبردنی ڕیکلام
│   └── logs.php            # ئاسایش
├── api/
│   ├── search.php
│   ├── get-links.php
│   └── toggle-favorite.php
└── assets/
    ├── css/style.css
    └── js/main.js
```

### تایبەتمەندییەکان:
- ✅ دیزاینی Glassmorphism (ڕەش + ئاڵتونی)
- ✅ Smart Preloader
- ✅ پلەیەری ڤیدیۆ بە ٣ زمان (کوردی، عەرەبی، ئینگلیزی)
- ✅ Multi-Server (Mirror Links)
- ✅ سیستمی وەرز و ئەڵقە بۆ زنجیرەکان
- ✅ گەڕان و فلتەرکردن (IMDb, Year, Genre, Quality)
- ✅ سیستمی بەکارهێنەر (تۆمارکردن، دڵخوازەکان، مێژوو)
- ✅ داواکردنی فیلم + نۆتیفیکەیشن
- ✅ SEO ئۆتۆماتیکی (Meta + Schema.org)
- ✅ پانێڵی ئادمین (داشبۆرد، زیادکردنی فیلم، ڕیکلام، ئاسایش)
- ✅ پاراستن دژی SQL Injection و XSS
- ✅ PDO بۆ داتابەیس
- ✅ Responsive Design
