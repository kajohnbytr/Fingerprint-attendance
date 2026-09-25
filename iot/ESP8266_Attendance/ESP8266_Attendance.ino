/*
  ============================================================
  ESP8266 BIOMETRIC ATTENDANCE SYSTEM
  ============================================================

  FEATURES
  ------------------------------------------------------------
  - Fingerprint attendance
  - Google Sheets logging
  - Captive Portal
  - Fingerprint enrollment
  - Fingerprint deletion
  - Program Head dashboard
  - Program Head login
  - Student registration with section and subjects
  - EEPROM student records
  - Enrolled ID list
  - Enrollment logs 
  - Delete logs
  - Timestamp using NTP
  - EEPROM persistent records
  - Reset button
  - Serial commands

  CAPTIVE PORTAL
  ------------------------------------------------------------
  SSID     : Attendance-Setup
  Password : 12345678
  IP       : 192.168.4.1

  FINGERPRINT
  ------------------------------------------------------------
  D5 = ESP8266 RX
  D6 = ESP8266 TX

  LCD
  ------------------------------------------------------------
  D2 = SDA
  D1 = SCL

  RESET
  ------------------------------------------------------------
  D7

  SERIAL COMMANDS
  ------------------------------------------------------------
  ENROLL 1
  ENROLL 25

  DELETE 1
  DELETE 25
*/


// ============================================================
// LIBRARIES
// ============================================================

#include <ESP8266WiFi.h>
#include <WiFiClientSecure.h>
#include <ESP8266HTTPClient.h>

#include <ESP8266WebServer.h>
#include <DNSServer.h>

#include <Adafruit_Fingerprint.h>
#include <LiquidCrystal_I2C.h>
#include <SoftwareSerial.h>
#include <Wire.h>

#include <EEPROM.h>
#include <time.h>
#include <string.h>


// ============================================================
// EEPROM CONFIGURATION
// ============================================================

#define EEPROM_SIZE 4096

#define EEPROM_MAGIC 0xA55A1234UL
#define STUDENT_EEPROM_MAGIC 0x53545544UL

const uint16_t EEPROM_MAGIC_ADDR = 0;

const uint16_t EEPROM_COUNT_ADDR =
  EEPROM_MAGIC_ADDR + sizeof(uint32_t);

const uint16_t EEPROM_RECORD_ADDR =
  EEPROM_COUNT_ADDR + sizeof(uint16_t);


// ============================================================
// LOG CONFIGURATION
// ============================================================

#define MAX_ENROLL_RECORDS 100
#define MAX_STUDENT_RECORDS 21



/*
  Each record contains:

  ID
  timestamp
  action
  result
*/

struct EnrollmentRecord {

  uint16_t id;

  uint32_t timestamp;

  uint8_t action;

  uint8_t result;

  uint8_t reserved[2];
};


EnrollmentRecord enrollmentRecords[
  MAX_ENROLL_RECORDS
];


uint16_t enrollmentRecordCount = 0;

const uint16_t STUDENT_EEPROM_MAGIC_ADDR = 2048;
const uint16_t STUDENT_EEPROM_COUNT_ADDR =
  STUDENT_EEPROM_MAGIC_ADDR + sizeof(uint32_t);
const uint16_t STUDENT_EEPROM_RECORD_ADDR =
  STUDENT_EEPROM_COUNT_ADDR + sizeof(uint16_t);

struct StudentRecord {
  uint16_t fingerprintId;
  uint32_t timestamp;
  uint8_t subjectMask;
  char studentId[17];
  char firstName[21];
  char lastName[21];
  char section[21];
};

StudentRecord studentRecords[MAX_STUDENT_RECORDS];
uint16_t studentRecordCount = 0;

// Retry stored student records one at a time after Wi-Fi connects.
uint16_t nextStudentToSync = 0;
unsigned long lastStudentSyncAttempt = 0;
const unsigned long STUDENT_SYNC_INTERVAL_MS = 15000;



// ============================================================
// ACTION TYPES
// ============================================================

#define ACTION_ENROLL 1
#define ACTION_DELETE 2


// ============================================================
// RESET BUTTON
// ============================================================

#define RESET_BUTTON_PIN D7

const unsigned long RESET_DEBOUNCE_MS = 50;

bool lastResetReading = HIGH;
bool stableResetState = HIGH;

unsigned long resetDebounceTime = 0;


// ============================================================
// WIFI
// ============================================================

const char* WIFI_NAME =
  "HUAWEI-2.4G-W9Tv";

const char* WIFI_PASSWORD =
  "eWw5JHcq";


// ============================================================
// CAPTIVE PORTAL
// ============================================================

const char* AP_SSID =
  "Attendance-Setup";

const char* AP_PASSWORD =
  "12345678";

IPAddress AP_IP(
  192,
  168,
  4,
  1
);

IPAddress AP_GATEWAY(
  192,
  168,
  4,
  1
);

IPAddress AP_SUBNET(
  255,
  255,
  255,
  0
);

const byte DNS_PORT = 53;

DNSServer dnsServer;

ESP8266WebServer server(80);

// Change these before deploying the device.
const char* PH_USERNAME = "programhead";
const char* PH_PASSWORD = "admin";


// ============================================================
// LARAVEL BACKEND API
// ============================================================

/*
  Copy the values supplied by the backend developer here.

  For Laravel Sanctum, copy the token into BACKEND_API_KEY and keep
  BACKEND_USE_BEARER_TOKEN set to true. The token is sent as:
    Authorization: Bearer <BACKEND_API_KEY>

  For a custom API-key middleware, set BACKEND_USE_BEARER_TOKEN to false.
  The token is then sent as:
    X-API-Key: <BACKEND_API_KEY>

  Keep these values in the device firmware, not in the portal HTML.
  Leave an endpoint empty until the backend route is ready.
*/
const char* BASE_URL =
  "http://192.168.100.34:8000";

const char* STUDENT_API_URL =
  "http://192.168.100.34:8000/api/students";

const char* ATTENDANCE_API_URL =
  "http://192.168.100.34:8000/api/readings";

const char* BACKEND_HEALTH_URL =
  "http://192.168.100.34:8000/api/health";

const char* BACKEND_API_KEY =
  "8PNG5RbdDBg8IZcN4jK9W49noTKK7LG0wdJRbvv9d32db3e2";

const char* BACKEND_API_KEY_HEADER =
  "Authorization";

const bool BACKEND_USE_BEARER_TOKEN = true;

const char* BACKEND_DEVICE_ID =
  "attendance-esp8266-01";

const uint16_t BACKEND_HTTP_TIMEOUT_MS = 10000;

WiFiClient backendHttpClient;
WiFiClientSecure backendHttpsClient;


// ============================================================
// FINGERPRINT
// ============================================================

SoftwareSerial fingerSerial(  
  D5,
  D6
);

Adafruit_Fingerprint finger(
  &fingerSerial
);


// ============================================================
// LCD
// ============================================================

LiquidCrystal_I2C lcd(
  0x27,
  16,
  2
);


// ============================================================
// SENSOR CONFIG
// ============================================================

const uint32_t SENSOR_BAUDS[] = {
  9600,
  57600,
  115200
};

const size_t SENSOR_BAUDS_COUNT =
  sizeof(SENSOR_BAUDS) /
  sizeof(SENSOR_BAUDS[0]);

uint32_t activeSensorBaud = 0;

const uint8_t SEARCH_ATTEMPTS = 4;

const uint16_t SEARCH_RETRY_DELAY_MS = 120;

const uint8_t REINIT_AFTER_ERRORS = 2;


// ============================================================
// WEB OPERATION STATE
// ============================================================

enum FingerOperation {

  FINGER_OP_IDLE,

  FINGER_OP_ENROLL,

  FINGER_OP_ENROLL_STUDENT
};


FingerOperation currentFingerOperation =
  FINGER_OP_IDLE;


int pendingEnrollId = 0;

StudentRecord pendingStudent;
bool pendingStudentActive = false;
bool portalAuthenticated = false;


bool portalOperationRunning =
  false;


String portalOperationStatus =
  "Idle";


String portalOperationMessage =
  "Ready";


// ============================================================
// FORWARD DECLARATIONS
// ============================================================

void servicePortal();

void handleResetButton();

void saveEnrollmentRecords();

void loadEnrollmentRecords();

void saveStudentRecords();

void loadStudentRecords();

void addStudentRecord(
  const StudentRecord& record
);

int findAvailableFingerprintId();

bool isStudentFieldValid(
  const String& value,
  size_t maxLength
);

String jsonEscape(
  const String& value
);

void addEnrollmentRecord(
  uint16_t id,
  uint8_t action,
  uint8_t result
);

bool isIdEnrolled(
  uint16_t id
);

void markIdDeleted(
  uint16_t id
);

String getTimestampString(
  uint32_t timestamp
);

String getCurrentTimestamp();

void setupTime();

uint8_t enrollFingerprint(
  uint16_t id
);

const char* fingerErrorText(
  int code
);

void processWebEnrollment();

void handleStudentRegister();

void processStudentEnrollment();

bool requirePortalAuthentication();

int getFingerprintID(
  int& status
);

bool postBackendJson(
  const char* endpoint,
  const String& payload
);

bool sendStudentToBackend(
  const StudentRecord& record
);

bool sendAttendanceToBackend(
  int id
);

String buildStudentJson(
  const StudentRecord& record
);

String buildAttendanceJson(
  int id
);


// ============================================================
// LCD
// ============================================================

void showMessage(
  const String& line1,
  const String& line2
) {

  lcd.clear();

  lcd.setCursor(
    0,
    0
  );

  lcd.print(
    line1
  );

  lcd.setCursor(
    0,
    1
  );

  lcd.print(
    line2
  );
}


// ============================================================
// EEPROM - LOAD
// ============================================================

void loadEnrollmentRecords() {

  EEPROM.begin(
    EEPROM_SIZE
  );


  uint32_t magic = 0;

  EEPROM.get(
    EEPROM_MAGIC_ADDR,
    magic
  );


  if (
    magic != EEPROM_MAGIC
  ) {

    enrollmentRecordCount = 0;

    EEPROM.put(
      EEPROM_MAGIC_ADDR,
      EEPROM_MAGIC
    );

    EEPROM.put(
      EEPROM_COUNT_ADDR,
      enrollmentRecordCount
    );

    EEPROM.commit();

    return;
  }

  EEPROM.get(
    EEPROM_COUNT_ADDR,
    enrollmentRecordCount
  );


  if (
    enrollmentRecordCount >
    MAX_ENROLL_RECORDS
  ) {

    enrollmentRecordCount = 0;
  }


  for (
    uint16_t i = 0;
    i < enrollmentRecordCount;
    i++
  ) {

    EEPROM.get(
      EEPROM_RECORD_ADDR +
      (
        i *
        sizeof(EnrollmentRecord)
      ),
      enrollmentRecords[i]
    );
  }


  Serial.print(
    "Enrollment records loaded: "
  );

  Serial.println(
    enrollmentRecordCount
  );
}


// ============================================================
// EEPROM - SAVE
// ============================================================

void saveEnrollmentRecords() {

  EEPROM.put(
    EEPROM_MAGIC_ADDR,
    EEPROM_MAGIC
  );


  EEPROM.put(
    EEPROM_COUNT_ADDR,
    enrollmentRecordCount
  );


  for (
    uint16_t i = 0;
    i < enrollmentRecordCount;
    i++
  ) {

    EEPROM.put(
      EEPROM_RECORD_ADDR +
      (
        i *
        sizeof(EnrollmentRecord)
      ),
      enrollmentRecords[i]
    );
  }


  EEPROM.commit();
}


