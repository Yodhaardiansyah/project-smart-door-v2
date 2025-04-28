<?php

require_once 'config.php';

header('Content-Type: application/json');

// 🔐 Konfigurasi Token dan Chat ID
$TOKEN = "7667552343:AAEd_M8j3eK3Zd_f4vn7fjI3ivc4AJavnPg";
$CHAT_ID_ADMIN = ["1243740148", "1474554925"];
$API_URL = "https://api.telegram.org/bot$TOKEN/";

// 🌐 Set Zona Waktu
date_default_timezone_set('Asia/Jakarta'); // ⏱ Pastikan sesuai zona waktu lokal
$current_time = date('Y-m-d H:i:s'); // ⏳ Format waktu lokal

// 📩 Fungsi Kirim Pesan ke Telegram
function sendMessage($chat_id, $message) {
    global $API_URL;
    $url = $API_URL . "sendMessage?chat_id=$chat_id&text=" . urlencode($message);
    file_get_contents($url);
}

// 📥 Tangkap Data dari ESP atau Telegram
$input = json_decode(file_get_contents("php://input"), true);
$message = $input["message"]["text"] ?? null;
$chat_id = $input["message"]["chat"]["id"] ?? null;
$rfid = isset($_GET['rfid']) ? $_GET['rfid'] : null;
$pin = isset($_GET['pin']) ? $_GET['pin'] : null;
$callback_query = $input["callback_query"] ?? null;

$device_param = isset($_GET['device']) ? $_GET['device'] : null;

// ✅ Proses Validasi dari ESP (RFID/PIN)
if (!empty($rfid) || !empty($pin)) {
    $method = !empty($rfid) ? "RFID" : "PIN";

    // 🔍 Cari pengguna berdasarkan RFID atau PIN
    $query = $conn->prepare("SELECT id, name, device FROM users WHERE (rfid = ? AND rfid IS NOT NULL) OR (pin = ? AND pin IS NOT NULL)");
    $query->bind_param("ss", $rfid, $pin);
    $query->execute();
    $result = $query->get_result();

    if ($row = $result->fetch_assoc()) {
        $user_id = $row['id'];
        $name = $row['name'];
        $device = $row['device'];

        // Validate device parameter if provided
        if ($device_param !== null && $device_param !== $device) {
            // ❌ Log Gagal karena device tidak cocok
            $logQuery = $conn->prepare("INSERT INTO access_logs (user_id, method, status, access_time) VALUES (?, ?, 'FAILED', ?)");
            $logQuery->bind_param("iss", $user_id, $method, $current_time);
            $logQuery->execute();

            echo json_encode(["status" => "error", "message" => "Access denied: Device type mismatch"]);
            exit;
        }

        // 📝 Simpan Log Akses DENGAN WAKTU LOKAL
        $logQuery = $conn->prepare("INSERT INTO access_logs (user_id, method, status, access_time) VALUES (?, ?, 'SUCCESS', ?)");
        $logQuery->bind_param("iss", $user_id, $method, $current_time);
        $logQuery->execute();

        // 🚀 Kirim Notifikasi ke Telegram
        sendMessage($CHAT_ID_ADMIN, "✅ Akses Berhasil!\n📌 Nama: $name\n🔑 Device: $device\n🆔 Metode: $method\n🕒 Waktu: $current_time");

        echo json_encode([
            "status" => "success",
            "message" => "Access granted",
            "name" => $name,
            "device" => $device,
            "method" => $method,
            "time" => $current_time
        ]);
    } else {
        // ❌ Log Gagal
        $logQuery = $conn->prepare("INSERT INTO access_logs (user_id, method, status, access_time) VALUES (NULL, ?, 'FAILED', ?)");
        $logQuery->bind_param("ss", $method, $current_time);
        $logQuery->execute();

        echo json_encode(["status" => "error", "message" => "Access denied: Invalid RFID or PIN"]);
    }
    exit;
}

