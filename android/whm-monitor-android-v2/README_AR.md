# WHM Server Monitor - Android

هذا الإصدار يحول لوحة مراقبة WHM الحالية إلى تطبيق Flutter يعمل على Android ويتصل مباشرة بـ API الموجود على:

`https://monitor.oxserver.net/api`

## ما الذي يعمل؟

- تسجيل الدخول باستخدام نفس حساب لوحة المراقبة.
- حفظ JWT بشكل آمن داخل الهاتف.
- عرض عدد السيرفرات والمواقع.
- عرض Online / Offline.
- عرض كل السيرفرات مرتبة حسب `sort_order`.
- عرض عدد المواقع و Active / Suspended.
- Load / RAM / Disk / Backup.
- حالة Apache / MySQL / DNS / Exim.
- عرض التنبيهات النشطة.
- تحديث تلقائي كل 30 ثانية.
- Pull to Refresh.
- تسجيل خروج.

## تشغيل المشروع

بعد فك الضغط:

```bash
flutter pub get
flutter run
```

لإنشاء مجلدات Android القياسية في حالة عدم وجودها:

```bash
flutter create .
flutter pub get
flutter build apk --release
```

ملف APK بعد البناء سيكون في:

`build/app/outputs/flutter-apk/app-release.apk`

## ملاحظة

التطبيق لا يصل إلى MySQL مباشرة. الاتصال يتم عبر API باستخدام JWT، لذلك بيانات قاعدة البيانات لا توضع داخل التطبيق.