// ============================================================
// STUDENT RECORDS - LOAD/SAVE
// ============================================================

void loadStudentRecords() {

  uint32_t magic = 0;

  EEPROM.get(
    STUDENT_EEPROM_MAGIC_ADDR,
    magic
  );

  if (magic != STUDENT_EEPROM_MAGIC) {

    studentRecordCount = 0;

    EEPROM.put(
      STUDENT_EEPROM_MAGIC_ADDR,
      STUDENT_EEPROM_MAGIC
    );

    EEPROM.put(
      STUDENT_EEPROM_COUNT_ADDR,
      studentRecordCount
    );

    EEPROM.commit();

    return;
  }

  EEPROM.get(
    STUDENT_EEPROM_COUNT_ADDR,
    studentRecordCount
  );

  if (studentRecordCount > MAX_STUDENT_RECORDS) {
    studentRecordCount = 0;
  }

  for (uint16_t i = 0; i < studentRecordCount; i++) {
    EEPROM.get(
      STUDENT_EEPROM_RECORD_ADDR +
      (i * sizeof(StudentRecord)),
      studentRecords[i]
    );
  }
}


void saveStudentRecords() {

  EEPROM.put(
    STUDENT_EEPROM_MAGIC_ADDR,
    STUDENT_EEPROM_MAGIC
  );

  EEPROM.put(
    STUDENT_EEPROM_COUNT_ADDR,
    studentRecordCount
  );

  for (uint16_t i = 0; i < studentRecordCount; i++) {
    EEPROM.put(
      STUDENT_EEPROM_RECORD_ADDR +
      (i * sizeof(StudentRecord)),
      studentRecords[i]
    );
  }

  EEPROM.commit();
}


void addStudentRecord(
  const StudentRecord& record
) {

  if (studentRecordCount >= MAX_STUDENT_RECORDS) {
    for (uint16_t i = 1; i < MAX_STUDENT_RECORDS; i++) {
      studentRecords[i - 1] = studentRecords[i];
    }

    studentRecordCount = MAX_STUDENT_RECORDS - 1;
  }

  studentRecords[studentRecordCount] = record;
  studentRecordCount++;
  saveStudentRecords();
}


int findAvailableFingerprintId() {

  for (int id = 1; id <= 1000; id++) {
    if (!isIdEnrolled(id)) {
      return id;
    }
  }

  return 0;
}


bool isStudentFieldValid(
  const String& value,
  size_t maxLength
) {

  if (value.length() == 0 || value.length() > maxLength) {
    return false;
  }

  for (size_t i = 0; i < value.length(); i++) {
    char c = value.charAt(i);

    if (c == '"' || c == '\\' || c == '<' || c == '>') {
      return false;
    }
  }

  return true;
}


String jsonEscape(
  const String& value
) {

  String escaped = value;
  escaped.replace("\\", "\\\\");
  escaped.replace("\"", "\\\"");
  escaped.replace("\r", "");
  escaped.replace("\n", "\\n");
  return escaped;
}


// ============================================================
// CHECK IF ID IS ENROLLED
// ============================================================

bool isIdEnrolled(
  uint16_t id
) {

  /*
    Search from newest record backwards.

    The newest action for an ID determines
    whether it is currently enrolled.
  */

  for (
    int i =
      enrollmentRecordCount - 1;
    i >= 0;
    i--
  ) {

    if (
      enrollmentRecords[i].id ==
      id
    ) {

      if (
        enrollmentRecords[i].action ==
        ACTION_ENROLL &&
        enrollmentRecords[i].result ==
        1
      ) {

        return true;
      }


      if (
        enrollmentRecords[i].action ==
        ACTION_DELETE &&
        enrollmentRecords[i].result ==
        1
      ) {

        return false;
      }
    }
  }


  return false;
}


// ============================================================
// ADD LOG RECORD
// ============================================================

void addEnrollmentRecord(
  uint16_t id,
  uint8_t action,
  uint8_t result
) {

  EnrollmentRecord record;

  record.id =
    id;

  record.timestamp =
    time(nullptr);

  record.action =
    action;

  record.result =
    result;

  record.reserved[0] =
    0;

  record.reserved[1] =
    0;


  /*
    If storage is full, shift records
    so the newest record is retained.
  */

  if (
    enrollmentRecordCount >=
    MAX_ENROLL_RECORDS
  ) {

    for (
      uint16_t i = 1;
      i < MAX_ENROLL_RECORDS;
      i++
    ) {

      enrollmentRecords[i - 1] =
        enrollmentRecords[i];
    }


    enrollmentRecordCount =
      MAX_ENROLL_RECORDS - 1;
  }


  enrollmentRecords[
    enrollmentRecordCount
  ] =
    record;


  enrollmentRecordCount++;


  saveEnrollmentRecords();


  Serial.println(
    "Enrollment log saved."
  );
}


// ============================================================
// MARK ID DELETED
// ============================================================

void markIdDeleted(
  uint16_t id
) {

  addEnrollmentRecord(
    id,
    ACTION_DELETE,
    1
  );
}


// ============================================================
// FORMAT TIMESTAMP
// ============================================================

String getTimestampString(
  uint32_t timestamp
) {

  if (
    timestamp == 0
  ) {

    return "Time unavailable";
  }


  time_t rawTime =
    timestamp;


  struct tm* timeInfo =
    localtime(
      &rawTime
    );


  if (
    timeInfo == nullptr
  ) {

    return "Time unavailable";
  }


  char buffer[30];


  snprintf(
    buffer,
    sizeof(buffer),
    "%04d-%02d-%02d %02d:%02d:%02d",

    timeInfo->tm_year + 1900,

    timeInfo->tm_mon + 1,

    timeInfo->tm_mday,

    timeInfo->tm_hour,

    timeInfo->tm_min,

    timeInfo->tm_sec
  );


  return String(
    buffer
  );
}


// ============================================================
// CURRENT TIMESTAMP
// ============================================================

String getCurrentTimestamp() {

  return getTimestampString(
    time(nullptr)
  );
}


// ============================================================
// NTP TIME
// ============================================================

void setupTime() {

  /*
    Philippines:
    UTC +8

    No daylight saving time.
  */

  configTime(
    8 * 3600,
    0,
    "pool.ntp.org",
    "time.nist.gov",
    "time.google.com"
  );


  Serial.println(
    "Synchronizing time..."
  );


  unsigned long start =
    millis();


  time_t now =
    time(nullptr);


  while (
    now < 1700000000 &&
    millis() - start < 15000
  ) {

    servicePortal();

    delay(250);

    now =
      time(nullptr);
  }


  if (
    now >= 1700000000
  ) {

    Serial.print(
      "Time synchronized: "
    );

    Serial.println(
      getCurrentTimestamp()
    );
  }

  else {

    Serial.println(
      "NTP time unavailable."
    );
  }
}


// ============================================================
// FINGERPRINT SENSOR INIT
// ============================================================

bool initFingerprintSensor(
  bool showUi
) {

  for (
    size_t i = 0;
    i < SENSOR_BAUDS_COUNT;
    i++
  ) {

    uint32_t baud =
      SENSOR_BAUDS[i];


    fingerSerial.begin(
      baud
    );


    finger.begin(
      baud
    );


    fingerSerial.listen();


    if (
      finger.verifyPassword()
    ) {

      activeSensorBaud =
        baud;


      if (
        showUi
      ) {

        showMessage(
          "Sensor OK",
          String(baud)
        );


        delay(600);
      }


      Serial.print(
        "Fingerprint baud: "
      );

      Serial.println(
        baud
      );


      return true;
    }
  }


  return false;
}


// ============================================================
// REINITIALIZE SENSOR
// ============================================================

void reinitFingerprintSensor() {

  if (
    activeSensorBaud > 0
  ) {

    fingerSerial.begin(
      activeSensorBaud
    );

    finger.begin(
      activeSensorBaud
    );

    fingerSerial.listen();
  }

  else {

    initFingerprintSensor(
      false
    );
  }
}


// ============================================================
// WIFI
// ============================================================

bool connectWiFi(
  bool showUi
) {

  if (
    WiFi.status() ==
    WL_CONNECTED
  ) {

    return true;
  }


  WiFi.mode(
    WIFI_AP_STA
  );


  WiFi.begin(
    WIFI_NAME,
    WIFI_PASSWORD
  );


  if (
    showUi
  ) {

    showMessage(
      "WiFi",
      "Connecting..."
    );
  }


  unsigned long start =
    millis();


  while (
    WiFi.status() != WL_CONNECTED &&
    millis() - start < 20000
  ) {

    servicePortal();

    handleResetButton();

    delay(100);
  }


  if (
    WiFi.status() ==
    WL_CONNECTED
  ) {

    Serial.print(
      "STA IP: "
    );

    Serial.println(
      WiFi.localIP()
    );


    if (
      showUi
    ) {

      showMessage(
        "WiFi",
        "Connected"
      );

      delay(700);
    }


    return true;
  }


  Serial.println(
    "WiFi connection failed."
  );


  return false;
}


// ============================================================
// CAPTIVE PORTAL IMPLEMENTATION
// ============================================================

// Kept as an include so the Arduino sketch preserves all existing
// globals and function names without duplicating portal code.
/*
  Captive portal implementation.

  This file is included by ESP8266_Attendance.ino intentionally rather
  than compiled as a separate Arduino translation unit. The portal keeps
  using the sketch's existing globals and function signatures.
*/

const char LOGIN_HTML[] PROGMEM = R"rawliteral(
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#000000">
<title>Program Head Login</title>
<style>
:root {
  --canvas: #ffffff;
  --surface-soft: #f5f5f5;
  --hairline: #e5e5e5;
  --ink: #090909;
  --body: #737373;
  --muted: #a3a3a3;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  min-height: 100vh;
  display: grid;
  place-items: center;
  background: var(--canvas);
  color: var(--ink);
  font-family: "SF Pro Rounded", "Segoe UI", Arial, sans-serif;
}
main {
  width: min(92vw, 420px);
  background: var(--canvas);
  border: 1px solid var(--hairline);
  border-radius: 28px;
  padding: 30px 26px 24px;
}
h1 {
  margin: 0;
  text-align: center;
  font-size: clamp(2.1rem, 5vw, 2.9rem);
  line-height: 1.08;
  letter-spacing: -0.06em;
  font-weight: 600;
}
p {
  margin: 12px 0 22px;
  color: var(--body);
  text-align: center;
  font-size: 0.96rem;
}
form {
  display: grid;
  gap: 10px;
}
input, button {
  width: 100%;
  box-sizing: border-box;
  font-size: 1rem;
}
input {
  border: 1px solid var(--hairline);
  background: var(--surface-soft);
  border-radius: 999px;
  padding: 15px 16px;
  color: var(--ink);
}
button {
  border: none;
  border-radius: 999px;
  background: #000000;
  color: #ffffff;
  padding: 15px 18px;
  font-weight: 600;
  cursor: pointer;
  margin-top: 6px;
}
#message {
  min-height: 22px;
  margin-top: 14px;
  color: #b91c1c;
  text-align: center;
  font-size: 0.9rem;
}
</style>
</head>
<body><main><h1>Program Head</h1><p>Attendance Management Portal</p>
<form onsubmit="login();return false">
<input id="username" autocomplete="username" placeholder="Username" required>
<input id="password" type="password" autocomplete="current-password" placeholder="Password" required>
<button type="submit">Log in</button></form><div id="message"></div>
<script>
function login(){
 fetch('/api/login?username='+encodeURIComponent(username.value)+'&password='+encodeURIComponent(password.value))
 .then(r=>r.json()).then(d=>{if(d.success){location.href='/'}else{message.innerText=d.message}})
 .catch(()=>message.innerText='Unable to connect to the portal.');
}
</script></main></body></html>
)rawliteral";

