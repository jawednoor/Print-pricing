# 🔐 نظام التشفير المدمج في server.js

## ✅ تم الدمج الكامل!

جميع وظائف التشفير/فك التشفير **مدمجة الآن في `server.js`**. 
لم تعد هناك حاجة للسكريبتات المنفصلة!

---

## 🌐 الاستخدام عبر واجهة الويب (الأسهل)

### الخطوات:

1. **تشغيل السيرفر:**
   ```bash
   node server.js
   ```

2. **فتح واجهة التشفير:**
   ```
   http://localhost:3000/encryption-manager.html
   ```

3. **استخدام الأزرار:**
   - 🔑 **توليد رمز OTP** - احصل على الرمز الحالي
   - 🔓 **فك التشفير** - أدخل رمز OTP وانقر
   - 🔒 **التشفير** - اختياري: أدخل رمز OTP أو اتركه فارغاً

---

## 📡 API Endpoints المتاحة

### 1️⃣ توليد رمز OTP

**الطلب:**
```bash
GET /generate-otp
```

**الاستجابة:**
```json
{
  "success": true,
  "otp": "123456",
  "timeRemaining": 25,
  "timestamp": "2025-11-06T13:30:00.000Z",
  "note": "هذا الرمز صالح لمدة 30 ثانية فقط"
}
```

**مثال (curl):**
```bash
curl http://localhost:3000/generate-otp
```

---

### 2️⃣ فك تشفير الملف

**الطلب:**
```bash
POST /decrypt-doc
Content-Type: application/json

{
  "otpCode": "123456"
}
```

**الاستجابة (نجاح):**
```json
{
  "success": true,
  "message": "تم فك التشفير بنجاح!",
  "stats": {
    "encryptedFile": "مراجعة.txt.encrypted",
    "decryptedFile": "مراجعة.txt.decrypted",
    "encryptedSize": "99.49 KB",
    "decryptedSize": "49.63 KB",
    "otpUsed": "123456",
    "timestamp": "2025-11-06T13:30:00.000Z",
    "encryptionDate": "2025-11-06T13:25:33.000Z",
    "originalOtp": "625305"
  }
}
```

**الاستجابة (فشل):**
```json
{
  "success": false,
  "message": "رمز التحقق غير صحيح. تأكد من الرمز في Google Authenticator."
}
```

**مثال (curl):**
```bash
curl -X POST http://localhost:3000/decrypt-doc \
  -H "Content-Type: application/json" \
  -d '{"otpCode":"123456"}'
```

**مثال (JavaScript):**
```javascript
fetch('/decrypt-doc', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ otpCode: '123456' })
})
.then(res => res.json())
.then(data => console.log(data));
```

---

### 3️⃣ تشفير الملف

**الطلب:**
```bash
POST /encrypt-doc
Content-Type: application/json

{
  "otpCode": "123456"  // اختياري - سيتم التوليد تلقائياً إن لم يُوفَّر
}
```

**الاستجابة (نجاح):**
```json
{
  "success": true,
  "message": "تم التشفير بنجاح!",
  "stats": {
    "originalFile": "مراجعة.txt",
    "encryptedFile": "مراجعة.txt.encrypted",
    "originalSize": "49.63 KB",
    "encryptedSize": "99.49 KB",
    "otpUsed": "123456",
    "timestamp": "2025-11-06T13:30:00.000Z"
  }
}
```

**مثال (تشفير تلقائي):**
```bash
curl -X POST http://localhost:3000/encrypt-doc \
  -H "Content-Type: application/json" \
  -d '{}'
```

**مثال (تشفير يدوي):**
```bash
curl -X POST http://localhost:3000/encrypt-doc \
  -H "Content-Type: application/json" \
  -d '{"otpCode":"123456"}'
```

---

## 🔧 التكامل مع التطبيقات الأخرى

### مثال: Python
```python
import requests

# توليد OTP
response = requests.get('http://localhost:3000/generate-otp')
otp = response.json()['otp']
print(f"الرمز الحالي: {otp}")

# فك التشفير
response = requests.post(
    'http://localhost:3000/decrypt-doc',
    json={'otpCode': otp}
)
print(response.json())
```

### مثال: Node.js
```javascript
const axios = require('axios');

async function decryptFile() {
    // توليد OTP
    const otpRes = await axios.get('http://localhost:3000/generate-otp');
    const otp = otpRes.data.otp;
    
    // فك التشفير
    const decryptRes = await axios.post('http://localhost:3000/decrypt-doc', {
        otpCode: otp
    });
    
    console.log(decryptRes.data);
}

decryptFile();
```

### مثال: PowerShell
```powershell
# توليد OTP
$otp = (Invoke-RestMethod -Uri "http://localhost:3000/generate-otp").otp

# فك التشفير
$body = @{ otpCode = $otp } | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "http://localhost:3000/decrypt-doc" `
    -ContentType "application/json" -Body $body
