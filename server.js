// -----------------------------
// تعريف المتغيرات الأساسية أولاً
const express = require('express');
const fs = require('fs');
const path = require('path');
const cors = require('cors');

const session = require('express-session');
const { authenticator } = require('otplib');
const app = express();
const PORT = 3000;

// إعداد الجلسة (session) لحماية الصفحات
app.use(session({
    secret: process.env.SESSION_SECRET || 'cpanel-otp-secret',
    resave: false,
    saveUninitialized: false,
    cookie: { 
        maxAge: 60 * 60 * 1000, // ساعة واحدة
        secure: process.env.NODE_ENV === 'production', // HTTPS only in production
        httpOnly: true, // منع الوصول عبر JavaScript
        sameSite: 'strict' // حماية من CSRF
    }
}));

// حماية ضد الهجمات الشائعة
const helmet = require('helmet');
app.use(helmet());

// Rate limiting لحماية من هجمات كسر كلمة المرور
const rateLimit = require('express-rate-limit');
const loginLimiter = rateLimit({
    windowMs: 15 * 60 * 1000, // 15 دقيقة
    max: 5 // 5 محاولات كحد أقصى
});
app.use('/login', loginLimiter);

app.use(express.json({ 
    limit: '10mb',
    verify: (req, res, buf) => {
        // التحقق من صحة JSON
        try {
            JSON.parse(buf);
        } catch(e) {
            throw new Error('Invalid JSON');
        }
    }
}));

// حفظ بيانات المستخدمين في user-data.json
app.post('/save-users', (req, res) => {
    console.log('Received save-users request:', req.body); // للتصحيح
    const filePath = path.join(__dirname, 'user-data.json');
    fs.writeFile(filePath, JSON.stringify(req.body, null, 2), 'utf8', err => {
        if (err) {
            console.error('Error saving users:', err);
            return res.status(500).json({ success: false, error: 'خطأ في حفظ المستخدمين' });
        }
        console.log('Users saved successfully');
        res.json({ success: true });
    });
});

// إعداد مفتاح OTP Base32 متوافق مع Google Authenticator (حروف كبيرة وأرقام 2-7 فقط)
const OTP_SECRET = 'JBSWY3DPEHPK3PXP'; // مفتاح Base32 (لا تضعه في أي مكان آخر)
authenticator.options = { step: 30, digits: 6, algorithm: 'sha1' };
// ملاحظة: OTP_SECRET يجب أن يكون Base32 (حروف كبيرة وأرقام 2-7 فقط)

