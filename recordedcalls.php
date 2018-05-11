<?php
define ("CALLS_DATABASE", 'db/calllogs.db');
function search($arg){
    if ($arg == "*")
        $query = "SELECT * FROM calls";
    else
        $query = "SELECT * FROM calls WHERE transcript LIKE '%" . $arg . "%'";
    return loadCallsFromDB($query);
}
function loadCallsFromDB($query){
    $db = new SQLite3(CALLS_DATABASE, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    if (!$db) {
        databaseError();
    }
    $db->exec($query) or databaseError();
    $results = $db->query($query);
    $temp = "";
    while ($row = $results->fetchArray()) {
        $temp .= '<tr>
                <td width=160>
                  <div>Fr.: ' . $row["fromRecipient"] . '</div>
                  <div>To: ' . $row["toRecipient"] . '</div>
                </td>
                <td width="200">
                  <audio controls  controlsList="nodownload">
                    <source src="' . $row["recordingUrl"] .'" type="audio/mpeg">
                Your browser does not support the audio element.
                  </audio>
                </td>
                <td width=80>
                  <div>' . $row["duration"] . ' secs</div>
                </td>
                <td>';
        if ($row["transcript"] == "") {
           $temp .= '<button class="btn btn-call" id="te_' . $row["id"] . '" onclick="transcribe(\'' . $row["id"] . '\')">Transcribe</button>
                    <div style="display: none" id="tt_' . $row["id"] . '" >' . $row["transcript"] . '</div>';
        }
        else {
           $temp .= '<button style="display: none" class="btn btn-call" id="te_' . $row["id"] . '" onclick="transcribe(\'' . $row["id"] . '\')">Transcribe</button>
                    <div id="tt_' . $row["id"] . '">' . $row["transcript"] . '</div>';
            }
        $temp .= '</td>
            </tr>';
    }
    return $temp;
}

$html = '<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Call Records Analysis Demo</title>
    <script src="public/js/main.js" type="text/javascript"></script>
    <script src="public/js/jquery-3.1.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="public/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="public/css/main.css">
    <script>
  </script>
</head>
<body>
  <nav class="navbar navbar-default">
    <div class="container-fluid">
      <div class="navbar-header">
        <a href="/" class="navbar-brand"><b>Call Recordings</b> ANALYSIS</a>
      </div>
      <ul class="nav navbar-nav">
        <li><a href="index.html">Read</a></li>
        <li><a href="recordedcalls.php">List</a></li>
      </ul>
      <ul class="nav navbar-nav navbar-right">
        <li><a href="https://www.ringcentral.com" target="_blank">Powered by&nbsp;<img alt="Brand" src="public/img/ringcentral.png" height="20"></a></li>
      </ul>
    </div>
  </nav>
  <section id="content">
    <div class="row">
      <div class="col-xs-12">
        <form action="recordedcalls.php" method="POST" class="form-inline">
          <div class="form-group">
            <input type="text" class="form-control" name="searchArg" placeholder="Search the calls" id="searchArg" class="search" required>
          </div>
          <button type="submit" class="btn btn-default" id="search">Search</button>
        </form>
      </div>
    </div>
    <div class="row">
      <div class="col-xs-12">
        <table class="table" id="callTable">
          <thead>
            <tr>
              <th>Call</th>
              <th>Audio</th>
              <th>Duration</th>
              <th>Transcript</th>
            </tr>
          </thead>
          <tbody>';
if (isset($_REQUEST['searchArg']))
    $html .= search($_REQUEST['searchArg']);
else
    $html .= search("*");

$html .= '</tbody>
      </table>
    </div>
  </div>
  </section>
</body>
</html>';

echo $html;