// ================================================================================
//                    📱 مولد رموز Google Authenticator
//                         للاستخدام في التشفير
// ================================================================================

const { authenticator } = require('otplib');

// إعدادات OTP (نفس إعدادات server.js)
const OTP_SECRET = 'JBSWY3DPEHPK3PXP';
authenticator.options = { step: 30, digits: 6, algorithm: 'sha1' };

console.log('================================================================================');
console.log('                    📱 مولد رموز Google Authenticator');
console.log('================================================================================\n');

// توليد الرمز الحالي
const currentToken = authenticator.generate(OTP_SECRET);

console.log('🔑 الرمز الحالي: ' + currentToken);
console.log('⏱️  صالح لمدة: 30 ثانية');
console.log('🕒 الوقت الآن: ' + new Date().toLocaleString('ar-SA'));

// حساب الوقت المتبقي
const timeRemaining = 30 - (Math.floor(Date.now() / 1000) % 30);
console.log('⏳ الوقت المتبقي: ' + timeRemaining + ' ثانية');

console.log('\n💡 استخدم هذا الرمز في:');
console.log('   - node encrypt-doc.js (للتشفير)');
console.log('   - node decrypt-doc.js (لفك التشفير)');
console.log('   - cpanel.html (لتسجيل الدخول)');

console.log('\n⚠️  ملاحظة: الرمز يتغير كل 30 ثانية تلقائياً');
console.log('\n================================================================================\n');

// عرض الأرقام التالية
console.log('الأرقام القادمة (للتوقع):');
setTimeout(() => {
    const nextToken = authenticator.generate(OTP_SECRET);
    console.log('   بعد 30 ثانية: ' + nextToken);
}, 30000);
