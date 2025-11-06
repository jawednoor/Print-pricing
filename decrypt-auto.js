// ================================================================================
//                    🔓 نظام فك تشفير تلقائي لملف مراجعة.txt
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
const INPUT_FILE = path.join(__dirname, 'مراجعة.txt.encrypted');
const OUTPUT_FILE = path.join(__dirname, 'مراجعة.txt.decrypted');

console.log('================================================================================');
console.log('                    🔓 فك تشفير ملف مراجعة.txt');
console.log('================================================================================\n');

// التحقق من وجود الملف المشفر
if (!fs.existsSync(INPUT_FILE)) {
    console.log('❌ الملف المشفر غير موجود: ' + INPUT_FILE);
    console.log('⚠️  تأكد من تشفير الملف أولاً باستخدام: node encrypt-auto.js\n');
    process.exit(1);
}

// توليد رمز OTP الحالي تلقائياً
const otpCode = authenticator.generate(OTP_SECRET);

console.log('🔑 استخدام رمز OTP الحالي: ' + otpCode);
console.log('🔄 جاري فك التشفير...\n');

try {
    // قراءة الملف المشفر
    const encryptedData = JSON.parse(fs.readFileSync(INPUT_FILE, 'utf8'));
    
    // استخراج IV والمحتوى المشفر
    const iv = Buffer.from(encryptedData.iv, 'hex');
    const encrypted = encryptedData.encrypted;
    
    // إنشاء مفتاح فك التشفير (نفس طريقة التشفير)
    const key = crypto.createHash('sha256')
        .update(OTP_SECRET + otpCode)
        .digest();
    
    // فك التشفير باستخدام AES-256-CBC
    const decipher = crypto.createDecipheriv('aes-256-cbc', key, iv);
    let decrypted = decipher.update(encrypted, 'hex', 'utf8');
    decrypted += decipher.final('utf8');
    
    // حفظ الملف المفكوك
    fs.writeFileSync(OUTPUT_FILE, decrypted, 'utf8');
    
    // إحصائيات
    const encryptedSize = fs.statSync(INPUT_FILE).size;
    const decryptedSize = Buffer.byteLength(decrypted, 'utf8');
    
    console.log('✅ تم فك التشفير بنجاح!\n');
    console.log('📊 الإحصائيات:');
    console.log('   🔒 الملف المشفر: ' + INPUT_FILE);
    console.log('   📄 الملف المفكوك: ' + OUTPUT_FILE);
    console.log('   📏 الحجم المشفر: ' + formatBytes(encryptedSize));
    console.log('   📏 الحجم الأصلي: ' + formatBytes(decryptedSize));
    console.log('   🕒 وقت فك التشفير: ' + new Date().toLocaleString('ar-SA'));
    console.log('   🔑 رمز OTP المستخدم: ' + otpCode);
    
    if (encryptedData.timestamp) {
        console.log('   📅 تاريخ التشفير: ' + new Date(encryptedData.timestamp).toLocaleString('ar-SA'));
    }
    
    if (encryptedData.otpUsed) {
        console.log('   🔑 رمز OTP الأصلي للتشفير: ' + encryptedData.otpUsed);
    }
    
    console.log('\n✅ يمكنك الآن فتح الملف: ' + OUTPUT_FILE);
    console.log('\n⚠️  ملاحظة أمنية:');
    console.log('   - احذف الملف المفكوك بعد الاطلاع عليه للحفاظ على الأمان');
    console.log('   - الملف الأصلي المشفر محفوظ بأمان في: ' + INPUT_FILE);
    console.log('   - استخدم: del ' + path.basename(OUTPUT_FILE) + ' (لحذف الملف المفكوك)');
    console.log('\n================================================================================\n');
    
} catch (error) {
    console.log('❌ حدث خطأ أثناء فك التشفير:');
    
    if (error.message.includes('bad decrypt')) {
        console.log('   ⚠️  رمز OTP الحالي لا يطابق الرمز المستخدم في التشفير');
        console.log('   ⚠️  انتظر 30 ثانية وحاول مرة أخرى للحصول على رمز جديد');
    } else if (error.message.includes('Unexpected token')) {
        console.log('   ⚠️  الملف المشفر تالف أو تم التلاعب به');
    } else {
        console.log('   ' + error.message);
    }
    
    console.log('\n');
}

// دالة مساعدة لتنسيق الحجم
function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}
