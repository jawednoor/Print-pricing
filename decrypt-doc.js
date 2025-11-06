// ================================================================================
//                    🔓 نظام فك تشفير ملف مراجعة.txt
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
    console.log('⚠️  تأكد من تشفير الملف أولاً باستخدام: node encrypt-doc.js\n');
    process.exit(1);
}

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
        
        if (encryptedData.timestamp) {
            console.log('   📅 تاريخ التشفير: ' + new Date(encryptedData.timestamp).toLocaleString('ar-SA'));
        }
        
        console.log('\n✅ يمكنك الآن فتح الملف: ' + OUTPUT_FILE);
        console.log('\n⚠️  ملاحظة أمنية:');
        console.log('   - احذف الملف المفكوك بعد الاطلاع عليه للحفاظ على الأمان');
        console.log('   - الملف الأصلي المشفر محفوظ بأمان في: ' + INPUT_FILE);
        console.log('\n================================================================================\n');
        
    } catch (error) {
        console.log('❌ حدث خطأ أثناء فك التشفير:');
        
        if (error.message.includes('bad decrypt')) {
            console.log('   ⚠️  رمز OTP غير صحيح أو الملف تالف');
            console.log('   ⚠️  تأكد من استخدام نفس الرمز المستخدم في التشفير');
        } else if (error.message.includes('Unexpected token')) {
            console.log('   ⚠️  الملف المشفر تالف أو تم التلاعب به');
        } else {
            console.log('   ' + error.message);
        }
        
        console.log('\n');
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