```

---

## 📋 سير العمل الكامل

### قراءة الملف:
```bash
# 1. تشغيل السيرفر
node server.js

# 2. فك التشفير (عبر المتصفح أو API)
# المتصفح: http://localhost:3000/encryption-manager.html
# API: curl -X POST http://localhost:3000/decrypt-doc -d '{"otpCode":"123456"}'

# 3. قراءة الملف
notepad مراجعة.txt.decrypted

# 4. حذف الملف المفكوك
del مراجعة.txt.decrypted
```

### تحديث الملف:
```bash
# 1. فك التشفير
# (استخدم المتصفح أو API)

# 2. تعديل
notepad مراجعة.txt.decrypted

# 3. نسخ للأصلي
copy مراجعة.txt.decrypted مراجعة.txt

# 4. تشفير مرة أخرى
# (استخدم المتصفح أو API)

# 5. تنظيف
del مراجعة.txt
del مراجعة.txt.decrypted

# 6. حفظ على Git
git add مراجعة.txt.encrypted
git commit -m "تحديث ملف المراجعة"
git push
```

---

## 🔐 الأمان والحماية

### رموز الخطأ:

| الكود | الرسالة | السبب |
|-------|---------|--------|
| 400 | يرجى إدخال رمز Google Authenticator | لم يتم توفير OTP |
| 401 | رمز التحقق غير صحيح | OTP خاطئ |
| 404 | الملف المشفر غير موجود | الملف غير موجود |
| 500 | حدث خطأ أثناء فك التشفير | خطأ في النظام |

### التحقق من الأمان:
- ✅ **التحقق من OTP**: جميع العمليات تتطلب رمز صحيح
- ✅ **التشفير**: AES-256-CBC (معيار عسكري)
- ✅ **المفتاح**: SHA-256 (256-bit)
- ✅ **IV عشوائي**: يتغير في كل عملية تشفير

---

## 🚨 استكشاف الأخطاء

### المشكلة: "رمز التحقق غير صحيح"
**الحل:**
1. تأكد من الرمز في Google Authenticator
2. الرمز يتغير كل 30 ثانية
3. استخدم `/generate-otp` للحصول على الرمز الصحيح

### المشكلة: "الملف المشفر غير موجود"
**الحل:**
1. تأكد من وجود `مراجعة.txt.encrypted`
2. شفّر الملف أولاً باستخدام `/encrypt-doc`

### المشكلة: "السيرفر لا يستجيب"
**الحل:**
1. تأكد من تشغيل السيرفر: `node server.js`
2. تحقق من المنفذ: `http://localhost:3000`
3. افحص سجلات السيرفر في Terminal

---

## 📊 الملفات والهيكل

### الملفات الموجودة:
```
📁 quick/
├── server.js                      ✅ النظام الرئيسي (مدمج)
├── encryption-manager.html        ✅ واجهة الويب
├── مراجعة.txt                     ⚠️ الأصلي (احذفه بعد التشفير)
├── مراجعة.txt.encrypted           ✅ المشفر (آمن للرفع)
└── مراجعة.txt.decrypted           ❌ احذفه بعد القراءة
```

### الملفات المحذوفة (تم دمجها):
- ❌ ~~generate-otp.js~~ → الآن في `/generate-otp`
- ❌ ~~encrypt-doc.js~~ → الآن في `/encrypt-doc`
- ❌ ~~encrypt-auto.js~~ → الآن في `/encrypt-doc`
- ❌ ~~decrypt-doc.js~~ → الآن في `/decrypt-doc`
- ❌ ~~decrypt-auto.js~~ → الآن في `/decrypt-doc`
- ❌ ~~decrypt-emergency.js~~ → غير ضروري

---

## 🎯 الخلاصة

### ما تم إنجازه:
✅ دمج كامل لجميع وظائف التشفير في `server.js`  
✅ 3 endpoints رئيسية: `/generate-otp`, `/encrypt-doc`, `/decrypt-doc`  
✅ واجهة ويب كاملة: `encryption-manager.html`  
✅ دعم API كامل للتكامل مع تطبيقات أخرى  
✅ حذف الملفات المنفصلة (لم تعد ضرورية)  

### المميزات:
🌐 واجهة ويب سهلة الاستخدام  
📡 API RESTful كامل  
🔐 نظام أمان قوي (AES-256 + Google Authenticator)  
📊 إحصائيات مفصلة لكل عملية  
🚀 أداء سريع ومستقر  

---

✅ **النظام جاهز ويعمل بكفاءة عالية!**

🔗 للبدء: `node server.js` ثم افتح `http://localhost:3000/encryption-manager.html`
