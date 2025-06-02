// script-content1.js

// GANTI DENGAN URL YANG BENAR KE FILE PHP API ANDA
// Contoh: 'http://localhost/nama_folder_proyek_anda/api.php'
const BACKEND_API_URL = 'http://localhost/hehe2/api.php'; 

// Fungsi untuk mengisi nilai pada input jumlah dari tombol pecahan
function isiJumlah(nominal) {
    document.getElementById('jumlah2').value = nominal;
    klik2(); // Panggil klik2() langsung setelah mengisi jumlah
}

// Fungsi utama untuk melakukan konversi
async function klik2() {
    const amount = parseFloat(document.getElementById("jumlah2").value);
    const fromCurrency = document.getElementById("Dom").value; // Mata uang asal
    const toCurrency = document.getElementById("Sub").value;   // Mata uang tujuan
    const hasilElement = document.getElementById("hasil2");    // Elemen textarea untuk hasil

    // Validasi input: pastikan jumlah adalah angka dan lebih dari 0
    if (isNaN(amount) || amount <= 0) {
        hasilElement.value = "Masukkan jumlah yang valid (angka positif).";
        return;
    }

    // Jika mata uang asal dan tujuan sama, hasilnya adalah jumlah itu sendiri
    if (fromCurrency === toCurrency) {
        hasilElement.value = amount.toFixed(2); // Batasi 2 angka di belakang koma
        // Anda bisa memilih untuk menyimpan ini ke histori atau tidak
        // saveConversionHistory(amount, fromCurrency, amount, toCurrency, 1);
        return;
    }

    try {
        // Panggil backend PHP Anda untuk mendapatkan kurs mata uang
        // Kita menggunakan GET request karena hanya mengambil data
        const response = await fetch(`${BACKEND_API_URL}?action=get_exchange_rate&from=${fromCurrency}&to=${toCurrency}`);
        const data = await response.json(); // Parse respons JSON dari PHP

        if (data.result === "success") {
            const conversionRate = parseFloat(data.rate); // Ambil kurs dari data respons
            
            // Pastikan kurs yang diterima valid
            if (!isNaN(conversionRate) && conversionRate > 0) {
                const convertedAmount = amount * conversionRate;
                hasilElement.value = convertedAmount.toFixed(2); // Tampilkan hasil konversi

                // Kirim data konversi ke backend PHP untuk disimpan di database
                // Ini akan menggunakan POST request
                saveConversionHistory(amount, fromCurrency, convertedAmount, toCurrency, conversionRate);

            } else {
                hasilElement.value = "Error: Kurs tidak valid dari server.";
                console.error("Kurs tidak valid:", data);
            }
        } else {
            // Jika ada error dari backend PHP (misalnya API Key salah, atau API eksternal error)
            hasilElement.value = `Error: ${data.message || 'Gagal mengambil data kurs dari server.'}`;
            console.error("Backend Error:", data.message, data);
        }
    } catch (error) {
        // Jika ada masalah koneksi ke server PHP
        hasilElement.value = "Gagal mengambil data kurs. Periksa koneksi ke server lokal Anda.";
        console.error("Fetch Error to Backend:", error);
    }
}

// Fungsi untuk mengirim riwayat konversi ke backend PHP untuk disimpan di database
async function saveConversionHistory(amountFrom, currencyFrom, amountTo, currencyTo, conversionRate) {
    try {
        const response = await fetch(`${BACKEND_API_URL}?action=save_conversion_history`, {
            method: 'POST', // Menggunakan metode POST untuk mengirim data
            headers: {
                'Content-Type': 'application/json' // Memberitahu server bahwa kita mengirim JSON
            },
            body: JSON.stringify({ // Mengirim data sebagai string JSON
                amountFrom: amountFrom,
                currencyFrom: currencyFrom,
                amountTo: amountTo,
                currencyTo: currencyTo,
                conversionRate: conversionRate
            })
        });
        const result = await response.json(); // Parse respons dari PHP
        console.log('Database save result:', result); // Log hasil penyimpanan ke console

        // Anda bisa menambahkan feedback ke user di sini jika perlu (misal: "Riwayat disimpan!")
    } catch (dbError) {
        console.error('Error saving to database:', dbError);
        // Anda bisa menambahkan feedback error ke user di sini
    }
}

// Tambahkan event listener agar konversi otomatis berjalan
// setiap kali nilai di dropdown atau input jumlah berubah
document.addEventListener('DOMContentLoaded', () => {
    const domSelect = document.getElementById("Dom");
    const subSelect = document.getElementById("Sub");
    const jumlahInput = document.getElementById("jumlah2");

    // Pastikan elemen ditemukan sebelum menambahkan event listener
    if (domSelect) {
        domSelect.addEventListener('change', klik2);
    }
    if (subSelect) {
        subSelect.addEventListener('change', klik2);
    }
    if (jumlahInput) {
        // Gunakan 'input' untuk deteksi perubahan secara real-time saat mengetik
        jumlahInput.addEventListener('input', klik2);
    }

    // Panggil klik2() saat halaman pertama kali dimuat
    // Ini memastikan ada hasil konversi default saat halaman dibuka
    klik2();
});