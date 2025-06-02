<?php
// Set header untuk mengizinkan CORS (Cross-Origin Resource Sharing)
// Ini penting agar JavaScript di browser Anda bisa memanggil skrip PHP ini.
// Di lingkungan produksi, ganti '*' dengan domain spesifik frontend Anda (misalnya 'http://localhost' jika Anda hanya menjalankannya secara lokal tanpa domain).
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); // Metode HTTP yang diizinkan
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Header yang diizinkan

// Handle preflight OPTIONS requests for CORS (ini diperlukan oleh browser sebelum permintaan POST)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Konfigurasi Database MySQL Anda
$servername = "localhost"; // Biasanya 'localhost' untuk XAMPP/Laragon
$username = "root";        // Ganti dengan username MySQL Anda
$password = "";            // Ganti dengan password MySQL Anda (default XAMPP/Laragon biasanya kosong)
$dbname = "currency_converter"; // Nama database yang sudah Anda buat dan perbaiki

// Buat koneksi ke Database
$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die(json_encode(["result" => "error", "message" => "Koneksi database gagal: " . $conn->connect_error]));
}

// API Key untuk ExchangeRate-API (PENTING: SIMPAN DI SISI SERVER UNTUK KEAMANAN)
// Pastikan ini adalah API Key Anda yang sebenarnya dari ExchangeRate-API.com
$api_key = '46a5280cac09665a80ca3c88'; 

// URL dasar API, SELALU gunakan USD sebagai base untuk akun gratis.
// Mata uang asal yang dipilih pengguna akan dikonversi ke USD terlebih dahulu.
$base_api_url_usd = "https://v6.exchangerate-api.com/v6/{$api_key}/latest/USD"; 

// Tangani permintaan berdasarkan parameter 'action'
$action = $_GET['action'] ?? ''; // Ambil nilai 'action' dari URL, default kosong jika tidak ada

if ($action === 'get_exchange_rate') {
    $from_currency = $_GET['from'] ?? '';
    $to_currency = $_GET['to'] ?? '';

    // Validasi input awal
    if (empty($from_currency) || empty($to_currency)) {
        echo json_encode(["result" => "error", "message" => "Mata uang asal dan tujuan diperlukan."]);
        exit();
    }

    // Ambil semua rate berdasarkan USD dari API eksternal
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_api_url_usd);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Mengembalikan respons sebagai string
    $api_response = curl_exec($ch); // Jalankan permintaan cURL
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Dapatkan HTTP status code
    curl_close($ch); // Tutup sesi cURL

    // Tangani error HTTP dari API eksternal
    if ($http_code !== 200) {
        echo json_encode(["result" => "error", "message" => "Gagal mengambil data dari API eksternal. Kode HTTP: " . $http_code]);
        exit();
    }

    $data = json_decode($api_response, true); // Dekode respons JSON menjadi array PHP

    // Cek apakah API berhasil mengembalikan data kurs yang valid dan conversion_rates ada
    if (isset($data['result']) && $data['result'] === "success" && isset($data['conversion_rates'])) {
        $rates = $data['conversion_rates'];

        // Cek apakah mata uang asal dan tujuan ada dalam daftar rate yang berbasis USD
        // Ini penting karena API gratis hanya memberikan rates DARI USD ke mata uang lain.
        // Jika FROM_CURRENCY bukan USD, kita akan hitung rate perbandingannya dengan USD.
        if (isset($rates[$from_currency]) && isset($rates[$to_currency])) {
            
            // Logika konversi dua arah melalui USD
            // Jika from_currency adalah USD, langsung pakai rate to_currency
            if ($from_currency === 'USD') {
                $rate = $rates[$to_currency];
            } else {
                // Jika from_currency bukan USD, konversi dulu ke USD, lalu dari USD ke to_currency
                // Misalnya: IDR -> USD -> MYR
                // Rate IDR ke USD adalah 1 / rates['IDR_from_USD_base']
                // Rate USD ke MYR adalah rates['MYR_from_USD_base']
                // Jadi, IDR ke MYR = (1 / rates['IDR']) * rates['MYR']
                
                // Pastikan rates[$from_currency] tidak nol untuk menghindari pembagian dengan nol
                if ($rates[$from_currency] == 0) {
                    echo json_encode(["result" => "error", "message" => "Rate mata uang asal (dari USD) tidak valid (nol)."]);
                    exit();
                }
                $rate = (1 / $rates[$from_currency]) * $rates[$to_currency];
            }
            
            echo json_encode(["result" => "success", "rate" => $rate, "source" => "api_via_usd"]);

        } else {
            echo json_encode(["result" => "error", "message" => "Mata uang asal atau tujuan tidak ditemukan dalam data API berbasis USD. Pastikan kode ISO sudah benar dan mata uang didukung."]);
        }
    } else {
        echo json_encode(["result" => "error", "message" => "API gagal mengembalikan data kurs yang valid atau result bukan 'success'."]);
    }

} elseif ($action === 'save_conversion_history' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Aksi untuk menyimpan riwayat konversi ke database
    $input = json_decode(file_get_contents('php://input'), true);

    $amount_from = $input['amountFrom'] ?? 0;
    $currency_from = $input['currencyFrom'] ?? '';
    $amount_to = $input['amountTo'] ?? 0;
    $currency_to = $input['currencyTo'] ?? '';
    $conversion_rate = $input['conversionRate'] ?? 0;

    // Validasi data input
    if (empty($currency_from) || empty($currency_to) || $amount_from <= 0) {
        echo json_encode(["result" => "error", "message" => "Data konversi tidak lengkap atau tidak valid."]);
        exit();
    }

    // Persiapkan statement SQL untuk INSERT
    $stmt = $conn->prepare("INSERT INTO conversion_history (amount_from, currency_from, amount_to, currency_to, conversion_rate) VALUES (?, ?, ?, ?, ?)");
    // Bind parameter untuk mencegah SQL Injection
    $stmt->bind_param("dsssd", $amount_from, $currency_from, $amount_to, $currency_to, $conversion_rate);

    // Jalankan statement
    if ($stmt->execute()) {
        echo json_encode(["result" => "success", "message" => "Riwayat konversi berhasil disimpan."]);
    } else {
        echo json_encode(["result" => "error", "message" => "Gagal menyimpan riwayat konversi: " . $stmt->error]);
    }
    $stmt->close(); // Tutup statement

} else {
    // Jika tidak ada 'action' yang valid atau metode HTTP tidak diizinkan
    echo json_encode(["result" => "error", "message" => "Aksi tidak valid atau metode tidak diizinkan."]);
}

$conn->close(); // Tutup koneksi database
?>