// ================================================================================
//                    🔐 نظام تشفير ملف مراجعة.txt
//                باستخدام Google Authenticator OTP
// ================================================================================

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { authenticator } = require('otplib');

// إعدادات OTP (نفس إعدادات server.js)
const OTP_SECRET = 'JBSWY3DPEHPK3PXP';
authenticator.options = { step: 30, digits: 6, algorithm: 'sha1' };

// المسارات
const INPUT_FILE = path.join(__dirname, 'مراجعة.txt');
const OUTPUT_FILE = path.join(__dirname, 'مراجعة.txt.encrypted');

console.log('================================================================================');
console.log('                    🔐 تشفير ملف مراجعة.txt');
console.log('================================================================================\n');

// طلب رمز OTP من المستخدم
const readline = require('readline');
const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
});

rl.question('أدخل رمز Google Authenticator (6 أرقام): ', (otpCode) => {
    
    // التحقق من صحة رمز OTP
    const isValid = authenticator.check(otpCode.trim(), OTP_SECRET);
    
    if (!isValid) {
        console.log('\n❌ رمز التحقق غير صحيح!');
        console.log('⚠️  تأكد من الرمز في Google Authenticator وحاول مرة أخرى.\n');
        rl.close();
        process.exit(1);
    }
    
    console.log('\n✅ رمز التحقق صحيح!');
    console.log('🔄 جاري تشفير الملف...\n');
    
    try {
        // قراءة محتوى الملف
        const fileContent = fs.readFileSync(INPUT_FILE, 'utf8');
        
        // استخدام رمز OTP كمفتاح تشفير (مع تحويله لـ 32 بايت)
        // نستخدم hash لإنشاء مفتاح ثابت من OTP_SECRET + رمز OTP الحالي
        const key = crypto.createHash('sha256')
            .update(OTP_SECRET + otpCode)
            .digest();
        
        // إنشاء IV عشوائي (Initialization Vector)
        const iv = crypto.randomBytes(16);
        
        // تشفير المحتوى باستخدام AES-256-CBC
        const cipher = crypto.createCipheriv('aes-256-cbc', key, iv);
        let encrypted = cipher.update(fileContent, 'utf8', 'hex');
        encrypted += cipher.final('hex');
        
        // حفظ IV + المحتوى المشفر
        const result = {
            iv: iv.toString('hex'),
            encrypted: encrypted,
            timestamp: new Date().toISOString(),
            note: 'هذا الملف مشفر باستخدام Google Authenticator OTP'
        };
        
        // حفظ الملف المشفر
        fs.writeFileSync(OUTPUT_FILE, JSON.stringify(result, null, 2), 'utf8');
        
        // إحصائيات
        const originalSize = Buffer.byteLength(fileContent, 'utf8');
        const encryptedSize = fs.statSync(OUTPUT_FILE).size;
        
        console.log('✅ تم التشفير بنجاح!\n');
        console.log('📊 الإحصائيات:');
        console.log('   📄 الملف الأصلي: ' + INPUT_FILE);
        console.log('   🔒 الملف المشفر: ' + OUTPUT_FILE);
        console.log('   📏 الحجم الأصلي: ' + formatBytes(originalSize));
        console.log('   📏 الحجم المشفر: ' + formatBytes(encryptedSize));
        console.log('   🕒 وقت التشفير: ' + new Date().toLocaleString('ar-SA'));
        console.log('\n⚠️  ملاحظات مهمة:');
        console.log('   1. لفك التشفير، ستحتاج لرمز Google Authenticator الحالي');
        console.log('   2. الرمز يتغير كل 30 ثانية، احفظ النسخة الأصلية في مكان آمن');
        console.log('   3. استخدم الأمر: node decrypt-doc.js لفك التشفير');
        console.log('\n================================================================================\n');
        
    } catch (error) {
        console.log('❌ حدث خطأ أثناء التشفير:');
        console.log('   ' + error.message + '\n');
    }
    
    rl.close();
});

// دالة مساعدة لتنسيق الحجم
function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}