const char PORTAL_HTML[] PROGMEM = R"rawliteral(

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta
  name="viewport"
  content="width=device-width,initial-scale=1.0"
>

<meta
  name="theme-color"
  content="#000000"
>

<title>
Program Head - Attendance
</title>


<style>

:root {
  --canvas: #ffffff;
  --surface-soft: #f5f5f5;
  --hairline: #e5e5e5;
  --ink: #090909;
  --body: #737373;
  --muted: #a3a3a3;
  --white: #ffffff;
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  background: var(--canvas);
  font-family: "SF Pro Rounded", "Segoe UI", Arial, sans-serif;
  color: var(--ink);
  min-height: 100vh;
}

.container {
  width: min(100%, 920px);
  margin: 0 auto;
  padding: 26px 18px 48px;
}

.header {
  padding: 18px 0 10px;
  border-bottom: 1px solid var(--hairline);
  margin-bottom: 20px;
}

.header h1 {
  margin: 0;
  text-align: center;
  font-size: clamp(2.2rem, 5vw, 3.2rem);
  line-height: 1.06;
  letter-spacing: -0.06em;
  font-weight: 600;
}

.header p {
  margin: 10px auto 0;
  max-width: 42ch;
  text-align: center;
  color: var(--body);
  font-size: 1rem;
}

.tabs {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  margin: 18px 0 16px;
  padding-bottom: 4px;
}

.tab {
  flex: 1;
  min-width: 120px;
  border: 1px solid var(--hairline);
  background: var(--surface-soft);
  color: #374151;
  padding: 12px 14px;
  border-radius: 999px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.tab.active {
  background: #000000;
  color: var(--white);
  border-color: #000000;
}

.page {
  display: none;
}

.page.active {
  display: block;
}

.card {
  background: var(--white);
  border: 1px solid var(--hairline);
  border-radius: 18px;
  padding: 22px;
  margin-bottom: 16px;
}

.stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.connection-card h3 {
  margin-top: 0;
}

.connection-list {
  display: grid;
  gap: 10px;
}

.connection-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 13px 14px;
  border: 1px solid var(--hairline);
  border-radius: 14px;
  background: var(--surface-soft);
}

.connection-name {
  font-weight: 600;
}

.connection-detail {
  display: block;
  margin-top: 3px;
  color: var(--body);
  font-size: 12px;
}

.connection-status {
  flex: 0 0 auto;
  padding: 6px 10px;
  border-radius: 999px;
  background: #fee2e2;
  color: #991b1b;
  font-size: 12px;
  font-weight: 700;
}

.connection-status.online {
  background: #dcfce7;
  color: #166534;
}

.stat {
  background: var(--surface-soft);
  border: 1px solid var(--hairline);
  border-radius: 16px;
  padding: 18px;
}

.stat-number {
  font-size: 2rem;
  line-height: 1.1;
  font-weight: 600;
  letter-spacing: -0.04em;
  color: #000000;
}

.stat-label {
  color: var(--body);
  margin-top: 6px;
  font-size: 0.92rem;
}

input {
  width: 100%;
  padding: 14px 15px;
  border: 1px solid var(--hairline);
  border-radius: 999px;
  font-size: 1rem;
  margin-bottom: 10px;
  background: var(--surface-soft);
  color: var(--ink);
}

button.action {
  width: 100%;
  border: none;
  padding: 15px 16px;
  border-radius: 999px;
  font-weight: 600;
  background: #000000;
  color: var(--white);
  cursor: pointer;
  margin-top: 7px;
}

button.delete {
  background: #dc2626;
}

button.restart {
  background: #374151;
}

.status {
  background: var(--surface-soft);
  border: 1px solid var(--hairline);
  padding: 14px 15px;
  border-radius: 14px;
  margin-top: 12px;
  line-height: 1.5;
  color: var(--body);
}

.table-wrap {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 600px;
}

th,
td {
  padding: 12px;
  border-bottom: 1px solid var(--hairline);
  text-align: left;
  white-space: nowrap;
}

th {
  background: var(--surface-soft);
  color: #374151;
}

.badge {
  display: inline-block;
  padding: 5px 9px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.badge.enrolled {
  background: #dcfce7;
  color: #166534;
}

.badge.deleted {
  background: #fee2e2;
  color: #991b1b;
}

.empty {
  text-align: center;
  padding: 30px;
  color: var(--body);
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
}

.info-item {
  background: var(--surface-soft);
  border: 1px solid var(--hairline);
  padding: 14px;
  border-radius: 12px;
}

.info-label {
  font-size: 12px;
  color: var(--body);
}

.info-value {
  font-weight: 600;
  margin-top: 4px;
}

.registration-intro {
  color: var(--body);
  line-height: 1.5;
  margin-top: -4px;
}

.field-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #475569;
  margin: 16px 0 7px;
}

.choice-panel {
  border: 1px solid var(--hairline);
  border-radius: 14px;
  overflow: hidden;
  background: var(--surface-soft);
}

.choice-panel summary {
  list-style: none;
  cursor: pointer;
  padding: 15px;
  font-weight: 600;
  color: #334155;
}

.choice-panel summary::-webkit-details-marker {
  display: none;
}

.choice-panel summary::after {
  content: "+";
  float: right;
  color: #000000;
  font-size: 20px;
  line-height: 16px;
}

.choice-panel[open] summary::after {
  content: "−";
}

.choice-list {
  padding: 0 10px 10px;
}

.choice {
  display: flex;
  align-items: flex-start;
  gap: 11px;
  padding: 13px 10px;
  border-top: 1px solid var(--hairline);
  color: #334155;
  line-height: 1.35;
  cursor: pointer;
}

.choice input {
  width: 20px;
  height: 20px;
  flex: 0 0 20px;
  margin: 0;
  accent-color: #000000;
}

.selection-hint {
  color: var(--body);
  font-size: 13px;
  margin: 7px 2px 0;
}

.student-save {
  margin-top: 20px !important;
}

@media (max-width: 600px) {
  .stats {
    grid-template-columns: 1fr;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }
}

</style>

</head>


<body>


<div class="container">


<!-- ======================================================
     HEADER
     ====================================================== -->

<div class="header">

  <h1>
    Attendance Portal
  </h1>

  <p>
    Biometric IoT attendance management for the program head.
  </p>

</div>


<!-- ======================================================
     TABS
     ====================================================== -->

<div class="tabs">

  <button
    class="tab active"
    onclick="openTab('dashboard',this)"
  >
    Dashboard
  </button>

  <button
    class="tab"
    onclick="openTab('students',this)"
  >
    Register Student
  </button>


  <button
    class="tab"
    onclick="openTab('fingerprints',this)"
  >
    Fingerprints
  </button>


  <button
    class="tab"
    onclick="openTab('logs',this)"
  >
    Enrollment Logs
  </button>

</div>


<!-- ======================================================
     DASHBOARD
     ====================================================== -->

<div
  id="dashboard"
  class="page active"
>


  <div class="card">

    <div class="stats">


      <div class="stat">

        <div
          id="enrolledCount"
          class="stat-number"
        >
          0
        </div>

        <div class="stat-label">
          Enrolled IDs
        </div>

      </div>


      <div class="stat">

        <div
          id="logCount"
          class="stat-number"
        >
          0
        </div>

        <div class="stat-label">
          Total Logs
        </div>

      </div>


      <div class="stat">

        <div
          id="wifi"
          class="stat-number"
        >
          -
        </div>

        <div class="stat-label">
          WiFi
        </div>

      </div>


    </div>

  </div>

  <div class="card connection-card">

    <h3>
      Connection Status
    </h3>

    <div class="connection-list">

      <div class="connection-item">
        <div>
          <div class="connection-name">ESP8266 WiFi</div>
          <span id="connectionWifiDetail" class="connection-detail">Checking device network...</span>
        </div>
        <span id="connectionWifiStatus" class="connection-status">Checking</span>
      </div>

      <div class="connection-item">
        <div>
          <div class="connection-name">Backend TCP port</div>
          <span id="connectionTcpDetail" class="connection-detail">Checking backend host and port...</span>
        </div>
        <span id="connectionTcpStatus" class="connection-status">Checking</span>
      </div>

      <div class="connection-item">
        <div>
          <div class="connection-name">Backend HTTP</div>
          <span id="connectionHttpDetail" class="connection-detail">Checking backend response...</span>
        </div>
        <span id="connectionHttpStatus" class="connection-status">Checking</span>
      </div>

      <div class="connection-item">
        <div>
          <div class="connection-name">Backend database</div>
          <span id="connectionDatabaseDetail" class="connection-detail">Checking database health endpoint...</span>
        </div>
        <span id="connectionDatabaseStatus" class="connection-status">Checking</span>
      </div>

    </div>

  </div>


  <div class="card">

    <h3>
      Device Information
    </h3>


    <div class="info-grid">


      <div class="info-item">

        <div class="info-label">
          Portal IP
        </div>

        <div
          id="portalIp"
          class="info-value"
        >
          -
        </div>

      </div>


      <div class="info-item">

        <div class="info-label">
          Router IP
        </div>

        <div
          id="routerIp"
          class="info-value"
        >
          -
        </div>

      </div>


      <div class="info-item">

        <div class="info-label">
          Fingerprint Sensor
        </div>

        <div
          id="sensor"
          class="info-value"
        >
          -
        </div>

      </div>


      <div class="info-item">

        <div class="info-label">
          Current Time
        </div>

        <div
          id="currentTime"
          class="info-value"
        >
          -
        </div>

      </div>


    </div>

  </div>


  <div class="card">

    <h3>
      System
    </h3>


    <button
      class="action restart"
      onclick="restartDevice()"
    >
      Restart ESP8266
    </button>

  </div>

</div>


<!-- ======================================================
     STUDENT REGISTRATION
     ====================================================== -->

<div
  id="students"
  class="page"
>

  <div class="card">
    <h3>Register Student</h3>
    <p class="registration-intro">Complete the details below, then ask the student to scan the same finger twice.</p>

    <input id="studentFirstName" maxlength="20" placeholder="First name">
    <input id="studentLastName" maxlength="20" placeholder="Last name">
    <input id="studentId" maxlength="16" placeholder="03-XXXX-XXXXX">

    <div class="selection-hint">Email, block, and subjects are completed later in Laravel.</div>

    <button id="studentRegisterButton" class="action student-save" onclick="registerStudent()">Register student and fingerprint</button>
    <div id="studentOperationStatus" class="status">Ready.</div>
  </div>

  <div class="card">
    <h3>Saved Students</h3>
    <div id="studentTable" class="table-wrap">Loading...</div>
  </div>

</div>


<!-- ======================================================
     FINGERPRINTS
     ====================================================== -->

<div
  id="fingerprints"
  class="page"