// -----------------------------
// تحقق تسجيل الدخول (API)
app.post('/login', (req, res) => {
    const { username, password } = req.body;
    // تحقق من اسم المستخدم admin فقط
    if (username !== 'admin') {
        return res.status(401).json({ success: false, message: 'اسم المستخدم غير صحيح' });
    }

    // تحميل بيانات المحاولات الفاشلة من الملف
    const failedFile = path.join(__dirname, 'failed-logins.json');
    let failedData = {};
    try {
        failedData = JSON.parse(fs.readFileSync(failedFile, 'utf8'));
    } catch (e) {
        failedData = { admin: { failedCount: 0, lastFailed: null, lockUntil: null } };
    }
    const now = Date.now();
    const userFail = failedData[username] || { failedCount: 0, lastFailed: null, lockUntil: null };

    // تحقق من وجود حظر
    if (userFail.lockUntil && now < userFail.lockUntil) {
        const remaining = Math.ceil((userFail.lockUntil - now) / (60 * 1000));
        return res.status(403).json({ success: false, message: `تم حظر الحساب مؤقتاً. حاول بعد ${remaining} دقيقة.` });
    }

    // تحقق من كلمة المرور OTP (Google Authenticator)
    const isValid = authenticator.check(password, OTP_SECRET);
    if (isValid) {
        req.session.isLoggedIn = true;
        // إعادة تعيين العدّ عند نجاح الدخول
        failedData[username] = { failedCount: 0, lastFailed: null, lockUntil: null };
        fs.writeFileSync(failedFile, JSON.stringify(failedData, null, 2), 'utf8');
        res.json({ success: true });
    } else {
        // زيادة العدّ
        let failedCount = userFail.failedCount || 0;
        let lastFailed = userFail.lastFailed;
        // إعادة العدّ إذا مرّ يوم جديد
        if (lastFailed && (now - new Date(lastFailed).getTime() > 24 * 60 * 60 * 1000)) {
            failedCount = 0;
        }
        failedCount++;
        let lockUntil = null;
        if (failedCount >= 3) {
            lockUntil = now + 12 * 60 * 60 * 1000; // 12 ساعة
        }
        failedData[username] = { failedCount, lastFailed: new Date(now).toISOString(), lockUntil };
        fs.writeFileSync(failedFile, JSON.stringify(failedData, null, 2), 'utf8');
        if (lockUntil) {
            return res.status(403).json({ success: false, message: 'تم حظر الحساب مؤقتاً لمدة 12 ساعة بسبب تكرار إدخال كلمة مرور خاطئة.' });
        }
        res.status(401).json({ success: false, message: `رمز التحقق غير صحيح. تبقى ${3 - failedCount} محاولة قبل الحظر.` });
    }
// ميدل وير حماية صفحات لوحة التحكم
function requireLogin(req, res, next) {
    if (req.session && req.session.isLoggedIn) {
        next();
    } else {
        res.status(401).send('غير مصرح بالدخول. يرجى تسجيل الدخول أولاً.');
    }
}

// حماية صفحات لوحة التحكم
app.use(['/values.html', '/users-table.html', '/settings.html', '/papers.html'], requireLogin, express.static(__dirname));
});

// -----------------------------

// لم يعد هناك حفظ مستخدمين، التحقق فقط عبر OTP

// -----------------------------
// أكواد بيانات الورق (values/papers)
// -----------------------------

const DATA_FILE = path.join(__dirname, 'values.json');

// استقبال البيانات وحفظها في values.json
app.post('/values.json', (req, res) => {
    fs.writeFile(DATA_FILE, JSON.stringify(req.body, null, 2), 'utf8', err => {
        if (err) {
            return res.status(500).json({ error: 'حدث خطأ أثناء الحفظ' });
        }
        res.json({ success: true });
    });
});

// عرض البيانات
app.get('/values.json', (req, res) => {
    fs.readFile(DATA_FILE, 'utf8', (err, data) => {
        if (err) {
            return res.status(500).json({ error: 'حدث خطأ أثناء القراءة' });
        }
        res.type('json').send(data);
    });
});

// عرض محتوى papers.json عند طلب /papers.json
app.get('/papers.json', (req, res) => {
    const filePath = path.join(__dirname, 'papers.json');
    fs.readFile(filePath, 'utf8', (err, data) => {
        if (err) {
            return res.json({ papers: [] });
        }
        try {
            res.json(JSON.parse(data));
        } catch (e) {
            res.json({ papers: [] });
        }
    });
});
// Endpoint لإرجاع أسماء الورق الداخلي حسب الشروط المطلوبة
app.get('/inner-paper-types', (req, res) => {
    const filePath = path.join(__dirname, 'papers.json');
    fs.readFile(filePath, 'utf8', (err, data) => {
        if (err) return res.status(500).json({ types: [] });
        try {
            const json = JSON.parse(data);
            const papers = Array.isArray(json.papers) ? json.papers : [];
            // استخراج أسماء الورق حسب الشروط
            const types = papers
                .filter(arr => arr[0] === "ورق" && arr[2] === true)
                .map(arr => arr[1]);
            res.json({ types });
            } catch (e) {
                res.status(500).json({ types: [] });
            }
        });
    });