// ✅ Fungsi Menampilkan Menu Bot
// ✅ Fungsi Menampilkan Menu Bot
function sendMenu($chat_id) {
    global $API_URL;
    
    $keyboard = [
        "inline_keyboard" => [
            [
                ["text" => "➕ Tambah User", "callback_data" => "add_user"],
                ["text" => "📋 Lihat User", "callback_data" => "list_users"]
            ],
            [
                ["text" => "🗑️ Hapus User", "callback_data" => "delete_user"],
                ["text" => "❓ Bantuan", "callback_data" => "help"]
            ],
            [
                ["text" => "🔄 Refresh", "callback_data" => "refresh"]
            ]
        ]
    ];

    $postData = [
        "chat_id" => $chat_id,
        "text" => "🔹 Pilih menu di bawah:",
        "reply_markup" => json_encode($keyboard)
    ];

    file_get_contents($API_URL . "sendMessage?" . http_build_query($postData));
}

// ✅ Callback dari Inline Keyboard
if (!empty($callback_query)) {
    $chat_id = $callback_query["message"]["chat"]["id"];
    $data = $callback_query["data"];

    switch ($data) {
        case "add_user":
            sendMessage($chat_id, "📝 Gunakan perintah: /adduser [alat1/alat2] [Nama] [RFID] [PIN]");
            break;
        case "list_users":
            $response = "📋 *Daftar User & Riwayat Akses*\n";

            $result = $conn->query("SELECT id, device, name, rfid FROM users");
            while ($row = $result->fetch_assoc()) {
                $user_id = $row["id"];
                $device = $row["device"];
                $name = $row["name"];
                $rfid = $row["rfid"];

                // 🔍 Riwayat akses
                $logResult = $conn->query("SELECT access_time, method, status FROM access_logs WHERE user_id = $user_id ORDER BY access_time DESC LIMIT 5");

                $response .= "\n🔹 *$device - $name* (RFID: `$rfid`)\n";
                while ($log = $logResult->fetch_assoc()) {
                    $response .= "📅 " . $log["access_time"] . " | 🔑 " . $log["method"] . " | " . ($log["status"] == "SUCCESS" ? "✅" : "❌") . "\n";
                }
            }

            sendMessage($chat_id, $response);
            break;
        case "delete_user":
            sendMessage($chat_id, "🗑️ Gunakan perintah: /deleteuser [id_user] untuk menghapus user.");
            break;
        case "help":
            $helpMessage = "❓ *Bantuan*\n\n";
            $helpMessage .= "/adduser [alat1/alat2] [Nama] [RFID] [PIN] - Tambah user baru\n";
            $helpMessage .= "/deleteuser [id_user] - Hapus user berdasarkan ID\n";
            $helpMessage .= "/listusers - Tampilkan daftar user\n";
            $helpMessage .= "/menu - Tampilkan menu utama\n";
            sendMessage($chat_id, $helpMessage);
            break;
        case "refresh":
            sendMenu($chat_id);
            break;
        default:
            sendMessage($chat_id, "❌ Perintah tidak dikenali.");
            break;
    }
    exit;
}

// ✅ Pesan Telegram
if (!empty($chat_id)) {
    if (!in_array($chat_id, $CHAT_ID_ADMIN)) {
        sendMessage($chat_id, "⛔ Akses Ditolak!");
        exit;
    }

    if ($message == "/start" || $message == "/menu") {
        sendMenu($chat_id);
        exit;
    }

    if (strpos($message, "/adduser") === 0) {
        $parts = explode(" ", $message);
        if (count($parts) < 5) {
            sendMessage($chat_id, "⚠ Format: /adduser [alat1/alat2] [Nama] [RFID] [PIN]");
        } else {
            $device = htmlspecialchars(trim($parts[1]));
            $nama = htmlspecialchars(trim($parts[2]));
            $rfid = htmlspecialchars(trim($parts[3]));
            $pin = htmlspecialchars(trim($parts[4]));

            $stmt = $conn->prepare("INSERT INTO users (device, name, rfid, pin, role) VALUES (?, ?, ?, ?, 'user')");
            $stmt->bind_param("ssss", $device, $nama, $rfid, $pin);
            $stmt->execute();

            sendMessage($chat_id, "✅ User $nama berhasil ditambahkan ke $device!");
        }
    }
}

$conn->close();
?>