>


  <div class="card">

    <h3>
      Fingerprint Management
    </h3>


    <p>
      Select an ID from 1 to 1000.
    </p>


    <input
      id="fingerId"
      type="number"
      min="1"
      max="1000"
      placeholder="Fingerprint ID"
    >


    <button
      id="enrollButton"
      class="action"
      onclick="enrollFingerprint()"
    >
      Enroll Fingerprint
    </button>


    <button
      id="deleteButton"
      class="action delete"
      onclick="deleteFingerprint()"
    >
      Delete Fingerprint
    </button>


    <div
      id="operationStatus"
      class="status"
    >
      Ready.
    </div>

  </div>


  <div class="card">

    <h3>
      Currently Enrolled IDs
    </h3>


    <div
      id="fingerprintTable"
      class="table-wrap"
    >
      Loading...
    </div>

  </div>

</div>


<!-- ======================================================
     LOGS
     ====================================================== -->

<div
  id="logs"
  class="page"
>


  <div class="card">

    <h3>
      Enrollment & Device Logs
    </h3>


    <div
      id="logsTable"
      class="table-wrap"
    >
      Loading...
    </div>

  </div>

</div>


</div>


<script>


// ==========================================================
// TAB NAVIGATION
// ==========================================================

function openTab(
  page,
  button
) {

  document
    .querySelectorAll(
      ".page"
    )
    .forEach(
      p =>
        p.classList.remove(
          "active"
        )
    );


  document
    .querySelectorAll(
      ".tab"
    )
    .forEach(
      b =>
        b.classList.remove(
          "active"
        )
    );


  document
    .getElementById(
      page
    )
    .classList.add(
      "active"
    );


  button.classList.add(
    "active"
  );


  if (
    page ===
    "fingerprints"
  ) {

    loadFingerprints();
  }


  if (
    page ===
    "logs"
  ) {

    loadLogs();
  }


  if (
    page ===
    "dashboard"
  ) {

    loadDashboard();
  }

  if (
    page ===
    "students"
  ) {

    loadStudents();
  }
}


// ==========================================================
// DASHBOARD
// ==========================================================

function loadDashboard() {

  fetch(
    "/api/dashboard"
  )

  .then(
    response =>
      response.json()
  )

  .then(
    data => {

      document
        .getElementById(
          "enrolledCount"
        )
        .innerText =
        data.enrolled;


      document
        .getElementById(
          "logCount"
        )
        .innerText =
        data.logs;


      document
        .getElementById(
          "wifi"
        )
        .innerText =
        data.wifi
        ? "OK"
        : "OFF";


      document
        .getElementById(
          "portalIp"
        )
        .innerText =
        data.portal_ip;


      document
        .getElementById(
          "routerIp"
        )
        .innerText =
        data.router_ip;


      document
        .getElementById(
          "sensor"
        )
        .innerText =
        data.sensor
        ? "Ready"
        : "Error";


      document
        .getElementById(
          "currentTime"
        )
        .innerText =
        data.time;

      updateConnectionStatus(
        "connectionWifiStatus",
        "connectionWifiDetail",
        data.wifi,
        data.wifi ? "Connected" : "Disconnected",
        data.wifi
          ? "ESP8266 is connected to the configured WiFi network."
          : "ESP8266 is not connected to the configured WiFi network."
      );

      updateConnectionStatus(
        "connectionTcpStatus",
        "connectionTcpDetail",
        data.backend_tcp,
        data.backend_tcp ? "Reachable" : "Offline",
        data.backend_host + ":" + data.backend_port +
          (data.backend_tcp
            ? " accepted a TCP connection."
            : " did not accept a TCP connection.")
      );

      updateConnectionStatus(
        "connectionHttpStatus",
        "connectionHttpDetail",
        data.backend_http,
        data.backend_http ? "Connected" : "Unavailable",
        data.backend_http_code > 0
          ? "HTTP response: " + data.backend_http_code
          : "No HTTP response from " + data.backend_url
      );

      updateConnectionStatus(
        "connectionDatabaseStatus",
        "connectionDatabaseDetail",
        data.backend_database,
        data.backend_database ? "Connected" : "Unavailable",
        data.backend_database_code > 0
          ? "Database health response: " + data.backend_database_code
          : "No database health response from the backend."
      );
    }
  );
}


function updateConnectionStatus(
  statusId,
  detailId,
  isOnline,
  statusText,
  detailText
) {
  const status = document.getElementById(statusId);
  status.innerText = statusText;
  status.classList.toggle("online", isOnline);
  document.getElementById(detailId).innerText = detailText;
}


// ==========================================================
// LOAD ENROLLED IDS
// ==========================================================

function loadFingerprints() {

  fetch(
    "/api/enrolled"
  )

  .then(
    response =>
      response.json()
  )

  .then(
    data => {

      const container =
        document.getElementById(
          "fingerprintTable"
        );


      if (
        data.items.length === 0
      ) {

        container.innerHTML =
          '<div class="empty">No enrolled IDs found.</div>';

        return;
      }


      let html =

        "<table>" +

        "<tr>" +

        "<th>ID</th>" +

        "<th>Enrolled At</th>" +

        "<th>Status</th>" +

        "</tr>";


      data.items.forEach(
        item => {

          html +=

            "<tr>" +

            "<td><strong>" +
            item.id +
            "</strong></td>" +

            "<td>" +
            item.timestamp +
            "</td>" +

            '<td><span class="badge enrolled">ENROLLED</span></td>' +

            "</tr>";
        }
      );


      html +=
        "</table>";


      container.innerHTML =
        html;
    }
  );
}


// ==========================================================
// LOAD LOGS
// ==========================================================

function loadLogs() {

  fetch(
    "/api/logs"
  )

  .then(
    response =>
      response.json()
  )

  .then(
    data => {

      const container =
        document.getElementById(
          "logsTable"
        );


      if (
        data.items.length === 0
      ) {

        container.innerHTML =
          '<div class="empty">No logs available.</div>';

        return;
      }


      let html =

        "<table>" +

        "<tr>" +

        "<th>#</th>" +

        "<th>ID</th>" +

        "<th>Action</th>" +

        "<th>Result</th>" +

        "<th>Timestamp</th>" +

        "</tr>";


      data.items
        .slice()
        .reverse()
        .forEach(
          (item,index) => {

            let badge = "";


            if (
              item.action ===
              "ENROLL"
            ) {

              badge =
                '<span class="badge enrolled">ENROLL</span>';

            }

            else {

              badge =
                '<span class="badge deleted">DELETE</span>';
            }


            html +=

              "<tr>" +

              "<td>" +
              (index + 1) +
              "</td>" +

              "<td><strong>" +
              item.id +
              "</strong></td>" +

              "<td>" +
              badge +
              "</td>" +

              "<td>" +
              (
                item.result
                ? "SUCCESS"
                : "FAILED"
              ) +
              "</td>" +

              "<td>" +
              item.timestamp +
              "</td>" +

              "</tr>";
          }
        );


      html +=
        "</table>";


      container.innerHTML =
        html;
    }
  );
}


// ==========================================================
// GET ID
// ==========================================================

function getID() {

  const value =
    parseInt(
      document
        .getElementById(
          "fingerId"
        )
        .value
    );


  if (
    isNaN(value) ||
    value < 1 ||
    value > 1000
  ) {

    alert(
      "Enter an ID from 1 to 1000."
    );


    return null;
  }


  return value;
}


// ==========================================================
// ENABLE BUTTONS
// ==========================================================

function setButtons(
  enabled
) {

  document
    .getElementById(
      "enrollButton"
    )
    .disabled =
    !enabled;


  document
    .getElementById(
      "deleteButton"
    )
    .disabled =
    !enabled;
}


// ==========================================================
// ENROLL
// ==========================================================

function enrollFingerprint() {

  const id =
    getID();


  if (
    id === null
  ) {

    return;
  }


  setButtons(
    false
  );


  document
    .getElementById(
      "operationStatus"
    )
    .innerText =
    "Starting enrollment for ID " +
    id +
    "...";


  fetch(
    "/api/enroll?id=" +
    id
  )

  .then(
    response =>
      response.json()
  )

  .then(
    data => {

      document
        .getElementById(
          "operationStatus"
        )
        .innerText =
        data.message;


      if (
        data.success
      ) {

        pollOperation();
      }

      else {

        setButtons(
          true
        );
      }

    }
  )

  .catch(
    error => {

      document
        .getElementById(
          "operationStatus"
        )
        .innerText =
        "Connection error.";

      setButtons(
        true
      );
    }
  );
}


// ==========================================================
// DELETE
// ==========================================================

function deleteFingerprint() {

  const id =
    getID();


  if (
    id === null
  ) {

    return;
  }


  if (
    !confirm(
      "Delete fingerprint ID " +
      id +
      "?"
    )
  ) {

    return;
  }


  setButtons(
    false
  );


  document
    .getElementById(
      "operationStatus"
    )
    .innerText =
    "Deleting ID " +
    id +
    "...";


  fetch(
    "/api/delete?id=" +
    id
  )

  .then(
    response =>
      response.json()
  )

  .then(
    data => {

      document
        .getElementById(
          "operationStatus"
        )
        .innerText =
        data.message;


      setButtons(
        true
      );


      loadFingerprints();

      loadLogs();

      loadDashboard();
    }
  );
}


function loadStudents() {

  fetch("/api/students")
  .then(response => response.json())
  .then(data => {
    const container = document.getElementById("studentTable");

    if (data.items.length === 0) {
      container.innerHTML = '<div class="empty">No students saved.</div>';
      return;
    }

    let html = "<table><tr><th>Name</th><th>Student ID</th><th>Section</th><th>Subjects</th><th>Fingerprint ID</th><th>Saved At</th></tr>";

    data.items.slice().reverse().forEach(item => {
      html += "<tr><td><strong>" + item.first_name + " " + item.last_name +
        "</strong></td><td>" + item.student_id + "</td><td>" + item.section +
        "</td><td>" + item.subjects + "</td><td>" + item.id +
        "</td><td>" + item.timestamp + "</td></tr>";
    });

    container.innerHTML = html + "</table>";
  })
  .catch(() => {
    document.getElementById("studentTable").innerText = "Unable to load students.";
  });
}


function registerStudent() {

  const firstName = document.getElementById("studentFirstName").value.trim();
  const lastName = document.getElementById("studentLastName").value.trim();
  const studentId = document.getElementById("studentId").value.trim();
  if (!firstName || !lastName || !studentId) {
  document.getElementById("studentOperationStatus").innerText =
    "Enter the student's first name, last name, and student ID.";
    return;
  }

  document.getElementById("studentRegisterButton").disabled = true;
  document.getElementById("studentOperationStatus").innerText =
    "Preparing fingerprint registration...";

  fetch("/api/register-student?first_name=" + encodeURIComponent(firstName) +
    "&last_name=" + encodeURIComponent(lastName) +
    "&student_id=" + encodeURIComponent(studentId))
  .then(response => response.json())
  .then(data => {
    document.getElementById("studentOperationStatus").innerText = data.message;

    if (data.success) {
      pollStudentOperation();
    } else {
      document.getElementById("studentRegisterButton").disabled = false;
    }
  })
  .catch(() => {
    document.getElementById("studentOperationStatus").innerText = "Connection error.";
    document.getElementById("studentRegisterButton").disabled = false;
  });
}