// Endpoint لإرجاع أسماء الورق الداخلي حسب الشروط المطلوبة
app.get('/inner-envelop-types', (req, res) => {
    const filePath = path.join(__dirname, 'papers.json');
    fs.readFile(filePath, 'utf8', (err, data) => {
        if (err) return res.status(500).json({ types: [] });
        try {
            const json = JSON.parse(data);
            const papers = Array.isArray(json.papers) ? json.papers : [];
            // استخراج أسماء ظرف حسب الشروط
            const types = papers
                .filter(arr => arr[0] === "ظرف" && arr[2] === true)
                .map(arr => arr[1]);
            res.json({ types });
            } catch (e) {
                res.status(500).json({ types: [] });
            }
        });
    });


// حفظ بيانات الورق في papers.json
app.post('/save-papers', (req, res) => {
    const papersData = req.body;
    const filePath = path.join(__dirname, 'papers.json');
    fs.writeFile(filePath, JSON.stringify(papersData, null, 2), (err) => {
        if (err) {
            return res.status(500).json({ success: false, message: 'خطأ في حفظ البيانات' });
        }
        res.json({ success: true, message: 'تم حفظ البيانات بنجاح' });
    });
});

// الصفحة الرئيسية
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'index.html'));
});

// مسار خاص لخدمة users-table.html مباشرة
app.get('/users-table.html', (req, res) => {
    res.sendFile(path.join(__dirname, 'users-table.html'));
});

// -----------------------------
// إعدادات عامة
// -----------------------------
// ...existing code...
// عرض محتوى settings.json عند طلب /settings.json
app.get('/settings.json', (req, res) => {
    const filePath = path.join(__dirname, 'settings.json');
    fs.readFile(filePath, 'utf8', (err, data) => {
        if (err) {
            return res.json({});
        }
        try {
            res.json(JSON.parse(data));
        } catch (e) {
            res.json({});
        }
    });
});
// حفظ الإعدادات الثابتة في ملف settings.json
app.post('/save-settings', (req, res) => {
    const settings = req.body;
    const filePath = path.join(__dirname, 'settings.json');
    fs.writeFile(filePath, JSON.stringify(settings, null, 2), 'utf8', err => {
        if (err) {
            return res.status(500).json({ success: false, message: 'حدث خطأ أثناء الحفظ' });
        }
        res.json({ success: true });
    });
});

// إعدادات CORS بشكل يدوي لجميع المسارات
app.use((req, res, next) => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    if (req.method === 'OPTIONS') {
        return res.sendStatus(200);
    }
    next();
});
app.use(express.json({ limit: '10mb' }));
app.use(express.static(__dirname));

// إضافة endpoint للـ health check وإبقاء السيرفر نشطاً
app.get('/health', (req, res) => {
    res.status(200).json({ 
        status: 'OK', 
        timestamp: new Date().toISOString(),
        uptime: process.uptime(),
        message: 'السيرفر يعمل بشكل طبيعي'
    });
});

// إضافة endpoint خاص للـ ping لمنع النوم
app.get('/ping', (req, res) => {
    res.status(200).json({ 
        ping: 'pong', 
        time: new Date().toISOString()
    });
});

// مهمة دورية لإبقاء السيرفر نشطاً (self-ping كل دقيقة ونصف)
const keepAlive = () => {
    const http = require('http');
    
    setInterval(() => {
        const options = {
            hostname: 'localhost',
            port: PORT,
            path: '/ping',
            method: 'GET'
        };
        
        const req = http.request(options, (res) => {
            console.log(`Keep-alive ping: ${res.statusCode} - ${new Date().toLocaleTimeString('ar-SA')}`);
        });
        
        req.on('error', (err) => {
            console.log('Keep-alive ping error:', err.message);
        });
        
        req.end();
    }, 90000); // كل دقيقة ونصف (90 ثانية)
};

app.listen(PORT, () => {
    console.log(`Server running on http://localhost:${PORT}`);
    
    // بدء مهمة إبقاء السيرفر نشطاً
    setTimeout(() => {
        keepAlive();
        console.log('Keep-alive service started - سيتم إرسال ping كل 90 ثانية');
    }, 5000); // انتظار 5 ثوان بعد بدء السيرفر
});
