#include <SPI.h>
#include <MFRC522.h>

// 🔧 RFID
#define RST_PIN 5  // D1
#define SS_PIN 2   // D4

// 🔧 LED & Buzzer
#define BUZZER_PIN 16  // D0
#define LED_R 15       // Merah
#define LED_Y 0        // Kuning
#define LED_G 4        // Hijau

MFRC522 rfid(SS_PIN, RST_PIN);

void setup() {
  Serial.begin(9600); // Komunikasi dengan D1
  SPI.begin();
  rfid.PCD_Init();

  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(LED_R, OUTPUT);
  pinMode(LED_Y, OUTPUT);
  pinMode(LED_G, OUTPUT);

  digitalWrite(LED_R, HIGH); // Awal: kunci tertutup
  digitalWrite(LED_Y, LOW);
  digitalWrite(LED_G, LOW);
}

void loop() {
  // Perintah dari D1 Mini
  if (Serial.available()) {
    String cmd = Serial.readStringUntil('\n');
    cmd.trim();

    if (cmd.startsWith("BUZZER:")) {
      int times = cmd.substring(7).toInt();
      beep(times);
    } else if (cmd.startsWith("LED:")) {
      setLED(cmd.substring(4));
    }
  }

  // Deteksi RFID
  if (!rfid.PICC_IsNewCardPresent() || !rfid.PICC_ReadCardSerial()) return;

  String uid = "";
  for (byte i = 0; i < rfid.uid.size; i++) {
    uid += (rfid.uid.uidByte[i] < 0x10 ? "0" : "");
    uid += String(rfid.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();

  Serial.println(uid);  // Kirim UID ke D1 Mini
  delay(1500);

  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();
}

void beep(int times) {
  for (int i = 0; i < times; i++) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(150);
    digitalWrite(BUZZER_PIN, LOW);
    delay(150);
  }
}

void setLED(String color) {
  digitalWrite(LED_R, LOW);
  digitalWrite(LED_Y, LOW);
  digitalWrite(LED_G, LOW);

  if (color == "R") digitalWrite(LED_R, HIGH);
  else if (color == "Y") digitalWrite(LED_Y, HIGH);
  else if (color == "G") digitalWrite(LED_G, HIGH);
}
