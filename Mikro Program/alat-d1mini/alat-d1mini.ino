#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiManager.h>  // Tambahkan library WiFiManager
#include <Keypad.h>
#include <ArduinoJson.h>

// 🔧 Server
const char* server = "https://smart-door.arunovasi.my.id/server.php";
#define DEVICE "alat1"

// 🔧 Relay
#define RELAY_PIN 15  // D8

// 🔧 Keypad
const byte ROWS = 4;
const byte COLS = 4;
char keys[ROWS][COLS] = {
  {'1','2','3','A'},
  {'4','5','6','B'},
  {'7','8','9','C'},
  {'*','0','#','D'}
};
byte rowPins[ROWS] = {5, 4, 0, 2}; // D1, D2, D3, D4
byte colPins[COLS] = {16, 14, 12, 13}; // D0, D5, D6, D7
Keypad keypad = Keypad(makeKeymap(keys), rowPins, colPins, ROWS, COLS);

// 🔧 WiFi & HTTP
WiFiClientSecure client;
HTTPClient http;

void setup() {
  Serial.begin(9600); // Komunikasi dengan ESP8266 (RFID)
  delay(1000);
  client.setInsecure(); // Abaikan SSL

  connectWiFi();  // Hubungkan WiFi menggunakan WiFiManager

  pinMode(RELAY_PIN, OUTPUT);
  digitalWrite(RELAY_PIN, HIGH); // Kunci tertutup
}

void loop() {
  // Keypad
  static String pinInput = "";
  char key = keypad.getKey();
  if (key) {
    sendToESP("BUZZER:1");
    if (key == '#') {
      if (pinInput.length() > 0) {
        validate("pin", pinInput);
        pinInput = "";
      }
    } else if (key == '*') {
      pinInput = "";
    } else {
      pinInput += key;
    }
  }

  // RFID input dari ESP
  if (Serial.available()) {
    String uid = Serial.readStringUntil('\n');
    uid.trim();
    if (uid.length() > 0 && uid.indexOf(':') == -1) {
      sendToESP("BUZZER:1");
      validate("rfid", uid);
    }
  }
}

void connectWiFi() {
  WiFiManager wifiManager;
  wifiManager.autoConnect("SmartDoorAP");  // AP default name: SmartDoorAP

  // Jika berhasil connect, lanjut. Kalau gagal, ESP akan restart dan coba lagi.
  Serial.println("Connected to WiFi!");
}

void validate(String type, String data) {
  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
    if (WiFi.status() != WL_CONNECTED) return;
  }

  String url = String(server) + "?device=" + DEVICE + "&" + type + "=" + data;

  sendToESP("LED:Y"); // Kuning = memproses

  http.begin(client, url);
  int httpCode = http.GET();
  String response = http.getString();
  http.end();

  if (httpCode > 0) {
    DynamicJsonDocument doc(256);
    DeserializationError error = deserializeJson(doc, response);
    if (!error && doc["status"] == "success") {
      openDoor();
    } else {
      sendToESP("LED:R"); // Merah
    }
  } else {
    sendToESP("LED:R");
  }
}

void openDoor() {
  sendToESP("LED:G");
  sendToESP("BUZZER:2");
  digitalWrite(RELAY_PIN, LOW);
  delay(5000);
  digitalWrite(RELAY_PIN, HIGH);
  sendToESP("LED:R");
  sendToESP("BUZZER:3");
}

void sendToESP(String command) {
  Serial.println(command); // Kirim ke ESP (buzzer/LED)
}