function pollStudentOperation() {

  if (pollTimer) {
    clearInterval(pollTimer);
  }

  pollTimer = setInterval(function() {
    fetch("/api/operation")
    .then(response => response.json())
    .then(data => {
      document.getElementById("studentOperationStatus").innerText = data.message;

      if (!data.running) {
        clearInterval(pollTimer);
        pollTimer = null;
        document.getElementById("studentRegisterButton").disabled = false;
        loadStudents();
        loadFingerprints();
        loadLogs();
        loadDashboard();

        if (data.status === "Success" || data.status === "LocalOnly") {
          document.getElementById("studentFirstName").value = "";
          document.getElementById("studentLastName").value = "";
          document.getElementById("studentId").value = "";
        }
      }
    });
  }, 500);
}


// ==========================================================
// POLL OPERATION
// ==========================================================

let pollTimer =
  null;


function pollOperation() {

  if (
    pollTimer
  ) {

    clearInterval(
      pollTimer
    );
  }


  pollTimer =
    setInterval(
      function() {

        fetch(
          "/api/operation"
        )

        .then(
          response =>
            response.json()
        )

        .then(
          data => {

            document
              .getElementById(
                "operationStatus"
              )
              .innerText =
              data.message;


            if (
              !data.running
            ) {

              clearInterval(
                pollTimer
              );


              pollTimer =
                null;


              setButtons(
                true
              );


              loadFingerprints();

              loadLogs();

              loadDashboard();
            }

          }
        );

      },
      500
    );
}


// ==========================================================
// RESTART
// ==========================================================

function restartDevice() {

  if (
    !confirm(
      "Restart ESP8266?"
    )
  ) {

    return;
  }


  fetch(
    "/restart"
  );


  setTimeout(
    function() {

      location.reload();

    },
    5000
  );
}


// ==========================================================
// INITIAL LOAD
// ==========================================================

loadDashboard();

</script>


</body>

</html>

)rawliteral";


// ============================================================
// ROOT PAGE
// ============================================================

void handlePortalRoot() {

  if (!portalAuthenticated) {
    server.send(
      200,
      "text/html",
      LOGIN_HTML
    );
    return;
  }

  server.send(
    200,
    "text/html",
    PORTAL_HTML
  );
}


bool requirePortalAuthentication() {

  if (portalAuthenticated) {
    return true;
  }

  server.send(
    401,
    "application/json",
    "{\"success\":false,\"message\":\"Program Head login required.\"}"
  );

  return false;
}


void handlePortalLogin() {

  if (
    !server.hasArg("username") ||
    !server.hasArg("password")
  ) {
    server.send(
      400,
      "application/json",
      "{\"success\":false,\"message\":\"Username and password are required.\"}"
    );
    return;
  }

  if (
    server.arg("username") == PH_USERNAME &&
    server.arg("password") == PH_PASSWORD
  ) {
    portalAuthenticated = true;
    server.send(
      200,
      "application/json",
      "{\"success\":true,\"message\":\"Login successful.\"}"
    );
    return;
  }

  server.send(
    401,
    "application/json",
    "{\"success\":false,\"message\":\"Invalid Program Head credentials.\"}"
  );
}


void handlePortalLogout() {
  portalAuthenticated = false;
  server.send(
    200,
    "application/json",
    "{\"success\":true,\"message\":\"Logged out.\"}"
  );
}


// ============================================================
// CAPTIVE PORTAL DETECTION
// ============================================================

void handleGenerate204() {

  server.sendHeader(
    "Location",
    "http://192.168.4.1/",
    true
  );


  server.send(
    302,
    "text/plain",
    ""
  );
}


void handleHotspotDetect() {

  handlePortalRoot();
}


void handleFwlink() {

  server.sendHeader(
    "Location",
    "http://192.168.4.1/",
    true
  );


  server.send(
    302,
    "text/plain",
    ""
  );
}


// ============================================================
// DASHBOARD API
// ============================================================

bool checkBackendConnection(
  String& backendHost,
  uint16_t& backendPort,
  bool& tcpReachable,
  bool& httpReachable,
  int& httpStatusCode,
  bool& databaseReachable,
  int& databaseStatusCode
) {
  backendHost = "";
  backendPort = 0;
  tcpReachable = false;
  httpReachable = false;
  httpStatusCode = 0;
  databaseReachable = false;
  databaseStatusCode = 0;

  if (
    BASE_URL == nullptr ||
    strlen(BASE_URL) == 0 ||
    WiFi.status() != WL_CONNECTED
  ) {
    return false;
  }

  String backendUrl = String(BASE_URL);
  int schemeEnd = backendUrl.indexOf("://");
  int hostStart = schemeEnd >= 0 ? schemeEnd + 3 : 0;
  int pathStart = backendUrl.indexOf('/', hostStart);
  String authority = pathStart >= 0
    ? backendUrl.substring(hostStart, pathStart)
    : backendUrl.substring(hostStart);

  int portSeparator = authority.lastIndexOf(':');
  if (portSeparator > 0) {
    backendHost = authority.substring(0, portSeparator);
    backendPort = static_cast<uint16_t>(
      authority.substring(portSeparator + 1).toInt()
    );
  }
  else {
    backendHost = authority;
    backendPort = backendUrl.startsWith("https://") ? 443 : 80;
  }

  if (
    backendHost.length() == 0 ||
    backendPort == 0
  ) {
    return false;
  }

  WiFiClient tcpClient;
  tcpClient.setTimeout(3000);
  tcpReachable = tcpClient.connect(
    backendHost.c_str(),
    backendPort
  );
  tcpClient.stop();

  HTTPClient http;
  http.setTimeout(3000);

  bool initialized = false;
  if (backendUrl.startsWith("https://")) {
    backendHttpsClient.setInsecure();
    initialized = http.begin(backendHttpsClient, backendUrl);
  }
  else {
    initialized = http.begin(backendHttpClient, backendUrl);
  }

  if (initialized) {
    httpStatusCode = http.GET();
    httpReachable =
      httpStatusCode >= 200 &&
      httpStatusCode < 400;
    http.end();
  }

  if (
    BACKEND_HEALTH_URL != nullptr &&
    strlen(BACKEND_HEALTH_URL) > 0
  ) {
    HTTPClient healthHttp;
    healthHttp.setTimeout(3000);

    bool healthInitialized = false;
    if (String(BACKEND_HEALTH_URL).startsWith("https://")) {
      backendHttpsClient.setInsecure();
      healthInitialized = healthHttp.begin(
        backendHttpsClient,
        BACKEND_HEALTH_URL
      );
    }
    else {
      healthInitialized = healthHttp.begin(
        backendHttpClient,
        BACKEND_HEALTH_URL
      );
    }

    if (healthInitialized) {
      healthHttp.addHeader("Accept", "application/json");

      if (
        BACKEND_API_KEY != nullptr &&
        strlen(BACKEND_API_KEY) > 0
      ) {
        if (BACKEND_USE_BEARER_TOKEN) {
          healthHttp.addHeader(
            "Authorization",
            String("Bearer ") + BACKEND_API_KEY
          );
        }
        else {
          healthHttp.addHeader(
            BACKEND_API_KEY_HEADER,
            BACKEND_API_KEY
          );
        }
      }

      databaseStatusCode = healthHttp.GET();
      databaseReachable =
        databaseStatusCode >= 200 &&
        databaseStatusCode < 300;
      healthHttp.end();
    }
  }

  return tcpReachable || httpReachable;
}


void handleDashboard() {

  if (!requirePortalAuthentication()) {
    return;
  }

  String backendHost;
  uint16_t backendPort = 0;
  bool backendTcpReachable = false;
  bool backendHttpReachable = false;
  int backendHttpStatusCode = 0;
  bool backendDatabaseReachable = false;
  int backendDatabaseStatusCode = 0;

  checkBackendConnection(
    backendHost,
    backendPort,
    backendTcpReachable,
    backendHttpReachable,
    backendHttpStatusCode,
    backendDatabaseReachable,
    backendDatabaseStatusCode
  );

  int enrolledCount = 0;


  /*
    Count IDs whose newest action is ENROLL.
  */

  for (
    uint16_t id = 1;
    id <= 1000;
    id++
  ) {

    if (
      isIdEnrolled(id)
    ) {

      enrolledCount++;
    }
  }


  String json =
    "{";


  json +=
    "\"enrolled\":" +
    String(enrolledCount);


  json +=
    ",\"logs\":" +
    String(enrollmentRecordCount);


  json +=
    ",\"wifi\":";


  json +=
    WiFi.status() ==
    WL_CONNECTED
    ? "true"
    : "false";


  json +=
    ",\"portal_ip\":\"" +
    WiFi.softAPIP().toString() +
    "\"";


  json +=
    ",\"router_ip\":\"" +
    WiFi.localIP().toString() +
    "\"";


  json +=
    ",\"sensor\":";


  json +=
    activeSensorBaud > 0
    ? "true"
    : "false";


  json +=
    ",\"time\":\"" +
    getCurrentTimestamp() +
    "\"";

  json +=
    ",\"backend_url\":\"" +
    String(BASE_URL == nullptr ? "" : BASE_URL) +
    "\"";

  json +=
    ",\"backend_host\":\"" +
    backendHost +
    "\"";

  json +=
    ",\"backend_port\":" +
    String(backendPort);

  json +=
    ",\"backend_tcp\":" +
    String(backendTcpReachable ? "true" : "false");

  json +=
    ",\"backend_http\":" +
    String(backendHttpReachable ? "true" : "false");

  json +=
    ",\"backend_http_code\":" +
    String(backendHttpStatusCode);

  json +=
    ",\"backend_database\":" +
    String(backendDatabaseReachable ? "true" : "false");

  json +=
    ",\"backend_database_code\":" +
    String(backendDatabaseStatusCode);


  json +=
    "}";


  server.send(
    200,
    "application/json",
    json
  );
}


// ============================================================
// ENROLLED IDS API
// ============================================================

void handleEnrolledIDs() {

  if (!requirePortalAuthentication()) {
    return;
  }

  String json =
    "{\"items\":[";


  bool first =
    true;


  /*
    Show the newest successful enrollment
    for each currently enrolled ID.
  */

  for (
    uint16_t id = 1;
    id <= 1000;
    id++
  ) {

    if (
      !isIdEnrolled(id)
    ) {

      continue;
    }


    /*
      Find latest successful ENROLL
      record for this ID.
    */

    int latestIndex =
      -1;


    for (
      int i =
        enrollmentRecordCount - 1;
      i >= 0;
      i--
    ) {

      if (
        enrollmentRecords[i].id ==
        id &&
        enrollmentRecords[i].action ==
        ACTION_ENROLL &&
        enrollmentRecords[i].result ==
        1
      ) {

        latestIndex =
          i;

        break;
      }
    }


    if (
      latestIndex < 0
    ) {

      continue;
    }


    if (!first) {

      json +=
        ",";
    }


    first =
      false;


    json +=
      "{\"id\":" +
      String(id);


    json +=
      ",\"timestamp\":\"";


    String timestamp =
      getTimestampString(
        enrollmentRecords[
          latestIndex
        ].timestamp
      );


    timestamp.replace(
      "\"",
      "\\\""
    );


    json +=
      timestamp;


    json +=
      "\"}";
  }


  json +=
    "]}";


  server.send(
    200,
    "application/json",
    json
  );
}


// ============================================================
// LOGS API
// ============================================================

