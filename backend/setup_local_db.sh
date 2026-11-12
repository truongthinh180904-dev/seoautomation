#!/bin/bash
set -e

echo "=========================================="
echo "  AuraSEO - Local Database Setup & Seed   "
echo "=========================================="

# 1. Tạo database nếu chưa tồn tại (sử dụng PHP PDO để không phụ thuộc mysql CLI trong PATH)
echo "[1/4] Đang tạo database DBAURASEO trong MySQL localhost..."
php -r '
$pdo = new PDO("mysql:host=127.0.0.1;port=3306", "root", "123456");
$pdo->exec("CREATE DATABASE IF NOT EXISTS DBAURASEO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
' || {
    echo "Lưu ý: Không thể kết nối MySQL tại 127.0.0.1:3306."
    echo "Hãy kiểm tra MySQL service đang chạy và tạo database tên: DBAURASEO"
}

cd "$(dirname "$0")"

# 2. Xóa cache config
echo "[2/4] Xóa cache cấu hình Laravel..."
php artisan config:clear
php artisan cache:clear

# 3. Chạy migration
echo "[3/4] Chạy Database Migrations..."
php artisan migrate --force

# 4. Nạp dữ liệu Seeder (Admin, Workspace, Credits, Demo Website & SEO Data)
echo "[4/4] Nạp dữ liệu mẫu (Seeder)..."
php artisan db:seed --force

echo "=========================================="
echo " ĐÃ HOÀN TẤT SETUP DATABASE & DỮ LIỆU!"
echo " Tài khoản Super Admin:"
echo "   - Email: admin@example.com"
echo "   - Mật khẩu: password"
echo "   - Credits: 500"
echo "=========================================="
