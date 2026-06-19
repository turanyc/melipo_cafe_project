$port = 8080
Write-Host "Melipo Cafe PHP Yerel Geliştirme Sunucusu" -ForegroundColor Cyan
Write-Host "Sunucu adresi: http://localhost:$port" -ForegroundColor Green
Write-Host "Durdurmak için Ctrl+C tuşlarına basın." -ForegroundColor DarkGray
Start-Process "http://localhost:$port"
php -S localhost:$port