void handleLogs() {

  if (!requirePortalAuthentication()) {
    return;
  }

  String json =
    "{\"items\":[";


  for (
    uint16_t i = 0;
    i < enrollmentRecordCount;
    i++
  ) {

    if (
      i > 0
    ) {

      json +=
        ",";
    }


    EnrollmentRecord& record =
      enrollmentRecords[i];


    json +=
      "{\"id\":" +
      String(record.id);


    json +=
      ",\"action\":\"";


    if (
      record.action ==
      ACTION_ENROLL
    ) {

      json +=
        "ENROLL";

    }

    else {

      json +=
        "DELETE";
    }


    json +=
      "\"";


    json +=
      ",\"result\":";


    json +=
      record.result
      ? "true"
      : "false";


    json +=
      ",\"timestamp\":\"";


    json +=
      getTimestampString(
        record.timestamp
      );


    json +=
      "\"}";
  }


  json +=
    "]}";


  server.send(
    200,
    "application/json",
    json
  );
}


// ============================================================
// STUDENT REGISTRATION API
// ============================================================

String subjectNames(
  uint8_t mask
) {

  String value;

  if (mask & 1) value += "ITE 310";
  if (mask & 2) value += value.length() ? ", ITE 367" : "ITE 367";
  if (mask & 4) value += value.length() ? ", ITE 381" : "ITE 381";
  if (mask & 8) value += value.length() ? ", Others" : "Others";

  return value;
}


void handleStudents() {

  if (!requirePortalAuthentication()) {
    return;
  }

  String json = "{\"items\":[";

  for (uint16_t i = 0; i < studentRecordCount; i++) {
    if (i > 0) json += ",";

    StudentRecord& record = studentRecords[i];
    json += "{\"id\":" + String(record.fingerprintId);
    json += ",\"first_name\":\"" + jsonEscape(record.firstName) + "\"";
    json += ",\"last_name\":\"" + jsonEscape(record.lastName) + "\"";
    json += ",\"student_id\":\"" + jsonEscape(record.studentId) + "\"";
    json += ",\"section\":\"" + jsonEscape(record.section) + "\"";
    json += ",\"subjects\":\"" + jsonEscape(subjectNames(record.subjectMask)) + "\"";
    json += ",\"timestamp\":\"" + jsonEscape(getTimestampString(record.timestamp)) + "\"}";
  }

  json += "]}";
  server.send(200, "application/json", json);
}


void handleStudentRegister() {

  if (!requirePortalAuthentication()) {
    return;
  }

  if (portalOperationRunning) {
    server.send(
      409,
      "application/json",
      "{\"success\":false,\"message\":\"Another fingerprint operation is running.\"}"
    );
    return;
  }

  if (activeSensorBaud == 0) {
    server.send(
      500,
      "application/json",
      "{\"success\":false,\"message\":\"Fingerprint sensor not ready.\"}"
    );
    return;
  }

  if (!pendingStudentActive) {
    if (
      !server.hasArg("first_name") ||
      !server.hasArg("last_name") ||
      !server.hasArg("student_id")
    ) {
      server.send(
        400,
        "application/json",
        "{\"success\":false,\"message\":\"Student name and student ID are required.\"}"
      );
      return;
    }

    String firstName = server.arg("first_name");
    String lastName = server.arg("last_name");
    String studentId = server.arg("student_id");
    if (
      !isStudentFieldValid(firstName, 20) ||
      !isStudentFieldValid(lastName, 20) ||
      !isStudentFieldValid(studentId, 16)
    ) {
      server.send(
        400,
        "application/json",
        "{\"success\":false,\"message\":\"Invalid student details.\"}"
      );
      return;
    }

    int id = findAvailableFingerprintId();
    if (id == 0) {
      server.send(
        507,
        "application/json",
        "{\"success\":false,\"message\":\"No fingerprint IDs are available.\"}"
      );
      return;
    }

    memset(&pendingStudent, 0, sizeof(pendingStudent));
    pendingStudent.fingerprintId = id;
    pendingStudent.subjectMask = 0;
    studentId.toCharArray(pendingStudent.studentId, sizeof(pendingStudent.studentId));
    firstName.toCharArray(pendingStudent.firstName, sizeof(pendingStudent.firstName));
    lastName.toCharArray(pendingStudent.lastName, sizeof(pendingStudent.lastName));
    pendingStudent.section[0] = '\0';
    pendingStudentActive = true;
    pendingEnrollId = id;
  }

  currentFingerOperation = FINGER_OP_ENROLL_STUDENT;
  portalOperationRunning = true;
  portalOperationStatus = "Starting";
  portalOperationMessage =
    "Registering fingerprint ID " + String(pendingEnrollId) +
    ". Place the student's finger when instructed.";

  server.send(
    200,
    "application/json",
    String("{\"success\":true,\"id\":") +
    String(pendingEnrollId) +
    ",\"message\":\"Fingerprint registration started. Place the same finger when instructed.\"}"
  );
}


// ============================================================
// ENROLL API
// ============================================================

void handlePortalEnroll() {

  if (!requirePortalAuthentication()) {
    return;
  }

  if (pendingStudentActive) {
    server.send(
      409,
      "application/json",
      "{\"success\":false,\"message\":\"Finish the pending student fingerprint registration first.\"}"
    );
    return;
  }

  if (
    portalOperationRunning
  ) {

    server.send(
      409,
      "application/json",
      "{\"success\":false,\"message\":\"Another operation is running.\"}"
    );


    return;
  }


  if (
    !server.hasArg("id")
  ) {

    server.send(
      400,
      "application/json",
      "{\"success\":false,\"message\":\"ID is required.\"}"
    );


    return;
  }


  int id =
    server.arg("id").toInt();


  if (
    id < 1 ||
    id > 1000
  ) {

    server.send(
      400,
      "application/json",
      "{\"success\":false,\"message\":\"ID must be 1-1000.\"}"
    );


    return;
  }


  if (
    activeSensorBaud == 0
  ) {

    server.send(
      500,
      "application/json",
      "{\"success\":false,\"message\":\"Fingerprint sensor not ready.\"}"
    );


    return;
  }


  /*
    Prevent accidentally overwriting an
    existing fingerprint.
  */

  if (
    isIdEnrolled(id)
  ) {

    server.send(
      409,
      "application/json",
      "{\"success\":false,\"message\":\"This ID is already enrolled. Delete it first if you want to replace it.\"}"
    );


    return;
  }


  pendingEnrollId =
    id;


  currentFingerOperation =
    FINGER_OP_ENROLL;


  portalOperationRunning =
    true;


  portalOperationStatus =
    "Starting";


  portalOperationMessage =
    "Preparing enrollment for ID " +
    String(id);


  Serial.println();

  Serial.print(
    "Portal enrollment requested: ID "
  );

  Serial.println(
    id
  );


  server.send(
    200,
    "application/json",
    "{\"success\":true,\"message\":\"Enrollment started. Place your finger when instructed.\"}"
  );
}


// ============================================================
// DELETE API
// ============================================================

void handlePortalDelete() {

  if (!requirePortalAuthentication()) {
    return;
  }

  if (
    portalOperationRunning
  ) {

    server.send(
      409,
      "application/json",
      "{\"success\":false,\"message\":\"Another operation is running.\"}"
    );


    return;
  }


  if (
    !server.hasArg("id")
  ) {

    server.send(
      400,
      "application/json",
      "{\"success\":false,\"message\":\"ID is required.\"}"
    );


    return;
  }


  int id =
    server.arg("id").toInt();


  if (
    id < 1 ||
    id > 1000
  ) {

    server.send(
      400,
      "application/json",
      "{\"success\":false,\"message\":\"ID must be 1-1000.\"}"
    );


    return;
  }


  if (
    activeSensorBaud == 0
  ) {

    server.send(
      500,
      "application/json",
      "{\"success\":false,\"message\":\"Fingerprint sensor not ready.\"}"
    );


    return;
  }


  fingerSerial.listen();


  uint8_t result =
    finger.deleteModel(
      id
    );


  if (
    result ==
    FINGERPRINT_OK
  ) {

    markIdDeleted(
      id
    );


    portalOperationStatus =
      "Success";


    portalOperationMessage =
      "ID " +
      String(id) +
      " deleted successfully.";


    showMessage(
      "Delete OK",
      String("ID ") + id
    );


    Serial.print(
      "Deleted fingerprint ID: "
    );

    Serial.println(
      id
    );


    server.send(
      200,
      "application/json",
      "{\"success\":true,\"message\":\"Fingerprint deleted and log saved.\"}"
    );


    return;
  }


  /*
    Even failed deletes are logged.
  */

  addEnrollmentRecord(
    id,
    ACTION_DELETE,
    0
  );


  portalOperationStatus =
    "Error";


  portalOperationMessage =
    "Delete failed. Error " +
    String(result);


  server.send(
    500,
    "application/json",
    String(
      "{\"success\":false,\"message\":\"Delete failed. Error "
    ) +
    String(result) +
    "\"}"
  );
}


// ============================================================
// OPERATION STATUS
// ============================================================

void handleOperationStatus() {

  if (!requirePortalAuthentication()) {
    return;
  }

  String safeMessage =
    portalOperationMessage;


  safeMessage.replace(
    "\\",
    "\\\\"
  );


  safeMessage.replace(
    "\"",
    "\\\""
  );


  String json =
    "{";


  json +=
    "\"running\":";


  json +=
    portalOperationRunning
    ? "true"
    : "false";


  json +=
    ",\"status\":\"";


  json +=
    portalOperationStatus;


  json +=
    "\"";


  json +=
    ",\"message\":\"";


  json +=
    safeMessage;


  json +=
    "\"";


  json +=
    ",\"id\":" +
    String(
      pendingEnrollId
    );


  json +=
    "}";


  server.send(
    200,
    "application/json",
    json
  );
}


// ============================================================
// WEB ENROLLMENT PROCESS
// ============================================================

void processWebEnrollment() {

  if (
    currentFingerOperation !=
    FINGER_OP_ENROLL
  ) {

    return;
  }


  int id =
    pendingEnrollId;


  currentFingerOperation =
    FINGER_OP_IDLE;


  uint8_t result =
    enrollFingerprint(
      id
    );


  if (
    result ==
    FINGERPRINT_OK
  ) {

    /*
      Only successful enrollment
      gets registered as enrolled.
    */

    addEnrollmentRecord(
      id,
      ACTION_ENROLL,
      1
    );


    portalOperationStatus =
      "Success";


    portalOperationMessage =
      "ID " +
      String(id) +
      " enrolled successfully.";


    showMessage(
      "Enroll OK",
      String("ID ") + id
    );


    Serial.print(
      "Enrolled ID: "
    );

    Serial.println(
      id
    );
  }

  else {

    /*
      Failed enrollment is also logged.
    */

    addEnrollmentRecord(
      id,
      ACTION_ENROLL,
      0
    );


    portalOperationStatus =
      "Error";


    portalOperationMessage =
      "Enrollment failed: " +
      String(
        fingerErrorText(
          result
        )
      );


    showMessage(
      "Enroll Fail",
      fingerErrorText(
        result
      )
    );
  }


  pendingEnrollId =
    0;


  portalOperationRunning =
    false;
}


