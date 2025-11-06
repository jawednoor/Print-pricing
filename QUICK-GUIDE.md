# 🔐 دليل التشفير السريع

## 🚀 الاستخدام السريع

### 📱 معرفة الرمز الحالي
```bash
node generate-otp.js
```

### 🔒 تشفير الملف
```bash
node encrypt-auto.js
```

### 🔓 فك التشفير
```bash
node decrypt-doc.js
# أدخل رمز Google Authenticator عندما يُطلب منك
```

### 🔓 فك تشفير تلقائي (سريع)
```bash
node decrypt-auto.js
# يستخدم الرمز الحالي تلقائياً
```

---

## ⚡ الأوامر الأساسية

| الوظيفة | الأمر |
|---------|-------|
| معرفة رمز OTP | `node generate-otp.js` |
| تشفير تلقائي | `node encrypt-auto.js` |
| تشفير يدوي | `node encrypt-doc.js` |
| فك تشفير يدوي | `node decrypt-doc.js` |
| فك تشفير تلقائي | `node decrypt-auto.js` |
| طوارئ (رمز محفوظ) | `node decrypt-emergency.js` |

---

## 🔐 الملفات الآمنة

| الملف | الحالة | الأمان |
|------|--------|--------|
| `مراجعة.txt.encrypted` | ✅ آمن للرفع | محمي بـ AES-256 |
| `مراجعة.txt` | ⚠️ لا ترفعه | محمي في .gitignore |
| `مراجعة.txt.decrypted` | ❌ احذفه فوراً | محمي في .gitignore |

---

## 📝 سير العمل اليومي

### قراءة الملف:
```bash
node decrypt-doc.js
notepad مراجعة.txt.decrypted
del مراجعة.txt.decrypted
```

### تحديث الملف:
```bash
# 1. فك التشفير
node decrypt-doc.js

# 2. التعديل
notepad مراجعة.txt.decrypted

# 3. نسخ للملف الأصلي
copy مراجعة.txt.decrypted مراجعة.txt

# 4. تشفير مرة أخرى
node encrypt-auto.js

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
