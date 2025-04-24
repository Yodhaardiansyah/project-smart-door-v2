#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecure.h>
#include <SPI.h>
#include <MFRC522.h>
#include <Keypad.h>
#include <ArduinoJson.h>

#define DEVICE "alat2"

// 🔹 Konfigurasi Telegram
const char* TELEGRAM_BOT_TOKEN = "7667552343:AAEd_M8j3eK3Zd_f4vn7fjI3ivc4AJavnPg";
const char* TELEGRAM_CHAT_ID = "1243740148";      

// 🔹 Konfigurasi WiFi & Server
const char* ssid = "Yoss";
const char* password = "06122002";
const char* server = "https://smart-door.arunovasi.my.id/server.php";

// 🔹 Konfigurasi RFID RC522
#define SS_PIN   2
#define RST_PIN  0
MFRC522 mfrc522(SS_PIN, RST_PIN);

// 🔹 Konfigurasi Keypad
const byte ROWS = 4;
const byte COLS = 4;
char keys[ROWS][COLS] = {
  {'1','2','3','A'},
  {'4','5','6','B'},
  {'7','8','9','C'},
  {'*','0','#','D'}
};
byte rowPins[ROWS] = {5, 4, 0, 2};  
byte colPins[COLS] = {14, 12, 13, 15}; 
Keypad keypad = Keypad(makeKeymap(keys), rowPins, colPins, ROWS, COLS);

WiFiClientSecure client;
HTTPClient http;
bool wifiConnected = false;
unsigned long lastRFIDScan = 0;
unsigned long lastKeypadInput = 0;

#define RELAY_PIN 10  // 🔹 Relay di GPIO16

void setup() {
    Serial.begin(115200);
    delay(1000);
    SPI.begin();
    mfrc522.PCD_Init();
    client.setInsecure();
    ESP.wdtDisable();
    connectWiFi();

    pinMode(RELAY_PIN, OUTPUT);  // 🔹 Atur GPIO16 sebagai output
    digitalWrite(RELAY_PIN, HIGH); // 🔹 Matikan relay saat awal
}

void loop() {
    ESP.wdtFeed();
    if (millis() - lastRFIDScan > 1000) checkRFID();
    if (millis() - lastKeypadInput > 500) checkKeypad();
}

// 🔹 Fungsi Koneksi WiFi
void connectWiFi() {
    WiFi.begin(ssid, password);
    Serial.print("Connecting to WiFi");
    int retry = 0;
    while (WiFi.status() != WL_CONNECTED && retry < 20) {
        delay(500);
        Serial.print(".");
        retry++;
    }
    wifiConnected = (WiFi.status() == WL_CONNECTED);
    if (wifiConnected) Serial.println("\n✅ WiFi Connected!");
    else Serial.println("\n❌ WiFi Connection Failed!");
}

// 🔹 Fungsi Membaca RFID
void checkRFID() {
    if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) return;
    lastRFIDScan = millis();

    String UID = "";
    for (byte i = 0; i < mfrc522.uid.size; i++) {
        UID += String(mfrc522.uid.uidByte[i], HEX);
    }
    Serial.println("📡 RFID Scanned: " + UID);

    if (verifyAccess(UID, "")) {
        Serial.println("✅ Akses Diterima!");
    } else {
        Serial.println("❌ Akses Ditolak!");
    }
}

// 🔹 Fungsi Membaca PIN dari Keypad
void checkKeypad() {
    String pinInput = "";
    char key;
    Serial.print("Enter PIN: ");
  
    while (pinInput.length() < 4) {
    key = keypad.getKey();
    if (key) {
        Serial.print(key);
        pinInput += key;
        delay(10);
      }
      ESP.wdtFeed();  // 🛠 Memberi tahu watchdog bahwa ESP masih berjalan
    }

    lastKeypadInput = millis();
    Serial.println("\n🔢 PIN Entered: " + pinInput);

    if (verifyAccess("", pinInput)) {
        Serial.println("✅ Akses Diterima!");
    } else {
        Serial.println("❌ Akses Ditolak!");
    }
}

// 🔹 Fungsi Verifikasi ke Server (Menggunakan GET)
bool verifyAccess(String rfid, String pin) {
    if (WiFi.status() == WL_CONNECTED) {
        // 🔹 Susun URL dengan parameter
        String fullUrl = String(server) + "?device=" + String(DEVICE) + "&rfid=" + rfid + "&pin=" + pin;

        Serial.println("🔗 Requesting: " + fullUrl);

        http.begin(client, fullUrl);  // Mulai request dengan URL yang sudah disusun
        http.setFollowRedirects(HTTPC_FORCE_FOLLOW_REDIRECTS);
        int httpCode = http.GET();    // Gunakan metode GET
        String response = http.getString();
        http.end();

        Serial.println("🔄 HTTP Code: " + String(httpCode));
        Serial.println("📝 Server Response: " + response);

        if (httpCode <= 0) {
            Serial.print("❌ HTTP Error: ");
            Serial.println(http.errorToString(httpCode).c_str());
            return false;
        }

        response.trim();

        // 🔹 Parsing JSON Response
        DynamicJsonDocument doc(256);
        DeserializationError error = deserializeJson(doc, response);
        if (error) {
            Serial.println("❌ JSON Parsing Failed!");
            return false;
        }

        String status = doc["status"];
        String message = doc["message"];
        String name = doc["name"];
        String method = doc["method"];

        if (status == "success") {
            Serial.println("✅ Akses Diberikan!");
            Serial.println("👤 User: " + name);
            Serial.println("🔑 Metode: " + method);

            sendTelegramMessage("✅ Akses Granted!\n👤 User: " + name + "\n🔑 Metode: " + method);
            unsigned long startRelayTime = millis();
            digitalWrite(RELAY_PIN, LOW); // 🔹 Aktifkan relay

            while (millis() - startRelayTime < 5000) { // 🔹 Tunggu 5 detik
                ESP.wdtFeed();  // 🔹 Hindari WDT reset
                delay(10);
            }

            digitalWrite(RELAY_PIN, HIGH); // 🔹 Matikan relay

            return true;
        } else {
            Serial.println("❌ Akses Ditolak: " + message);
            return false;
        }
    } else {
        Serial.println("❌ WiFi Not Connected!");
    }
    
    return false;
}


// 🔹 Fungsi Kirim Notifikasi ke Telegram
void sendTelegramMessage(String message) {
    if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        String telegramServer = "https://api.telegram.org/bot" + String(TELEGRAM_BOT_TOKEN) + "/sendMessage";
        String postData = "chat_id=" + String(TELEGRAM_CHAT_ID) + "&text=" + message;
        
        http.begin(client, telegramServer);
        http.addHeader("Content-Type", "application/x-www-form-urlencoded");
        int httpCode = http.POST(postData);
        String response = http.getString();
        http.end();

        Serial.println("📡 Telegram Sent: " + message);
        Serial.println("🔄 HTTP Code: " + String(httpCode));
        Serial.println("📝 Telegram Response: " + response);
    }
}