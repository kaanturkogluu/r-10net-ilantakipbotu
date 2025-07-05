<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';  // PHPMailer autoload

// Ayarlar
$url = "https://www.r10.net/yazilim-kodlama-is-verenler/";
$file = __DIR__ . '/ilanlar.txt';
$mailTo = "kaantrrkoglu@gmail.com";  // Mail gönderilecek adres
$mailSubject = "Yeni R10 İlanları";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
    CURLOPT_REFERER => "https://www.r10.net/",
    CURLOPT_ENCODING => "",
]);

$html = curl_exec($ch);
curl_close($ch);

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

$threads = $xpath->query("//li[contains(@class, 'thread')]");

// Eski veriler
$existingData = [];
if (file_exists($file)) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (empty(trim($line))) {
            continue;
        }
        $fields = str_getcsv($line);
        if (count($fields) >= 4) {  // Ensure we have all required fields
            $existingData[$fields[1]] = $fields;
        }
    }
}

$newEntries = [];
$currentEntries = [];

foreach ($threads as $thread) {
    $titleNode = $xpath->query(".//a[starts-with(@id, 'thread_title_')]", $thread)->item(0);
    $title = $titleNode ? trim($titleNode->textContent) : "Yok";
    $link = $titleNode ? $titleNode->getAttribute('href') : "#";

    $userNode = $xpath->query(".//div[contains(@class,'desktop')]/a[contains(@class,'usernames')]", $thread)->item(0);
    $username = $userNode ? trim($userNode->textContent) : "Yok";

    $dateNode = $xpath->query(".//div[contains(@class, 'date')]", $thread)->item(0);
    $date = $dateNode ? trim($dateNode->textContent) : "Yok";

    $currentEntries[$link] = [$title, $link, $username, $date];
    
    if (!isset($existingData[$link])) {
        $newEntries[$link] = [$title, $link, $username, $date];
    }
}

 file_put_contents('isilanlar.json', json_encode($currentEntries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Yeni ilanları dosyanın başına ekle
if (!empty($newEntries)) {
    try {
        // Önce mevcut içeriği oku
        $currentContent = file_exists($file) ? file_get_contents($file) : '';
        
        // Yeni içeriği oluştur
        $newContent = '';
        foreach ($newEntries as $entry) {
            $newContent .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $entry)) . "\n";
        }
        
        // Yeni içeriği dosyanın başına ekle
        if (file_put_contents($file, $newContent . $currentContent) === false) {
            throw new Exception("Dosya yazma hatası oluştu.");
        }
        
        // Mail gönder
        $mail = new PHPMailer(true);
        
        // SMTP Ayarları
        $mail->isSMTP();
        $mail->Host       = '#';
        $mail->SMTPAuth   = true;
        $mail->Username   = '#';
        $mail->Password   = '#';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';
        
        // Gönderen ve alıcı
        $mail->setFrom('#', 'R10 İlan Takip');
        $mail->addAddress($mailTo);
        
        // Mail içeriği
        $mail->isHTML(true);
        $mail->Subject = $mailSubject;
        
        $htmlMessage = "<h2>R10'da Yeni İlanlar Bulundu</h2>";
        $htmlMessage .= "<p>Toplam " . count($newEntries) . " yeni ilan bulundu.</p>";
        $htmlMessage .= "<ul>";
        foreach ($newEntries as $entry) {
            $htmlMessage .= "<li><strong>" . htmlspecialchars($entry[0]) . "</strong><br>";
            $htmlMessage .= "Kullanıcı: " . htmlspecialchars($entry[2]) . "<br>";
            $htmlMessage .= "Tarih: " . htmlspecialchars($entry[3]) . "<br>";
            $htmlMessage .= "<a href='" . htmlspecialchars($entry[1]) . "' target='_blank'>İlana Git</a></li><br>";
        }
        $htmlMessage .= "</ul>";
        
        $mail->Body = $htmlMessage;
        
        if (!$mail->send()) {
            throw new Exception("Mail gönderilemedi: " . $mail->ErrorInfo);
        }
        
        echo "<div class='alert alert-success'>Yeni ilanlar için mail gönderildi.</div>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Hata: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "<div class='alert alert-info'>Yeni ilan bulunamadı.</div>";
}

// HTML Tablo çıktısı
echo "<!DOCTYPE html><html lang='tr'><head><meta charset='UTF-8'><title>R10 İlanları</title>";
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">';
echo "</head><body><div class='container mt-4'>";
echo "<h2>R10 Yazılım - Kodlama İş Verenler İlanları</h2>";

if (empty($threads)) {
    echo "<p>İlan bulunamadı.</p>";
} else {
    echo "<table class='table table-bordered table-striped'>";
    echo "<thead><tr><th>Başlık</th><th>Link</th><th>Kullanıcı</th><th>Tarih</th></tr></thead><tbody>";

    foreach ($threads as $thread) {
        $titleNode = $xpath->query(".//a[starts-with(@id, 'thread_title_')]", $thread)->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : "Yok";
        $link = $titleNode ? $titleNode->getAttribute('href') : "#";

        $userNode = $xpath->query(".//div[contains(@class,'desktop')]/a[contains(@class,'usernames')]", $thread)->item(0);
        $username = $userNode ? trim($userNode->textContent) : "Yok";

        $dateNode = $xpath->query(".//div[contains(@class, 'date')]", $thread)->item(0);
        $date = $dateNode ? trim($dateNode->textContent) : "Yok";

        echo "<tr>
            <td>" . htmlspecialchars($title) . "</td>
            <td><a href='" . htmlspecialchars($link) . "' target='_blank'>İlana Git</a></td>
            <td>" . htmlspecialchars($username) . "</td>
            <td>" . htmlspecialchars($date) . "</td>
        </tr>";
    }

    echo "</tbody></table>";
}

echo "</div></body></html>";
