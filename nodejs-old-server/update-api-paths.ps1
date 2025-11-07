# سكريبت PowerShell لتحديث جميع ملفات HTML لاستخدام server.php

$files = @(
    "index.html",
    "cpanel.html",
    "values.html",
    "settings.html",
    "papers.html",
    "users-table.html",
    "categories.html",
    "invoices.html",
    "books-magazines.html",
    "brochures-stickers.html",
    "letters-memos.html",
    "envelopes.html",
    "folder.html"
)

foreach ($file in $files) {
    $path = "D:\مواقع ويب\quick\$file"
    if (Test-Path $path) {
        Write-Host "تعديل $file..." -ForegroundColor Yellow
        
        $content = Get-Content $path -Raw -Encoding UTF8
        
        # استبدال جميع fetch calls
        $content = $content -replace "fetch\('/login'", "fetch('server.php/login'"
        $content = $content -replace "fetch\('/save-users'", "fetch('server.php/save-users'"
        $content = $content -replace "fetch\('/save-papers'", "fetch('server.php/save-papers'"
        $content = $content -replace "fetch\('/save-settings'", "fetch('server.php/save-settings'"
        $content = $content -replace "fetch\('/settings-display'", "fetch('server.php/settings-display'"
        $content = $content -replace "fetch\('/inner-paper-types'", "fetch('server.php/inner-paper-types'"
        $content = $content -replace "fetch\('/inner-envelop-types'", "fetch('server.php/inner-envelop-types'"
        $content = $content -replace "fetch\('/ping'", "fetch('server.php/ping'"
        $content = $content -replace "fetch\('/health'", "fetch('server.php/health'"
        
        # ملفات JSON تبقى كما هي (لا تحتاج server.php)
        # '/values.json', '/papers.json', '/settings.json', '/user-data.json'
        
        $content | Set-Content $path -Encoding UTF8 -NoNewline
        Write-Host "✓ تم تعديل $file" -ForegroundColor Green
    } else {
        Write-Host "✗ لم يتم العثور على $file" -ForegroundColor Red
    }
}

Write-Host "`n✓ تم الانتهاء من تحديث جميع الملفات!" -ForegroundColor Cyan
