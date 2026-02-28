<?php

function ensure_articles_schema(PDO $pdo): void
{
    $migrations = [
        "ALTER TABLE articles ADD COLUMN summary varchar(255) DEFAULT NULL",
        "ALTER TABLE articles ADD COLUMN image_path varchar(255) DEFAULT NULL",
        "ALTER TABLE articles ADD COLUMN author_name varchar(120) DEFAULT NULL",
        "ALTER TABLE articles ADD COLUMN published_at date DEFAULT NULL",
        "ALTER TABLE articles ADD COLUMN is_active tinyint(1) NOT NULL DEFAULT 1",
    ];

    foreach ($migrations as $sql) {
        try {
            $pdo->query($sql);
        } catch (Throwable $e) {
            // ignore if column already exists
        }
    }
}

function seed_default_articles(PDO $pdo): void
{
    $total = (int) $pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn();
    if ($total > 0) {
        return;
    }

    $insert = $pdo->prepare("
        INSERT INTO articles (title, summary, content, category, author_name, published_at, is_active, created_by)
        VALUES (?, ?, ?, ?, ?, ?, 1, NULL)
    ");

    $defaults = [
        [
            'ARTIKEL PENTINGNYA PENDIDIKAN BAGI MASA DEPAN',
            'Membahas alasan pendidikan menjadi pondasi utama masa depan generasi muda.',
            'Pendidikan adalah investasi jangka panjang yang berdampak pada kualitas hidup dan kesempatan kerja.',
            'education',
            'Dispendik Mojokerto',
            '2023-08-15',
        ],
        [
            '8 Cara Membuat Katalog Online untuk Tingkatkan Bisnis',
            'Tips praktis membuat katalog online agar promosi produk lebih efektif.',
            'Katalog online yang baik membantu pelanggan memahami produk dengan cepat dan meningkatkan konversi.',
            'artikel',
            'Redaksi Jagoan Hosting',
            '2023-09-23',
        ],
        [
            'Cara Penerima Beasiswa Amanah Bangun Desa Memasuki Tahap Implementasi Proyek',
            'Program beasiswa mulai masuk tahap implementasi di lapangan.',
            'Tahap implementasi proyek menjadi langkah penting agar hasil pembinaan benar-benar berdampak.',
            'education',
            'Kompasiana',
            '2023-09-14',
        ],
    ];

    foreach ($defaults as $row) {
        $insert->execute($row);
    }
}
