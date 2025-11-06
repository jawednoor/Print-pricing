// معالج التحديثات للتطبيق - يضمن وصول الإشعارات للمستخدم
console.log('Update handler loaded');

// فحص إذا كان التطبيق مثبت (standalone mode)
function isAppInstalled() {
    // فحص إذا كان يعمل في وضع standalone
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
                        window.navigator.standalone === true ||
                        document.referrer.includes('android-app://');
    
    console.log('App installed check:', isStandalone);
    return isStandalone;
}

// التأكد من تشغيل الكود بعد تحميل الصفحة
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initUpdateHandler);
} else {
    initUpdateHandler();
}

function initUpdateHandler() {
    console.log('Initializing update handler...');
    
    // التحقق من دعم Service Worker
    if ('serviceWorker' in navigator) {
        console.log('Service Worker supported');
        
        // الاستماع لرسائل Service Worker
        navigator.serviceWorker.addEventListener('message', handleServiceWorkerMessage);
        
        // التعامل مع registration الحالية - مهم للـ PWA المثبتة على الجوال
        navigator.serviceWorker.getRegistration().then(registration => {
            if (!registration) {
                console.log('No registration found');
                return;
            }

            // إذا كان هناك waiting worker بالفعل، فاطلب من المستخدم التحديث فوراً
            if (registration.waiting) {
                console.log('Found waiting service worker');
                promptUserToUpdate(registration);
                return;
            }

            // الاستماع عندما يتم العثور على تحديث جديد أثناء عمر الـ registration
            registration.addEventListener('updatefound', () => {
                console.log('Service worker update found');
                const newWorker = registration.installing;
                if (!newWorker) return;

                newWorker.addEventListener('statechange', () => {
                    console.log('New worker state:', newWorker.state);
                    if (newWorker.state === 'installed') {
                        // إذا كان هناك controller، يعني هذا أنه تحديث وليس تثبيت أولي
                        if (navigator.serviceWorker.controller) {
                            console.log('New worker installed and update ready');
                            promptUserToUpdate(registration);
                        } else {
                            console.log('Service worker installed for the first time');
                        }
                    }
                });
            });

            // الاستماع لتغيّر الـ controller - يحدث بعد skipWaiting
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                console.log('Controller changed - new service worker has taken control');
                // إعادة تحميل التطبيق للتأكد من تحميل الموارد الجديدة
                try {
                    window.location.reload(true);
                } catch (e) {
                    window.location.reload();
                }
            });

            // فحص دوري للتحديثات كل 15 ثانية - أقل تكرار مناسب للجوال
            setInterval(() => {
                console.log('Checking for updates...');
                registration.update();
            }, 15000);

            // تشغيل فوري للفحص
            console.log('Triggering immediate update check');
            registration.update();
        }).catch(err => {
            console.warn('Error getting service worker registration:', err);
        });
    }
}

function handleServiceWorkerMessage(event) {
    console.log('Received message from Service Worker:', event.data);
    
    if (!event.data) return;
    
    try {
        // التحقق من مصدر الرسالة
        if (!event.origin || !event.origin.startsWith(window.location.origin)) {
            throw new Error('Rejected message from unauthorized source');
        }
        
        // التحقق من صحة البيانات
        if (typeof event.data !== 'object') {
            throw new Error('Invalid message format');
        }
        
        // فحص إذا كان التطبيق مثبت - إظهار التحديثات فقط للتطبيقات المثبتة
        if (!isAppInstalled()) {
            console.log('App not installed - skipping update notification');
            return;
        }
        
        // التحقق من وجود timestamp وصلاحيته
        if (!event.data.timestamp || 
            Date.now() - new Date(event.data.timestamp).getTime() > 5 * 60 * 1000) { // 5 دقائق كحد أقصى
            throw new Error('Message timestamp invalid or expired');
        }
        
        const { type, message, version, timestamp } = event.data;
        
        // دعم عدة أنواع من الرسائل من Service Worker
        if (type === 'UPDATE_AVAILABLE' || type === 'UPDATE_READY' || type === 'UPDATE' || type === 'FORCE_RELOAD') {
            // على بعض الأجهزة/متصفحات، قد نحتاج فقط لإظهار حوار للتحديث
            promptSimpleUpdate(message || 'تحديث متوفر');
        }
    } catch (error) {
        console.error('Error handling service worker message:', error);
        return;
    }
}

function promptUserToUpdate(registration) {
    // فحص إذا كان التطبيق مثبت - إظهار التحديثات فقط للتطبيقات المثبتة
    if (!isAppInstalled()) {
        console.log('App not installed - skipping update prompt');
        return;
    }
    
    // عرض حوار مخصص للمستخدم مع تفاصيل و خيار للتحديث الآن
    const userAccepted = confirm('🔄 يوجد تحديث جديد للتطبيق!\n\nالتحديث يتضمن تحسينات وميزات جديدة.\nهل تريد تفعيله الآن؟\n\n✅ سيتم إعادة تحميل التطبيق بعد التحديث');
    if (userAccepted) {
        // أرسل رسالة للـ waiting worker لطلب skipWaiting
        if (registration.waiting) {
            console.log('Sending SKIP_WAITING to waiting worker');
            registration.waiting.postMessage({ type: 'SKIP_WAITING' });
        }
    } else {
        console.log('User postponed the update');
    }
}

function promptSimpleUpdate(message) {
    // فحص إذا كان التطبيق مثبت - إظهار التحديثات فقط للتطبيقات المثبتة
    if (!isAppInstalled()) {
        console.log('App not installed - skipping simple update prompt');
        return;
    }
    
    const accepted = confirm(`🔄 ${message || 'تحديث متاح'}\n\n📱 هل تريد تحديث التطبيق الآن؟`);
    if (accepted) performUpdate();
}

function performUpdate() {
    console.log('Performing update...');
    showUpdateProgress();
    if ('caches' in window) {
        caches.keys().then(cacheNames => {
            console.log('Clearing caches:', cacheNames);
            return Promise.all(cacheNames.map(name => caches.delete(name)));
        }).then(() => {
            // ensure reload after caches cleared
            try {
                window.location.reload(true);
            } catch (e) {
                window.location.reload();
            }
        });
    } else {
        try { window.location.reload(true); } catch (e) { window.location.reload(); }
    }
}

function showUpdateProgress() {
    // إنشاء عنصر لإظهار رسالة التحديث
    const existing = document.getElementById('update-progress');
    if (existing) return;

    const progressDiv = document.createElement('div');
    progressDiv.id = 'update-progress';
    progressDiv.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #4CAF50;
        color: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        z-index: 10000;
        font-family: 'Cairo', Arial, sans-serif;
        text-align: center;
        direction: rtl;
    `;
    progressDiv.innerHTML = `
        <div>🔄 جاري تحديث التطبيق...</div>
        <div style="margin-top: 10px; font-size: 14px;">يرجى الانتظار</div>
    `;
    
    document.body.appendChild(progressDiv);
}

function showUpdateReminder(message, version) {
    const reminder = confirm(
        `🔔 تذكير: يوجد تحديث متوفر\n\n` +
        `${message}\n` +
        `الإصدار: ${version || 'غير محدد'}\n\n` +
        `هل تريد التحديث الآن؟`
    );
    
    if (reminder) {
        performUpdate();
    }
}

// التحقق عند عودة النافذة إلى التركيز
window.addEventListener('focus', () => {
    console.log('Window focused, checking for updates...');
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistration().then(registration => {
            if (registration) registration.update();
        });
    }
});

console.log('Update handler setup complete');