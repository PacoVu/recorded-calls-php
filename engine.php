<?php
header("Content-Type: application/json", true);
require_once('_bootstrap.php');
use RingCentral\SDK\SDK;
define("OK", 0);
define("FAILED", 1);
define("UNKNOWN", 2);
define ("CALLS_DATABASE", 'db/calllogs.db');

class Recording
{
    public $id;
    public $fromRecipient;
    public $toRecipient;
    public $duration;
    public $recordingId;
    public $transcript = "";
}

class Response
{
    public function Response($status, $message)
    {
        $this->status = $status;
        $this->message = $message;
    }
    public $status;
    public $message;
}

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();
$rcsdk = null;
if (getenv('DEV_MODE') == "sandbox") {
    $rcsdk = new SDK(getenv('CLIENT_ID_SB'),
        getenv('CLIENT_SECRET_SB'), RingCentral\SDK\SDK::SERVER_SANDBOX);
}else{
    $rcsdk = new SDK(getenv('CLIENT_ID_PROD'),
        getenv('CLIENT_SECRET_PROD'), RingCentral\SDK\SDK::SERVER_PRODUCTION);
}
$platform = $rcsdk->platform();
createTable();

if (isset($_REQUEST['readlogs'])) {
  login();
}elseif (isset($_REQUEST['transcribe'])) {
  transcriptCallRecording($_REQUEST['audioId']);
}

function login(){
  global $platform;
  try {
      if (getenv('DEV_MODE') == "sandbox")
          $platform->login(getenv('USERNAME_SB'), null, getenv('PASSWORD_SB'));
      else
          $platform->login(getenv('USERNAME_PROD'), null, getenv('PASSWORD_PROD'));
      readCallLogsAsync();
  }catch (\RingCentral\SDK\Http\ApiException $e) {
      print($e);
  }
}
function createTable() {
    $db = new SQLite3(CALLS_DATABASE, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    if (!$db) {
        databaseError();
    }
    $query = 'CREATE TABLE if not exists calls (id DOUBLE PRIMARY KEY, fromRecipient VARCHAR(12) NOT NULL, toRecipient VARCHAR(12) NOT NULL, recordingUrl VARCHAR(256) NOT NULL, duration INT DEFAULT 0, transcript TEXT NOT NULL)';
    $db->exec($query) or databaseError();
}

function transcriptCallRecording($audioId){
    $audioSrc = "./recordings/" . $audioId . ".mp3";
    $username = getenv('WATSON_USERNAME');
    $password = getenv('WATSON_PWD');
    $url = "https://stream.watsonplatform.net/speech-to-text/api/v1/recognize?timestamps=true&word_alternatives_threshold=0.9&interim_results=false&max_alternatives=1&model=en-US_NarrowbandModel";
    $file = fopen($audioSrc, 'r');
    $size = filesize($audioSrc);
    $fildata = fread($file,$size);
    $headers = array("Content-Type: audio/mp3");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fildata);
    curl_setopt($ch, CURLOPT_INFILE, $file);
    curl_setopt($ch, CURLOPT_INFILESIZE, $size);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $results = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($results, true);
    $response = "";
    foreach ($json['results'] as $result){
        $response .= $result['alternatives'][0]['transcript'];
    }

    $text = str_replace("'", "\'", $response);
    $text = str_replace("%HESITATION ", "", $text);
    $text = trim($text);
    $query = 'UPDATE calls SET transcript="' . $text . '" WHERE id=' . $audioId;
    $db = new SQLite3(CALLS_DATABASE, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    if (!$db) {
        databaseError();
    }
    $ok = $db->exec($query);
    if (!$ok) {
        $res = new Response(FAILED, "Please try again.");
        createResponse($res);
    }else{
        $res = new Response(OK, $text);
        createResponse($res);
    }
}

function readCallLogsAsync(){
    global $platform;
    if ($_REQUEST['access'] == "account")
        $endpoint = '/account/~/call-log';
    else
        $endpoint = '/account/~/extension/~/call-log';
    try {
        $response = $platform->get($endpoint,
        array(
          'recordingType' => $_REQUEST['recordingType'],
          'dateFrom' => $_REQUEST['dateFrom'],
          'dateTo' => $_REQUEST['dateTo'],
          'perPage' => $_REQUEST['perPage'],
        ));
        $records = $response->json()->records;
        $db = new SQLite3(CALLS_DATABASE, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        if (!$db) {
            databaseError();
        }
        if (count($records) > 0) {
            foreach ($records as $record){
                if (isset($record->recording)) {
                    $item = new Recording();
                    if (isset($record->from->phoneNumber))
                        $item->fromRecipient = $record->from->phoneNumber;
                    else if (isset($record->from->name))
                        $item->fromRecipient = $record->from->name;
                    if (isset($record->to->phoneNumber))
                        $item->toRecipient = $record->to->phoneNumber;
                    else if (isset($record->to->name))
                        $item->toRecipient = $record->to->name;

                    $item->duration = $record->duration;
                    $item->id = $record->recording->id;

                    $item->recordingUrl = "http://" . $_SERVER['HTTP_HOST'] . "/recorded-calls-php/recordings/" . $record->recording->id . '.mp3';
                    $query = "INSERT or IGNORE  into calls VALUES (" . $item->id . ",'" . $item->fromRecipient . "','" . $item->toRecipient . "','" . $item->recordingUrl . "'," . $item->duration . ",'')";

                    $ok = $db->exec($query);
                    if (!$ok) {
                      //
                    } else {
                        $fileName = "recordings/" . $record->recording->id . '.mp3';
                        $apiResponse = $platform->get($record->recording->contentUri);
                        file_put_contents($fileName, $apiResponse->raw());
                        sleep(1);
                    }
                }
            }
            createResponse(new Response(OK, "Ok to load"));
        }else {
            createResponse(new Response(FAILED, "RC server connection error. Please try again."));
        }
    }catch (\RingCentral\SDK\Http\ApiException $e) {
        print($e);
    }
}

function createResponse($res){
    $response= json_encode($res);
    echo $response;
}
function databaseError(){
    $res = new Response(UNKNOWN, "Unknown database error. Please try again.");
    $response= json_encode($res);
    die ($response);
}
