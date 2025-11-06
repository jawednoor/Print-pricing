# 🔐 دليل التشفير السريع

## 🚀 الاستخدام السريع (عبر واجهة الويب)

### 🌐 الطريقة الأسهل (موصى بها)
1. شغّل السيرفر: `node server.js`
2. افتح المتصفح: `http://localhost:3000/encryption-manager.html`
3. استخدم الواجهة لتشفير/فك التشفير بنقرة واحدة!

### � عبر API مباشرة

**معرفة الرمز الحالي:**
```bash
curl http://localhost:3000/generate-otp
```

**تشفير الملف:**
```bash
curl -X POST http://localhost:3000/encrypt-doc -H "Content-Type: application/json" -d "{}"
```

**فك التشفير:**
```bash
curl -X POST http://localhost:3000/decrypt-doc -H "Content-Type: application/json" -d "{\"otpCode\":\"123456\"}"
```

---

## ⚡ الأوامر الأساسية

### 🌐 عبر واجهة الويب (الأسهل)
1. `node server.js` - تشغيل السيرفر
2. افتح: `http://localhost:3000/encryption-manager.html`
3. استخدم الأزرار في الواجهة!

### 📡 عبر API
| الوظيفة | الطريقة | المسار |
|---------|---------|--------|
| معرفة رمز OTP | GET | `/generate-otp` |
| تشفير تلقائي | POST | `/encrypt-doc` |
| فك التشفير | POST | `/decrypt-doc` |

---

## 🔐 الملفات الآمنة

| الملف | الحالة | الأمان |
|------|--------|--------|
| `مراجعة.txt.encrypted` | ✅ آمن للرفع | محمي بـ AES-256 |
| `مراجعة.txt` | ⚠️ لا ترفعه | محمي في .gitignore |
| `مراجعة.txt.decrypted` | ❌ احذفه فوراً | محمي في .gitignore |

---

## 📝 سير العمل اليومي

### 🌐 الطريقة السهلة (عبر المتصفح):
```bash
# 1. تشغيل السيرفر
node server.js

# 2. فتح المتصفح
# اذهب إلى: http://localhost:3000/encryption-manager.html

# 3. استخدم الواجهة لفك التشفير/التشفير
```

### 💻 الطريقة البديلة (عبر API):
```bash
# 1. معرفة الرمز
curl http://localhost:3000/generate-otp

# 2. فك التشفير
curl -X POST http://localhost:3000/decrypt-doc \
  -H "Content-Type: application/json" \
  -d '{"otpCode":"123456"}'

# 3. قراءة الملف
notepad مراجعة.txt.decrypted

# 4. حذف الملف المفكوك
del مراجعة.txt.decrypted
```

### تحديث الملف:
```bash
# 1. فك التشفير (من المتصفح أو API)

# 2. تعديل الملف
notepad مراجعة.txt.decrypted

# 3. نسخ للملف الأصلي
copy مراجعة.txt.decrypted مراجعة.txt

# 4. تشفير مرة أخرى (من المتصفح أو API)
curl -X POST http://localhost:3000/encrypt-doc \
  -H "Content-Type: application/json" \
  -d '{}'

# 5. التنظيف
del مراجعة.txt.decrypted

# 6. حفظ على Git
git add مراجعة.txt.encrypted
git commit -m "تحديث ملف المراجعة"
git push
```

---

## ⚠️ قواعد الأمان

1. ✅ **دائماً** احذف `مراجعة.txt.decrypted` بعد الاستخدام
2. ✅ **لا ترفع** `مراجعة.txt` الأصلي إلى GitHub
3. ✅ **استخدم** `مراجعة.txt.encrypted` فقط على GitHub
4. ✅ **احفظ** `OTP_SECRET` في مكان آمن

---

## 🔑 رمز OTP

- **Secret**: `JBSWY3DPEHPK3PXP`
- **التطبيق**: Google Authenticator
- **التغيير**: كل 30 ثانية
- **الطول**: 6 أرقام

---

## 📞 المساعدة

للتوثيق الكامل: افتح `README-ENCRYPTION.md`

---

✅ **نظام التشفير جاهز ويعمل!**
