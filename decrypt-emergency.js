// ================================================================================
//      🔓 فك تشفير باستخدام الرمز المحفوظ في الملف (للطوارئ فقط)
// ================================================================================

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const INPUT_FILE = path.join(__dirname, 'مراجعة.txt.encrypted');
const OUTPUT_FILE = path.join(__dirname, 'مراجعة.txt.decrypted');
const OTP_SECRET = 'JBSWY3DPEHPK3PXP';

console.log('================================================================================');
console.log('          🔓 فك تشفير باستخدام الرمز المحفوظ (للطوارئ)');
console.log('================================================================================\n');

try {
    // قراءة الملف المشفر
    const encryptedData = JSON.parse(fs.readFileSync(INPUT_FILE, 'utf8'));
    
    if (!encryptedData.otpUsed) {
        console.log('❌ الملف لا يحتوي على رمز OTP محفوظ');
        process.exit(1);
    }
    
    const otpCode = encryptedData.otpUsed;
    console.log('🔑 استخدام رمز OTP المحفوظ: ' + otpCode);
    console.log('🔄 جاري فك التشفير...\n');
    
    // استخراج IV والمحتوى المشفر
    const iv = Buffer.from(encryptedData.iv, 'hex');
    const encrypted = encryptedData.encrypted;
    
    // إنشاء مفتاح فك التشفير
    const key = crypto.createHash('sha256')
        .update(OTP_SECRET + otpCode)
        .digest();
    
    // فك التشفير
    const decipher = crypto.createDecipheriv('aes-256-cbc', key, iv);
    let decrypted = decipher.update(encrypted, 'hex', 'utf8');
    decrypted += decipher.final('utf8');
    
    // حفظ الملف المفكوك
    fs.writeFileSync(OUTPUT_FILE, decrypted, 'utf8');
    
    console.log('✅ تم فك التشفير بنجاح!\n');
    console.log('📊 الإحصائيات:');
    console.log('   📄 الملف المفكوك: ' + OUTPUT_FILE);
    console.log('   🔑 رمز OTP المستخدم: ' + otpCode);
    console.log('   📅 تاريخ التشفير: ' + new Date(encryptedData.timestamp).toLocaleString('ar-SA'));
    console.log('\n⚠️  تحذير: هذه الطريقة للطوارئ فقط!');
    console.log('   - استخدم decrypt-doc.js للأمان الأفضل');
    console.log('\n================================================================================\n');
    
} catch (error) {
    console.log('❌ حدث خطأ: ' + error.message + '\n');
}
