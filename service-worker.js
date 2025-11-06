const CACHE_NAME = 'basmahart-v10';
const urlsToCache = [
  './',
  './index.html',
  './categories.html',
  './books-magazines.html',
  './brochures-stickers.html',
  './letters-memos.html',
  './envelopes.html',
  './invoices.html',
  './manifest.json',
  './img/logo-app_1.png',
  './img/logo-albasmahart.png',
  './img/logo-albasmahart2.png',
  './img/bg.gif',
  './user-data.json',
  './papers.json',
  './settings.json',
  './values.json',
  './update-handler.js'
];

// تثبيت service worker وحفظ الملفات في الكاش
self.addEventListener('install', function(event) {
  console.log('Service Worker installing... v10 - INSTALLED-ONLY UPDATES');
  // إجبار التنشيط الفوري بدون انتظار
  self.skipWaiting();
  
  event.waitUntil(
    // مسح جميع الكاشات القديمة أولاً
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          console.log('Deleting cache:', cacheName);
          return caches.delete(cacheName);
        })
      );
    }).then(() => {
      // فتح كاش جديد تماماً
      return caches.open(CACHE_NAME);
    }).then(function(cache) {
      console.log('Opened fresh cache v10');
      // إضافة الملفات الجديدة
      return cache.addAll(urlsToCache);
    })
  );
});

// تنشيط service worker
self.addEventListener('activate', function(event) {
  console.log('Service Worker activating... v10 - INSTALLED-ONLY UPDATES');
  event.waitUntil(
    // إجبار السيطرة على جميع التبويبات المفتوحة
    clients.claim().then(() => {
      console.log('Service Worker v10 now controls all pages');

      // إرسال إشعار فوري ومباشر لجميع العملاء
      return clients.matchAll({includeUncontrolled: true, type: 'window'}).then(clientList => {
        console.log('Found', clientList.length, 'clients to notify');
        clientList.forEach((client, index) => {
          console.log('Sending notification to client', index);
          client.postMessage({
            type: 'UPDATE_AVAILABLE',
            message: 'تحديث v10 متوفر - تحديثات للتطبيقات المثبتة فقط',
            version: 'v10',
            forced: true,
            timestamp: new Date().toLocaleTimeString('ar-SA')
          });
        });
      });
    }).then(() => {
      // مسح جميع الكاشات مرة أخرى للتأكد
      return caches.keys().then(function(cacheNames) {
        return Promise.all(
          cacheNames.map(function(cacheName) {
            if (cacheName !== CACHE_NAME) {
              console.log('Force deleting cache during activate:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      });
    })
  );
});

// استرداد الملفات - دائماً جلب النسخة الجديدة للملفات المهمة
self.addEventListener('fetch', function(event) {
  // الملفات المهمة التي نريد تحديثها دائماً
  const importantFiles = ['manifest.json', 'img/logo-app_1.png', 'img/logo-albasmahart.png', 'index.html'];
  const url = new URL(event.request.url);
  const isImportantFile = importantFiles.some(file => url.pathname.includes(file));
  
  if (isImportantFile) {
    // للملفات المهمة، جلب النسخة الجديدة دائماً
    event.respondWith(
      fetch(event.request).then(response => {
        // حفظ في الكاش
        if (response.ok) {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseClone);
          });
        }
        return response;
      }).catch(() => {
        // في حالة عدم توفر الإنترنت، استخدم الكاش
        return caches.match(event.request);
      })
    );
  } else {
    // للملفات الأخرى، استخدم الطريقة العادية
    event.respondWith(
      caches.match(event.request)
        .then(function(response) {
          if (response) {
            return response;
          }
          return fetch(event.request);
        }
      )
    );
  }
});

// التعامل مع الرسائل من العميل
self.addEventListener('message', function(event) {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    console.log('Force skipping waiting...');
    self.skipWaiting();
  }
});