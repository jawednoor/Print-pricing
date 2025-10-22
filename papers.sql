-- قاعدة بيانات MySQL لجدول بيانات الورق
CREATE TABLE papers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paper_name VARCHAR(255) NOT NULL, -- اسم الورق
    available BOOLEAN NOT NULL,        -- التوفر
    price DECIMAL(10,2) NOT NULL,     -- السعر
    size VARCHAR(20) NOT NULL,        -- المقاس
    count_per_pack INT NOT NULL,      -- عدد الورق لكل رزمة
    a3 INT DEFAULT 0,                 -- A3
    a4 INT DEFAULT 0,                 -- A4
    a5 INT DEFAULT 0,                 -- A5
    a6 INT DEFAULT 0,                 -- A6
    a7 INT DEFAULT 0,                 -- A7
    carton INT DEFAULT 0              -- الكرت
);

-- مثال لإدخال بيانات
INSERT INTO papers (paper_name, available, price, size, count_per_pack, a3, a4, a5, a6, a7, carton)
VALUES ('ورق طباعة', true, 120.50, '70X100', 500, 10, 20, 30, 40, 50, 5);