void processStudentEnrollment() {

  if (
    currentFingerOperation !=
    FINGER_OP_ENROLL_STUDENT
  ) {
    return;
  }

  int id = pendingEnrollId;
  currentFingerOperation = FINGER_OP_IDLE;

  uint8_t result = enrollFingerprint(id);

  if (result == FINGERPRINT_OK) {
    pendingStudent.timestamp = time(nullptr);
    addStudentRecord(pendingStudent);
    addEnrollmentRecord(id, ACTION_ENROLL, 1);

    bool backendSaved =
      sendStudentToBackend(pendingStudent);

    portalOperationStatus =
      backendSaved
      ? "Success"
      : "LocalOnly";

    portalOperationMessage =
      backendSaved
      ? "Student saved locally and sent to the backend."
      : "Student saved locally. Backend sync is not available.";

    showMessage("Student saved", String("ID ") + id);
    pendingStudentActive = false;
    pendingEnrollId = 0;
  }
  else {
    addEnrollmentRecord(id, ACTION_ENROLL, 0);

    portalOperationStatus = "Error";
    portalOperationMessage =
      "Fingerprint registration failed (" +
      String(fingerErrorText(result)) +
      "). Press register again to retry the same ID.";

    showMessage("Register failed", "Retry same ID");
  }

  portalOperationRunning = false;
}


// ============================================================
// ENROLL FINGERPRINT
// ============================================================

uint8_t enrollFingerprint(
  uint16_t id
) {

  fingerSerial.listen();


  portalOperationStatus =
    "Enrolling";


  portalOperationMessage =
    "Place finger on sensor";


  showMessage(
    "Enroll ID",
    String(id)
  );


  delay(700);


  // ----------------------------------------------------------
  // FIRST SCAN
  // ----------------------------------------------------------

  portalOperationMessage =
    "Place finger";


  showMessage(
    "Place finger",
    ""
  );


  int p = -1;


  while (
    p != FINGERPRINT_OK
  ) {

    servicePortal();

    handleResetButton();


    p =
      finger.getImage();


    if (
      p ==
      FINGERPRINT_NOFINGER
    ) {

      delay(120);

      continue;
    }


    if (
      p !=
      FINGERPRINT_OK
    ) {

      return p;
    }
  }


  p =
    finger.image2Tz(1);


  if (
    p !=
    FINGERPRINT_OK
  ) {

    return p;
  }


  // ----------------------------------------------------------
  // REMOVE FINGER
  // ----------------------------------------------------------

  portalOperationMessage =
    "Remove finger";


  showMessage(
    "Remove finger",
    ""
  );


  unsigned long removeStart =
    millis();


  while (
    finger.getImage() !=
    FINGERPRINT_NOFINGER
  ) {

    servicePortal();

    handleResetButton();

    delay(100);


    if (
      millis() -
      removeStart >
      15000
    ) {

      return FINGERPRINT_PACKETRECIEVEERR;
    }
  }


  // ----------------------------------------------------------
  // SECOND SCAN
  // ----------------------------------------------------------

  portalOperationMessage =
    "Place finger again";


  showMessage(
    "Place again",
    ""
  );


  p = -1;


  while (
    p != FINGERPRINT_OK
  ) {

    servicePortal();

    handleResetButton();


    p =
      finger.getImage();


    if (
      p ==
      FINGERPRINT_NOFINGER
    ) {

      delay(120);

      continue;
    }


    if (
      p !=
      FINGERPRINT_OK
    ) {

      return p;
    }
  }


  p =
    finger.image2Tz(2);


  if (
    p !=
    FINGERPRINT_OK
  ) {

    return p;
  }


  // ----------------------------------------------------------
  // CREATE MODEL
  // ----------------------------------------------------------

  portalOperationMessage =
    "Comparing fingerprints";


  p =
    finger.createModel();


  if (
    p !=
    FINGERPRINT_OK
  ) {

    return p;
  }


  // ----------------------------------------------------------
  // SAVE MODEL
  // ----------------------------------------------------------

  portalOperationMessage =
    "Saving fingerprint";


  p =
    finger.storeModel(
      id
    );


  return p;
}


// ============================================================
// FINGERPRINT ERROR
// ============================================================

const char* fingerErrorText(
  int code
) {

  switch (code) {

    case FINGERPRINT_OK:
      return "OK";

    case FINGERPRINT_PACKETRECIEVEERR:
      return "COMM";

    case FINGERPRINT_NOFINGER:
      return "NOFINGER";

    case FINGERPRINT_IMAGEFAIL:
      return "IMGFAIL";

    case FINGERPRINT_IMAGEMESS:
      return "IMGMESS";

    case FINGERPRINT_FEATUREFAIL:
      return "FEATURE";

    case FINGERPRINT_INVALIDIMAGE:
      return "INVALID";

    case FINGERPRINT_NOMATCH:
      return "NOMATCH";

    case FINGERPRINT_NOTFOUND:
      return "NOTFOUND";

    case FINGERPRINT_ENROLLMISMATCH:
      return "MISMATCH";

    case FINGERPRINT_BADLOCATION:
      return "BADLOC";

    case FINGERPRINT_FLASHERR:
      return "FLASH";

    default:
      return "ERROR";
  }
}


// ============================================================
// RESET BUTTON
// ============================================================

void handleResetButton() {

  bool reading =
    digitalRead(
      RESET_BUTTON_PIN
    );


  if (
    reading !=
    lastResetReading
  ) {

    resetDebounceTime =
      millis();
  }


  if (
    millis() -
    resetDebounceTime >=
    RESET_DEBOUNCE_MS
  ) {

    if (
      reading !=
      stableResetState
    ) {

      stableResetState =
        reading;


      if (
        stableResetState ==
        LOW
      ) {

        showMessage(
          "System",
          "Resetting..."
        );


        delay(300);


        ESP.restart();
      }
    }
  }


  lastResetReading =
    reading;
}


// ============================================================
// RESTART API
// ============================================================

void handleRestart() {

  if (!requirePortalAuthentication()) {
    return;
  }

  server.send(
    200,
    "text/plain",
    "Restarting..."
  );


  delay(500);


  ESP.restart();
}


// ============================================================
// NOT FOUND
// ============================================================

void handleNotFound() {

  server.sendHeader(
    "Location",
    "http://192.168.4.1/",
    true
  );


  server.send(
    302,
    "text/plain",
    ""
  );
}


// ============================================================
// START PORTAL
// ============================================================

void startCaptivePortal() {

  WiFi.persistent(false);
  WiFi.disconnect();
  delay(100);

  WiFi.mode(
    WIFI_AP_STA
  );

  delay(100);


  bool apConfigReady =
    WiFi.softAPConfig(
    AP_IP,
    AP_GATEWAY,
    AP_SUBNET
  );


  bool apReady =
    WiFi.softAP(
    AP_SSID,
    AP_PASSWORD
  );

  delay(300);


  Serial.println();

  Serial.println(
    "================================"
  );

  Serial.println(
    "CAPTIVE PORTAL"
  );

  Serial.println(
    "================================"
  );


  Serial.print(
    "SSID: "
  );

  Serial.println(
    AP_SSID
  );


  Serial.print(
    "Password: "
  );

  Serial.println(
    AP_PASSWORD
  );


  Serial.print(
    "IP: "
  );

  Serial.println(
    WiFi.softAPIP()
  );

  Serial.print(
    "AP config: "
  );

  Serial.println(
    apConfigReady
    ? "OK"
    : "FAILED"
  );

  Serial.print(
    "AP start: "
  );

  Serial.println(
    apReady
    ? "OK"
    : "FAILED"
  );


  // ----------------------------------------------------------
  // DNS
  // ----------------------------------------------------------

  dnsServer.start(
    DNS_PORT,
    "*",
    AP_IP
  );


  // ----------------------------------------------------------
  // ROUTES
  // ----------------------------------------------------------

  server.on(
    "/",
    HTTP_GET,
    handlePortalRoot
  );


  server.on(
    "/generate_204",
    HTTP_GET,
    handleGenerate204
  );


  server.on(
    "/gen_204",
    HTTP_GET,
    handleGenerate204
  );


  server.on(
    "/hotspot-detect.html",
    HTTP_GET,
    handleHotspotDetect
  );


  server.on(
    "/fwlink",
    HTTP_GET,
    handleFwlink
  );


  // Dashboard

  server.on(
    "/api/dashboard",
    HTTP_GET,
    handleDashboard
  );

  server.on(
    "/api/login",
    HTTP_GET,
    handlePortalLogin
  );

  server.on(
    "/api/logout",
    HTTP_GET,
    handlePortalLogout
  );

  server.on(
    "/api/students",
    HTTP_GET,
    handleStudents
  );

  server.on(
    "/api/register-student",
    HTTP_GET,
    handleStudentRegister
  );


  // Enrolled IDs

  server.on(
    "/api/enrolled",
    HTTP_GET,
    handleEnrolledIDs
  );


  // Logs

  server.on(
    "/api/logs",
    HTTP_GET,
    handleLogs
  );


  // Enrollment

  server.on(
    "/api/enroll",
    HTTP_GET,
    handlePortalEnroll
  );


  // Delete

  server.on(
    "/api/delete",
    HTTP_GET,
    handlePortalDelete
  );


  // Operation status

  server.on(
    "/api/operation",
    HTTP_GET,
    handleOperationStatus
  );


  // Restart

  server.on(
    "/restart",
    HTTP_GET,
    handleRestart
  );


  server.onNotFound(
    handleNotFound
  );


  server.begin();


  Serial.println(
    "HTTP server started."
  );
}


// ============================================================
// SERVICE PORTAL
// ============================================================

void servicePortal() {

  dnsServer.processNextRequest();

  server.handleClient();
}


// ============================================================


// SERIAL COMMANDS
// ============================================================

int readEnrollIdFromSerial() {

  if (
    !Serial.available()
  ) {

    return 0;
  }


  String line =
    Serial.readStringUntil(
      '\n'
    );


  line.trim();

  line.toUpperCase();


  if (
    line.startsWith(
      "ENROLL"
    )
  ) {

    line =
      line.substring(6);

    line.trim();


    int id =
      line.toInt();


    if (
      id >= 1 &&
      id <= 1000
    ) {

      return id;
    }


    return -1;
  }


  if (
    line.startsWith(
      "DELETE"
    )
  ) {

    line =
      line.substring(6);

    line.trim();


    int id =
      line.toInt();


    if (
      id < 1 ||
      id > 1000
    ) {

      showMessage(
        "Invalid ID",
        "Use 1-1000"
      );


      return 0;
    }


    fingerSerial.listen();


    uint8_t result =
      finger.deleteModel(
        id
      );


    if (
      result ==
      FINGERPRINT_OK
    ) {

      markIdDeleted(
        id
      );


      showMessage(
        "Delete OK",
        String("ID ") + id
      );


      Serial.println(
        "Deleted ID " +
        String(id)
      );
    }

    else {

      addEnrollmentRecord(
        id,
        ACTION_DELETE,
        0
      );


      showMessage(
        "Delete Fail",
        String(result)
      );
    }


    delay(1200);


    showMessage(
      "Ready",
      "Scan Finger"
    );


    return 0;
  }


  return 0;
}


// ============================================================
// FINGERPRINT SEARCH
// ============================================================

int getFingerprintID(
  int& status
) {

  uint8_t consecutiveErrors =
    0;


  for (
    uint8_t attempt = 0;
    attempt < SEARCH_ATTEMPTS;
    attempt++
  ) {

    servicePortal();

    fingerSerial.listen();


    status =
      finger.getImage();


    if (
      status ==
      FINGERPRINT_NOFINGER
    ) {

      return -1;
    }


    if (
      status !=
      FINGERPRINT_OK
    ) {

      consecutiveErrors++;


      if (
        consecutiveErrors >=
        REINIT_AFTER_ERRORS
      ) {

        reinitFingerprintSensor();

        consecutiveErrors =
          0;
      }


      delay(
        SEARCH_RETRY_DELAY_MS
      );


      continue;
    }


    status =
      finger.image2Tz();


    if (
      status !=
      FINGERPRINT_OK
    ) {

      consecutiveErrors++;


      if (
        consecutiveErrors >=
        REINIT_AFTER_ERRORS
      ) {

        reinitFingerprintSensor();

        consecutiveErrors =
          0;
      }


      delay(
        SEARCH_RETRY_DELAY_MS
      );


      continue;
    }


    status =
      finger.fingerFastSearch();


    if (
      status ==
      FINGERPRINT_OK
    ) {

      return finger.fingerID;
    }


    if (
      status ==
      FINGERPRINT_NOTFOUND
    ) {

      return -1;
    }


    consecutiveErrors++;


    if (
      consecutiveErrors >=
      REINIT_AFTER_ERRORS
    ) {

      reinitFingerprintSensor();

      consecutiveErrors =
        0;
    }


    delay(
      SEARCH_RETRY_DELAY_MS
    );
  }


  return -1;
}


// ============================================================
// LARAVEL BACKEND REQUESTS
// ============================================================

bool postBackendJson(
  const char* endpoint,
  const String& payload
) {

  if (
    endpoint == nullptr ||
    strlen(endpoint) == 0 ||
    BACKEND_API_KEY == nullptr ||
    strlen(BACKEND_API_KEY) == 0 ||
    strcmp(BACKEND_API_KEY, "YOUR_SANCTUM_TOKEN") == 0 ||
    strcmp(BACKEND_API_KEY, "YOUR_NEW_PRIVATE_TOKEN") == 0 ||
    strcmp(BACKEND_API_KEY, "YOUR_NEW_PRIVATE_SANCTUM_TOKEN") == 0 ||
    WiFi.status() != WL_CONNECTED
  ) {
    Serial.println("Backend API is not configured or WiFi is unavailable.");
    return false;
  }

  HTTPClient http;
  http.setTimeout(BACKEND_HTTP_TIMEOUT_MS);

  bool initialized = false;

  if (String(endpoint).startsWith("https://")) {
    backendHttpsClient.setInsecure();
    initialized = http.begin(backendHttpsClient, endpoint);
  }
  else {
    initialized = http.begin(backendHttpClient, endpoint);
  }

  if (!initialized) {
    Serial.println("Backend API connection could not be initialized.");
    return false;
  }

  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");

  if (BACKEND_USE_BEARER_TOKEN) {
    http.addHeader(
      "Authorization",
      String("Bearer ") + BACKEND_API_KEY
    );
  }
  else {
    http.addHeader(
      BACKEND_API_KEY_HEADER,
      BACKEND_API_KEY
    );
  }

  int statusCode = http.POST(payload);
  String response = http.getString();

  Serial.print("Backend HTTP status: ");
  Serial.println(statusCode);

  if (response.length() > 0) {
    Serial.print("Backend response: ");
    Serial.println(response);
  }

  http.end();

  return statusCode >= 200 && statusCode < 300;
}


String buildStudentJson(
  const StudentRecord& record
) {

  String json = "{";
  json += "\"device_id\":\"" + String(BACKEND_DEVICE_ID) + "\"";
  json += ",\"student_id\":\"" + jsonEscape(record.studentId) + "\"";
  json += ",\"first_name\":\"" + jsonEscape(record.firstName) + "\"";
  json += ",\"last_name\":\"" + jsonEscape(record.lastName) + "\"";
  json += ",\"section\":\"" + jsonEscape(record.section) + "\"";
  json += ",\"subjects\":\"" + jsonEscape(subjectNames(record.subjectMask)) + "\"";
  json += ",\"fingerprint_id\":" + String(record.fingerprintId);
  json += ",\"enrolled_at\":" + String(record.timestamp);
  json += "}";
  return json;
}


bool sendStudentToBackend(
  const StudentRecord& record
) {
  return postBackendJson(
    STUDENT_API_URL,
    buildStudentJson(record)
  );
}


String buildAttendanceJson(
  int id
) {

  String json = "{";
  json += "\"device_id\":\"" + String(BACKEND_DEVICE_ID) + "\"";
  json += ",\"fingerprint_id\":" + String(id);
  json += ",\"scanned_at\":" + String(time(nullptr));
  json += "}";
  return json;
}


bool sendAttendanceToBackend(
  int id
) {
  return postBackendJson(
    ATTENDANCE_API_URL,
    buildAttendanceJson(id)
  );
}


// ============================================================
// RETRY STORED STUDENT RECORDS TO LARAVEL
// ============================================================

void syncNextStoredStudent() {
  if (WiFi.status() != WL_CONNECTED ||
      studentRecordCount == 0 ||
      currentFingerOperation != FINGER_OP_IDLE ||
      portalOperationRunning) {
    return;
  }

  if (millis() - lastStudentSyncAttempt < STUDENT_SYNC_INTERVAL_MS) {
    return;
  }
  lastStudentSyncAttempt = millis();

  if (nextStudentToSync >= studentRecordCount) {
    nextStudentToSync = 0;
  }

  const StudentRecord& record = studentRecords[nextStudentToSync];
  Serial.print("Syncing stored student: ");
  Serial.println(record.studentId);

  if (sendStudentToBackend(record)) {
    Serial.println("Student synced with Laravel.");
    nextStudentToSync++;
  } else {
    Serial.println("Sync failed; retrying this student later.");
  }
}

// ============================================================
// SETUP
// ============================================================

void setup() {

  // ----------------------------------------------------------
  // RESET
  // ----------------------------------------------------------

  pinMode(
    RESET_BUTTON_PIN,
    INPUT_PULLUP
  );


  lastResetReading =
    digitalRead(
      RESET_BUTTON_PIN
    );


  stableResetState =
    lastResetReading;


  // ----------------------------------------------------------
  // SERIAL
  // ----------------------------------------------------------

  Serial.begin(
    115200
  );


  Serial.setTimeout(
    200
  );


  // ----------------------------------------------------------
  // LCD
  // ----------------------------------------------------------

  Wire.begin(
    D2,
    D1
  );


  lcd.init();

  lcd.backlight();


  showMessage(
    "Booting",
    "Please wait"
  );


  // ----------------------------------------------------------
  // EEPROM
  // ----------------------------------------------------------

  loadEnrollmentRecords();

  loadStudentRecords();


  // ----------------------------------------------------------
  // CAPTIVE PORTAL
  // ----------------------------------------------------------

  startCaptivePortal();


  // ----------------------------------------------------------
  // WIFI
  // ----------------------------------------------------------

  connectWiFi(
    true
  );


  // ----------------------------------------------------------
  // TIME
  // ----------------------------------------------------------

  if (
    WiFi.status() ==
    WL_CONNECTED
  ) {

    setupTime();
  }


  // ----------------------------------------------------------
  // FINGERPRINT
  // ----------------------------------------------------------

  if (
    !initFingerprintSensor(
      true
    )
  ) {

    showMessage(
      "Sensor Error",
      "Check wiring"
    );


    Serial.println(
      "Fingerprint sensor not detected."
    );

  }


  // ----------------------------------------------------------
  // READY
  // ----------------------------------------------------------

  showMessage(
    "Ready",
    "Scan Finger"
  );


  Serial.println();

  Serial.println(
    "================================"
  );

  Serial.println(
    "SYSTEM READY"
  );

  Serial.println(
    "================================"
  );


  Serial.print(
    "Portal: http://"
  );

  Serial.println(
    WiFi.softAPIP()
  );


  Serial.println(
    "Serial commands:"
  );


  Serial.println(
    "ENROLL <ID>"
  );


  Serial.println(
    "DELETE <ID>"
  );
}


// ============================================================
// MAIN LOOP
// ============================================================

void loop() {

  servicePortal();


  handleResetButton();


  // ----------------------------------------------------------
  // WEB ENROLLMENT
  // ----------------------------------------------------------

  if (
    currentFingerOperation ==
    FINGER_OP_ENROLL
  ) {

    processWebEnrollment();


    showMessage(
      "Ready",
      "Scan Finger"
    );


    delay(1000);


    return;
  }


  if (
    currentFingerOperation ==
    FINGER_OP_ENROLL_STUDENT
  ) {

    processStudentEnrollment();

    showMessage(
      "Ready",
      "Scan Finger"
    );

    delay(1000);

    return;
  }


  // ----------------------------------------------------------
  // WIFI RECONNECT
  // ----------------------------------------------------------

  if (
    WiFi.status() !=
    WL_CONNECTED
  ) {

    connectWiFi(
      false
    );


    servicePortal();
  }


  // ----------------------------------------------------------
  // SERIAL
  // ----------------------------------------------------------

  int enrollId =
    readEnrollIdFromSerial();


  if (
    enrollId > 0
  ) {

    if (
      isIdEnrolled(
        enrollId
      )
    ) {

      Serial.println(
        "ID already enrolled."
      );


      showMessage(
        "Already enrolled",
        String("ID ") + enrollId
      );


      delay(1200);


      showMessage(
        "Ready",
        "Scan Finger"
      );


      return;
    }


    portalOperationRunning =
      true;


    uint8_t result =
      enrollFingerprint(
        enrollId
      );


    if (
      result ==
      FINGERPRINT_OK
    ) {

      addEnrollmentRecord(
        enrollId,
        ACTION_ENROLL,
        1
      );


      showMessage(
        "Enroll OK",
        String("ID ") + enrollId
      );
    }

    else {

      addEnrollmentRecord(
        enrollId,
        ACTION_ENROLL,
        0
      );


      showMessage(
        "Enroll Fail",
        fingerErrorText(
          result
        )
      );
    }


    portalOperationRunning =
      false;


    delay(1200);


    showMessage(
      "Ready",
      "Scan Finger"
    );


    return;
  }


  // ----------------------------------------------------------
  // Sync a stored record before checking for a fingerprint.
  syncNextStoredStudent();

  // NORMAL ATTENDANCE SCANNING
  // ----------------------------------------------------------

  int status =
    FINGERPRINT_NOFINGER;


  int fingerID =
    getFingerprintID(
      status
    );


  if (
    fingerID > 0
  ) {

    showMessage(
      String("ID ") +
      fingerID,
      "Sending..."
    );


    sendAttendanceToBackend(
      fingerID
    );


    while (
      finger.getImage() !=
      FINGERPRINT_NOFINGER
    ) {

      servicePortal();

      handleResetButton();

      delay(200);
    }


    delay(500);


    showMessage(
      "Ready",
      "Scan Finger"
    );


    return;
  }


  if (
    status ==
    FINGERPRINT_NOTFOUND
  ) {

    showMessage(
      "Not registered",
      "See Program Head"
    );


    delay(1000);


    showMessage(
      "Ready",
      "Scan Finger"
    );
  }


  servicePortal();


  delay(80);
}

// Do not create a new token every demo
// The API token is stored inside the ESP8266 firmware. Only create a new token if:
// the old token was revoked;
// you changed the device ID;
// you changed the backend user;
// the token was exposed publicly.

// php artisan tinker --execute "echo App\Models\User::where('email', 'admin@northbridge.test')->firstOrFail()->createToken('attendance-esp8266-01', ['students:create','attendance:create'])->plainTextToken;"


// Running Commands
// cd C:\Users\aronj\Downloads\backend_cap
// php artisan config:clear
// php artisan migrate --force
// php artisan serve --host=0.0.0.0 --port=8000

// Testing Commands
// Invoke-WebRequest http://localhost:8000 -UseBasicParsing
// Test-NetConnection YOUR_COMPUTER_IP -Port 8000
